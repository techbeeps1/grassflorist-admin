<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use App\Models\SubscriptionActivity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Lifecycle Audit Trail & Events';

    protected static ?string $icon = 'heroicon-o-clipboard-document-list';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\BadgeColumn::make('activity_type')
                    ->label('Event Type')
                    ->colors([
                        'primary' => 'created',
                        'warning' => fn ($state) => in_array($state, ['paused', 'rescheduled_delivery', 'skipped_delivery']),
                        'success' => fn ($state) => in_array($state, ['resumed', 'payment_success', 'completed']),
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'payment_failed']),
                        'info' => 'delivery_order_created',
                    ]),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }
}
