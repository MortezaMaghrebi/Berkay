<?php

namespace Webkul\Berkay\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class PhoneVerification
{
    private const SESSION_KEY = 'berkay.phone.challenge';

    public function __construct(
        protected IntegrationSettings $settings,
        protected KavenegarSms $sms,
    ) {}

    /** Normalize the number before it is used for storage, lookup or throttling. */
    public function normalize(string $phone): string
    {
        try {
            return $this->sms->normalizePhone($phone);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.invalid-phone')]);
        }
    }

    public function issue(Request $request, string $purpose, string $phone, ?int $customerId = null): void
    {
        $template = $this->settings->get('kavenegar.'.$purpose.'_template')
            ?: $this->settings->get('kavenegar.login_template');

        if (! $this->settings->get('kavenegar.enabled') || ! $template || ! $this->settings->get('kavenegar.api_key')) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.unavailable')]);
        }

        $recipient = hash_hmac('sha256', $phone, config('app.key'));
        $limitKey = 'berkay:sms:hour:'.$recipient;

        if (RateLimiter::tooManyAttempts($limitKey, 8) || ! Cache::add('berkay:sms:cooldown:'.$recipient, true, 60)) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.wait')]);
        }

        RateLimiter::hit($limitKey, 3600);
        $id = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);

        try {
            $this->sms->lookup($phone, $template, $code);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.send-failed')]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'id' => $id,
            'phone' => $phone,
            'purpose' => $purpose,
            'channel' => core()->getCurrentChannel()->id,
            'customer' => $customerId,
            'hash' => $this->digest($id, $code),
            'expires' => now()->addMinutes(5)->timestamp,
        ]);
    }

    public function pending(Request $request, string $purpose, ?int $customerId = null): ?array
    {
        $challenge = $request->session()->get(self::SESSION_KEY);

        if (
            ! $challenge
            || $challenge['purpose'] !== $purpose
            || $challenge['channel'] !== core()->getCurrentChannel()->id
            || $challenge['customer'] !== $customerId
            || $challenge['expires'] <= now()->timestamp
        ) {
            return null;
        }

        return $challenge;
    }

    public function consume(Request $request, string $purpose, string $code, ?int $customerId = null): string
    {
        $challenge = $this->pending($request, $purpose, $customerId);

        if ($challenge) {
            $id = $challenge['id'];
            $attempts = RateLimiter::hit('berkay:sms:attempt:'.$id, 300);

            if (
                $attempts <= 5
                && hash_equals($challenge['hash'], $this->digest($id, KavenegarSms::westernDigits($code)))
                && Cache::add('berkay:sms:consumed:'.$id, true, 300)
            ) {
                $request->session()->forget(self::SESSION_KEY);

                return $challenge['phone'];
            }

            if ($attempts >= 5) {
                $request->session()->forget(self::SESSION_KEY);
            }
        }

        throw ValidationException::withMessages(['code' => trans('berkay::sms.invalid-code')]);
    }

    private function digest(string $id, string $code): string
    {
        return hash_hmac('sha256', $id.':'.$code, config('app.key'));
    }
}
