<?php

namespace Webkul\Berkay\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Webkul\Berkay\Contracts\CustomRequest;
use Webkul\Core\Eloquent\Repository;

class CustomRequestRepository extends Repository
{
    /** Resolve the replaceable bespoke request contract. */
    public function model(): string
    {
        return CustomRequest::class;
    }

    /** Paginate requests with the newest first. */
    public function listing(): LengthAwarePaginator
    {
        return $this->model->newQuery()->latest('id')->paginate(20);
    }
}
