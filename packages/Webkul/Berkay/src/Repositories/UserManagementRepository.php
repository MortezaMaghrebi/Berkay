<?php

namespace Webkul\Berkay\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\User\Repositories\AdminRepository;
use Webkul\User\Repositories\RoleRepository;

class UserManagementRepository
{
    /** Inject the native account repositories. */
    public function __construct(protected CustomerRepository $customers, protected AdminRepository $admins, protected RoleRepository $roles) {}

    /** List accounts with a bounded search and stable pagination. */
    public function listing(string $kind, string $search): LengthAwarePaginator
    {
        $query = ($kind === 'staff' ? $this->admins : $this->customers)->getModel()->newQuery();
        if ($kind === 'staff') {
            $query->with('role');
        }
        $columns = $kind === 'staff' ? ['name', 'email'] : ['first_name', 'last_name', 'email', 'phone'];
        $query->when($search !== '', fn ($query) => $query->where(function ($query) use ($columns, $search) {
            foreach ($columns as $column) {
                $query->orWhere($column, db_grammar()->caseInsensitiveLike(), '%'.$search.'%');
            }
        }));

        return $query->latest('id')->paginate(20)->withQueryString();
    }

    /** Find the requested account in its explicit account type. */
    public function account(string $kind, int $id): mixed
    {
        return ($kind === 'staff' ? $this->admins : $this->customers)->findOrFail($id);
    }

    /** Return roles available to the store owner. */
    public function roles(): mixed
    {
        return $this->roles->all();
    }

    /** Find an existing staff account without silently linking credentials. */
    public function staffForEmail(string $email): mixed
    {
        return $this->admins->findOneByField('email', $email);
    }

    /** Save explicit account fields and protect active owner access within a transaction. */
    public function save(string $kind, ?int $id, array $data): mixed
    {
        return DB::transaction(function () use ($kind, $id, $data) {
            $locked = $this->admins->getModel()->newQuery()->with('role')->orderBy('id')->lockForUpdate()->get();
            $account = $id ? $this->account($kind, $id) : null;
            $password = filled($data['password'] ?? null) ? Hash::make($data['password']) : null;
            if ($kind === 'staff') {
                $role = $this->roles->findOrFail($data['role_id']);
                $reducesAccess = ! $data['status'] || $role->permission_type !== 'all';
                if (
                    $account
                    && $account->role->permission_type === 'all'
                    && $account->status
                    && $reducesAccess
                    && $locked->filter(fn ($admin) => $admin->status && $admin->role->permission_type === 'all')->count() <= 1
                ) {
                    throw ValidationException::withMessages(['role_id' => trans('berkay::app.users.last-owner')]);
                }
                if (
                    $account?->id === auth()->guard('admin')->id()
                    && $reducesAccess
                ) {
                    throw ValidationException::withMessages(['status' => trans('berkay::app.users.self-access')]);
                }
                $values = array_intersect_key($data, array_flip(['name', 'email', 'role_id', 'status']));
                if ($password) {
                    $values += ['password' => $password, 'remember_token' => null];
                }

                Event::dispatch('user.admin.'.($account ? 'update' : 'create').'.before', $id);
                $saved = $account ? $this->admins->update($values, $id) : $this->admins->create($values);
                Event::dispatch('user.admin.'.($account ? 'update' : 'create').'.after', $saved);

                return $saved;
            }
            $values = array_intersect_key($data, array_flip(['first_name', 'last_name', 'email', 'phone', 'status']));
            if ($password) {
                $values += ['password' => $password];
            }
            Event::dispatch('customer.update.before', $id);
            $saved = $this->customers->update($values, $id);
            Event::dispatch('customer.update.after', $saved);
            if (! empty($data['grant_admin'])) {
                if ($this->staffForEmail($data['email'])) {
                    throw ValidationException::withMessages(['grant_admin' => trans('berkay::app.users.staff-exists')]);
                }
                $this->roles->findOrFail($data['role_id']);
                Event::dispatch('user.admin.create.before');
                $staff = $this->admins->create(['name' => trim($saved->first_name.' '.$saved->last_name), 'email' => $saved->email, 'password' => $password, 'role_id' => $data['role_id'], 'status' => 1]);
                Event::dispatch('user.admin.create.after', $staff);
            }

            return $saved;
        });
    }
}
