<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\User;
use App\Models\Product;

class ReviewSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        $products = Product::all();

        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->info('No hay usuarios o productos para asignar reseñas.');
            return;
        }

        $comentarios = [
            'Excelente calidad y diseño.',
            'Muy cómodo y elegante.',
            'No era lo que esperaba, pero está bien.',
            'Perfecto para mi hogar.',
            'Volvería a comprar sin duda.',
            'El producto llegó dañado.',
            'Me encantó, superó mis expectativas.',
            'Entrega rápida y buen servicio.',
            'Diseño moderno y funcional.',
            'Buen precio por la calidad.'
        ];

        foreach ($products as $product) {
            // Generar entre 3 y 10 reseñas por producto
            $reviewCount = rand(3, 10);

            // Evita duplicados seleccionando usuarios únicos
            $selectedUsers = $users->random(min($reviewCount, $users->count()));

            foreach ($selectedUsers as $user) {
                Review::create([
                    'product_id' => $product->id,
                    'user_id'    => $user->id,
                    'rating'     => rand(1, 5),
                    'comment'    => $comentarios[array_rand($comentarios)],
                ]);
            }
        }

        $this->command->info('Muchas reseñas generadas correctamente.');
    }
}
