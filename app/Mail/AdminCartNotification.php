<?php

namespace App\Mail;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminCartNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Cart $cart;
    public Product $product;
    public int $quantity;

    public function __construct(Cart $cart, Product $product, int $quantity)
    {
        $this->cart = $cart;
        $this->product = $product;
        $this->quantity = $quantity;
    }

    public function build()
    {
        return $this->subject('🛒 Producto añadido al carrito')
            ->view('emails.admin.cart-notification');
    }
}
