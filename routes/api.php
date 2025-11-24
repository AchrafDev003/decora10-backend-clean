<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TestimonioController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\HeroItemController;
use App\Http\Controllers\FakeCheckoutController;
use App\Http\Controllers\CouponController;
use App\Http\Middleware\CheckUserRole;
use App\Http\Controllers\Payment\StripeController;


/*
|--------------------------------------------------------------------------
| Test & Utils
|--------------------------------------------------------------------------
*/

/** Test API basic */
Route::get('/test', fn() => 'API test OK');

/** Test email */
Route::get('/test-mail', function () {
    try {
        Mail::raw('Correo de prueba SMTP desde Laravel', function($message) {
            $message->to('decoraycolchon10@gmail.com')->subject('Prueba SMTP Laravel');
        });
        return response()->json(['success' => true, 'message' => 'Correo enviado correctamente.']);
    } catch (\Exception $e) {
        Log::error('Error enviando correo: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Error SMTP', 'error' => $e->getMessage()], 500);
    }
});

/** Test file permissions */
Route::get('/test-perm', function () {
    try {
        \Illuminate\Support\Facades\Storage::disk('local')->put('invoices/test.txt', 'Prueba archivo');
        return '✅ Archivo creado correctamente.';
    } catch (\Exception $e) {
        return '❌ Error al guardar archivo: ' . $e->getMessage();
    }
});

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // ==============================
    // Public resources
    // ==============================

    /** Logo público */
    Route::get('/logo', function () {
        return response()->file(public_path('images/logo-decora10.png'));
    });
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']); // verificar cupón
    Route::get('/coupons/active', [CouponController::class, 'active']);  // solo activos

    /** Auth Public */
    Route::post('/register', [AuthController::class, 'register']); // Public
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // Public
    Route::post('/login/google', [AuthController::class, 'loginGoogle']); // ✅ Google OAuth login
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail']); // Email verification
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword']); // Forgot password
    Route::post('/password/reset', [AuthController::class, 'resetPassword']); // Reset password
    Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);
    Route::middleware('auth:sanctum')->patch('/users/{id}/photo', [UserController::class, 'updateUserPhoto']);
    // Productos generales (todas las categorías excepto Colchonería)
    Route::get('/products/general', [ProductController::class, 'getPaginatedWithoutColchoneria']);

