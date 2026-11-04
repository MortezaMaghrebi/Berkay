<?php

namespace Webkul\Berkay\Repositories;

use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Contracts\PaymentAttempt;
use Webkul\Core\Eloquent\Repository;

class PaymentAttemptRepository extends Repository
{
    /** Resolve the replaceable payment attempt contract. */
    public function model(): string
    {
        return PaymentAttempt::class;
    }

    /** Persist a bank receipt before local invoice finalization is attempted. */
    public function recordVerification(int $id, string $receipt): mixed
    {
        return DB::transaction(function () use ($id, $receipt) {
            $attempt = $this->model->newQuery()->lockForUpdate()->findOrFail($id);

            if ($attempt->status === 'pending') {
                $attempt->update(['status' => 'verified', 'receipt' => $receipt, 'verified_at' => now()]);
            }

            return $attempt;
        });
    }

    /** Execute invoice finalization and the paid marker as one local transaction. */
    public function finalize(int $id, callable $callback): mixed
    {
        return DB::transaction(function () use ($id, $callback) {
            $attempt = $this->model->newQuery()->lockForUpdate()->findOrFail($id);

            if ($attempt->status === 'verified') {
                $callback($attempt);
                $attempt->update(['status' => 'paid']);
            }

            return $attempt;
        });
    }
}
