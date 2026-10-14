<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // When user account is deactivated
        if ($user->isDirty('is_active') && !$user->is_active) {
            // Revoke all Sanctum API tokens immediately
            try {
                $user->tokens()->delete();
            } catch (\Throwable $t) {}

            try {
                /** @var NotificationService $notificationService */
                $notificationService = app(NotificationService::class);
                $notificationService->sendAccountDeactivatedNotification($user);
            } catch (\Throwable $e) {
                Log::error('[UserObserver] Failed to send deactivation notification: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle the User "deleting" event.
     */
    public function deleting(User $user): void
    {
        try {
            $user->tokens()->delete();
        } catch (\Throwable $t) {}

        try {
            /** @var NotificationService $notificationService */
            $notificationService = app(NotificationService::class);
            $notificationService->sendAccountDeletedNotification($user);
        } catch (\Throwable $e) {
            Log::error('[UserObserver] Failed to send deletion notification: ' . $e->getMessage());
        }
    }
}