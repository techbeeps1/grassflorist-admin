<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactInquiryResource\Pages;
use App\Models\ContactInquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactInquiryResource extends Resource
{
    protected static ?string $model = ContactInquiry::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Inquiries & Leads';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Contact Inquiry';
    protected static ?string $pluralModelLabel = 'Contact Inquiries';

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'new')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Information (بيانات العميل)')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->disabled(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->disabled(),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),
                        Forms\Components\TextInput::make('locale')
                            ->label('Submission Language')
                            ->formatStateUsing(fn ($state) => $state === 'en' ? 'English (الإنجليزية)' : 'Arabic (العربية)')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Inquiry Content (محتوى الرسالة والطلب)')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject / Topic')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('message')
                            ->label('Message Content')
                            ->rows(5)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Status & Internal Follow-up (المتابعة والإجراءات)')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Inquiry Status')
                            ->options([
                                'new' => 'New / Unread (جديد)',
                                'in_progress' => 'In Progress / Contacted (قيد المتابعة)',
                                'resolved' => 'Resolved / Completed (تم الحل)',
                                'closed' => 'Closed (مغلق)',
                            ])
                            ->default('new')
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Internal Admin Notes (ملاحظات الإدارة الداخلية)')
                            ->placeholder('Add follow-up notes, customer response details, or team instructions...')
                            ->rows(3),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (ContactInquiry $record) => $record->email),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('—')
                    ->url(fn (ContactInquiry $record) => $record->phone ? "tel:{$record->phone}" : null),

                Tables\Columns\TextColumn::make('locale')
                    ->label('Lang')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'en' ? 'EN 🇬🇧' : 'AR 🇸🇦')
                    ->color(fn (?string $state) => $state === 'en' ? 'info' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('General Inquiry'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message Preview')
                    ->limit(45)
                    ->tooltip(fn (ContactInquiry $record) => $record->message),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'new' => 'New (جديد)',
                        'in_progress' => 'In Progress (متابعة)',
                        'resolved' => 'Resolved (تم الحل)',
                        'closed' => 'Closed (مغلق)',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'new' => 'New (جديد)',
                        'in_progress' => 'In Progress (قيد المتابعة)',
                        'resolved' => 'Resolved (تم الحل)',
                        'closed' => 'Closed (مغلق)',
                    ]),
                Tables\Filters\SelectFilter::make('locale')
                    ->label('Language')
                    ->options([
                        'en' => 'English 🇬🇧',
                        'ar' => 'العربية 🇸🇦',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reply_email')
                    ->label('Reply Email')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->url(fn (ContactInquiry $record) => "mailto:{$record->email}?subject=" . rawurlencode("Re: " . ($record->subject ?: 'Your inquiry with Grass Florist')))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactInquiries::route('/'),
        ];
    }
}
