<?php

namespace Webkul\Shop\Http\Controllers\Customer;

use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\User\SessionController as AdminSessionController;
use Webkul\Berkay\Services\PhoneVerification;
use Webkul\Shop\Http\Controllers\Controller;
use Webkul\Shop\Http\Requests\Customer\StorefrontLoginRequest;

class SessionController extends Controller
{
    use ThrottlesLogins;

    /**
     * The failed login attempts an email address and caller may make before waiting.
     */
    protected $maxAttempts = 6;

    /**
     * How many minutes those failed attempts are remembered for.
     */
    protected $decayMinutes = 1;

    /**
     * Display the resource.
     *
     * @return RedirectResponse|View
     */
    public function index()
    {
        if (auth()->guard('admin')->check()) {
            return redirect()->route('admin.dashboard.index');
        }

        if (auth()->guard('customer')->check()) {
            return redirect()->route('shop.home.index');
        }

        return view('shop::customers.sign-in');
    }

    /**
     * The request field holding the identifier login attempts are counted against.
     */
    public function username(): string
    {
        return 'email';
    }

    /**
     * Authenticate a storefront password sign-in and redirect by account role.
     */
    public function store(StorefrontLoginRequest $request, PhoneVerification $verification): RedirectResponse
    {
        if (auth()->guard('admin')->check()) {
            return redirect()->route('admin.dashboard.index');
        }

        $identifier = $request->validated('email');
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $identity = $isEmail ? $identifier : $verification->normalize($identifier);
        $request->merge(['email' => $identity]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        $password = $request->validated('password');
        $adminCredentials = ['email' => $identity, 'password' => $password];

        if ($isEmail && auth()->guard('admin')->validate($adminCredentials)) {
            return app(AdminSessionController::class)->store();
        }

        $guard = auth()->guard('customer');

        if (
            ! core()->getConfigData('berkay.store.authentication.customer_password')
            || ! $guard->attempt([
                $isEmail ? 'email' : 'phone' => $identity,
                'password' => $password,
                'channel_id' => core()->getCurrentChannel()->id,
                'status' => true,
                'is_verified' => true,
                'is_suspended' => false,
            ])
        ) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'email' => trans('shop::app.customers.login-form.invalid-credentials'),
            ])->redirectTo(route('shop.customer.session.index', ['tab' => 'password']));
        }

        $this->clearLoginAttempts($request);
        $request->session()->regenerate();
        $request->session()->forget(['shop.url.intended', 'berkay.phone.verified']);

        Event::dispatch('customer.after.login', $guard->user());

        return redirect()->route('shop.customers.account.orders.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy()
    {
        $id = auth()->guard('customer')->user()->id;

        auth()->guard('customer')->logout();

        session()->invalidate();

        session()->regenerateToken();

        Event::dispatch('customer.after.logout', $id);

        return redirect()->route('shop.home.index');
    }
}
