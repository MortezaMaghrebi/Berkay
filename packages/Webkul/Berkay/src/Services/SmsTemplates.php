<?php

namespace Webkul\Berkay\Services;

class SmsTemplates
{
    public const EVENTS = [
        'register', 'login', 'change', 'delete', 'order', 'confirmed', 'shipped',
        'completed', 'canceled', 'refunded', 'status', 'coupon', 'test',
    ];

    public function __construct(protected IntegrationSettings $settings) {}

    public function template(string $event): ?string
    {
        if (! $this->settings->get('kavenegar.enabled') || ! $this->settings->get('kavenegar.api_key')) {
            return null;
        }

        return $this->settings->get('kavenegar.'.$event.'_template') ?: null;
    }
}
