<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Cloudinary\Cloudinary;

class UploadUserPhotos extends Command
{
    protected $signature = 'cloudinary:upload-users';
    protected $description = 'Sube las fotos de usuarios a Cloudinary y actualiza la DB';

    public function handle()
    {
        $cloudinary = new Cloudinary(
            'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
        );

        $basePath = storage_path('app/public/photos/users');

        $users = User::all();
        $this->info("Procesando " . $users->count() . " fotos de usuarios...");

        foreach ($users as $user) {
            if (!$user->photo) {
                $this->warn("Usuario ID {$user->id} no tiene foto.");
                continue;
            }

            $localPath = $basePath . DIRECTORY_SEPARATOR . basename($user->photo);

            if (!file_exists($localPath)) {
                $this->error("Archivo no encontrado: " . $localPath);
                continue;
            }

            try {
                $result = $cloudinary->uploadApi()->upload($localPath, [
                    'folder' => 'users',
                    'public_id' => pathinfo($user->photo, PATHINFO_FILENAME),
                    'resource_type' => 'image',
                ]);

                $secureUrl = $result['secure_url'] ?? null;

                if (!$secureUrl) {
                    $this->error("No se obtuvo URL de Cloudinary para " . $user->photo);
                    continue;
                }

                // Actualizamos la DB
                $user->photo = $secureUrl;
                $user->save();

                $this->info("Subida correcta: Usuario ID {$user->id} -> {$secureUrl}");

            } catch (\Exception $e) {
                $this->error("Error subiendo {$user->photo}: " . $e->getMessage());
            }
        }

        $this->info("¡Todas las fotos de usuarios procesadas!");
    }
}
