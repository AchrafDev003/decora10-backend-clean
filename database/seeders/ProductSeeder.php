<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Carbon\Carbon;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'id_product'    => 'PROD001',
            'name'          => 'CAMA MANGO RATAN 167X212X120 29,33 BLANCO ROTO',
            'description'   => <<<EOT
Descripción: CAMA MANGO RATAN 167X212X120 29,33 BLANCO ROTO
Referencia: MB-215075
Tipo: CAMA
Familia: MESITAS DE NOCHE Y CABECEROS
Marca: IT
Código de barras: 8424002150754
Catálogo: 2024-04
Ambientes: ROMANTICO
Colección: SEA SIDE
CAJA REGALO: NO
MATERIAL 1: MANGO
MATERIAL 2: RATAN
COLOR 1:
DESMONTABLE: SI
EOT,
            'price'         => 670.84,
            'promo_price'   => 620.99,
            'is_promo'      => true,
            'promo_ends_at' => now()->addDays(15),
            'quantity'      => 10,
            'image'         => 'cama1.jpg',
            'category_id'   => 8,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Product::create([
            'id_product'    => 'PROD002',
            'name'          => 'Colchón Ortopédico Premium',
            'description'   => 'Colchón ergonómico con memoria inteligente para un descanso óptimo.',
            'price'         => 499.00,
            'promo_price'   => null,
            'is_promo'      => false,
            'promo_ends_at' => null,
            'quantity'      => 20,
            'image'         => 'colchon1.jpg',
            'category_id'   => 2,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Product::create([
            'id_product'    => 'PROD003',
            'name'          => 'Lámpara Náutica de Techo',
            'description'   => 'Lámpara colgante estilo marinero con detalles en cuerda y metal oxidado.',
            'price'         => 129.90,
            'promo_price'   => 109.90,
            'is_promo'      => true,
            'promo_ends_at' => now()->addDays(7),
            'quantity'      => 18,
            'image'         => 'lampara1.jpg',
            'category_id'   => 3,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Product::create([
            'id_product'    => 'PROD004',
            'name'          => 'MACETERO BAMBU METAL 26X26X40 NATURAL',
            'description'   => <<<EOT
Descripción: MACETERO BAMBU METAL 26X26X40 NATURAL
Referencia: MC-198851
Tipo: MACETERO
Familia: JAULAS
Marca: ITEM
Código de barras: 8424001988518
Catálogo: 2022-04
Ambientes: COLONIAL
Colección: SEA SIDE
disenyitem: NO
CAJA REGALO: NO
MATERIAL 1: BAMBU
MATERIAL 2: METAL
COLOR 1: NATURAL
COLOR 2: NEGRO
DESMONTABLE: NO
EOT,
            'price'         => 35.00,
            'promo_price'   => 25.00,
            'is_promo'      => false,
            'promo_ends_at' => null,
            'quantity'      => 30,
            'image'         => 'macetero1.jpg',
            'category_id'   => 4,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Product::create([
            'id_product'    => 'PROD005',
            'name'          => 'Set ITEM Home Aromaterapia',
            'description'   => 'Difusor y velas aromáticas con fragancia natural para ambientar tus espacios.',
            'price'         => 39.90,
            'promo_price'   => 29.90,
            'is_promo'      => true,
            'promo_ends_at' => now()->addDays(5),
            'quantity'      => 50,
            'image'         => 'Aromaterapia.jpg',
            'category_id'   => 5,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Product::create([
            'id_product'    => 'PROD006',
            'name'          => 'Espejo Sol Decorativo',
            'description'   => 'Espejo redondo con marco dorado en forma de sol, ideal para salas y entradas.',
            'price'         => 89.00,
            'promo_price'   => null,
            'is_promo'      => false,
            'promo_ends_at' => null,
            'quantity'      => 12,
            'image'         => 'espejosol1.jpg',
            'category_id'   => 6,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
