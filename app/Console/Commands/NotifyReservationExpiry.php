<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CartItem;
use Illuminate\Support\Facades\Log;
use App\Notifications\ReservationExpiryWarning;
use Carbon\Carbon; // ✅ Esto es correcto, solo aquí

class NotifyReservationExpiry extends Command
{
    /**
     * Nombre y firma del comando
     */
    protected $signature = 'notify:reservation-expiry';

    /**
     * Descripción del comando
     */
    protected $description = 'Notifica al usuario antes de que su reserva de producto expire.';

    /**
     * Ejecuta el comando
     */
    public function handle()
    {
        // --- Configuración ---
        $notifyMinutes = (int) config('app.reservation_notify_minutes', 3);
        $threshold = Carbon::now()->addMinutes($notifyMinutes);

        // --- Traer items de carrito que necesitan notificación ---
        $items = CartItem::with(['product', 'cart.user'])
            ->where('notified_expiry', false)
            ->get();

        $count = 0;

        foreach ($items as $item) {
            // Siempre asignar reserved_until = ahora + 24h
            $item->reserved_until = Carbon::now()->addHours(24);
            $item->save();

            // Notificar solo si reserved_until está dentro del umbral
            if ($item->reserved_until <= $threshold) {
                $user = $item->cart->user ?? null;

                if ($user && $user->email) {
                    try {
                        $user->notify(new ReservationExpiryWarning($item));

                        // Marcar como notificado
                        $item->notified_expiry = true;
                        $item->save();

                        $count++;
                    } catch (\Exception $e) {
                        Log::error("Error notificando usuario ID {$user->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->info("Usuarios notificados: $count");
        Log::info("Comando notify:reservation-expiry ejecutado. Usuarios notificados: $count");
    }
}
