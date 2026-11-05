<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\DataGrids\PaymentDataGrid;
use Webkul\Berkay\Http\Requests\CustomRequestStatus;
use Webkul\Berkay\Repositories\CustomRequestRepository;

class OperationsController extends Controller
{
    /** Inject the bespoke operations repository. */
    public function __construct(protected CustomRequestRepository $requests) {}

    /** Show a reference image only to staff allowed to read bespoke requests. */
    public function image(int $id, int $index): StreamedResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.custom-orders'), 403);
        $inquiry = $this->requests->findOrFail($id);
        $path = $inquiry->images[$index] ?? null;
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Show actionable store configuration and operation links. */
    public function index(): View
    {
        abort_unless(bouncer()->hasPermission('berkay'), 403);

        return view('berkay::admin.operations');
    }

    /** Show bank attempts and invoice reconciliation states. */
    public function payments(): View|JsonResponse|BinaryFileResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.payments'), 403);

        if (request()->ajax()) {
            return datagrid(PaymentDataGrid::class)->process();
        }

        return view('berkay::admin.payments');
    }

    /** List customer inquiries for authorized staff. */
    public function requests(): View
    {
        abort_unless(bouncer()->hasPermission('berkay.custom-orders'), 403);

        return view('berkay::admin.custom-orders', ['requests' => $this->requests->listing()]);
    }

    /** Record inquiry status and private staff notes. */
    public function updateRequest(CustomRequestStatus $request, int $id): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.custom-orders.edit'), 403);
        $data = $request->validated();

        $this->requests->findOrFail($id);
        $this->requests->update($data, $id);

        return redirect()->route('admin.berkay.custom_orders.index')->with('success', trans('berkay::app.saved'));
    }
}
