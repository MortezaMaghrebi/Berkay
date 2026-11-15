<?php

namespace Webkul\Berkay\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\CartRule\Repositories\CartRuleRepository;

class StoreCampaign
{
    /**
     * List the available campaign occasions.
     */
    public const EVENTS = [
        'black-friday' => 'جمعهٔ سیاه', 'valentine' => 'ولنتاین', 'women' => 'روز زن و مادر',
        'men' => 'روز مرد و پدر', 'girls' => 'روز دختر', 'boys' => 'روز پسر',
        'teachers' => 'روز معلم', 'nurses' => 'روز پرستار', 'yalda' => 'شب یلدا',
        'nowruz' => 'نوروز', 'festival' => 'جشنوارهٔ چرم برکای', 'autumn' => 'کالکشن پاییز',
    ];

    /**
     * List the available wheel prize types.
     */
    public const TYPES = [
        'none' => 'بدون جایزه', 'percent' => 'تخفیف درصدی', 'credit' => 'اعتبار خرید (تومان)',
        'shipping' => 'ارسال رایگان', 'product' => 'هدیهٔ محصولی (تحویل با مدیر)',
    ];

    /**
     * Format a whole toman amount without converting it to a float.
     */
    public static function formatTomanAmount(int|string $amount): string
    {
        $digits = ltrim((string) $amount, '0') ?: '0';

        return strrev(implode(',', str_split(strrev($digits), 3)));
    }

    /**
     * Initialize the service with the campaign settings store.
     */
    public function __construct(private IntegrationSettings $settings) {}

    /**
     * Retrieve the current channel's campaign settings.
     */
    public function settings(): array
    {
        return $this->settings->get('campaign.'.core()->getCurrentChannel()->id) ?? [];
    }

    /**
     * Retrieve the current campaign and its playable wheel.
     */
    public function active(): ?array
    {
        $settings = $this->settings();

        if (! isset(self::EVENTS[$settings['event'] ?? ''])) {
            return null;
        }

        $settings['prizes'] = array_values(array_filter((array) ($settings['prizes'] ?? []), static fn ($prize) => is_array($prize) && isset($prize['label'], $prize['type'], $prize['weight'])
            && isset(self::TYPES[$prize['type']]) && (int) $prize['weight'] > 0
        ));
        $settings['wheel'] = ! empty($settings['wheel']) && Str::isUuid($settings['run'] ?? '')
            && count($settings['prizes']) >= 2 && count($settings['prizes']) <= 8;

        return $settings;
    }

