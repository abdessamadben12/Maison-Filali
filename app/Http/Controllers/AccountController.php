<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function orders(Request $request)
    {
        $orders = $request->user()->orders()->with('items.product')->latest('id')->paginate(20);

        return view('site.account.orders', [
            'orders' => $orders,
            'current' => $request->user()->orders()->whereIn('status', ['en_attente', 'confirmee', 'expediee'])->latest('id')->first(),
        ]);
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('site.account.order', ['order' => $order->load('items.product')]);
    }

    public function info(Request $request)
    {
        return view('site.account.info', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->validate(['phone' => ['nullable', 'string', 'max:64'], 'email' => ['nullable', 'string', 'max:190']]);
        $request->merge(['phone' => normalize_phone($request->input('phone')), 'email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'phone' => ['required', 'regex:/^0[5-7][0-9]{8}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'city' => ['nullable', 'string', Rule::in(config('shop.cities'))],
            'address' => ['nullable', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ], ['phone.regex' => 'Entrez un numéro marocain valide (ex. 06 12 34 56 78).']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);
        Customer::where('user_id', $user->id)->update(['email' => $user->email]);

        return back()->with('success', 'Vos informations sont enregistrées.');
    }
}
