<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('site.auth.login', ['mode' => 'login']);
    }

    public function showRegister()
    {
        return view('site.auth.login', ['mode' => 'register']);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string']]);
        $key = 'login:'.Str::lower($data['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Trop de tentatives. Réessayez dans '.ceil(RateLimiter::availableIn($key) / 60).' min.']);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $value = $field === 'phone' ? normalize_phone($data['login']) : Str::lower($data['login']);

        if (! Auth::attempt([$field => $value, 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        if (Auth::user()->is_admin) {
            return redirect('/admin');
        }

        return redirect()->intended(route('account.orders'));
    }

    public function register(Request $request)
    {
        $request->validate(['phone' => ['nullable', 'string', 'max:64'], 'email' => ['nullable', 'string', 'max:190']]);
        $request->merge(['phone' => normalize_phone($request->input('phone')), 'email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'phone' => ['required', 'regex:/^0[5-7][0-9]{8}$/', Rule::unique('users', 'phone')],
            'city' => ['nullable', 'string', Rule::in(config('shop.cities'))],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'phone.regex' => 'Entrez un numéro marocain valide (ex. 06 12 34 56 78).',
            'phone.unique' => 'Un compte existe déjà avec ce numéro.',
            'email.unique' => 'Un compte existe déjà avec cet e-mail.',
        ]);

        $user = User::create($data);

        // Rattache les commandes passées sans compte avec le même téléphone
        Customer::where('phone', $user->phone)->update(['user_id' => $user->id, 'email' => $user->email]);
        Order::where('phone', $user->phone)->whereNull('user_id')->update(['user_id' => $user->id]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('account.orders')->with('success', 'Votre compte est créé.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
