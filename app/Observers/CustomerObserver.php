<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    /**
     * Handle the Customer "updating" event.
     */
    public function updating(Customer $customer): void
    {
        // When account is deactivated
        if ($customer->isDirty('is_active') && !$customer->is_active) {
            // Increment token_version to immediately invalidate all issued JWT tokens
            $customer->token_version = ((int) ($customer->token_version ?? 1)) + 1;

            try {
                /** @var NotificationService $notificationService */
                $notificationService = app(NotificationService::class);
                $notificationService->sendAccountDeactivatedNotification($customer);
            } catch (\Throwable $e) {
                Log::error('[CustomerObserver] Failed to send deactivation notification: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle the Customer "deleting" event.
     */
    public function deleting(Customer $customer): void
    {
        try {
            /** @var NotificationService $notificationService */
            $notificationService = app(NotificationService::class);
            $notificationService->sendAccountDeletedNotification($customer);
        } catch (\Throwable $e) {
            Log::error('[CustomerObserver] Failed to send deletion notification: ' . $e->getMessage());
        }
    }
}
