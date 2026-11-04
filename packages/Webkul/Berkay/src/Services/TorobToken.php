<?php

namespace Webkul\Berkay\Services;

use InvalidArgumentException;
use JsonException;

class TorobToken
{
    /** Verify Torob Ed25519 signatures, validity times and the intended store host. */
    public function verify(string $token, string $audience, ?int $now = null): bool
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return false;
            }

            [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
            $header = json_decode($this->decode($encodedHeader), true, 16, JSON_THROW_ON_ERROR);
            $claims = json_decode($this->decode($encodedPayload), true, 16, JSON_THROW_ON_ERROR);
            $signature = $this->decode($encodedSignature);
            $now ??= time();

            if (
                ($header['alg'] ?? null) !== 'EdDSA'
                || ($header['v'] ?? null) !== 1
                || ! is_int($claims['exp'] ?? null)
                || ! is_int($claims['nbf'] ?? null)
                || $claims['exp'] <= $now
                || $claims['nbf'] > $now
                || ($claims['aud'] ?? null) !== $audience
                || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            ) {
                return false;
            }

            return sodium_crypto_sign_verify_detached(
                $signature,
                $encodedHeader.'.'.$encodedPayload,
                base64_decode('t6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=', true)
            );
        } catch (InvalidArgumentException|JsonException) {
            return false;
        }
    }

    /** Decode only canonical JWT base64url segments. */
    protected function decode(string $value): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException('Invalid token encoding.');
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid token encoding.');
        }

        return $decoded;
    }
}
