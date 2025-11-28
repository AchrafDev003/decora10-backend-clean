<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductImage;
use Cloudinary\Cloudinary;

class UploadProductImages extends Command
{
    protected $signature = 'cloudinary:upload-products';
    protected $description = 'Sube las imágenes de productos a Cloudinary y actualiza la base de datos';

    public function handle()
    {
        $cloudinary = new Cloudinary(
            'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
        );

        $basePath = storage_path('app/public/images/products');

        $images = ProductImage::all();
        $this->info("Procesando " . $images->count() . " imágenes de productos...");

        foreach ($images as $img) {
            $localPath = $basePath . DIRECTORY_SEPARATOR . basename($img->image_path);

            if (!file_exists($localPath)) {
                $this->error("Archivo no encontrado: " . $localPath);
                continue;
            }

            $attempts = 0;
            $maxAttempts = 3;
            $uploaded = false;

            while (!$uploaded && $attempts < $maxAttempts) {
                $attempts++;
                try {
                    $result = $cloudinary->uploadApi()->upload($localPath, [
                        'folder' => 'products',
                        'public_id' => pathinfo($img->image_path, PATHINFO_FILENAME),
                        'resource_type' => 'image',
                    ], [
                        'timeout' => 60
                    ]);

                    $secureUrl = $result['secure_url'] ?? null;

                    if (!$secureUrl) {
                        throw new \Exception("No se obtuvo URL de Cloudinary");
                    }

                    // Actualizamos la DB
                    $img->image_path = $secureUrl;
                    $img->save();

                    $this->info("✅ Subida correcta: Producto ID {$img->product_id} -> {$secureUrl}");
                    $uploaded = true;

                } catch (\Exception $e) {
                    $this->error("⚠️ Intento $attempts fallido para {$img->image_path}: " . $e->getMessage());
                    if ($attempts < $maxAttempts) {
                        sleep(2); // espera antes de reintentar
                    } else {
                        $this->error("❌ No se pudo subir {$img->image_path} después de $maxAttempts intentos");
                    }
                }
            }
        }

        $this->info("¡Todas las imágenes de productos procesadas!");
    }
}
