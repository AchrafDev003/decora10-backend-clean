<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\ExpirePromosJob;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Libera reservas expiradas cada 10 minutos
        $schedule->command('release:reservations')->everyTenMinutes();

        // Notifica a usuarios sobre reservas que caducan cada 5 minutos
        $schedule->command('notify:reservation-expiry')->everyFiveMinutes();

        // Limpia promos caducadas una vez al día (medianoche)
        $schedule->job(new ExpirePromosJob)->dailyAt('00:00');
    }

    /* protected function commands(): void
    {
        // Carga automáticamente todos los comandos en app/Console/Commands
        // Cargar solo ImportMuebles
        $this->command(\App\Console\Commands\ImportMuebles::class);

        require base_path('routes/console.php');

        // No hace falta listar comandos aquí, solo si quieres registrar uno dinámicamente
        // Ejemplo (opcional):
        // $this->command(Commands\ImportMuebles::class);
    }*/
}