    /**
     * Run a campaign operation under the current channel's lock.
     */
    public function locked(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            $channel = core()->getCurrentChannel();
            $channel->newQuery()->whereKey($channel->id)->lockForUpdate()->firstOrFail();

            return $callback($channel);
        }, 3);
    }

    /**
     * Save settings and preserve the run for the same occasion.
     */
    public function save(array $settings): void
    {
        $this->locked(function ($channel) use ($settings) {
            $previous = $this->settings();
            $settings['run'] = ($previous['event'] ?? null) === ($settings['event'] ?? null)
                ? ($previous['run'] ?? (string) Str::uuid())
                : (string) Str::uuid();
            $this->settings->put('campaign.'.$channel->id, $settings);
        });
    }

    /**
     * Find the customer's active result for a campaign run.
     */
    public function result(string $run, int $customerId): ?object
    {
        return DB::table('berkay_campaign_spins')
            ->where(['run' => $run, 'customer_id' => $customerId, 'attempt_slot' => 'active'])
            ->first();
    }

    /**
     * Reopen an attempt and expire its unused coupon.
     */
    public function resetSpin(int $id): bool
    {
        return $this->locked(function ($channel) use ($id) {
            $event = $this->active();

            if (! ($event['wheel'] ?? false)) {
                return false;
            }

            $query = DB::table('berkay_campaign_spins')->where([
                'id' => $id,
                'channel_id' => $channel->id,
                'run' => $event['run'],
                'attempt_slot' => 'active',
            ]);
            $spin = $query->lockForUpdate()->first();

            if (! $spin) {
                return false;
            }

            $now = now();

            if ($spin->coupon) {
                $couponQuery = DB::table('cart_rule_coupons')->where('code', $spin->coupon);
                $coupon = $couponQuery->lockForUpdate()->first();

                if ($coupon && ! $coupon->times_used) {
                    $couponQuery->update(['expired_at' => $now->copy()->subDay()->toDateString()]);
                }
            }

            $query->update([
                'attempt_slot' => (string) $spin->id,
                'reset_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        });
    }

    /**
     * Identify the wheel's current prize configuration.
     */
    public function wheelVersion(array $event): string
    {
        return hash('sha256', json_encode($event['prizes'], JSON_THROW_ON_ERROR));
    }

    /**
     * Determine whether the code belongs to a wheel prize.
     */
    public static function isWheelCoupon(?string $code): bool
    {
        return preg_match('/\A(?:BK-WHEEL-.*|BW-[A-Z0-9]{7})\z/i', (string) $code) === 1;
    }

    /**
     * Retrieve the active prize owned by the customer on this channel.
     */
    public static function couponPrize(string $code, ?int $customerId, int $channelId): ?array
    {
        if (! $customerId) {
            return null;
        }

        $spin = DB::table('berkay_campaign_spins')->where([
            'coupon' => $code,
            'customer_id' => $customerId,
            'channel_id' => $channelId,
            'attempt_slot' => 'active',
        ])->first();

        if (! $spin) {
            return null;
        }

        return json_decode($spin->prize, true);
    }

    /**
     * Record a weighted draw and issue its prize to the customer.
     */
    public function spin(string $run, $customer, ?string $version = null): bool
    {
        return $this->locked(function ($channel) use ($run, $customer, $version) {
            $event = $this->active();

            if (
                ! $event
                || ! $event['wheel']
                || $event['run'] !== $run
                || $this->result($run, $customer->id)
            ) {
                return false;
            }

            $wheelVersion = $this->wheelVersion($event);

            if (
                $version !== null
                && $version !== $wheelVersion
            ) {
                return false;
            }

            $weights = array_map('intval', array_column($event['prizes'], 'weight'));
            $draw = random_int(1, array_sum($weights));
            foreach ($event['prizes'] as $index => $prize) {
                $draw -= $weights[$index];
                if ($draw <= 0) {
                    break;
                }
            }

            $prize['segment'] = $index;
            $prize['wheel_version'] = $wheelVersion;
            $prize['days'] = (int) ($event['days'] ?? 7);
            if (! empty($prize['max_purchase'])) {
                $cap = ltrim((string) $prize['max_purchase'], '0');
                $prize['max_purchase_base'] = $channel->base_currency->code === 'IRR' ? $cap.'0' : $cap;
            }
            $coupon = match ($prize['type']) {
                'percent', 'credit', 'shipping' => $this->coupon($prize, $channel, $customer, $prize['days']),
                default => null,
            };

            DB::table('berkay_campaign_spins')->insert([
                'run' => $run,
                'channel_id' => $channel->id,
                'customer_id' => $customer->id,
                'event' => $event['event'],
                'prize' => json_encode($prize, JSON_THROW_ON_ERROR),
                'coupon' => $coupon,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Create a single-use coupon for a wheel prize.
     */
    private function coupon(array $prize, $channel, $customer, int $days): string
    {
        do {
            $code = 'BW-'.strtoupper(Str::random(7));
        } while (
            DB::table('cart_rule_coupons')->where('code', $code)->exists()
            || DB::table('berkay_campaign_spins')->where('coupon', $code)->exists()
        );
        $amount = $prize['value'] ?? 0;
        if (
            $prize['type'] === 'credit'
            && $channel->base_currency->code === 'IRR'
        ) {
            $amount *= 10;
        }

        app(CartRuleRepository::class)->create([
            'name' => 'Berkay wheel: '.$prize['label'],
            'status' => 1,
            'coupon_type' => 1,
            'use_auto_generation' => 0,
            'uses_per_coupon' => 1,
            'usage_per_customer' => 1,
            'coupon_code' => $code,
            'ends_till' => now()->addDays($days),
            'channels' => [$channel->id],
            'customer_groups' => [$customer->group?->id ?? core()->getGuestCustomerGroup()->id],
            'action_type' => $prize['type'] === 'percent' ? 'by_percent' : 'cart_fixed',
            'discount_amount' => $prize['type'] === 'shipping' ? 0 : $amount,
            'discount_quantity' => 0,
            'free_shipping' => $prize['type'] === 'shipping',
            'end_other_rules' => 1,
        ]);

        return $code;
    }
}
