<?php

namespace Webkul\Shop\Http\Controllers\Customer;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductReviewRepository;
use Webkul\Shop\Http\Controllers\Controller;
use Webkul\Shop\Http\Requests\Customer\ProfileRequest;

class CustomerController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected ProductReviewRepository $productReviewRepository,
    ) {}

    /**
     * Taking the customer to profile details page.
     *
     * @return View
     */
    public function index()
    {
        $customer = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        return view('shop::customers.account.profile.index', compact('customer'));
    }

    /**
     * For loading the edit form page.
     *
     * @return View
     */
    public function edit()
    {
        $customer = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        return view('shop::customers.account.profile.edit', compact('customer'));
    }

    /**
     * Edit function for editing customer profile.
     *
     * @return Response
     */
    public function update(ProfileRequest $profileRequest)
    {
        $customer = auth()->guard('customer')->user();
        Event::dispatch('customer.update.before');
        $saved = $this->customerRepository->update($profileRequest->safe()->except('image'), $customer->id);

        if ($profileRequest->hasFile('image')) {
            $previous = $saved->image;
            $saved->image = $profileRequest->file('image')->store('customer/'.$saved->id);
            $saved->save();

            if ($previous) {
                Storage::delete($previous);
            }
        }

        Event::dispatch('customer.update.after', $saved);

        return redirect()->route('shop.customers.account.profile.index')
            ->with('success', trans('shop::app.customers.account.profile.index.edit-success'));
    }


    /**
     * Load the view for the customer account panel, showing approved reviews.
     *
     * @return View
     */
    public function reviews()
    {
        $reviews = $this->productReviewRepository->getCustomerReview();

        return view('shop::customers.account.reviews.index', compact('reviews'));
    }

    /**
     * Taking the customer to account details page.
     *
     * @return View
     */
    public function account()
    {
        return view('shop::customers.account.index');
    }
}
