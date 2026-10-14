<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Imports\CustomersImport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;




class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationGroup = "Shop";

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('customers') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public $file;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                ->schema([
                Forms\Components\TextInput::make( name: 'first_name')
                ->maxValue(value: 50)
                ->required(),
                 Forms\Components\TextInput::make( name: 'last_name')
                ->maxValue(value: 50)
                ->required(),
                Forms\Components\TextInput::make( name: 'email')
                ->label( label: 'Email Address')
                ->required()
                ->email()
                ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make( name: 'phone')
                ->label( label: 'Phone Number')
                ->required()
                ->maxValue(value: 50),
                Forms\Components\DatePicker::make( name: 'date_of_birth')
                ->label( label: 'Date of Birth'),
                Forms\Components\TextInput::make( name: 'city')
                ->label( label: 'City'),
                Forms\Components\TextInput::make( name: 'district')
                ->label( label: 'District'),
                Forms\Components\TextInput::make( name: 'zip_code')
                ->label( label: 'Zip Code'),
                Forms\Components\TextInput::make( name: 'state')
                ->label( label: 'State'),
                Forms\Components\TextInput::make( name: 'address')
                ->label( label: 'Address'),
                Forms\Components\TextInput::make( name: 'address_2')
                ->label( label: 'Address 2'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active Account')
                    ->helperText('Deactivating immediately logs out the customer, invalidates all access/refresh tokens, and dispatches SMS/Email notification.')
                    ->default(true)
                    ->required(),
                
                ])->columns(2),
                
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(name: 'id')
                ->sortable(),
                Tables\Columns\TextColumn::make(name: 'first_name')
                ->sortable()
                ->searchable(),
                Tables\Columns\TextColumn::make(name: 'last_name')
                ->sortable()
                ->searchable(),
                Tables\Columns\TextColumn::make('source_id')
                    ->label('WP ID')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Registered')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make(),
                Action::make('sync_wp_customers')
                    ->label('Sync from WooCommerce')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Customers from WooCommerce')
                    ->modalDescription('Do you want to fetch and sync customers directly from grassflorist.com?')
                    ->action(function (\App\Services\StoreImportService $importService) {
                        try {
                            $initial = $importService->importCustomersChunk(1, 100);
                            $pagesToRun = min(5, $initial['total_pages']);
                            for ($p = 2; $p <= $pagesToRun; $p++) {
                                $importService->importCustomersChunk($p, 100);
                            }

                            Notification::make()
                                ->title('Customers Synced Successfully!')
                                ->body("Synced customers from grassflorist.com.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Sync Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All Accounts')
                    ->trueLabel('Active Only')
                    ->falseLabel('Deactivated Only'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (Customer $record): bool => (bool)$record->is_active)
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Customer Account')
                        ->modalDescription('Are you sure you want to deactivate this customer account? The customer will be immediately logged out, tokens invalidated, and they will receive an SMS & Email notification.')
                        ->action(function (Customer $record) {
                            $record->is_active = false;
                            $record->save();

                            Notification::make()
                                ->title('Customer Deactivated')
                                ->body("Account for {$record->email} deactivated. Tokens invalidated and notification dispatched.")
                                ->warning()
                                ->send();
                        }),
                    Tables\Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Customer $record): bool => !((bool)$record->is_active))
                        ->requiresConfirmation()
                        ->modalHeading('Activate Customer Account')
                        ->modalDescription('Activate this customer account so they can log in again?')
                        ->action(function (Customer $record) {
                            $record->is_active = true;
                            $record->save();

                            Notification::make()
                                ->title('Customer Activated')
                                ->body("Account for {$record->email} is now active.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
             'import' => Pages\ImportCustomer::route('/import'),
        ];
    }
}
