<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            'Colchonería' => [
                'Colchones',
                'Canapés',
                'Cabeceros',
                'Bases tapizadas',
                'Somieres',
                'Protectores de colchón',
                'Almohadas',
                'Edredones y fundas nórdicas',
                'Ropa de cama',
                'Accesorios para descanso',
            ],
            'Mobiliario' => [
                'Sillas',
                'Mesas',
                'Escritorios',
                'Estanterías',
                'Aparadores',
                'Vitrinas',
                'Taburetes',
            ],

            'Iluminación' => [
                'Lámparas de mesita',
                'Lámparas de techo',
                'Lámparas de mesa',
                'Lámparas de pie',
                'Faroles, portavelas y candelabros',
                'Velas aromáticas y decorativas',
            ],
            'Textil hogar' => [
                'Felpudos originales',
                'Barras de cortinas',
                'Mantas para sofás y plaids',
                'Alfombras',
                'Alfombras de bambú',
                'Cojines',
                'Estores',
                'Manteles y servilletas',
                'Ropa de cama',
                'Cortinas y visillos',
            ],
            'Decoración' => [
                'Plantas y flores artificiales',
                'Espejos de pared',
                'Tapas contador luz',
                'Figuras decorativas',
                'Accesorios decorativos',
                'Vaciabolsillos',
                'Topes para puertas',
                'Relojes de pared',
                'Joyeros',
                'Cajas organizadoras y decorativas',
                'Marcos de fotos y portafotos',
                'Lienzos y cuadros decorativos',
                'Murales de pared',
                'Jarrones y floreros',
                'Paragüeros',
                'Centros de mesa',
                'Macetas y maceteros',
            ],
            'Muebles' => [
                'Percheros',
                'Zapateros',
                'Muebles de baño',
                'Banquetas y bancos',
                'Estanterías, librerías y estantes',
                'Recibidores y mesitas consola',
                'Mesas',
                'Asientos',
                'Biombos Separadores',
                'Camping',
                'Playa',
            ],
        ];

        foreach ($categories as $name => $subcategories) {
            Category::create([
                'name' => $name,
                'slug' => strtolower($name),
                'description' => implode(', ', $subcategories),
            ]);
        }
    }
}
