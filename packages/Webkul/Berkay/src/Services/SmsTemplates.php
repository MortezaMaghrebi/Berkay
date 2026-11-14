<?php

namespace Webkul\Berkay\Services;

class SmsTemplates
{
    public const EVENTS = [
        'register', 'login', 'change', 'delete', 'order', 'confirmed', 'shipped',
        'completed', 'canceled', 'refunded', 'stage_review', 'stage_approved', 'status', 'coupon', 'test',
    ];

    /** Order notifications with independent delivery switches. */
    public const ORDER_EVENTS = ['order', 'confirmed', 'shipped', 'completed', 'canceled', 'refunded', 'stage_review', 'stage_approved', 'status'];

    public function __construct(protected IntegrationSettings $settings) {}

    /** Treat existing settings without a switch as enabled. */
    public function eventEnabled(string $event): bool
    {
        return (bool) ($this->settings->get('kavenegar.'.$event.'_enabled') ?? true);
    }

    public function template(string $event): ?string
    {
        if (config('berkay.kavenegar.paused') || ! $this->settings->get('kavenegar.enabled') || ! $this->settings->get('kavenegar.api_key')) {
            return null;
        }

        if (in_array($event, self::ORDER_EVENTS, true) && ! $this->eventEnabled($event)) {
            return null;
        }

        return $this->settings->get('kavenegar.'.$event.'_template') ?: null;
    }
}
