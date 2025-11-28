<?php
require 'vendor/autoload.php';

use Cloudinary\Cloudinary;

try {
    $cloudinary = new Cloudinary(
        'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
    );

    $filePath = 'C:\\xampp\\htdocs\\decora10\\decora10-backend-clean\\storage\\app\\public\\DECORA10.png';

    $result = $cloudinary->uploadApi()->upload($filePath, [
        'folder' => 'decora10-test',
        'public_id' => 'DECORA10',
        'resource_type' => 'image',
    ]);

    echo "¡Subida correcta! URL: " . $result['secure_url'] . PHP_EOL;

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
