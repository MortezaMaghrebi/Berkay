<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Berkay\Services\StoreCampaign;

class CampaignController
{
    public function artwork(string $event): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless(isset(StoreCampaign::EVENTS[$event]), 404);

        $file = public_path('berkay/event-banners/'.$event.'.jpg');
        abort_unless(is_file($file), 404);

        $response = response()->file($file, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ])->setAutoEtag();
        $response->isNotModified(request());

        return $response;
    }

    public function index(Request $request, StoreCampaign $campaign): View
    {
        $event = $campaign->active();
        abort_unless($event, 404);
        $customer = auth()->guard('customer')->user();

        if (! $customer && $event['wheel']) {
            $request->session()->put('shop.url.intended', route('shop.berkay.campaign.index'));
        }

        return view('berkay::shop.campaign', [
            'event' => $event,
            'customer' => $customer,
            'result' => $customer && $event['wheel'] ? $campaign->result($event['run'], (int) $customer->id) : null,
        ]);
    }

    public function rewards(Request $request, StoreCampaign $campaign): View|RedirectResponse
    {
        $customer = auth()->guard('customer')->user();
        if (! $customer) {
            $request->session()->put('shop.url.intended', route('shop.berkay.campaign.rewards'));

            return redirect()->route('shop.customer.session.index');
        }

        $results = DB::table('berkay_campaign_spins')
            ->where(['customer_id' => $customer->id, 'channel_id' => core()->getCurrentChannel()->id])
            ->latest('id')
            ->simplePaginate(15);

        $codes = $results->getCollection()->pluck('coupon')->filter()->all();
        $couponUses = $codes ? DB::table('cart_rule_coupons')
            ->whereIn('code', $codes)
            ->selectRaw('code, MAX(times_used) as times_used')
            ->groupBy('code')
            ->pluck('times_used', 'code') : collect();

        return view('berkay::shop.campaign-rewards', [
            'results' => $results,
            'couponUses' => $couponUses,
            'wheelAvailable' => (bool) ($campaign->active()['wheel'] ?? false),
        ]);
    }

    public function spin(Request $request, StoreCampaign $campaign): RedirectResponse|JsonResponse
    {
        $customer = auth()->guard('customer')->user();
        abort_unless($customer && $customer->status && $customer->is_verified && ! $customer->is_suspended
            && (int) $customer->channel_id === (int) core()->getCurrentChannel()->id, 403);
        $data = $request->validate([
            'run' => ['required', 'uuid'],
            'wheel_version' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $spun = $campaign->spin($data['run'], $customer, $data['wheel_version']);
        $url = route('shop.berkay.campaign.index').'#wheel';

        if ($request->expectsJson()) {
            $result = $spun ? $campaign->result($data['run'], $customer->id) : null;
            $prize = $result ? json_decode($result->prize, true, flags: JSON_THROW_ON_ERROR) : [];

            return response()->json([
                'segment' => $prize['segment'] ?? null,
                'won' => isset($prize['type']) && $prize['type'] !== 'none',
                'label' => $prize['label'] ?? null,
                'url' => $url,
            ]);
        }

        return redirect()->to($url);
    }
}
