<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\Repositories\UserManagementRepository;

class UserManagementController extends Controller
{
    /** Inject account management operations. */
    public function __construct(protected UserManagementRepository $users) {}

    /** List customers or staff for the store owner. */
    public function index(Request $request): View
    {
        $this->authorizeOwner();
        $data = $request->validate(['kind' => ['nullable', Rule::in(['customers', 'staff'])], 'q' => ['nullable', 'string', 'max:100']]);
        $kind = $data['kind'] ?? 'customers';

        return view('berkay::admin.users.index', ['kind' => $kind, 'accounts' => $this->users->listing($kind, $data['q'] ?? '')]);
    }

    /** Show a staff creation form. */
    public function create(): View
    {
        $this->authorizeOwner();

        return view('berkay::admin.users.edit', ['kind' => 'staff', 'account' => null, 'staff' => null, 'roles' => $this->users->roles()]);
    }

    /** Show the selected account without exposing its current password. */
    public function edit(string $kind, int $id): View
    {
        $this->authorizeOwner();
        $account = $this->users->account($kind, $id);

        return view('berkay::admin.users.edit', ['kind' => $kind, 'account' => $account, 'staff' => $kind === 'customers' ? $this->users->staffForEmail($account->email) : null, 'roles' => $this->users->roles()]);
    }

    /** Validate and save an existing account or a new staff member. */
    public function save(Request $request, string $kind = 'staff', ?int $id = null): RedirectResponse
    {
        $this->authorizeOwner();
        if ($id) {
            $this->users->account($kind, $id);
        }
        $grant = $kind === 'customers' && $request->boolean('grant_admin');
        $rules = [
            'email' => ['required', 'email', 'max:255', Rule::unique($kind === 'staff' ? 'admins' : 'customers', 'email')->ignore($id)],
            'password' => [$id && ! $grant ? 'nullable' : 'required', 'string', 'min:12', 'max:128', 'confirmed'],
            'status' => ['required', 'boolean'],
        ];
        if ($kind === 'staff') {
            $rules += ['name' => ['required', 'string', 'max:150'], 'role_id' => ['required', 'integer', 'exists:roles,id']];
        } else {
            $rules += ['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($id)], 'grant_admin' => ['nullable', 'boolean'], 'role_id' => [$grant ? 'required' : 'nullable', 'integer', 'exists:roles,id']];
        }
        $this->users->save($kind, $id, $request->validate($rules));

        return redirect()->route('admin.berkay.users.index', ['kind' => $grant ? 'staff' : $kind])->with('success', trans('berkay::app.saved'));
    }

    /** Restrict credential and role administration to full-access store owners. */
    protected function authorizeOwner(): void
    {
        abort_unless(auth()->guard('admin')->user()?->role?->permission_type === 'all', 403);
    }
}
