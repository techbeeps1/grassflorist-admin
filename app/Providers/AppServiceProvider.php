<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Order;
use App\Observers\OrderObserver;


if (file_exists(app_path('helpers.php'))) {
    require_once app_path('helpers.php');
}

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
        \App\Models\Customer::observe(\App\Observers\CustomerObserver::class);
        \App\Models\User::observe(\App\Observers\UserObserver::class);

        \Filament\Tables\Table::$defaultCurrency = config('app.currency', 'INR');
        \Filament\Tables\Table::$defaultNumberLocale = config('app.currency_locale', 'en_IN');
        \Illuminate\Support\Number::useCurrency(config('app.currency', 'INR'));

        // Gate Policies for Custom Menu Resource
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Menu::class, \App\Policies\MenuPolicy::class);

        // Universal Mail Event Listener: Ensure EVERY email dispatched is logged in notification_logs
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, function (\Illuminate\Mail\Events\MessageSent $event) {
            try {
                $sentMessage = method_exists($event->sent, 'getSymfonySentMessage')
                    ? $event->sent->getSymfonySentMessage()
                    : null;
                $original = $sentMessage && method_exists($sentMessage, 'getOriginalMessage')
                    ? $sentMessage->getOriginalMessage()
                    : ($event->message ?? null);

                if (!$original) {
                    return;
                }

                $headers = method_exists($original, 'getHeaders') ? $original->getHeaders() : null;

                // Skip if already logged by NotificationService
                if ($headers && $headers->has('X-Grass-Logged')) {
                    return;
                }

                $toAddresses = [];
                if (method_exists($original, 'getTo') && is_iterable($original->getTo())) {
                    foreach ($original->getTo() as $addr) {
                        $toAddresses[] = method_exists($addr, 'getAddress') ? $addr->getAddress() : (string)$addr;
                    }
                }

                $recipient = implode(', ', $toAddresses);
                $subject = method_exists($original, 'getSubject') ? $original->getSubject() : 'System Notification';
                $content = method_exists($original, 'getHtmlBody') ? $original->getHtmlBody() : (method_exists($original, 'getTextBody') ? $original->getTextBody() : '');
                $eventKey = ($headers && $headers->has('X-Grass-Event')) ? $headers->get('X-Grass-Event')->getBodyAsString() : 'system_email';

                \App\Models\NotificationLog::create([
                    'channel' => 'email',
                    'event_key' => $eventKey,
                    'recipient' => $recipient ?: 'Unknown',
                    'subject_or_sender' => $subject ?: 'System Email',
                    'content' => (string)$content,
                    'status' => 'sent',
                    'response_payload' => ['auto_logged_via_event' => true],
                    'ip_address' => request()?->ip(),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[MailLogEvent] Auto-log email warning: ' . $e->getMessage());
            }
        });
    }
}
