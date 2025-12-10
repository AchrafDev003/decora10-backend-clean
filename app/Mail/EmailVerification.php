<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function build()
    {
        $url = config('app.frontend_url') . "/verify-email?token={$this->user->email_verification_token}";

        return $this->view('emails.verify')
            ->subject('Verifica tu cuenta en Decora10')
            ->with([
                'url' => $url
            ]);
    }


}
