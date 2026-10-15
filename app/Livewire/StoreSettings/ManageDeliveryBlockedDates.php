<?php

namespace App\Livewire\StoreSettings;

use App\Filament\Resources\DeliveryBlockedDateResource;
use App\Models\DeliveryBlockedDate;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ManageDeliveryBlockedDates extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(DeliveryBlockedDate::query())
            ->headerActions([
                CreateAction::make()
                    ->label('Add Blocked Date / Holiday')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Block Delivery Date / Set Holiday')
                    ->modalWidth('3xl')
                    ->form(DeliveryBlockedDateResource::getFormSchema())
                    ->successNotificationTitle('Blocked date/holiday added successfully'),
            ])
            ->columns([
                TextColumn::make('title_en')
                    ->label('Holiday / Reason')
                    ->searchable()
                    ->description(fn (DeliveryBlockedDate $record) => $record->title_ar),

                TextColumn::make('start_date')
                    ->label('Blocked Date(s)')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(function (DeliveryBlockedDate $record) {
                        if ($record->end_date && $record->end_date->ne($record->start_date)) {
                            return $record->start_date->format('M d, Y') . ' ➔ ' . $record->end_date->format('M d, Y');
                        }
                        return $record->start_date->format('M d, Y');
                    }),

                TextColumn::make('reason_en')
                    ->label('Customer Notice')
                    ->limit(40)
                    ->description(fn (DeliveryBlockedDate $record) => \Illuminate\Support\Str::limit($record->reason_ar, 40)),

                TextColumn::make('delivery_slot_id')
                    ->label('Scope')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'danger')
                    ->formatStateUsing(fn ($state, DeliveryBlockedDate $record) => $record->deliverySlot ? $record->deliverySlot->title_en : '⛔ All Day Closed'),

                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('start_date', 'asc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status'),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Edit Blocked Date / Holiday')
                    ->modalWidth('3xl')
                    ->form(DeliveryBlockedDateResource::getFormSchema())
                    ->successNotificationTitle('Blocked date/holiday updated successfully'),
                DeleteAction::make()
                    ->successNotificationTitle('Blocked date/holiday deleted'),
            ]);
    }

    public function render(): View
    {
        return view('livewire.store-settings.manage-delivery-blocked-dates');
    }
}
