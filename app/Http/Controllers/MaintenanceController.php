<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Jobs\ExpirePromosJob;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class MaintenanceController extends Controller
{
    /**
     * Ejecutar todas las tareas de mantenimiento
     */
    public function runAll(): JsonResponse
    {
        try {
            Log::info("🔄 [Maintenance] Ejecutando todas las tareas...");

            $this->release();
            $this->notify();
            $this->promos();
            $this->cleanup();

            return response()->json([
                'success' => true,
                'message' => 'Todas las tareas de mantenimiento ejecutadas con éxito.'
            ]);
        } catch (\Exception $e) {
            Log::error("❌ [Maintenance] Error en runAll: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error ejecutando mantenimiento',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liberar reservas bloqueadas
     */
    public function release(): JsonResponse
    {
        try {
            Artisan::call('release:reservations');
            Log::info("✅ [Maintenance] release:reservations ejecutado correctamente.");

            return response()->json([
                'success' => true,
                'message' => 'Reservas liberadas correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error("❌ [Maintenance] Error en release: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function notifyUser(User $user)
    {
        try {
            // Ejemplo: enviar correo al usuario
            Mail::raw("Se le notifica al usuario {$user->name}", function ($message) use ($user) {
                $message->to($user->email)->subject("Notificación de Relanzamiento");
            });

            return response()->json([
                'success' => true,
                'message' => "Usuario {$user->name} relanzado correctamente"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al relanzar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notificar a usuarios pendientes
     */
    public function notify(): JsonResponse
    {
        try {
            // ⚠️ Cambiado al comando correcto
            Artisan::call('notify:reservation-expiry');
            Log::info("✅ [Maintenance] notify:reservation-expiry ejecutado correctamente.");

            return response()->json([
                'success' => true,
                'message' => 'Usuarios notificados correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error("❌ [Maintenance] Error en notify: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Expirar promociones caducadas
     */
    public function promos(): JsonResponse
    {
        try {
            // Ejecutar el Job directamente
            ExpirePromosJob::dispatchSync();

            return response()->json([
                'success' => true,
                'message' => 'Promociones caducadas eliminadas.'
            ]);
        } catch (\Exception $e) {
            Log::error("❌ [Maintenance] Error en promos: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Limpieza general (logs, cache, etc.)
     */
    public function cleanup(): JsonResponse
    {
        try {
            Artisan::call('optimize:clear');
            Log::info("✅ [Maintenance] optimize:clear ejecutado correctamente.");

            return response()->json([
                'success' => true,
                'message' => 'Sistema limpiado y optimizado.'
            ]);
        } catch (\Exception $e) {
            Log::error("❌ [Maintenance] Error en cleanup: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
