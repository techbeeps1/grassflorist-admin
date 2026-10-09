<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerInquiryResource\Pages;
use App\Models\PartnerInquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerInquiryResource extends Resource
{
    protected static ?string $model = PartnerInquiry::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Pages';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Brand Partner Inquiry';
    protected static ?string $pluralModelLabel = 'Partner With Us';

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'new')->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Company & Brand Information')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')->label('Company / Brand Name')->required(),
                        Forms\Components\TextInput::make('category')->required(),
                        Forms\Components\TextInput::make('country')->required(),
                        Forms\Components\TextInput::make('city')->required(),
                        Forms\Components\TextInput::make('website')->url(),
                        Forms\Components\TextInput::make('social_media')->label('Social Media Handle / URL'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Contact Person')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')->required(),
                        Forms\Components\TextInput::make('last_name'),
                        Forms\Components\TextInput::make('contact_role')->label('Role / Designation'),
                        Forms\Components\TextInput::make('email')->email()->required(),
                        Forms\Components\TextInput::make('country_code'),
                        Forms\Components\TextInput::make('phone')->tel()->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Uploaded Partner Documents')
                    ->schema([
                        Forms\Components\Placeholder::make('company_profile_preview')
                            ->label('Company / Brand Profile Document')
                            ->content(function (?PartnerInquiry $record) {
                                if (!$record?->company_profile_url) {
                                    return 'No file uploaded';
                                }
                                return new \Illuminate\Support\HtmlString(
                                    '<a href="' . e($record->company_profile_url) . '" target="_blank" class="text-primary-600 underline font-semibold flex items-center gap-1">📁 Download / View Profile</a>'
                                );
                            }),

                        Forms\Components\Placeholder::make('product_list_preview')
                            ->label('Product List / Catalogue Document')
                            ->content(function (?PartnerInquiry $record) {
                                if (!$record?->product_list_url) {
                                    return 'No file uploaded';
                                }
                                return new \Illuminate\Support\HtmlString(
                                    '<a href="' . e($record->product_list_url) . '" target="_blank" class="text-primary-600 underline font-semibold flex items-center gap-1">📦 Download / View Product List</a>'
                                );
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Review & Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'new' => 'New Application',
                                'under_review' => 'Under Review',
                                'contacted' => 'Contacted / In Discussion',
                                'approved' => 'Approved as Partner',
                                'declined' => 'Declined',
                            ])
                            ->default('new')
                            ->required(),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Internal Admin Notes')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Brand / Company')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->state(fn (PartnerInquiry $record) => "{$record->city}, {$record->country}"),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Contact')
                    ->state(fn (PartnerInquiry $record) => "{$record->full_name} ({$record->contact_role})")
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->state(fn (PartnerInquiry $record) => $record->full_phone)
                    ->url(fn (PartnerInquiry $record) => "tel:{$record->full_phone}"),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->url(fn (PartnerInquiry $record) => "mailto:{$record->email}"),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'new' => 'New',
                        'under_review' => 'Under Review',
                        'contacted' => 'Contacted',
                        'approved' => 'Approved',
                        'declined' => 'Declined',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Chocolates' => 'Chocolates',
                        'Home Accessories' => 'Home Accessories',
                        'Candles' => 'Candles',
                        'Cakes' => 'Cakes',
                        'Bakery' => 'Bakery',
                        'Sweets' => 'Sweets',
                        'Perfumes' => 'Perfumes',
                        'Beauty' => 'Beauty',
                        'Fashion' => 'Fashion',
                        'Other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'new' => 'New Application',
                        'under_review' => 'Under Review',
                        'contacted' => 'Contacted',
                        'approved' => 'Approved',
                        'declined' => 'Declined',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerInquiries::route('/'),
        ];
    }
}
