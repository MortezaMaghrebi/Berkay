<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\Berkay\Http\Requests\CustomRequest;
use Webkul\Berkay\Repositories\CustomRequestRepository;
use Webkul\Shop\Http\Controllers\Controller;

class CustomRequestController extends Controller
{
    /** Inject the bespoke request repository. */
    public function __construct(protected CustomRequestRepository $requests) {}

    /** Display the enabled bespoke request form. */
    public function create(): View
    {
        abort_unless(core()->getConfigData('berkay.store.identity.custom_orders'), 404);

        return view('berkay::shop.custom-order');
    }

    /** Validate and record an inquiry without accepting a payment. */
    public function store(CustomRequest $request): RedirectResponse
    {
        abort_unless(core()->getConfigData('berkay.store.identity.custom_orders'), 404);

        $data = $request->validated();

        unset($data['website'], $data['images']);
        $data['reference'] = 'BK-'.Str::upper((string) Str::ulid());
        $paths = [];

        try {
            foreach ($request->file('images', []) as $image) {
                $path = $image->store('berkay/custom-orders', 'private');
                throw_unless($path, \RuntimeException::class, 'Reference image storage failed.');
                $paths[] = $path;
            }

            $data['images'] = $paths;
            $this->requests->create($data);
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($paths);

            throw $exception;
        }

        return redirect()->route('shop.berkay.custom_order.create')
            ->with('success', trans('berkay::app.request-saved', ['reference' => $data['reference']]));
    }
}
