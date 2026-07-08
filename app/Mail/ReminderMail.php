<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Reminder $reminder;
    public string $cancelUrl;
    public string $rescheduleUrl;
    public string $senderName;
    public string $productUrl;

    public function __construct(Reminder $reminder, string $senderName = 'BuyLater')
    {
        $this->reminder = $reminder;
        $this->senderName = $senderName;

        $baseUrl = config('app.url');
        $this->cancelUrl = $baseUrl . '/reminder/cancel/' . $reminder->token;
        $this->rescheduleUrl = $baseUrl . '/reminder/reschedule/' . $reminder->token;
        
        $shopDomain = $reminder->shop->name ?? 'Store';
        $this->productUrl = "https://{$shopDomain}/products/{$reminder->product_handle}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                $this->senderName
            ),
            subject: '⏰ Reminder: You wanted to buy "' . $this->reminder->product_title . '"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder',
        );
    }
}
