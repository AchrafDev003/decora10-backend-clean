<p>Hola,</p>
<p>Haz clic en el siguiente enlace para restablecer tu contraseña:</p>
<p>
    <a href="{{ env('FRONTEND_URL') }}/reset-password?token={{ $token }}&email={{ $email }}">
        Restablecer Contraseña
    </a>
</p>
<p>Si no solicitaste este cambio, ignora este correo.</p>
<p>Decora10</p>
