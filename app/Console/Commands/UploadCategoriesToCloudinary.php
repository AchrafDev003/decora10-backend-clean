<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use Cloudinary\Cloudinary;

class UploadCategoriesToCloudinary extends Command
{
    protected $signature = 'cloudinary:upload-categories';
    protected $description = 'Sube todas las imágenes de categorías a Cloudinary y actualiza la DB';

    public function handle()
    {
        $cloudinary = new Cloudinary(
            env('CLOUDINARY_URL')
        );

        $categories = Category::all();

        foreach ($categories as $category) {
            if (!$category->image) {
                $this->warn("Categoría '{$category->name}' no tiene imagen, saltando...");
                continue;
            }

            $localPath = storage_path('app/public/' . $category->image);

            if (!file_exists($localPath)) {
                $this->error("Archivo no encontrado: {$localPath}");
                continue;
            }

            try {
                $result = $cloudinary->uploadApi()->upload($localPath, [
                    'folder' => 'categories',
                    'public_id' => pathinfo($category->image, PATHINFO_FILENAME),
                    'resource_type' => 'image',
                ]);

                $secureUrl = $result['secure_url'] ?? null;

                if ($secureUrl) {
                    $category->image = $secureUrl;
                    $category->save();
                    $this->info("Subida correcta: {$category->name} -> $secureUrl");
                } else {
                    $this->error("No se obtuvo URL de Cloudinary para {$category->name}");
                }

            } catch (\Exception $e) {
                $this->error("Error subiendo {$category->name}: " . $e->getMessage());
            }
        }

        $this->info("¡Todas las categorías procesadas!");
    }
}
