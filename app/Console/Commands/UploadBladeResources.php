<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Cloudinary\Cloudinary;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class UploadBladeResources extends Command
{
    protected $signature = 'cloudinary:upload-blade-resources';
    protected $description = 'Sube imágenes de Blade a Cloudinary y guarda las URLs en la tabla settings';

    public function handle()
    {
        $cloudinary = new Cloudinary(
            'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
        );

        $basePath = storage_path('app/public/photos/recursosBlade');

        if (!is_dir($basePath)) {
            $this->error("🚨 Carpeta no encontrada: {$basePath}");
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        $this->info("📂 Procesando imágenes en Blade resources...");

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $folder = str_replace('\\', '/', dirname($relativePath));

            // Generamos un public_id válido para Cloudinary
            $publicId = pathinfo($relativePath, PATHINFO_FILENAME);
            $publicId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $publicId);

            try {
                $result = $cloudinary->uploadApi()->upload($file->getPathname(), [
                    'folder'        => 'blade-resources/' . ($folder === '.' ? '' : $folder),
                    'public_id'     => $publicId,
                    'resource_type' => 'image',
                ], [
                    'timeout' => 60, // timeout 60s
                ]);

                $secureUrl = $result['secure_url'] ?? null;

                if (!$secureUrl) {
                    $this->warn("⚠️ No se obtuvo URL para {$relativePath}");
                    continue;
                }

                // Guardamos en settings: inserción segura con timestamps
                DB::table('settings')->updateOrInsert(
                    ['key' => $relativePath],
                    [
                        'value'      => $secureUrl,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $this->info("✅ Subida correcta: {$relativePath} -> {$secureUrl}");

            } catch (\Exception $e) {
                $this->error("❌ Error subiendo {$relativePath}: " . $e->getMessage());
            }
        }

        $this->info("🎉 Todas las imágenes de Blade procesadas con éxito!");
    }
}
