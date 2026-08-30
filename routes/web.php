<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HealthVideoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MedicalDocumentController;
use App\Http\Controllers\Patient\AppointmentController;
use App\Http\Controllers\Patient\Auth\LoginController;
use App\Http\Controllers\Patient\Auth\PasswordResetController;
use App\Http\Controllers\Patient\Auth\RegisterController;
use App\Http\Controllers\Patient\DashboardController;
use App\Http\Controllers\Patient\ProfileController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
|
| Every page a visitor can see without an account. The admin panel is not
| listed here — Filament registers /admin itself from
| app/Providers/Filament/AdminPanelProvider.php.
|
| Route names (home, about, booking…) are used throughout the Blade views, so
| renaming a URL below updates the whole site automatically.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');
Route::get('/services', ServiceController::class)->name('services');
Route::get('/contact', ContactController::class)->name('contact');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/gallery', GalleryController::class)->name('gallery');
Route::get('/faq', FaqController::class)->name('faq');

Route::get('/health-videos', [HealthVideoController::class, 'index'])->name('videos.index');
Route::get('/health-videos/{video}', [HealthVideoController::class, 'show'])->name('videos.show');

/*
|--------------------------------------------------------------------------
| Booking
|--------------------------------------------------------------------------
|
| A guest may browse dates and pick a slot; the Livewire component sends them
| to register or sign in before it will confirm anything.
|
*/

Route::get('/book', BookingController::class)->name('booking');

/*
|--------------------------------------------------------------------------
| Patient accounts
|--------------------------------------------------------------------------
|
| A completely separate guard from the staff panel. See config/auth.php.
|
*/

Route::prefix('patient')->name('patient.')->group(function (): void {

    // --- Signed out only -------------------------------------------------
    Route::middleware('guest:patient')->group(function (): void {
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('register.store');

        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])
            // Brute-force protection. The controller also rate-limits per
            // email address, which stops one attacker locking out everybody.
            ->middleware('throttle:10,1')
            ->name('login.store');

        Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'email'])
            ->middleware('throttle:6,1')
            ->name('password.email');

        Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('password.update');
    });

    // --- Signed in only --------------------------------------------------
    Route::middleware('auth:patient')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
            ->name('appointments.cancel');

        Route::get('/documents', [MedicalDocumentController::class, 'index'])->name('documents.index');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});

/*
 | Downloading a prescription or report.
 |
 | Outside the patient prefix because staff reach it too — the controller
 | authorises both audiences. The file itself lives on the private `medical`
 | disk and has no URL of its own.
 */
Route::get('/documents/{document}/download', [MedicalDocumentController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('documents.download');

/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
*/

Route::post('/appointments/{appointment}/pay', [PaymentController::class, 'start'])
    ->middleware(['auth:patient', 'throttle:10,1'])
    ->name('payments.start');

/*
 | The gateway sends the patient back here, and may also post an IPN from its
 | own servers.
 |
 | CSRF is exempted for these two in bootstrap/app.php. That is safe ONLY
 | because the callback trusts nothing in the request body — it re-validates
 | every payment directly with the gateway's API before believing a word of it.
 | See App\Services\Payments\Gateways\SslCommerzGateway::handleCallback().
 */
Route::match(['get', 'post'], '/payments/callback/{gateway}/{outcome}', [PaymentCallbackController::class, 'handle'])
    ->whereIn('outcome', ['success', 'fail', 'cancel'])
    ->name('payments.callback');

Route::post('/payments/ipn/{gateway}', [PaymentCallbackController::class, 'ipn'])
    ->name('payments.ipn');

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
