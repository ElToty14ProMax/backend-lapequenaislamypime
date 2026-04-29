<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order, public string $previousStatus)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Actualizacion de pedido '.$this->order->number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-status-changed');
    }
}
