<?php

namespace App\Filament\Resources\SalesForecastResource\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class SalesForecastChart extends ChartWidget
{
    protected static ?string $heading = 'Sales Forecast';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = null;

      protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get actual data
        $actualData = Order::query()
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                SUM(total_amount) as total_sales,
                COUNT(*) as order_count
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Get current month and next month
        $currentMonth = Carbon::now()->format('Y-m');
        $nextMonth = Carbon::now()->addMonth()->format('Y-m');
        
        // Prepare labels and datasets
        $labels = [];
        $salesData = [];
        $ordersData = [];
        
        // Process actual data
        foreach ($actualData as $record) {
            $labels[] = Carbon::createFromFormat('Y-m', $record->month)->format('M Y');
            $salesData[] = $record->total_sales;
            $ordersData[] = $record->order_count;
        }
        
        // Add forecast if we don't already have next month's data
        if (!in_array($nextMonth, array_column($actualData->toArray(), 'month'))) {
            $lastMonthSales = end($salesData);
            $lastMonthOrders = end($ordersData);
            
            // Simple forecast: 10% increase
            $forecastSales = $lastMonthSales * 1.10;
            $forecastOrders = $lastMonthOrders * 1.10;
            
            $labels[] = Carbon::createFromFormat('Y-m', $nextMonth)->format('M Y') . ' (Forecast)';
            $salesData[] = $forecastSales;
            $ordersData[] = $forecastOrders;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Sales (' . currency_symbol() . ')',
                    'data' => $salesData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                    'borderWidth' => 3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Number of Orders',
                    'data' => $ordersData,
                    'backgroundColor' => 'rgba(255, 165, 0, 0.5)', // semi-transparent orange
                    'borderColor' => 'rgba(255, 165, 0, 1)',       // solid orange
                    'borderWidth' => 3,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'animation' => [
                'duration' => 1500,
                'easing' => 'easeOutQuart',
            ],
            'responsive' => true,
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'scales' => [
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Total Sales (' . currency_symbol() . ')',
                    ],
                    'position' => 'left',
                ],
                'y1' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Orders',
                    ],
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

}   