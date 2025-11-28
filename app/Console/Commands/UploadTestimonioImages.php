<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Testimonio;
use Cloudinary\Cloudinary;

class UploadTestimonioImages extends Command
{
    protected $signature = 'cloudinary:upload-testimonios';
    protected $description = 'Sube las imágenes de testimonios a Cloudinary y actualiza la DB';

    public function handle()
    {
        $cloudinary = new Cloudinary(
            'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
        );

        $basePath = storage_path('app/public/photos/users');

        $items = Testimonio::all();
        $this->info("Procesando " . $items->count() . " imágenes de testimonios...");

        foreach ($items as $item) {
            if (!$item->imagen) {
                $this->warn("Testimonio ID {$item->id} no tiene imagen.");
                continue;
            }

            $localPath = $basePath . DIRECTORY_SEPARATOR . basename($item->imagen);

            if (!file_exists($localPath)) {
                $this->error("Archivo no encontrado: " . $localPath);
                continue;
            }

            try {
                $result = $cloudinary->uploadApi()->upload($localPath, [
                    'folder' => 'testimonios',
                    'public_id' => pathinfo($item->imagen, PATHINFO_FILENAME),
                    'resource_type' => 'image',
                ]);

                $secureUrl = $result['secure_url'] ?? null;

                if (!$secureUrl) {
                    $this->error("No se obtuvo URL de Cloudinary para " . $item->imagen);
                    continue;
                }

                // Actualizamos la DB
                $item->imagen = $secureUrl;
                $item->save();

                $this->info("Subida correcta: Testimonio ID {$item->id} -> {$secureUrl}");

            } catch (\Exception $e) {
                $this->error("Error subiendo {$item->imagen}: " . $e->getMessage());
            }
        }

        $this->info("¡Todas las imágenes de testimonios procesadas!");
    }
}
