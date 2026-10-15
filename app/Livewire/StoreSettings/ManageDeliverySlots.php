<?php

namespace App\Livewire\StoreSettings;

use App\Filament\Resources\DeliverySlotResource;
use App\Models\DeliverySlot;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ManageDeliverySlots extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(DeliverySlot::query())
            ->headerActions([
                CreateAction::make()
                    ->label('Add Delivery Slot')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Create New Delivery Slot')
                    ->modalWidth('3xl')
                    ->form(DeliverySlotResource::getFormSchema())
                    ->successNotificationTitle('Delivery slot created successfully'),
            ])
            ->columns([
                TextColumn::make('day_type')
                    ->label('Day Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'friday' => 'warning',
                        'regular' => 'info',
                        'all' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'friday' => '🕌 Friday Special',
                        'regular' => '📅 Regular Days (Sat-Thu)',
                        'all' => '🌟 Everyday',
                        default => $state,
                    }),

                TextColumn::make('title_en')
                    ->label('Slot Timing')
                    ->searchable()
                    ->description(fn (DeliverySlot $record) => $record->title_ar),

                TextColumn::make('start_time')
                    ->label('Time Window')
                    ->formatStateUsing(fn (DeliverySlot $record) => date('h:i A', strtotime($record->start_time)) . ' - ' . date('h:i A', strtotime($record->end_time))),

                TextColumn::make('cutoff_hours_before')
                    ->label('Cutoff')
                    ->formatStateUsing(fn ($state) => $state . ' hr before'),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('extra_charge')
                    ->label('Extra Charge')
                    ->money('SAR'),
            ])
            ->defaultSort('day_type', 'desc')
            ->filters([
                SelectFilter::make('day_type')
                    ->options([
                        'regular' => 'Regular Days',
                        'friday' => 'Friday',
                        'all' => 'All Days',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status'),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Edit Delivery Slot')
                    ->modalWidth('3xl')
                    ->form(DeliverySlotResource::getFormSchema())
                    ->successNotificationTitle('Delivery slot updated successfully'),
                DeleteAction::make()
                    ->successNotificationTitle('Delivery slot deleted'),
            ]);
    }

    public function render(): View
    {
        return view('livewire.store-settings.manage-delivery-slots');
    }
}
