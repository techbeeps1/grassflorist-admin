<?php

namespace App\Filament\Resources;

use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\SalesForecastResource\Pages;

class SalesForecastResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationLabel = 'Sales Forecast';

    protected static ?string $modelLabel = 'Sales Forecast';

    protected static ?string $slug = 'sales-forecast';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('sales_forecast') ?? false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(function ($state) {
                        $formatted = Carbon::createFromFormat('Y-m', $state)->format('F Y');
                        if ($state === Carbon::now()->addMonth()->format('Y-m')) {
                            $formatted .= ' (Predicted)';
                        }
                        return $formatted;
                    }),

                TextColumn::make('total_sales')
                    ->label('Total Sales (' . currency_symbol() . ')'),
                TextColumn::make('order_count')
                    ->label('Number of Orders'),
            ])
            // ->filters([
            //     SelectFilter::make('date_range')
            //         ->label('Date Range')
            //         ->options([
            //             '3' => 'Last 3 Months',
            //             '6' => 'Last 6 Months',
            //             '12' => 'Last 12 Months',
            //         ])
            //         ->default('3')
            //         ->query(function (Builder $query, $state) {
            //             $query->where('created_at', '>=', now()->subMonths((int)$state)->startOfMonth());
            //         }),
            // ])
            ->defaultSort('month', 'desc')
            ->emptyStateHeading('No sales data found')
            ->emptyStateDescription('Try adjusting your filters or check back later')
            ->actions([])
            ->bulkActions([])
            ->recordUrl(null);
    }

//     public static function getEloquentQuery(): Builder
// {
//     $nextMonth = Carbon::now()->addMonth()->format('Y-m');
//     $currentMonth = Carbon::now()->format('Y-m');
//     $previousMonth = Carbon::now()->subMonth()->format('Y-m');
    
//     return parent::getEloquentQuery()
//         ->selectRaw('
//             DATE_FORMAT(created_at, "%Y-%m") as month,
//             MIN(id) as id,
//             SUM(total_amount) as total_sales,
//             COUNT(*) as order_count
//         ')
//         ->groupBy('month')
//         ->union(
//             Order::query()
//                 ->selectRaw(
//                     '? as month, 
//                     0 as id, 
//                     COALESCE((SELECT SUM(total_amount) * 1.025 FROM orders WHERE DATE_FORMAT(created_at, "%Y-%m") = ?), 10000) as total_sales,
//                     COALESCE(ROUND((SELECT COUNT(*) * 1.025 FROM orders WHERE DATE_FORMAT(created_at, "%Y-%m") = ?)), 25) as order_count',
//                     [$nextMonth, $currentMonth, $currentMonth]
//                 )
//                 ->whereRaw('1=1') // Just to make the query valid
//         )
//         ->orderBy('month', 'desc');
// }

    public static function getEloquentQuery(): Builder
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $nextMonth = Carbon::now()->addMonth()->format('Y-m');

        $currentData = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentMonth])
            ->selectRaw("SUM(total_amount) as current_sales, COUNT(*) as current_orders")
            ->first();

        $actualQuery = DB::table('orders')
            ->whereNull('deleted_at')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, MIN(id) as id, SUM(total_amount) as total_sales, COUNT(*) as order_count, NULL as deleted_at")
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"));

        $hasNextMonth = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$nextMonth])
            ->exists();

        if (!$hasNextMonth && $currentData && ($currentData->current_orders > 0 || $currentData->current_sales > 0)) {
            $forecastSales = round($currentData->current_sales * 1.10, 2);
            $forecastOrders = (int) round($currentData->current_orders * 1.10);

            $predictQuery = DB::query()->selectRaw(
                "? as month, 0 as id, ? as total_sales, ? as order_count, NULL as deleted_at",
                [$nextMonth, $forecastSales, $forecastOrders]
            );
            $subQuery = $actualQuery->union($predictQuery);
        } else {
            $subQuery = $actualQuery;
        }

        return parent::getEloquentQuery()
            ->fromSub($subQuery, 'orders');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesForecasts::route('/'),
        ];
    }
    public static function getWidgets(): array
    {
        return [
            SalesForecastResource\Widgets\SalesForecastChart::class,
        ];
    }
}