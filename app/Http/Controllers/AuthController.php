<?php

namespace App\Http\Controllers;
use Google_Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{
    Auth,
    Hash,
    Mail,
    Log,
    DB
};
use Illuminate\Support\Str;
use App\Mail\{
    EmailVerification,
    ResetPasswordMail
};

class AuthController extends Controller
{
    // ============================
    // 🔐 LOGIN TRADICIONAL
    // ============================
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'photo' => $user->photo,
                'provider' => $user->provider,
            ],
            'token' => $token
        ], 200);
    }

    // ============================
    // 🔓 LOGOUT
    // ============================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente'], 200);
    }

    // ============================
    // 📝 REGISTRO
    // ============================
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'photo'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('photos/users', 'public')
            : null;

        $user = User::create([
            'name'  => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role'  => 'cliente',
            'photo' => $photoPath,
            'provider' => null,
            'provider_id' => null,
            'email_verification_token' => Str::random(60),
        ]);

        try {
            Mail::to($user->email)->send(new EmailVerification($user));
        } catch (\Exception $e) {
            Log::error('Error enviando correo de verificación: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Usuario creado correctamente. Verifica tu correo electrónico.',
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'photo' => $user->photo,
            ]
        ], 201);
    }

    // ============================
    // 👤 USUARIO LOGUEADO
    // ============================
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // ============================
    // ✅ VERIFICACIÓN EMAIL
    // ============================
    public function verifyEmail($token)
    {
        // Buscar usuario por token
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return response()->json([
                'error' => 'Token inválido o caducado'
            ], 400);
        }

        // Si ya está verificado
        if (!is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Correo ya verificado'
            ], 409);
        }

        // Marcar como verificado
        $user->email_verified_at = now(); // Se guarda como timestamp automáticamente
        $user->email_verification_token = null;
        $user->save();

        return response()->json([
            'message' => 'Correo verificado exitosamente'
        ], 200);
    }


    // ============================
    // 🔑 LOGIN CON GOOGLE
    // ============================
    // ------------------- LOGIN GOOGLE -------------------
    public function loginGoogle(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        // Inicializar Google Client con tu CLIENT_ID
        $client = new \Google_Client([
            'client_id' => env('GOOGLE_CLIENT_ID'),
        ]);

        // 🔧 Configurar SSL correctamente en entorno local (Windows/XAMPP)
        if (app()->environment('local')) {
            $cacertPath = 'C:\\xampp\\php\\extras\\ssl\\cacert.pem';

            if (file_exists($cacertPath)) {
                // Usa el certificado descargado (✅ forma recomendada)
                $guzzleClient = new \GuzzleHttp\Client([
                    'verify' => realpath($cacertPath),
                ]);
            } else {
                // Solo en local: si no existe el certificado, desactiva SSL (⚠️ solo desarrollo)
                $guzzleClient = new \GuzzleHttp\Client([
                    'verify' => false,
                ]);
            }

            // 👉 Forzar al Google Client a usar este cliente Guzzle personalizado
            $client->setHttpClient($guzzleClient);
        }

        // ✅ Verificar el token de Google
        try {
            $payload = $client->verifyIdToken($request->token);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error verificando el token de Google: ' . $e->getMessage(),
            ], 500);
        }

        if (!$payload) {
            return response()->json([
                'success' => false,
                'error' => 'Token de Google inválido',
            ], 401);
        }

        // Extraer los datos del usuario
        $email = $payload['email'];
        $name = $payload['name'] ?? 'Usuario Google';
        $photo = $payload['picture'] ?? null;
        $googleId = $payload['sub'];

        // Buscar usuario existente o crear uno nuevo
        $user = \App\Models\User::where('email', $email)->first();

        if ($user) {
            if ($user->provider !== 'google') {
                $user->update([
                    'provider' => 'google',
                    'provider_id' => $googleId,
                ]);
            }
        } else {
            $user = \App\Models\User::create([
                'name'  => $name,
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                'role'  => 'cliente',
                'photo' => $photo,
                'provider' => 'google',
                'provider_id' => $googleId,
                'email_verified_at' => now(),
            ]);
        }

        // Iniciar sesión y crear token de API
        \Illuminate\Support\Facades\Auth::login($user);
        $token = $user->createToken('API Token')->plainTextToken;

        // Respuesta JSON
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'role'     => $user->role,
                    'photo'    => $user->photo,
                    'provider' => $user->provider,
                ],
                'token' => $token,
            ]
        ], 200);
    }




    // ============================
    // 🔓 OLVIDÉ MI CONTRASEÑA
    // ============================
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'No existe usuario con ese email'], 404);
        }

        $token = Str::random(60);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => now()]
        );

        try {
            Mail::to($request->email)->send(new ResetPasswordMail($request->email, $token));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al enviar el enlace.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Revisa tu correo para el enlace de recuperación.']);
    }

    // ============================
    // 🔑 RESTABLECER CONTRASEÑA
    // ============================
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Token inválido o expirado'], 400);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Contraseña restablecida correctamente']);
    }
}
