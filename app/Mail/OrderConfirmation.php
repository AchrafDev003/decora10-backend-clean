<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function build(): self
    {
        return $this->subject('Confirmación de tu pedido en Decora10')
            ->view('emails.order-confirmation'); // sin attach ni pdf
    }
}
