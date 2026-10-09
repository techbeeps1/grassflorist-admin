<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Filament\Resources\AbandonedCartResource\Widgets\AbandonedCartOverview;
use App\Mail\AbandonedCartReminderMail;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column as ExcelColumn;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AbandonedCartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Abandoned Carts';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyPermission(['orders', 'abandoned_carts']) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'abandoned')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        // Only show carts that have items and are not blank/empty
        return parent::getEloquentQuery()
            ->has('items')
            ->whereIn('status', ['abandoned', 'recovered', 'expired'])
            ->with(['items.product', 'customer', 'recoveredOrder'])
            ->latest('updated_at');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cart Details')
                ->schema([
                    Forms\Components\TextInput::make('customer_name')
                        ->label('Customer Name')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('customer_phone')
                        ->label('Customer Phone')
                        ->tel()
                        ->maxLength(25),
                    Forms\Components\TextInput::make('customer_email')
                        ->label('Customer Email')
                        ->email()
                        ->maxLength(150),
                    Forms\Components\Select::make('status')
                        ->options([
                            'abandoned' => 'Abandoned',
                            'recovered' => 'Recovered',
                            'expired' => 'Expired',
                            'active' => 'Active',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Admin Notes')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_info')
                    ->label('Customer')
                    ->html()
                    ->state(function (Cart $record): string {
                        $name = e($record->getEffectiveCustomerName());
                        $type = $record->user_id ? 'Registered' : 'Guest';
                        $phone = $record->getEffectiveCustomerPhone();
                        $email = e($record->getEffectiveCustomerEmail() ?? '');

                        $contactParts = [];
                        if ($phone) {
                            $contactParts[] = "<a href='tel:+91{$phone}' class='text-emerald-500 hover:text-emerald-400 hover:underline font-medium inline-flex items-center gap-1' title='Click to Call +91 {$phone}' onclick='event.stopPropagation();'><svg class='w-3 h-3 inline' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'></path></svg>+91 {$phone}</a>";
                        }
                        if ($email) {
                            $contactParts[] = "<a href='mailto:{$email}' class='text-gray-400 hover:text-gray-200 hover:underline font-normal' title='Send Email to {$email}' onclick='event.stopPropagation();'>{$email}</a>";
                        }

                        $contactLine = count($contactParts) > 0 
                            ? implode(" <span class='text-gray-500'>•</span> ", $contactParts)
                            : "<span class='text-gray-500 text-xs font-normal'>No contact info</span>";

                        $noteBadge = '';
                        if (!empty($record->admin_notes)) {
                            $escapedNote = e($record->admin_notes);
                            $shortNote = e(Str::limit($record->admin_notes, 28));
                            $noteBadge = "<div class='mt-1 inline-flex items-center gap-1 text-[11px] text-amber-400 bg-amber-950/60 border border-amber-800/60 px-2 py-0.5 rounded' title='{$escapedNote}'><span class='font-semibold'>Note:</span> {$shortNote}</div>";
                        }

                        return "<div>
                            <div class='font-bold text-gray-900 dark:text-white'>{$name} <span class='text-xs font-normal text-gray-500 dark:text-gray-400'>({$type})</span></div>
                            <div class='text-xs mt-0.5'>{$contactLine}</div>
                            {$noteBadge}
                        </div>";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $clean = preg_replace('/\D/', '', $search);
                        return $query->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%")
                            ->orWhere('customer_email', 'like', "%{$search}%")
                            ->orWhere('admin_notes', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($q) use ($search, $clean) {
                                $q->where('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%")
                                  ->orWhere('phone', 'like', "%{$clean}%");
                            });
                    }),

                Tables\Columns\TextColumn::make('items_summary')
                    ->label('Items in Cart')
                    ->state(function (Cart $record): string {
                        $totalQty = $record->getItemsCount();
                        $distinctItems = $record->items->count();
                        if ($totalQty === $distinctItems) {
                            return $totalQty === 1 ? '1 Item' : "{$totalQty} Items";
                        }
                        return "{$totalQty} Items ({$distinctItems} Products)";
                    })
                    ->description(function (Cart $record): string {
                        $titles = $record->items->take(2)->map(function ($item) {
                            $name = $item->product?->name ?? 'Product';
                            $qty = (int) $item->quantity;
                            $shortName = strlen($name) > 30 ? substr($name, 0, 27) . '...' : $name;
                            return $qty > 1 ? "{$shortName} (x{$qty})" : $shortName;
                        })->toArray();

                        if ($record->items->count() > 2) {
                            $more = $record->items->count() - 2;
                            $titles[] = "+{$more} more";
                        }

                        return implode(' • ', $titles);
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Cart Total')
                    ->state(function (Cart $record): string {
                        return number_format($record->calculateTotal(), 2) . ' SAR';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withSum('items as total_price', 'price')
                            ->orderBy('total_price', $direction);
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('abandoned_at')
                    ->label('Abandoned Time')
                    ->state(function (Cart $record): string {
                        $time = $record->abandoned_at ?? $record->updated_at;
                        return $time ? $time->diffForHumans() : 'N/A';
                    })
                    ->description(function (Cart $record): ?string {
                        $time = $record->abandoned_at ?? $record->updated_at;
                        return $time ? $time->format('d M Y, h:i A') : null;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'abandoned' => 'warning',
                        'recovered' => 'success',
                        'expired' => 'gray',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('reminders')
                    ->label('Reminder')
                    ->badge()
                    ->state(function (Cart $record): string {
                        if ($record->reminder_sent_count > 0) {
                            return "Sent ({$record->reminder_sent_count})";
                        }
                        return 'Not Sent';
                    })
                    ->color(fn (Cart $record): string => $record->reminder_sent_count > 0 ? 'info' : 'gray')
                    ->description(function (Cart $record): ?string {
                        if ($record->last_reminder_channel && $record->last_reminder_at) {
                            $channel = ucfirst($record->last_reminder_channel);
                            $ago = $record->last_reminder_at->diffForHumans();
                            return "via {$channel} ({$ago})";
                        }
                        return null;
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Cart Status')
                    ->options([
                        'abandoned' => 'Abandoned',
                        'recovered' => 'Recovered',
                        'expired' => 'Expired',
                    ])
                    ->default('abandoned'),

                Tables\Filters\Filter::make('has_contact')
                    ->label('Contactable Only (Phone / Email)')
                    ->query(function (Builder $query): Builder {
                        return $query->where(function ($q) {
                            $q->whereNotNull('customer_phone')
                              ->orWhereNotNull('customer_email')
                              ->orWhereNotNull('user_id');
                        });
                    }),

                Tables\Filters\Filter::make('not_reminded')
                    ->label('Reminders Not Yet Sent')
                    ->query(fn (Builder $query): Builder => $query->where('reminder_sent_count', 0)),

                Tables\Filters\Filter::make('today')
                    ->label('Abandoned Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('updated_at', now()->today())),
            ])
            ->actions([
                // 1. WhatsApp Reminder (Direct Green Icon Button)
                Tables\Actions\Action::make('whatsapp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->iconButton()
                    ->tooltip('Send WhatsApp Reminder')
                    ->color('success')
                    ->modalHeading('Send WhatsApp Reminder')
                    ->modalSubmitActionLabel('Open in WhatsApp')
                    ->form([
                        Forms\Components\TextInput::make('phone')
                            ->label('Customer Mobile')
                            ->default(fn (Cart $record) => $record->getEffectiveCustomerPhone())
                            ->placeholder('Enter 10-digit mobile number')
                            ->prefix('+91')
                            ->required()
                            ->helperText(function (Cart $record): string {
                                if (empty($record->getEffectiveCustomerPhone())) {
                                    return 'Customer has not provided a mobile number yet. Please enter a valid 10-digit number to launch WhatsApp and save it.';
                                }
                                return 'Personalized message and recovery link are included automatically.';
                            }),

                        Forms\Components\Textarea::make('message')
                            ->label('WhatsApp Message')
                            ->default(fn (Cart $record) => $record->getWhatsAppMessageText())
                            ->rows(8)
                            ->required()
                            ->helperText('You can edit this message before launching WhatsApp.'),
                    ])
                    ->action(function (Cart $record, array $data, LivewireComponent $livewire): void {
                        $cleanPhone = preg_replace('/\D/', '', (string) $data['phone']);
                        if (str_starts_with($cleanPhone, '91') && strlen($cleanPhone) > 10) {
                            $cleanPhone = substr($cleanPhone, 2);
                        }

                        $record->increment('reminder_sent_count');
                        $record->update([
                            'customer_phone' => $record->customer_phone ?: $cleanPhone,
                            'last_reminder_at' => now(),
                            'last_reminder_channel' => 'whatsapp',
                        ]);

                        $encoded = rawurlencode($data['message']);
                        $waUrl = "https://wa.me/91{$cleanPhone}?text={$encoded}";

                        Notification::make()
                            ->title('WhatsApp Reminder Ready')
                            ->body("WhatsApp window opened for +91 {$cleanPhone}.")
                            ->success()
                            ->actions([
                                NotificationAction::make('openWhatsApp')
                                    ->label('Open WhatsApp')
                                    ->button()
                                    ->url($waUrl, shouldOpenInNewTab: true),
                            ])
                            ->send();

                        $livewire->js("window.open(" . json_encode($waUrl) . ", '_blank');");
                    }),

                // 2. Email Reminder (Direct Red Icon Button)
                Tables\Actions\Action::make('sendEmail')
                    ->icon('heroicon-o-envelope')
                    ->iconButton()
                    ->tooltip('Send Email Reminder')
                    ->color('danger')
                    ->modalHeading('Send Abandoned Cart Reminder Email')
                    ->modalSubmitActionLabel('Send Email')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Customer Email')
                            ->default(fn (Cart $record) => $record->getEffectiveCustomerEmail())
                            ->placeholder('Enter customer email address')
                            ->email()
                            ->required()
                            ->helperText(function (Cart $record): string {
                                if (empty($record->getEffectiveCustomerEmail())) {
                                    return 'Customer did not provide an email address. Enter an email address to send the reminder.';
                                }
                                return 'The reminder will be delivered to this email address.';
                            }),

                        Forms\Components\TextInput::make('subject')
                            ->label('Email Subject')
                            ->default(fn () => 'Items waiting in your shopping bag - ' . config('app.name', 'Bookwindow'))
                            ->required(),

                        Forms\Components\Textarea::make('message')
                            ->label('Email Message')
                            ->default(fn (Cart $record) => $record->getReminderMessageText())
                            ->rows(8)
                            ->required()
                            ->helperText('You can edit the message or change the language before sending. The direct cart recovery link is included.'),
                    ])
                    ->action(function (Cart $record, array $data): void {
                        try {
                            Mail::to($data['email'])->send(new AbandonedCartReminderMail(
                                $record,
                                $data['subject'] ?? null,
                                $data['message'] ?? null
                            ));

                            $record->increment('reminder_sent_count');
                            $record->update([
                                'customer_email' => $record->customer_email ?: $data['email'],
                                'last_reminder_at' => now(),
                                'last_reminder_channel' => 'email',
                            ]);

                            Notification::make()
                                ->title('Email Sent')
                                ->body("Reminder email sent to {$data['email']}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Email Sending Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 3. Dropdown Menu for Secondary Actions
                Tables\Actions\ActionGroup::make([
                    // View Details Modal (Night / Dark Mode and Light Mode compatible)
                    Tables\Actions\Action::make('viewDetails')
                        ->label('View Details')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading('Cart Items & Customer Details')
                        ->modalSubmitActionLabel('Close')
                        ->form([
                            Forms\Components\Placeholder::make('customer_info')
                                ->label('Customer Information')
                                ->content(function (Cart $record): HtmlString {
                                    $name = e($record->getEffectiveCustomerName());
                                    $phone = e($record->getEffectiveCustomerPhone() ?? 'None');
                                    $email = e($record->getEffectiveCustomerEmail() ?? 'None');
                                    $type = $record->user_id ? 'Registered Customer' : 'Guest';
                                    $token = e($record->ensureRecoveryToken());
                                    $recoveryUrl = e($record->getRecoveryUrl());
                                    $status = e(strtoupper($record->status));

                                    $notesHtml = '';
                                    if (!empty($record->admin_notes)) {
                                        $escapedNotes = nl2br(e($record->admin_notes));
                                        $notesHtml = "
                                            <div class='mt-3 pt-3 border-t border-amber-300 dark:border-amber-700/80 bg-amber-50 dark:bg-amber-950/40 p-3 rounded-lg border border-amber-200 dark:border-amber-900'>
                                                <div class='text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider mb-1'>Internal Admin Note:</div>
                                                <div class='text-sm text-amber-950 dark:text-amber-200 leading-relaxed font-medium'>{$escapedNotes}</div>
                                            </div>
                                        ";
                                    } else {
                                        $notesHtml = "
                                            <div class='mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500'>
                                                <span>No internal staff note added yet. (Use the 3-dot ⋮ menu to add or edit note)</span>
                                            </div>
                                        ";
                                    }

                                    return new HtmlString("
                                        <div class='rounded-xl border border-gray-200 bg-gray-50/90 p-4 text-sm leading-relaxed text-gray-800 dark:border-gray-700 dark:bg-gray-800/90 dark:text-gray-100'>
                                            <div class='grid grid-cols-1 sm:grid-cols-2 gap-2.5'>
                                                <div><span class='text-gray-500 dark:text-gray-400'>Customer:</span> <strong class='text-gray-900 dark:text-white'>{$name}</strong> <span class='text-xs text-gray-500 dark:text-gray-400'>({$type})</span></div>
                                                <div><span class='text-gray-500 dark:text-gray-400'>Mobile:</span> <strong class='text-gray-900 dark:text-white'>+91 {$phone}</strong></div>
                                                <div><span class='text-gray-500 dark:text-gray-400'>Email:</span> <strong class='text-gray-900 dark:text-white'>{$email}</strong></div>
                                                <div><span class='text-gray-500 dark:text-gray-400'>Status:</span> <span class='font-bold text-xs px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200'>{$status}</span></div>
                                            </div>
                                            <div class='mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-1.5'>
                                                <span class='text-gray-500 dark:text-gray-400'>Recovery Link:</span>
                                                <a href='{$recoveryUrl}' target='_blank' class='text-primary-600 dark:text-primary-400 hover:underline font-mono text-xs break-all'>{$recoveryUrl}</a>
                                            </div>
                                            {$notesHtml}
                                        </div>
                                    ");
                                }),

                            Forms\Components\Placeholder::make('items_breakdown')
                                ->label('Books in Bag')
                                ->content(function (Cart $record): HtmlString {
                                    $html = "<div class='overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700'>";
                                    $html .= "<table class='w-full text-left text-sm'>";
                                    $html .= "<thead class='bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700'>";
                                    $html .= "<tr><th class='p-3'>Book Title</th><th class='p-3 text-center'>Qty</th><th class='p-3 text-right'>Unit Price</th><th class='p-3 text-right'>Subtotal</th></tr>";
                                    $html .= "</thead>";
                                    $html .= "<tbody class='divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100'>";

                                    foreach ($record->items as $item) {
                                        $title = e($item->product?->name ?? 'Book Item');
                                        $qty = (int) $item->quantity;
                                        $price = number_format((float) ($item->price ?? ($item->product?->price ?? 0)), 2);
                                        $subtotal = number_format((float) (($item->price ?? ($item->product?->price ?? 0)) * $qty), 2);

                                        $html .= "<tr>";
                                        $html .= "<td class='p-3 font-medium text-gray-900 dark:text-white'>{$title}</td>";
                                        $html .= "<td class='p-3 text-center font-semibold text-gray-700 dark:text-gray-300'>{$qty}</td>";
                                        $html .= "<td class='p-3 text-right text-gray-600 dark:text-gray-300'>₹{$price}</td>";
                                        $html .= "<td class='p-3 text-right font-bold text-gray-900 dark:text-white'>₹{$subtotal}</td>";
                                        $html .= "</tr>";
                                    }

                                    $total = number_format($record->calculateTotal(), 2);
                                    $totalQty = $record->getItemsCount();
                                    $html .= "</tbody>";
                                    $html .= "<tfoot class='bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 font-bold'>";
                                    $html .= "<tr>";
                                    $html .= "<td class='p-3 text-gray-700 dark:text-gray-300'>Total Items:</td>";
                                    $html .= "<td class='p-3 text-center text-gray-900 dark:text-white'>{$totalQty}</td>";
                                    $html .= "<td class='p-3 text-right text-gray-700 dark:text-gray-300'>Total Cart Value:</td>";
                                    $html .= "<td class='p-3 text-right text-base text-red-600 dark:text-red-400'>₹{$total}</td>";
                                    $html .= "</tr>";
                                    $html .= "</tfoot>";
                                    $html .= "</table>";
                                    $html .= "</div>";

                                    return new HtmlString($html);
                                }),
                        ]),

                    // Copy Recovery Link
                    Tables\Actions\Action::make('copyLink')
                        ->label('Copy Recovery Link')
                        ->icon('heroicon-o-link')
                        ->color('gray')
                        ->modalHeading('Direct Recovery Link')
                        ->modalSubmitActionLabel('Close')
                        ->form([
                            Forms\Components\TextInput::make('recovery_url')
                                ->label('Recovery URL')
                                ->default(fn (Cart $record) => $record->getRecoveryUrl())
                                ->extraAttributes(['readonly' => true])
                                ->helperText('Customer can open this link in any browser to instantly restore their shopping bag with all items.'),
                        ])
                        ->action(function (Cart $record): void {
                            Notification::make()
                                ->title('Link Ready')
                                ->body($record->getRecoveryUrl())
                                ->info()
                                ->send();
                        }),

                    // Mark as Recovered
                    Tables\Actions\Action::make('markRecovered')
                        ->label('Mark as Recovered')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Cart $record): void {
                            $record->update([
                                'status' => 'recovered',
                                'recovered_at' => now(),
                            ]);
                            Notification::make()
                                ->title('Cart marked as Recovered')
                                ->success()
                                ->send();
                        }),

                    // Mark as Expired
                    Tables\Actions\Action::make('markExpired')
                        ->label('Mark as Expired')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Cart $record): void {
                            $record->update(['status' => 'expired']);
                            Notification::make()
                                ->title('Cart marked as Expired')
                                ->success()
                                ->send();
                        }),

                    // Internal Admin Note
                    Tables\Actions\Action::make('adminNote')
                        ->label(fn (Cart $record) => $record->admin_notes ? 'Edit Note' : 'Add Note')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->modalHeading(fn (Cart $record) => "Admin Note - Cart #{$record->id}")
                        ->modalSubmitActionLabel('Save Note')
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Internal Note')
                                ->placeholder('e.g., Customer called, promised to complete purchase tomorrow morning. Or UPI payment failed.')
                                ->default(fn (Cart $record) => $record->admin_notes)
                                ->rows(4)
                                ->helperText('This note is strictly internal and never visible to the customer.'),
                        ])
                        ->action(function (Cart $record, array $data): void {
                            $record->update([
                                'admin_notes' => $data['admin_notes'] ?? null,
                            ]);

                            Notification::make()
                                ->title('Note Saved')
                                ->body('Internal admin note saved successfully.')
                                ->success()
                                ->send();
                        }),
                ])
                ->tooltip('More Options'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export to Excel')
                    ->exports([
                        ExcelExport::make()
                            ->withFilename(fn () => 'abandoned-carts-' . date('Y-m-d'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                            ->withColumns([
                                ExcelColumn::make('id')->heading('Cart ID'),
                                ExcelColumn::make('customer_name')->heading('Customer Name')
                                    ->formatStateUsing(fn ($record) => $record->getEffectiveCustomerName()),
                                ExcelColumn::make('customer_phone')->heading('Mobile Number')
                                    ->formatStateUsing(fn ($record) => $record->getEffectiveCustomerPhone() ? '+91' . $record->getEffectiveCustomerPhone() : 'N/A'),
                                ExcelColumn::make('customer_email')->heading('Email Address')
                                    ->formatStateUsing(fn ($record) => $record->getEffectiveCustomerEmail() ?? 'N/A'),
                                ExcelColumn::make('items_summary')->heading('Books in Cart')
                                    ->formatStateUsing(fn ($record) => $record->items->map(fn ($i) => ($i->product?->name ?? 'Book') . ' (Qty: ' . $i->quantity . ')')->implode(', ')),
                                ExcelColumn::make('total_items')->heading('Total Qty')
                                    ->formatStateUsing(fn ($record) => $record->getItemsCount()),
                                ExcelColumn::make('total_value')->heading('Cart Total (INR)')
                                    ->formatStateUsing(fn ($record) => $record->calculateTotal()),
                                ExcelColumn::make('status')->heading('Status')
                                    ->formatStateUsing(fn ($record) => ucfirst($record->status)),
                                ExcelColumn::make('abandoned_at')->heading('Abandoned Date')
                                    ->formatStateUsing(fn ($record) => $record->abandoned_at ? $record->abandoned_at->format('Y-m-d H:i') : ($record->updated_at ? $record->updated_at->format('Y-m-d H:i') : '')),
                                ExcelColumn::make('recovery_url')->heading('Direct Recovery Link')
                                    ->formatStateUsing(fn ($record) => $record->getRecoveryUrl()),
                                ExcelColumn::make('reminder_sent_count')->heading('Reminders Sent'),
                                ExcelColumn::make('admin_notes')->heading('Admin Notes'),
                            ]),
                    ]),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('Export Selected')
                    ->exports([
                        ExcelExport::make()
                            ->withFilename(fn () => 'abandoned-carts-selected-' . date('Y-m-d'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                            ->withColumns([
                                ExcelColumn::make('id')->heading('Cart ID'),
                                ExcelColumn::make('customer_name')->heading('Customer Name')
                                    ->formatStateUsing(fn ($record) => $record->getEffectiveCustomerName()),
                                ExcelColumn::make('customer_phone')->heading('Mobile Number')
                                    ->formatStateUsing(fn ($record) => $record->getEffectiveCustomerPhone() ? '+91' . $record->getEffectiveCustomerPhone() : 'N/A'),
                                ExcelColumn::make('customer_email')->heading('Email Address')
                                    ->formatStateUsing(fn ($record) => $record->getEffectiveCustomerEmail() ?? 'N/A'),
                                ExcelColumn::make('items_summary')->heading('Books in Cart')
                                    ->formatStateUsing(fn ($record) => $record->items->map(fn ($i) => ($i->product?->name ?? 'Book') . ' (Qty: ' . $i->quantity . ')')->implode(', ')),
                                ExcelColumn::make('total_items')->heading('Total Qty')
                                    ->formatStateUsing(fn ($record) => $record->getItemsCount()),
                                ExcelColumn::make('total_value')->heading('Cart Total (INR)')
                                    ->formatStateUsing(fn ($record) => $record->calculateTotal()),
                                ExcelColumn::make('status')->heading('Status')
                                    ->formatStateUsing(fn ($record) => ucfirst($record->status)),
                                ExcelColumn::make('abandoned_at')->heading('Abandoned Date')
                                    ->formatStateUsing(fn ($record) => $record->abandoned_at ? $record->abandoned_at->format('Y-m-d H:i') : ($record->updated_at ? $record->updated_at->format('Y-m-d H:i') : '')),
                                ExcelColumn::make('recovery_url')->heading('Direct Recovery Link')
                                    ->formatStateUsing(fn ($record) => $record->getRecoveryUrl()),
                                ExcelColumn::make('reminder_sent_count')->heading('Reminders Sent'),
                                ExcelColumn::make('admin_notes')->heading('Admin Notes'),
                            ]),
                    ]),

                Tables\Actions\BulkAction::make('markExpiredBulk')
                    ->label('Mark Selected as Expired')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['status' => 'expired'])),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            AbandonedCartOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbandonedCarts::route('/'),
        ];
    }
}
