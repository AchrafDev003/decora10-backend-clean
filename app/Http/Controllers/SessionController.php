<?php

namespace App\Http\Controllers;

use App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Listar todas las sesiones (histórico)
     */
    public function index()
    {
        $sessions = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select(
                'sessions.id',
                'users.id as user_id',
                'users.name',
                'users.email',
                'sessions.ip_address',
                'sessions.device_type',
                'sessions.browser_name',
                'sessions.os_name',
                'sessions.location',
                'sessions.pages_visited',
                'sessions.total_time_seconds',
                'sessions.login_at',
                'sessions.logout_at',
                'sessions.last_activity'
            )
            ->orderByDesc('sessions.last_activity')
            ->get()
            ->map(function ($s) {
                return [
                    'session_id'   => $s->id,
                    'user'         => $s->user_id ? [
                        'id'    => $s->user_id,
                        'name'  => $s->name,
                        'email' => $s->email,
                    ] : null,
                    'ip_address'   => $s->ip_address,
                    'device_type'  => $s->device_type,
                    'browser'      => $s->browser_name,
                    'os'           => $s->os_name,
                    'location'     => $s->location,
                    'pages'        => $s->pages_visited,
                    'time_seconds' => $s->total_time_seconds,
                    'login_at'     => optional($s->login_at)->toDateTimeString(),
                    'logout_at'    => optional($s->logout_at)->toDateTimeString(),
                    'last_activity'=> Carbon::createFromTimestamp(
                        $s->last_activity
                    )->toDateTimeString(),
                    'online'       => is_null($s->logout_at),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    /**
     * Usuarios actualmente online
     */
    public function online(Request $request)
    {
        $minutes = $request->query('minutes', 15);

        $sessions = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->whereNull('sessions.logout_at')
            ->where('sessions.last_activity', '>=', now()->subMinutes($minutes)->timestamp)
            ->select(
                'sessions.id',
                'users.name',
                'users.email',
                'sessions.device_type',
                'sessions.ip_address',
                'sessions.browser_name',
                'sessions.last_activity'
            )
            ->orderByDesc('sessions.last_activity')
            ->get();

        return response()->json([
            'success' => true,
            'online_window_minutes' => $minutes,
            'data' => $sessions
        ]);
    }

    /**
     * Forzar cierre de sesión (logout)
     */
    public function forceLogout(string $sessionId)
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->update([
                'logout_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    /**
     * Forzar logout de todas las sesiones de un usuario
     */
    public function forceLogoutUser(int $userId)
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->whereNull('logout_at')
            ->update([
                'logout_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las sesiones del usuario han sido cerradas'
        ]);
    }
}
