<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\FPC\Concerns\ForgetsPages;
use Webkul\Berkay\Services\StoreCampaign;

class CampaignController
{
    use ForgetsPages;

    public function index(Request $request, StoreCampaign $campaign): View
    {
        abort_unless(bouncer()->hasPermission('berkay.campaigns'), 403);

        $filter = $request->query('event_filter');
        $filter = is_string($filter) && isset(StoreCampaign::EVENTS[$filter]) ? $filter : null;

        $results = DB::table('berkay_campaign_spins')
            ->when($filter, fn ($query) => $query->where('berkay_campaign_spins.event', $filter))
            ->leftJoin('customers', 'customers.id', '=', 'berkay_campaign_spins.customer_id')
            ->where('berkay_campaign_spins.channel_id', core()->getCurrentChannel()->id)
            ->select('berkay_campaign_spins.*', 'customers.first_name', 'customers.last_name')
            ->latest('berkay_campaign_spins.id')
            ->paginate(20)->withQueryString();

        return view('berkay::admin.campaign', ['settings' => $campaign->settings(), 'results' => $results, 'filter' => $filter]);
    }

    public function update(Request $request, StoreCampaign $campaign): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.campaigns.edit'), 403);

        $data = $request->validate([
            'event' => ['nullable', Rule::in(array_keys(StoreCampaign::EVENTS))],
            'title' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:240'],
            'wheel' => ['required', 'boolean'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'prizes' => ['nullable', 'array', 'max:8'],
            'prizes.*' => ['array:label,type,value,weight'],
            'prizes.*.label' => ['nullable', 'string', 'max:45'],
            'prizes.*.type' => ['nullable', Rule::in(array_keys(StoreCampaign::TYPES))],
            'prizes.*.weight' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'prizes.*.value' => ['nullable', 'integer', 'min:0', 'max:9000000'],
        ]);

        $data['prizes'] = array_values(array_filter($data['prizes'] ?? [], fn ($prize) => filled($prize['label'] ?? null)));

        if (empty($data['event'])) {
            $data['wheel'] = false;
        }

        if ($data['wheel'] && count($data['prizes']) < 2) {
            throw ValidationException::withMessages(['prizes' => 'برای گردونه یک رویداد و دست‌کم دو جایزه یا گزینهٔ بدون جایزه تعریف کنید.']);
        }

        foreach ($data['prizes'] as $prize) {
            $type = $prize['type'] ?? null;
            $value = $prize['value'] ?? 0;
            if (
                ! $type || empty($prize['weight'])
                || ($type === 'percent' && ($value < 1 || $value > 100))
                || ($type === 'credit' && ($value < 1 || ! in_array(core()->getCurrentChannel()->base_currency->code, ['IRR', 'IRT'], true)))
            ) {
                throw ValidationException::withMessages(['prizes' => 'نوع، وزن و مقدار هر جایزه را بررسی کنید. درصد باید بین ۱ تا ۱۰۰ باشد و اعتبار خرید به تومان یا ریال نیاز دارد.']);
            }
        }

        $data['event'] ??= null;
        $campaign->save($data);
        $this->forgetPages([$this->homePath()]);

        return redirect()
            ->route('admin.berkay.campaigns.index')
            ->with('success', $data['event']
                ? 'تنظیمات ذخیره شد. بنر رویداد در صفحهٔ اول فروشگاه نمایش داده می‌شود.'
                : 'تنظیمات ذخیره شد. بنر رویداد از صفحهٔ اول فروشگاه برداشته شد.');
    }

    public function fulfill(int $id): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.campaigns.edit'), 403);

        $query = DB::table('berkay_campaign_spins')->where(['id' => $id, 'channel_id' => core()->getCurrentChannel()->id]);
        $result = $query->first();
        abort_unless($result && json_decode($result->prize, true)['type'] === 'product', 404);
        $query->whereNull('fulfilled_at')->update(['fulfilled_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'تحویل هدیه ثبت شد.');
    }
}
