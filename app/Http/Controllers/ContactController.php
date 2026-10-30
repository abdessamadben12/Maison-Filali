<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function show()
    {
        return view('site.contact');
    }

    public function send(Request $request)
    {
        // Champ piège anti-robots : doit rester vide
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('success', 'Merci, votre message est bien envoyé.');
        }

        $request->validate(['phone' => ['nullable', 'string', 'max:64']]);
        $request->merge(['phone' => normalize_phone($request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'regex:/^0[5-7][0-9]{8}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'subject' => ['required', Rule::in(array_keys(ContactMessage::SUBJECTS))],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], ['phone.regex' => 'Entrez un numéro marocain valide (ex. 06 12 34 56 78).']);

        ContactMessage::create($data);

        return redirect()->route('contact')->with('success', 'Merci '.explode(' ', $data['name'])[0].', votre message est bien envoyé. Nous vous répondons sous 24 h (Lun – Sam).');
    }
}
