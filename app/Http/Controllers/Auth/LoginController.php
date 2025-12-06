<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginDevice;
use App\Models\Customer;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('guest:admin_web')->except('logout');
    }

    /**
     * Show the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        $data['searchDisable'] = false;
        return view('auth.login',$data);
    }

    /**
     * Handle the login request for normal login.
     * Tries to login as Customer first, then as Admin (User).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        DB::beginTransaction();

        try {
            // Try to login as Customer first
            if (Auth::guard('web')->attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::guard('web')->user();

                if ($user->status == PENDING) {
                    Auth::guard('web')->logout();
                    return back()->with(['error' => __("Before login, please wait for approval.")]);
                } elseif ($user->status == DISABLE || in_array($user->contributor_status, [CONTRIBUTOR_STATUS_HOLD, CONTRIBUTOR_STATUS_CANCELLED])) {
                    Auth::guard('web')->logout();
                    return back()->with(['error' => __("Your account has been disabled.")]);
                } else {
                    $this->customerPlanAndLoginDeviceCheck($user);

                    $this->redirectTo = RouteServiceProvider::getHome();

                    DB::commit();
                    return redirect($this->redirectTo)->with('success', __("Logged in successfully."));
                }
            }
            // Try to login as Admin (User)
            elseif (Auth::guard('admin_web')->attempt(['email' => $request->email, 'password' => $request->password])) {
                $admin = Auth::guard('admin_web')->user();

                DB::commit();
                return redirect()->route('admin.dashboard')->with('success', __("Logged in successfully."));
            } else {
                DB::rollBack();
                return back()->withErrors(['email' => __("Invalid email or password.")]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with(['error' => __("An error occurred. Please try again later.")]);
        }
    }


    /**
     * Redirect the user to the Google authentication page.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectSocialLogin($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle social login (Facebook, Google, etc.)
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleSocialLogin($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Check if the user already exists by email, or create a new one
            $user = Customer::where('email', $socialUser->email)->first();

            if ($user) {
                // If user exists, update the provider ID and log in
                $user->provider_id = $socialUser->id;
                $user->avatar = $socialUser->avatar;
                $user->save();

                if ($user->status == PENDING) {
                    return back()->with(['error' => __("Before login, please wait for approval.")]);
                } elseif ($user->status == DISABLE || in_array($user->contributor_status, [CONTRIBUTOR_STATUS_HOLD, CONTRIBUTOR_STATUS_CANCELLED])) {
                    return back()->with(['error' => __("Your account has been disabled.")]);
                } else {
                    Auth::login($user);
                }
            } else {
                // If user doesn't exist, register the user
                $nameParts = explode(' ', $socialUser->name, 2);
                $firstName = $nameParts[0];
                $lastName = isset($nameParts[1]) ? $nameParts[1] : '';


                $user = Customer::create([
                    'user_name' => preg_replace('/\s+/', '', strtolower($firstName)) . rand(1000, 9999),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'slug' => Str::slug($firstName . '-' . $lastName),
                    'email' => $socialUser->email,
                    'email_verified_at' => now(),
                    'password' => bcrypt(Str::random(8)),
                    'role' => 1, // Assuming 1 is customer role
                    'provider_id' => $socialUser->id,
                    'avatar' => $socialUser->avatar,
                    'status' => (getOption('registration_approval') == 1) ? PENDING : ACTIVE
                ]);

                if ($user->status == PENDING) {
                    return back()->with(['error' => __("Before login, please wait for approval.")]);
                } elseif ($user->status == DISABLE || in_array($user->contributor_status, [CONTRIBUTOR_STATUS_HOLD, CONTRIBUTOR_STATUS_CANCELLED])) {
                    return back()->with(['error' => __("Your account has been disabled.")]);
                } else {
                    Auth::login($user);
                }
            }

            // Perform login checks
            $this->customerPlanAndLoginDeviceCheck($user);

            return redirect($this->redirectTo)->route('home')->with('success', 'Logged in successfully.');
        } catch (\Exception $e) {
            return redirect(route('login'))->with('error', 'Error logging in with ' . ucfirst($provider) . ': ' . $e->getMessage());
        }
    }

    /**
     * Handle customer plan and login device checks.
     *
     * @param Customer $customer
     * @return void
     */
    protected function customerPlanAndLoginDeviceCheck($customer)
    {
        $customerPlan = customerPlanExit($customer->id);

        if ($customerPlan) {
            // Count active devices
            $userLogins = LoginDevice::whereCustomerId($customer->id)->count();

            if ($userLogins >= $customerPlan->plan->device_limit) {
                // Retrieve all session IDs for the user
                $sessions = LoginDevice::whereCustomerId($customer->id)->pluck('session_id');

                foreach ($sessions as $sessionId) {
                    // Skip the current session
                    if ($sessionId == session()->getId()) {
                        continue;
                    }

                    // Delete the session file
                    $sessionFile = storage_path('framework/sessions/' . $sessionId);
                    if (file_exists($sessionFile) && !is_null($sessionId)) {
                        unlink($sessionFile);
                    }
                }

                // Delete all old login device records
                LoginDevice::whereCustomerId($customer->id)->where('session_id', '!=', session()->getId())->delete();
            }
        } else {
            // Enforce a single device login
            $sessions = LoginDevice::whereCustomerId($customer->id)->pluck('session_id');

            foreach ($sessions as $sessionId) {
                if ($sessionId == session()->getId()) {
                    continue;
                }

                // Delete the session file
                $sessionFile = storage_path('framework/sessions/' . $sessionId);
                if (file_exists($sessionFile) && !is_null($sessionId)) {
                    unlink($sessionFile);
                }
            }

            // Delete all old login device records
            LoginDevice::whereCustomerId($customer->id)->where('session_id', '!=', session()->getId())->delete();
        }

        // Register the current device
        $this->loginDeviceStore($customer->id);
    }

    /**
     * Store the login device information.
     *
     * @param int $customerId
     * @return LoginDevice
     */
    protected function loginDeviceStore($customerId)
    {
        $info = json_decode(json_encode(array_merge(getIpInfo(), osBrowser())), true);

        $userLoginDevice = new LoginDevice();
        $userLoginDevice->ip = @$info['ip'];
        $userLoginDevice->location = "";
//        $userLoginDevice->location = @implode(',', $info['city']) . (" - " . @implode(',', $info['area']) . "- ") . @implode(',', $info['country']) . (" - " . @implode(',', $info['code']) . " ");
        $userLoginDevice->browser = @$info['browser'];
        $userLoginDevice->os = @$info['os_platform'];
        $userLoginDevice->longitude = @implode(',', $info['long']);
        $userLoginDevice->latitude = @implode(',', $info['lat']);
        $userLoginDevice->time = @$info['time'];
        $userLoginDevice->customer_id = $customerId;
        $userLoginDevice->session_id = session()->getId(); // Save session ID
        $userLoginDevice->save();

        return $userLoginDevice;
    }

    /**
     * Log the user out of the application.
     * Handles both Customer and Admin logout.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Check if user is logged in as admin
        if (Auth::guard('admin_web')->check()) {
            Auth::guard('admin_web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $request->wantsJson()
                ? new JsonResponse([], 204)
                : redirect()->route('login')->with('success', __('Logged out successfully.'));
        }
        
        // Handle customer logout
        if (Auth::guard('web')->check()) {
            $loginDevice = LoginDevice::where(['session_id' => session()->getId(), 'customer_id' => Auth::guard('web')->id()])->first();
            if ($loginDevice) {
                // Delete the device record
                $loginDevice->delete();
            }
            
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/')->with('success', __('Logged out successfully.'));
    }

    /**
     * Log the user out of the application (legacy method for compatibility).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function loggedOut(Request $request)
    {
        return $this->logout($request);
    }

}
