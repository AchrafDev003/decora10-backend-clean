@component('mail::message')
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:auto;border:1px solid #ddd;border-radius:8px;overflow:hidden;font-family:'Segoe UI',sans-serif;">
        {{-- Header / Logo --}}
        <tr style="background-color:#27ae60;">
            <td style="text-align:center;padding:20px;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Decora10" style="width:200px;height:auto;">
                @else
                    <h2 style="color:#fff;margin:0;">Decora10</h2>
                @endif
            </td>
        </tr>

        {{-- Cuerpo del mensaje --}}
        <tr>
            <td style="padding:25px;background-color:#f9f9f9;">
                <h2 style="color:#27ae60;margin-top:0;">¡Hola {{ $user->name }}!</h2>
                <p style="font-size:16px;color:#333;margin-bottom:10px;">
                    Has reservado el siguiente producto:
                </p>
                <h3 style="font-size:18px;color:#555;margin:5px 0 15px;">{{ $product->name }}</h3>
                <p style="font-size:14px;color:#555;margin:5px 0;">
                    Tu reserva <strong>expira el {{ $expiry->format('d/m/Y H:i') }}</strong>.
                </p>
                <p style="font-size:14px;color:#555;margin:15px 0;">
                    Te recomendamos finalizar tu compra antes de que se libere el producto.
                </p>

                {{-- Botón --}}
                <div style="text-align:center;margin:20px 0;">
                    @component('mail::button', ['url' => $cartUrl, 'color' => 'success'])
                        Finalizar compra
                    @endcomponent
                </div>
            </td>
        </tr>

        {{-- Firma opcional --}}
        @if($firmaUrl)
            <tr>
                <td style="padding:15px;background-color:#f9f9f9;text-align:right;">
                    <img src="{{ $firmaUrl }}" alt="Firma Decora10" style="width:120px;height:auto;">
                </td>
            </tr>
        @endif

        {{-- Footer --}}
        <tr>
            <td style="background-color:#27ae60;text-align:center;padding:15px;color:#fff;font-size:12px;">
                Gracias por confiar en <strong>Decora10</strong> 💚<br>
                <a href="{{ url('/') }}" style="color:#fff;text-decoration:underline;">www.decora10.com</a>
            </td>
        </tr>
    </table>
@endcomponent
