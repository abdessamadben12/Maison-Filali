@php($c = $contact)
<!doctype html>
<html lang="fr">
<body style="margin:0;padding:24px;background:#fbf7f0;font-family:Arial,Helvetica,sans-serif;color:#1f2a27">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5d9c5">
    <tr><td style="background:#0f4a3e;padding:18px 24px;color:#fbf7f0;font-size:18px;letter-spacing:2px">MAISON FILALI</td></tr>
    <tr><td style="padding:24px">
        <p style="margin:0 0 4px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#c6a15b">Formulaire de contact du site</p>
        <h1 style="margin:0 0 18px;font-size:22px;font-weight:normal">{{ $c->subject_label }}</h1>
        <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:22px">
            <tr><td style="padding:3px 16px 3px 0;color:#6e6a60">Nom</td><td><strong>{{ $c->name }}</strong></td></tr>
            <tr><td style="padding:3px 16px 3px 0;color:#6e6a60">Téléphone</td><td><a href="tel:{{ $c->phone }}" style="color:#0f4a3e">{{ format_phone($c->phone) }}</a>
                · <a href="https://wa.me/212{{ substr($c->phone, 1) }}" style="color:#0f4a3e">WhatsApp</a></td></tr>
            @if ($c->email)
                <tr><td style="padding:3px 16px 3px 0;color:#6e6a60">E-mail</td><td><a href="mailto:{{ $c->email }}" style="color:#0f4a3e">{{ $c->email }}</a></td></tr>
            @endif
            <tr><td style="padding:3px 16px 3px 0;color:#6e6a60">Reçu le</td><td>{{ $c->created_at->translatedFormat('j F Y à H:i') }}</td></tr>
        </table>
        <div style="margin-top:18px;padding:16px;background:#f3eadb;font-size:15px;line-height:23px;white-space:pre-line">{{ $c->message }}</div>
        <p style="margin:20px 0 0;font-size:13px;color:#6e6a60">Ce message est aussi enregistré dans l'administration (menu « Messages »).
            @if ($c->email) Cliquez sur « Répondre » pour écrire directement au client. @endif</p>
    </td></tr>
</table>
</body>
</html>
