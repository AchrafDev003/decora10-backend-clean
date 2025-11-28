<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HeroItem;
use Cloudinary\Cloudinary;

class UploadHeroItems extends Command
{
    protected $signature = 'cloudinary:upload-hero';
    protected $description = 'Sube las imágenes y videos de hero_items a Cloudinary y actualiza la base de datos';

    public function handle()
    {
        $cloudinary = new Cloudinary(
            'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
        );

        $basePath = storage_path('app/public/hero');

        $items = HeroItem::all();
        $this->info("Procesando " . $items->count() . " hero items...");

        foreach ($items as $item) {
            $localPath = $basePath . DIRECTORY_SEPARATOR . $item->media_filename;

            if (!file_exists($localPath)) {
                $this->error("Archivo no encontrado: " . $localPath);
                continue;
            }

            // Determinar tipo de recurso
            $resourceType = strpos($item->media_type, 'video') !== false ? 'video' : 'image';

            try {
                $result = $cloudinary->uploadApi()->upload($localPath, [
                    'folder' => 'hero',
                    'public_id' => pathinfo($item->media_filename, PATHINFO_FILENAME),
                    'resource_type' => $resourceType,
                ], [
                    'timeout' => 60
                ]);

                $secureUrl = $result['secure_url'] ?? null;

                if (!$secureUrl) {
                    $this->error("No se obtuvo URL de Cloudinary para " . $item->media_filename);
                    continue;
                }

                // Actualizamos la DB
                $item->media_filename = $secureUrl;
                $item->save();

                $this->info("✅ Subida correcta: HeroItem ID {$item->id} -> {$secureUrl}");

            } catch (\Exception $e) {
                $this->error("❌ Error subiendo {$item->media_filename}: " . $e->getMessage());
            }
        }

        $this->info("¡Todos los hero items procesados!");
    }
}
