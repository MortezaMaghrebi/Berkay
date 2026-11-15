<?php

namespace Webkul\Shop\Http\Controllers\Customer;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    /** Show the authenticated customer's password form. */
    public function editPassword(Request $request): View
    {
        $hasPassword = filled($request->user('customer')->password);

        return view('shop::customers.account.profile.password', compact('hasPassword'));
    }

    /** Set the first password or replace it after verifying the current password. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $credentials = $request->validate([
            'current_password' => [filled($customer->password) ? 'required' : 'nullable', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', 'not_regex:/\x00/', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('validation.max.string')->translate(['max' => 72]);
                }
            }],
        ]);

        if (! $this->customerRepository->changePassword($customer->id, $credentials['password'], $credentials['current_password'] ?? null)) {
            throw ValidationException::withMessages([
                'current_password' => trans('shop::app.customers.account.profile.index.unmatched'),
            ]);
        }

        $request->session()->regenerate(true);

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
