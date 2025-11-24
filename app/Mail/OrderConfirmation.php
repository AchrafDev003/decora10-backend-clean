<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $pdfPath;

    public function __construct($order, $pdfPath)
    {
        $this->order = $order;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        return $this->subject('Confirmación de tu pedido #' . $this->order->order_code)
            ->view('emails.order_confirmation') // 👈 vista del correo
            ->attach($this->pdfPath, [
                'as' => 'Factura-Pedido-' . $this->order->id . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
