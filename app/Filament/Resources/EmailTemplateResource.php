<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Email Templates';
    protected static ?string $modelLabel = 'Email Template';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('email_templates') ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Template Name')
                                    ->required(),

                                Forms\Components\TextInput::make('event_key')
                                    ->label('Event Identifier Key')
                                    ->disabled()
                                    ->required(),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('recipient_type')
                                    ->label('Recipient Target')
                                    ->options([
                                        'admin' => 'Admin Team Only',
                                        'customer' => 'Customer Only',
                                        'both' => 'Both (Customer & Admin Team)',
                                    ])
                                    ->default('both')
                                    ->required(),

                                Forms\Components\TextInput::make('notification_emails')
                                    ->label('Admin Notification Recipient Email(s)')
                                    ->placeholder('e.g. events@grassflorist.com, asif@techbeeps.com')
                                    ->helperText('Kis kis email per bejna hai (comma-separated email addresses).')
                                    ->columnSpan(1),
                            ]),

                        Forms\Components\Placeholder::make('allowed_shortcodes_display')
                            ->label('Available Dynamic Shortcodes (Click & Copy)')
                            ->content(fn (?EmailTemplate $record) => $record?->allowed_shortcodes ?? '{customer_name}, {order_id}, {total_amount}, {payment_method}, {delivery_date}, {delivery_time}')
                            ->helperText('These placeholders will be automatically replaced with live booking/order values when emails are sent.'),
                    ]),

                Forms\Components\Tabs::make('Languages')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('English Template')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('subject_en')
                                    ->label('Email Subject (English)')
                                    ->required(),

                                Forms\Components\RichEditor::make('body_en')
                                    ->label('Email Body / Message (English)')
                                    ->required()
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'link',
                                        'bulletList',
                                        'orderedList',
                                        'h2',
                                        'h3',
                                        'undo',
                                        'redo',
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Arabic Template (العربية)')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('subject_ar')
                                    ->label('Email Subject (Arabic)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->required(),

                                Forms\Components\RichEditor::make('body_ar')
                                    ->label('Email Body / Message (Arabic)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->required()
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'link',
                                        'bulletList',
                                        'orderedList',
                                        'h2',
                                        'h3',
                                        'undo',
                                        'redo',
                                    ]),
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
                    ->description(fn (EmailTemplate $record) => $record->subject_en),

                Tables\Columns\TextColumn::make('event_key')
                    ->label('Event Key')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('recipient_type')
                    ->label('Recipient')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('notification_emails')
                    ->label('Notification Recipients')
                    ->placeholder('System Default')
                    ->limit(30)
                    ->tooltip(fn (EmailTemplate $record) => $record->notification_emails),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, h:i A'),
            ])
            ->actions([
                Tables\Actions\Action::make('send_test')
                    ->label('Send Test Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('test_email')
                            ->label('Send Test Preview To Email')
                            ->email()
                            ->default(fn () => auth()->user()?->email ?? 'admin@grassflorist.com')
                            ->required(),
                        Forms\Components\Select::make('test_language')
                            ->label('Language Preview')
                            ->options([
                                'en' => 'English Template',
                                'ar' => 'Arabic Template (العربية)',
                            ])
                            ->default('en')
                            ->required(),
                    ])
                    ->action(function (EmailTemplate $record, array $data) {
                        try {
                            $sampleData = [
                                // Event Booking placeholders
                                'client_name' => 'Nouf Al-Husseini',
                                'first_name' => 'Nouf',
                                'last_name' => 'Al-Husseini',
                                'phone' => '+966 55 123 4567',
                                'email' => 'nouf@example.com',
                                'event_type' => 'Hall Wedding',
                                'event_date' => date('Y-m-d', strtotime('+30 days')),
                                'location' => 'Jeddah - Hilton Ballroom',
                                'guests' => '150-300',
                                'message' => 'We want an ethereal white and sage botanical tablescape with overhead hanging wisteria.',
                                'submission_date' => date('d M Y, h:i A'),
                                // Order placeholders
                                'customer_name' => 'Sara Al-Otaibi',
                                'order_id' => '92766',
                                'total_amount' => '250.00 SAR',
                                'payment_method' => 'Apple Pay',
                                'delivery_date' => date('Y-m-d'),
                                'delivery_time' => '11:00 AM to 03:00 PM',
                                'recipient_name' => 'Nouf',
                                'recipient_phone' => '+966 50 123 4567',
                                'gift_message' => 'Happy Birthday Dearest Nouf! 🌹🎂',
                                'tracking_link' => 'https://grassflorist.com/track/92766',
                                'retry_payment_url' => 'https://grassflorist.com/checkout/pay/92766',
                            ];

                            $rendered = $record->render($sampleData, $data['test_language']);

                            Mail::html($rendered['body'], function ($message) use ($data, $rendered) {
                                $message->to($data['test_email'])
                                    ->subject('[TEST PREVIEW] ' . $rendered['subject']);
                            });

                            Notification::make()
                                ->title('Test Email Sent Successfully!')
                                ->body('Sample preview sent to ' . $data['test_email'])
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Email Dispatch Failed')
                                ->body($e->getMessage())
                                ->danger()
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
            'index' => Pages\ListEmailTemplates::route('/'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
