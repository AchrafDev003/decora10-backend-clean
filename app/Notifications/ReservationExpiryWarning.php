<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ReservationExpiryWarning extends Notification
{
    protected $item;
    protected $logoHeader;
    protected $firmaSrc;

    public function __construct($item)
    {
        $this->item = $item;

        // Rutas de las imágenes
        $basePath = public_path('storage/photos');

        // Logo cabecera
        $this->logoHeader = file_exists($basePath . '/header.png')
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($basePath . '/header.png'))
            : '';

        // Firma
        $this->firmaSrc = file_exists($basePath . '/Decor@10.png')
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($basePath . '/Decor@10.png'))
            : '';
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $product = $this->item->product;

        return (new MailMessage)
            ->subject('Tu reserva está a punto de caducar')
            // Aquí indicamos que use tu Blade completo
            ->view('emails.reservation_expiry', [
                'user' => $notifiable,
                'product' => $product,
                'expiry' => $this->item->reserved_until,
                'cartUrl' => url('/cart'),
                'logoUrl' => $this->logoHeader,
                'firmaUrl' => $this->firmaSrc,
            ]);
    }
}