// Productos exclusivos de Colchonería
    Route::get('/products/colchoneria2', [ProductController::class, 'getPaginatedColchoneria']);

    Route::get('/products/{productId}/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);
// 🔹 Testimonios públicos (cualquiera puede acceder, incluso no logueado)
    Route::get('/testimonios', [TestimonioController::class, 'index']);

// 🔹 Testimonios admin/dueno (todos, publicados y no publicados)
    Route::get('/testimonios/admin', [TestimonioController::class, 'adminIndex'])
        ->middleware('auth:sanctum'); // o cualquier middleware de auth que uses

    /** Newsletter public */
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
    Route::post('/newsletter/validate', [NewsletterController::class, 'validateCode']);
    Route::post('/newsletter/use', [NewsletterController::class, 'markAsUsed']);

    /** Hero Items - public read */
    Route::get('/hero_items', [HeroItemController::class, 'index']);
    Route::get('/hero_items/{id}', [HeroItemController::class, 'show']);

    /** Products & Categories - public read */
    Route::get('/products/featured', [ProductController::class, 'getFeaturedByCategory']);
    Route::get('/products/colchoneria', [ProductController::class, 'getColchoneriaHighlights']);
    Route::get('products/search', [ProductController::class, 'search']);
    Route::apiResource('products', ProductController::class)->only(['index','show']);
    Route::apiResource('categories', CategoryController::class)->only(['index','show']);


    Route::middleware(['auth:sanctum', CheckUserRole::class . ':admin,dueno'])->group(function () {
        Route::get('/carts/admin', [CartController::class, 'adminIndex']);
        Route::get('/orders/admin', [OrderController::class, 'adminOrders']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'update']);
        Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
        Route::patch('/orders/{id}', [OrderController::class, 'adminUpdate']);
        Route::get('/orders/revenue', [OrderController::class, 'getTotalRevenue']);
        Route::get('/orders/revenue-stats', [OrderController::class, 'getRevenueStats']);
        Route::get('/orders/revenue-monthly', [OrderController::class, 'getMonthlyRevenue']);

    });

    Route::get('/products/{productId?}/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);
    Route::get('/products/related-by-first-word/{id}', [ProductController::class, 'relatedByFirstWord']);


    // ==============================
    // Authenticated routes (Client/Admin/Owner)
    // ==============================
    Route::middleware('auth:sanctum')->group(function () {

        // Crear un nuevo testimonio
        Route::post('/testimonios', [TestimonioController::class, 'store']);

        // Actualizar un testimonio (solo autor, admin o dueño)
        Route::put('/testimonios/{id}', [TestimonioController::class, 'update']);

        // Eliminar un testimonio (solo autor, admin o dueño)
        Route::delete('/testimonios/{id}', [TestimonioController::class, 'destroy']);
        Route::patch('/testimonios/{id}/toggle-publicado', [TestimonioController::class, 'togglePublicado']);

        /** Logout */
        Route::post('/logout', [AuthController::class, 'logout']);

        // --------------------------
        // Cart
        // --------------------------
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            // View cart
            Route::post('/items/{productId}', [CartController::class, 'add']);    // Add item
            Route::put('/items/{productId}', [CartController::class, 'update']);  // Update quantity
            Route::delete('/items/{productId}', [CartController::class, 'remove']); // Remove item
            Route::delete('/', [CartController::class, 'empty']);                 // Empty cart
            Route::get('/total', [CartController::class, 'total']);               // Cart total
            Route::post('/checkout', [CartController::class, 'checkout']);        // Checkout
        });

        // --------------------------
        // Favorites
        // --------------------------
        Route::get('/favorites', [FavoriteController::class, 'index']);      // List favorites
        Route::post('/favorites', [FavoriteController::class, 'store']);         // Add favorite
        Route::delete('/favorites/{productId}', [FavoriteController::class, 'destroy']); // Remove favorite

        // --------------------------
        // User profile
        // --------------------------
        Route::put('users/{id}', [UserController::class, 'update']); // Update profile

        // --------------------------
        // Addresses
        // --------------------------
        Route::apiResource('addresses', AddressController::class)->only(['index','store','update','destroy']);

        // --------------------------
// Orders
// --------------------------


        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/track/{tracking_number}', [OrderController::class, 'trackOrder']);


        Route::get('/orders/{id}', [OrderController::class, 'show']);       // Ver un pedido
        Route::get('/orders', [OrderController::class, 'getOrders']);       // Listar todos los pedidos del usuario
        Route::delete('/orders/{id}', [OrderController::class, 'destroy']);


// Nueva ruta para seguir el pedido (timeline) -> estilo Amazon



        // --------------------------
        // Reviews
        // --------------------------
        Route::post('/products/{productId}/reviews', [ReviewController::class, 'store']);


        // --------------------------
        // Notifications
        // --------------------------
        Route::get('/notifications/count', [NotificationController::class, 'countNewNotifications']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/checkout/fake', [FakeCheckoutController::class, 'checkout']);
        });

        Route::middleware('auth:sanctum')->post('/payments/stripe-intent', [StripeController::class, 'createIntent']);



        // ==============================
        // Admin & Owner
        // ==============================
        Route::middleware(CheckUserRole::class . ':admin,dueno')->group(function () {

            /** Users management */
            Route::apiResource('users', UserController::class)->only(['index','destroy']);

            /** Products & Categories CRUD */
            Route::apiResource('products', ProductController::class)->only(['store','update','destroy']);
            Route::apiResource('categories', CategoryController::class)->only(['store','update','destroy']);

            /** Addresses admin */
            Route::get('addresses/user/{user}', [AddressController::class, 'getUserAddresses']);
            Route::put('addresses/admin-update/{address}', [AddressController::class, 'adminUpdate']);
            Route::delete('addresses/admin-delete/{address}', [AddressController::class, 'adminDestroy']);


            /** Gestión de cupones */
            Route::get('/coupons', [CouponController::class, 'index']);          // Listar todos
            Route::post('/coupons', [CouponController::class, 'store']);         // Crear cupón
            Route::get('/coupons/{id}', [CouponController::class, 'show']);      // Ver cupón
            Route::put('/coupons/{id}', [CouponController::class, 'update']);    // Actualizar
            Route::delete('/coupons/{id}', [CouponController::class, 'destroy']); // Borrar
            Route::patch('/coupons/{id}/toggle', [CouponController::class, 'toggleStatus']);


            /** Reviews management */
            Route::put('/reviews/{id}', [ReviewController::class, 'update']);
            Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

            /** Dashboard */
            Route::prefix('dashboard')->group(function () {
                Route::get('stats', [DashboardController::class, 'stats']);
                Route::get('sales-graph', [DashboardController::class, 'salesGraph']);
                Route::get('top-products', [DashboardController::class, 'topProducts']);
                Route::get('user-growth', [DashboardController::class, 'userGrowth']);
                Route::get('products-per-category', [DashboardController::class, 'productsPerCategory']);
                Route::get('average-reviews', [DashboardController::class, 'averageReviews']);
                Route::get('latest-reviews', [DashboardController::class, 'latestReviews']);
                Route::get('reviews-per-product', [DashboardController::class, 'reviewsPerProduct']);
                Route::get('average-rating', [DashboardController::class, 'averageRating']);
            });

            /** Maintenance tasks */
            Route::prefix('maintenance')->group(function () {
                Route::post('/run-all', [MaintenanceController::class, 'runAll']);
                Route::post('/release', [MaintenanceController::class, 'release']);
                Route::post('/notify', [MaintenanceController::class, 'notify']);
                Route::post('/notify-user/{user}', [MaintenanceController::class, 'notifyUser']);
                Route::post('/promos', [MaintenanceController::class, 'promos']);
                Route::post('/cleanup', [MaintenanceController::class, 'cleanup']);
            });






            /** Hero Items CRUD */
            Route::post('/hero_items', [HeroItemController::class, 'store']);
            Route::put('/hero_items/{id}', [HeroItemController::class, 'update']);
            Route::put('/hero_items/{id}/toggle', [HeroItemController::class, 'toggle']);
            Route::delete('/hero_items/{id}', [HeroItemController::class, 'destroy']);
        });
    });
});
