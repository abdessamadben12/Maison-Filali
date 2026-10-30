<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail envoyé à Maison Filali pour chaque message du formulaire de contact. */
class ContactMessageReceived extends Mailable
{
    use Queueable;

    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouveau message — '.$this->contact->subject_label.' — '.$this->contact->name,
            // « Répondre » écrit directement au client (s'il a donné son e-mail)
            replyTo: $this->contact->email ? [new Address($this->contact->email, $this->contact->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-message');
    }
}
