<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Berkay\Services\StoreCampaign;

class CampaignController
{
    public function banner(StoreCampaign $campaign): View
    {
        return view('berkay::shop.campaign-banner', ['event' => $campaign->active()]);
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
            'result' => $customer ? $campaign->result($event['run'], $customer->id) : null,
        ]);
    }

    public function rewards(): View
    {
        $customer = auth()->guard('customer')->user();
        abort_unless($customer, 403);

        $results = \Illuminate\Support\Facades\DB::table('berkay_campaign_spins')
            ->where(['customer_id' => $customer->id, 'channel_id' => core()->getCurrentChannel()->id])
            ->latest('id')->paginate(15);

        return view('berkay::shop.campaign-rewards', compact('results'));
    }

    public function spin(Request $request, StoreCampaign $campaign): RedirectResponse
    {
        $customer = auth()->guard('customer')->user();
        abort_unless($customer && $customer->status && $customer->is_verified && ! $customer->is_suspended
            && (int) $customer->channel_id === (int) core()->getCurrentChannel()->id, 403);
        $data = $request->validate(['run' => ['required', 'uuid']]);
        $campaign->spin($data['run'], $customer);

        return redirect()->route('shop.berkay.campaign.index');
    }
}
