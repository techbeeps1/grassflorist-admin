<?php

namespace App\Livewire\StoreSettings;

use App\Filament\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ManageShippingMethods extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(ShippingMethod::query())
            ->headerActions([
                CreateAction::make()
                    ->label('Add Shipping Method')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Create New Shipping Method')
                    ->modalWidth('4xl')
                    ->form(ShippingMethodResource::getFormSchema())
                    ->successNotificationTitle('Shipping method created successfully'),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Method Name')
                    ->weight('bold')
                    ->formatStateUsing(fn ($record) => format_translatable($record->name, app()->getLocale()) ?: (format_translatable($record->name, 'en') ?: 'Shipping Method'))
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('city')
                    ->label('City / Area')
                    ->badge()
                    ->color('info')
                    ->default('Jeddah'),

                TextColumn::make('price')
                    ->label('Delivery Fee')
                    ->formatStateUsing(fn ($state) => 'SAR ' . number_format((float)$state, 2))
                    ->weight('semibold')
                    ->sortable(),

                IconColumn::make('is_free_shipping_eligible')
                    ->label('Free Threshold')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->actions([
                EditAction::make()
                    ->modalHeading('Edit Shipping Method')
                    ->modalWidth('4xl')
                    ->form(ShippingMethodResource::getFormSchema())
                    ->successNotificationTitle('Shipping method updated successfully'),
                DeleteAction::make()
                    ->successNotificationTitle('Shipping method deleted'),
            ]);
    }

    public function render(): View
    {
        return view('livewire.store-settings.manage-shipping-methods');
    }
}
