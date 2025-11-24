<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Devuelve estadísticas generales del sistema.
     */
    public function stats()
    {
        $now = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();

        // Ejemplo de cálculo para crecimiento de usuarios
        $currentUsers = User::whereMonth('created_at', $now->month)->count();
        $previousUsers = User::whereMonth('created_at', $lastMonth->month)->count();
        $userGrowth = $previousUsers > 0 ? round((($currentUsers - $previousUsers) / $previousUsers) * 100, 2) : null;

        // Similar para orders y sales...

        return response()->json([
            'total_users'       => User::count(),
            'total_products'    => Product::count(),
            'total_orders'      => Order::count(),
            'total_sales'       => Order::where('status', 'completed')->sum('total'),

            // Nuevos campos de tendencia
            'user_growth'       => $userGrowth,
            // 'order_growth' => ..., 'sales_growth' => ... (si quieres)
        ]);
    }


    /**
     * Devuelve datos mensuales de ventas para una gráfica.
     */
    public function salesGraph()
    {
        $sales = Order::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(total) as total_sales')
        )
            ->where('status', 'completed')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json($sales);
    }

    /**
     * Devuelve el top 5 de productos más vendidos.
     */
    public function topProducts()
    {
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json($topProducts);
    }

    /**
     * Devuelve evolución de usuarios nuevos por mes.
     */
    public function userGrowth()
    {
        $users = User::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json($users);
    }

    /**
     * Devuelve el número de productos por categoría.
     */
    public function productsPerCategory()
    {
        $data = Category::withCount('products')
            ->orderByDesc('products_count')
            ->get(['id', 'name', 'products_count']);

        return response()->json($data);
    }

    /**
     * Devuelve el promedio de reviews por producto.
     */
    public function averageReviews()
    {
        $average = Review::select(
            DB::raw('AVG(rating) as average_rating'),
            DB::raw('COUNT(*) as total_reviews')
        )->first();

        return response()->json($average);
    }
    public function cleanup()
    {
        try {
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => '🧹 Limpieza realizada correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error durante la limpieza',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

}
