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
    /** Use six decimal digits for newly issued codes. */
    public const CODE_LENGTH = 6;

    /** Store the current challenge in the browser session. */
    private const SESSION_KEY = 'berkay.phone.challenge';

    /** Limit failures across challenges for the same number. */
    private const MAX_FAILURES = 5;

    /** Observe failed attempts over a 15-minute window. */
    private const FAILURE_WINDOW = 900;

    /** Pause new challenges and verification for ten minutes after the limit. */
    private const LOCKOUT = 600;

    /** Inject the settings and SMS delivery services. */
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

    /** Send a fresh challenge without resetting verification failures. */
    public function issue(Request $request, string $purpose, string $phone, ?int $customerId = null): void
    {
        $template = $this->settings->get('kavenegar.'.$purpose.'_template')
            ?: $this->settings->get('kavenegar.login_template');

        if (! $this->settings->get('kavenegar.enabled') || ! $template || ! $this->settings->get('kavenegar.api_key')) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.unavailable')]);
        }

        $recipient = hash_hmac('sha256', $phone, config('app.key'));
        $this->ensureUnlocked($recipient, 'phone');
        $limitKey = 'berkay:sms:hour:'.$recipient;

        if (RateLimiter::tooManyAttempts($limitKey, 8) || ! Cache::add('berkay:sms:cooldown:'.$recipient, true, 60)) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.wait')]);
        }

        RateLimiter::hit($limitKey, 3600);
        $id = (string) Str::uuid();
        $code = self::generateCode();

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
            'length' => self::CODE_LENGTH,
            'hash' => $this->digest($id, $code),
            'expires' => now()->addMinutes(5)->timestamp,
        ]);
    }

    /** Generate a cryptographically random decimal code with leading zeroes. */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, (10 ** self::CODE_LENGTH) - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /** Resolve a live challenge for this session and operation. */
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

    /** Consume a correct code once and count failures across browser sessions. */
    public function consume(Request $request, string $purpose, string $code, ?int $customerId = null): string
    {
        $challenge = $this->pending($request, $purpose, $customerId);

        if ($challenge) {
            $recipient = hash_hmac('sha256', $challenge['phone'], config('app.key'));

            return Cache::lock('berkay:sms:verification:'.$recipient, 10)->block(3, function () use ($request, $challenge, $recipient, $code) {
                $this->ensureUnlocked($recipient, 'code');
                $id = $challenge['id'];
                $failures = 'berkay:sms:failures:'.$recipient;

                if (hash_equals($challenge['hash'], $this->digest($id, KavenegarSms::westernDigits($code)))) {
                    if (! Cache::add('berkay:sms:consumed:'.$id, true, 300)) {
                        throw ValidationException::withMessages(['code' => trans('berkay::sms.invalid-code')]);
                    }

                    RateLimiter::clear($failures);
                    $request->session()->forget(self::SESSION_KEY);

                    return $challenge['phone'];
                }

                if (RateLimiter::hit($failures, self::FAILURE_WINDOW) >= self::MAX_FAILURES) {
                    Cache::put('berkay:sms:locked:'.$recipient, now()->timestamp + self::LOCKOUT, self::LOCKOUT);
                    RateLimiter::clear($failures);
                    $this->ensureUnlocked($recipient, 'code');
                }

                throw ValidationException::withMessages(['code' => trans('berkay::sms.invalid-code')]);
            });
        }

        throw ValidationException::withMessages(['code' => trans('berkay::sms.invalid-code')]);
    }

    /** Report the remaining cooldown without extending it. */
    private function ensureUnlocked(string $recipient, string $field): void
    {
        $remaining = (int) Cache::get('berkay:sms:locked:'.$recipient) - now()->timestamp;

        if ($remaining > 0) {
            throw ValidationException::withMessages([$field => trans('auth.throttle', ['seconds' => $remaining])]);
        }
    }

    /** Bind the challenge identifier to its code with a keyed digest. */
    private function digest(string $id, string $code): string
    {
        return hash_hmac('sha256', $id.':'.$code, config('app.key'));
    }
}
