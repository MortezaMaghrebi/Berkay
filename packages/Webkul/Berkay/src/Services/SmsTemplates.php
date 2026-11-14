<?php

namespace Webkul\Berkay\Services;

class SmsTemplates
{
    public const EVENTS = [
        'register', 'login', 'change', 'delete', 'order', 'confirmed', 'shipped',
        'completed', 'canceled', 'refunded', 'stage_review', 'stage_approved', 'status', 'coupon', 'test', 'admin_new_order', 'admin_order_paid',
    ];

    public const ADMIN_EVENTS = ['admin_new_order', 'admin_order_paid'];

    public const NOTIFICATION_EVENTS = [...self::ORDER_EVENTS, ...self::ADMIN_EVENTS];

    /** Order notifications with independent delivery switches. */
    public const ORDER_EVENTS = ['order', 'confirmed', 'shipped', 'completed', 'canceled', 'refunded', 'stage_review', 'stage_approved', 'status'];

    public function __construct(protected IntegrationSettings $settings) {}

    /** Treat existing settings without a switch as enabled. */
    public function eventEnabled(string $event): bool
    {
        return (bool) ($this->settings->get('kavenegar.'.$event.'_enabled') ?? ! in_array($event, self::ADMIN_EVENTS, true));
    }

    public function adminRecipient(): ?string
    {
        return $this->settings->get('kavenegar.admin_recipient') ?: null;
    }

    public function template(string $event): ?string
    {
        if (config('berkay.kavenegar.paused') || ! $this->settings->get('kavenegar.enabled') || ! $this->settings->get('kavenegar.api_key')) {
            return null;
        }

        if (in_array($event, self::NOTIFICATION_EVENTS, true) && ! $this->eventEnabled($event)) {
            return null;
        }

        return $this->settings->get('kavenegar.'.$event.'_template') ?: null;
    }
}
