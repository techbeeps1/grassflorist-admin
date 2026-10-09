<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicationController;
use App\Http\Controllers\OrderPdfController;

// Route::get('/', function () {
//     return view('welcome');
// })->name('home');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow: /\n", 200)
        ->header('Content-Type', 'text/plain');
});

Route::redirect('/', 'admin/login');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::get('/orders/{order}/pdf', [OrderPdfController::class, 'download'])
    ->name('orders.pdf');
Route::get('/orders/{order}/print', [OrderPdfController::class, 'print'])
    ->name('orders.print');

// 🌸 Florist Gift Card & ZATCA Tax Invoice Printable Routes
Route::get('/orders/{id}/gift-card', [\App\Http\Controllers\Admin\OrderPrintController::class, 'printGiftCard'])
    ->name('orders.gift_card');
Route::get('/orders/{id}/tax-invoice', [\App\Http\Controllers\Admin\OrderPrintController::class, 'printTaxInvoice'])
    ->name('orders.tax_invoice');

// 📜 Swagger API Documentation UI (OpenAPI 3.1)
Route::get('/docs/swagger', function () {
    return view('swagger');
})->name('docs.swagger');

// ⭐ Google Reviews CSV Export & Template Routes
Route::get('/admin/google-reviews/export-csv', function () {
    return \App\Services\GoogleReviewCsvService::exportCsv();
})->name('google_reviews.export_csv');

Route::get('/admin/google-reviews/sample-template', function () {
    return \App\Services\GoogleReviewCsvService::downloadSampleCsv();
})->name('google_reviews.sample_template');


 // Route::get('/api/categories', [CategoryController::class, 'index']);
 // Route::get('/api/products', [ProductController::class, 'index']);
 // Route::get('/api/products/{slug}', [ProductController::class, 'show']);
 // Route::get('/api/categories/{slug}/products', [ProductController::class, 'productsByCategorySlug']);
 // Route::get('/api/publications', [PublicationController::class, 'index']);

require __DIR__.'/auth.php';
