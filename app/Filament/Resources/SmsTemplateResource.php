<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsTemplateResource\Pages;
use App\Models\SmsTemplate;
use App\Services\Notifications\MsegatSmsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmsTemplateResource extends Resource
{
    protected static ?string $model = SmsTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'SMS Templates';
    protected static ?string $modelLabel = 'SMS Template';
    protected static ?string $pluralModelLabel = 'SMS Templates';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('sms_templates') ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Identification')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Template Name')
                                    ->placeholder('e.g. Order Delivered SMS')
                                    ->required(),

                                Forms\Components\TextInput::make('event_key')
                                    ->label('Event Identifier Key')
                                    ->placeholder('e.g. order_delivered')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Unique system key used by notifications (e.g. order_confirmed, contact_us).'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->default(true),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('recipient_type')
                                    ->label('Target Recipient')
                                    ->options([
                                        'customer' => 'Customer Only (العميل فقط)',
                                        'admin' => 'Admin Team Only (الإدارة فقط)',
                                        'both' => 'Both (Customer & Admin)',
                                    ])
                                    ->default('customer')
                                    ->required(),

                                Forms\Components\TextInput::make('notification_phones')
                                    ->label('Admin Notification Phone(s)')
                                    ->placeholder('e.g. 0551234567, 966509876543')
                                    ->helperText('Comma-separated numbers to alert store managers.'),
                            ]),

                        Forms\Components\Placeholder::make('allowed_shortcodes_display')
                            ->label('Available Dynamic Placeholders (Shortcodes)')
                            ->content(fn (?SmsTemplate $record) => $record?->allowed_shortcodes ?: '{customer_name}, {order_id}, {total_amount}, {currency}, {payment_method}, {delivery_date}, {delivery_time}, {recipient_name}')
                            ->helperText('Placeholders will be automatically substituted with live customer and order values.'),
                    ]),

                Forms\Components\Tabs::make('Message Translations')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Arabic Message (رسالة نصية بالعربية)')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\Textarea::make('message_ar')
                                    ->label('SMS Text (Arabic)')
                                    ->extraInputAttributes(['dir' => 'rtl', 'class' => 'font-sans text-sm'])
                                    ->rows(4)
                                    ->required()
                                    ->helperText('Standard Arabic SMS is up to 70 characters per segment. Longer messages will be concatenated.'),
                            ]),

                        Forms\Components\Tabs\Tab::make('English Message')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\Textarea::make('message_en')
                                    ->label('SMS Text (English)')
                                    ->rows(4)
                                    ->required()
                                    ->helperText('Standard English SMS is up to 160 characters per segment.'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SmsTemplate $record) => substr($record->message_ar, 0, 50) . '...'),

                Tables\Columns\TextColumn::make('event_key')
                    ->label('Event Key')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('recipient_type')
                    ->label('Recipient')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'customer' => 'success',
                        'admin' => 'warning',
                        'both' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('notification_phones')
                    ->label('Admin Alert Phones')
                    ->placeholder('N/A')
                    ->limit(20),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('send_test')
                    ->label('Send Test SMS')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('test_phone')
                            ->label('Recipient Mobile Number')
                            ->placeholder('e.g. 0501234567 or 966501234567')
                            ->required()
                            ->helperText('Saudi mobile number to receive the test SMS.'),

                        Forms\Components\Select::make('test_language')
                            ->label('Language')
                            ->options([
                                'ar' => 'Arabic (العربية)',
                                'en' => 'English',
                            ])
                            ->default('ar')
                            ->required(),
                    ])
                    ->action(function (SmsTemplate $record, array $data, MsegatSmsService $smsService) {
                        $sampleData = [
                            'customer_name' => 'نوف الحسين',
                            'order_id' => '92788',
                            'order_number' => '92788',
                            'total_amount' => '250.00',
                            'currency' => 'SAR',
                            'payment_method' => 'مدى / Mada',
                            'delivery_date' => date('Y-m-d'),
                            'delivery_time' => '11:00 AM - 03:00 PM',
                            'recipient_name' => 'سارة العتيبي',
                            'recipient_phone' => '0551234567',
                            'event_type' => 'حفل زفاف',
                            'event_date' => date('Y-m-d', strtotime('+14 days')),
                        ];

                        $renderedMessage = $record->render($sampleData, $data['test_language']);

                        $result = $smsService->send(
                            $data['test_phone'],
                            $renderedMessage,
                            'test_dispatch_' . $record->event_key
                        );

                        if ($result['success']) {
                            Notification::make()
                                ->title('Test SMS Sent Successfully!')
                                ->body("Message delivered via Msegat to {$data['test_phone']}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('SMS Dispatch Failed')
                                ->body($result['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsTemplates::route('/'),
            'create' => Pages\CreateSmsTemplate::route('/create'),
            'edit' => Pages\EditSmsTemplate::route('/{record}/edit'),
        ];
    }
}
