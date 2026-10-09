<?php

namespace App\Filament\Resources\AbandonedCartResource\Widgets;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AbandonedCartOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $this->columns = 4;

        // 1. Abandoned Carts count and total potential value
        $abandonedCount = Cart::where('status', 'abandoned')->count();
        $abandonedValue = (float) DB::table('cart_items')
            ->join('carts', 'cart_items.cart_id', '=', 'carts.id')
            ->where('carts.status', 'abandoned')
            ->sum(DB::raw('cart_items.price * cart_items.quantity'));

        // 2. Recovered Carts count and recovered revenue
        $recoveredCount = Cart::where('status', 'recovered')->count();
        $recoveredRevenue = (float) Order::whereHas('recoveredCarts')
            ->whereIn('payment_status', ['paid', 'completed'])
            ->sum('total_amount');

        if ($recoveredRevenue == 0 && $recoveredCount > 0) {
            // Fallback: calculate from cart items if order total not directly attached
            $recoveredRevenue = (float) DB::table('cart_items')
                ->join('carts', 'cart_items.cart_id', '=', 'carts.id')
                ->where('carts.status', 'recovered')
                ->sum(DB::raw('cart_items.price * cart_items.quantity'));
        }

        // 3. Recovery Rate percentage
        $totalCarts = $abandonedCount + $recoveredCount;
        $recoveryRate = $totalCarts > 0 ? round(($recoveredCount / $totalCarts) * 100, 1) : 0;

        // 4. Pending Reminders (Abandoned carts with 0 reminders sent)
        $pendingReminders = Cart::where('status', 'abandoned')
            ->where('reminder_sent_count', 0)
            ->count();

        return [
            Stat::make('Abandoned Carts', number_format($abandonedCount))
                ->description('Potential Value: ' . number_format($abandonedValue, 2) . ' SAR')
                ->color('warning'),

            Stat::make('Recovered Revenue', number_format($recoveredRevenue, 2) . ' SAR')
                ->description($recoveredCount . ' carts converted to orders')
                ->color('success'),

            Stat::make('Recovery Rate', $recoveryRate . '%')
                ->description('Converted vs Abandoned')
                ->color($recoveryRate >= 15 ? 'success' : ($recoveryRate >= 5 ? 'warning' : 'danger')),

            Stat::make('Pending Follow-ups', number_format($pendingReminders))
                ->description('Carts not yet contacted')
                ->color($pendingReminders > 0 ? 'danger' : 'success'),
        ];
    }
}
