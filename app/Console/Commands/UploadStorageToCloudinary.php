<?php
require 'vendor/autoload.php';

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

try {
    $filePath = 'C:\\xampp\\htdocs\\decora10\\decora10-backend-clean\\storage\\app\\public\\DECORA10.png';

    $result = Cloudinary::uploadApi()->upload($filePath, [
        'folder' => 'decora10-test',   // la carpeta en Cloudinary
        'public_id' => 'DECORA10',     // ID único para el archivo
        'resource_type' => 'image',    // porque es PNG
    ]);

    echo "¡Subida correcta! URL: " . $result['secure_url'] . PHP_EOL;

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
