<?php

namespace App\Filament\Resources\SalesForecastResource\Pages;

use App\Filament\Resources\SalesForecastResource;
use App\Filament\Resources\SalesForecastResource\Widgets\SalesForecastChart;
use Filament\Resources\Pages\ListRecords;

class ListSalesForecasts extends ListRecords
{
    protected static string $resource = SalesForecastResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SalesForecastChart::class,
        ];
    }
}