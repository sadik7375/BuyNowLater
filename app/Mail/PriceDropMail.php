<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceDropMail extends Mailable
{
    use Queueable, SerializesModels;

    public Subscriber $subscriber;
    public float $newPrice;
    public string $senderName;
    public string $productUrl;

    public function __construct(Subscriber $subscriber, float $newPrice, string $senderName = 'BuyLater')
    {
        $this->subscriber = $subscriber;
        $this->newPrice = $newPrice;
        $this->senderName = $senderName;
        $this->productUrl = 'https://' . optional($subscriber->shop)->name . '/products/' . $subscriber->product_handle;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                $this->senderName
            ),
            subject: '🔥 Price Drop Alert: "' . $this->subscriber->product_title . '" is now cheaper!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.price-drop',
        );
    }
}
