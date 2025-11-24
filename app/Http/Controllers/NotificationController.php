<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use App\Models\Review;
use App\Models\Cart;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    // Conteo de nuevas notificaciones desde la última revisión
    public function countNewNotifications(Request $request)
    {
        $lastCheck = $request->input('last_check');

        try {
            $lastCheckDate = $lastCheck ? Carbon::parse($lastCheck) : Carbon::now()->subMinutes(10);
        } catch (\Exception $e) {
            $lastCheckDate = Carbon::now()->subMinutes(10);
        }

        $counts = Cache::remember("notifications_count_" . md5($lastCheckDate), 5, function() use ($lastCheckDate) {
            return [
                'new_users' => User::where('created_at', '>', $lastCheckDate)->count(),
                'new_orders' => Order::where('created_at', '>', $lastCheckDate)->count(),
                'new_reviews' => Review::where('created_at', '>', $lastCheckDate)->count(),
                'new_carts' => Cart::where('created_at', '>', $lastCheckDate)->count(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'checked_at' => now(),
            'data' => $counts
        ]);
    }

    // Últimas 5 reseñas para mostrar en el dashboard
    public function latestReviews()
    {
        $reviews = Review::with('user', 'product')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json($reviews);
    }

    // Promedio de calificación general
    public function averageRating()
    {
        $avg = Review::avg('rating');
        return response()->json(['average_rating' => round($avg, 2)]);
    }

    // Número de reseñas por producto (para gráfica)
    public function reviewsPerProduct()
    {
        $data = Product::withCount('reviews')->get(['id', 'name']);
        return response()->json($data);
    }
}
