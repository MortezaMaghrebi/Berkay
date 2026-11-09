<?php

namespace Webkul\Berkay\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class KavenegarSms
{
    /** Normalize Iranian mobile numbers and reject other recipients. */
    public function normalizePhone(string $phone): string
    {
        $phone = self::westernDigits($phone);
        $phone = preg_replace('/[\s()-]/u', '', $phone);
        $phone = preg_replace('/^(?:\+98|0098|98)/', '0', $phone);

        if (! preg_match('/^09[0-9]{9}$/', $phone)) {
            throw new InvalidArgumentException('Invalid Iranian mobile number.');
        }

        return $phone;
    }

    public static function westernDigits(string $value): string
    {
        return strtr($value, array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789')
        ));
    }

    /** Send a verified template without exposing the API key in exceptions. */
    public function lookup(string $phone, string $template, string $token, ?string $token2 = null): void
    {
        $key = app(IntegrationSettings::class)->get('kavenegar.api_key');

        if (
            ! is_string($key)
            || ! preg_match('/^[a-zA-Z0-9]+$/', $key)
            || ! preg_match('/^[a-zA-Z0-9_-]+$/', $template)
        ) {
            throw new RuntimeException('Kavenegar configuration is incomplete.');
        }

        $data = array_filter([
            'receptor' => $this->normalizePhone($phone),
            'template' => $template,
            'token' => $token,
            'token2' => $token2,
        ], fn ($value) => $value !== null);

        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(15)
                ->post('https://api.kavenegar.com/v1/'.$key.'/verify/lookup.json', $data);
        } catch (Throwable) {
            throw new RuntimeException('Kavenegar could not be reached.');
        }

        if (
            ! $response->successful()
            || (int) $response->json('return.status') !== 200
        ) {
            throw new RuntimeException('Kavenegar did not accept the message.');
        }
    }
}
