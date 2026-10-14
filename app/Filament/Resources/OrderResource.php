<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Filament\Forms;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use App\Imports\OrdersImport;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\ViewField;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = "Shop";

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return 'Order #' . ($record->order_number ?: $record->id);
    }

    // Access control: Admin, Vendor, or Team with 'orders' permission
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->isAdmin() || $user->isVendor()) return true;
        return $user->hasPermission('orders');
    }

    // Disable the create action globally
    public static function canCreate(): bool
    {
        return false;
    }

    // Show pending order count in navigation
    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->isVendor()) {
            $vendor = auth()->user()->vendor;
            if ($vendor) {
                return (string) static::getModel()::whereHas('items', function ($q) use ($vendor) {
                    $q->where('vendor_id', $vendor->id);
                })->whereIn('status', ['pending', 'processing'])->count();
            }
            return '0';
        }

        return (string) static::getModel()::whereIn('status', ['pending', 'processing'])->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->isVendor()) {
            $vendor = auth()->user()->vendor;
            if ($vendor) {
                $query->whereHas('items', function ($q) use ($vendor) {
                    $q->where('vendor_id', $vendor->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Use Tabs for organization
                Tabs::make('Order Details')
                    ->tabs([
                        // 1. Customer & Shipping Tab
                        Tab::make('Customer & Shipping')
                            ->icon('heroicon-o-user')
                            ->schema([
                                // Customer Information Section
                                Section::make('Customer Information')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('first_name')
                                                    ->label('First Name')
                                                    ->maxLength(100),

                                                TextInput::make('last_name')
                                                    ->label('Last Name')
                                                    ->maxLength(100),

                                                TextInput::make('email')
                                                    ->label('Email')
                                                    ->email()
                                                    ->maxLength(150),

                                                TextInput::make('customer_phone')
                                                    ->label('Phone Number / Mobile')
                                                    ->tel()
                                                    ->placeholder('e.g. 9876543210')
                                                    ->maxLength(20),
                                            ]),
                                    ]),

                                // Shipping Address Section
                                Section::make('Shipping Address')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('address')
                                                    ->label('Address Line 1')
                                                    ->columnSpanFull(),

                                                TextInput::make('address_2')
                                                    ->label('Address Line 2')
                                                    ->columnSpanFull(),

                                                TextInput::make('city')
                                                    ->label('City'),

                                                TextInput::make('district')
                                                    ->label('District'),

                                                TextInput::make('state')
                                                    ->label('State/Province'),

                                                TextInput::make('zip_code')
                                                    ->label('Postal Code / PIN'),

                                                TextInput::make('country')
                                                    ->label('Country')
                                                    ->default('Saudi Arabia'),

                                                TextInput::make('location_link')
                                                    ->label('Google Maps / Location Link')
                                                    ->prefixIcon('heroicon-o-map-pin')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        // 2. Order Status Tab
                        Tab::make('Order Status')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                Section::make('Order Status & Tracking')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Placeholder::make('order_number')
                                                    ->label('Order ID')
                                                    ->content(function ($record) {
                                                        return $record->order_number ?? 'N/A';
                                                    }),
                                                
                                                Select::make('status')
                                                    ->label('Order Status')
                                                    ->options([
                                                        "pending" => "Pending",
                                                        "pending_payment" => "Pending payment",
                                                        "processing" => "Processing",
                                                        "printed" => "Printed",
                                                        "shipped" => "Shipped",
                                                        "delivered" => "Delivered",
                                                        "completed" => "Completed",
                                                        "cancelled" => "Cancelled",
                                                        "refunded" => "Refunded",
                                                        "failed" => "Failed",
                                                    ])
                                                    ->required()
                                                    ->live()
                                                    ->native(true),

                                                Select::make('order_language')
                                                    ->label('Order Language (لغة إشعارات الطلب)')
                                                    ->options([
                                                        'ar' => 'العربية (Arabic)',
                                                        'en' => 'English (الإنجليزية)',
                                                    ])
                                                    ->default('ar')
                                                    ->helperText('Language used for customer emails and SMS updates for this order.')
                                                    ->native(true),

                                                Placeholder::make('cancelled_by_display')
                                                    ->label('Cancelled By')
                                                    ->visible(fn ($record, Forms\Get $get) => (in_array($get('status'), ['cancelled', 'declined', 'payment_cancelled']) || str_contains((string)$get('status'), 'cancel')) && !empty($record?->cancelled_by))
                                                    ->content(function ($record) {
                                                        return match($record?->cancelled_by) {
                                                            'customer' => 'Customer (From User Account)',
                                                            'admin' => 'Admin / Store Staff',
                                                            'payment_failed' => 'Payment Cancelled / Failed at Gateway',
                                                            default => ucfirst($record?->cancelled_by ?? 'N/A'),
                                                        };
                                                    }),

                                                Select::make('cancellation_reason_preset')
                                                    ->label('Cancellation Reason Preset')
                                                    ->options([
                                                        'Customer requested cancellation' => 'Customer requested cancellation',
                                                        'Product out of stock' => 'Product out of stock',
                                                        'Customer unreachable on phone' => 'Customer unreachable on phone',
                                                        'Incorrect or incomplete delivery address' => 'Incorrect or incomplete delivery address',
                                                        'Payment issue / Unpaid COD' => 'Payment issue / Unpaid COD',
                                                        'Duplicate order placed by mistake' => 'Duplicate order placed by mistake',
                                                        'Other reason' => 'Other reason (Specify in remarks below)',
                                                    ])
                                                    ->visible(fn (Forms\Get $get) => in_array($get('status'), ['cancelled', 'declined']))
                                                    ->live()
                                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                        if ($state && $state !== 'Other reason') {
                                                            $set('cancellation_reason', $state);
                                                        }
                                                    })
                                                    ->dehydrated(false),

                                                Textarea::make('cancellation_reason')
                                                    ->label('Cancellation Reason / Remarks')
                                                    ->placeholder('Enter detailed reason or remark for order cancellation...')
                                                    ->visible(fn (Forms\Get $get) => in_array($get('status'), ['cancelled', 'declined']))
                                                    ->required(fn (Forms\Get $get) => in_array($get('status'), ['cancelled', 'declined']))
                                                    ->rows(2)
                                                    ->columnSpanFull(),

                                                Textarea::make('failed_order_reason')
                                                    ->label('Failed Order Reason')
                                                    ->placeholder('Payment gateway failure or card decline error...')
                                                    ->visible(fn ($record, Forms\Get $get) => in_array($get('status'), ['failed', 'declined']) || !empty($record?->failed_order_reason))
                                                    ->rows(2)
                                                    ->columnSpanFull(),

                                                TextInput::make('invoice_number')
                                                    ->label('Tax Invoice Number')
                                                    ->placeholder('e.g. INV/2026/92811/183')
                                                    ->maxLength(100),

                                                TextInput::make('courier_partner')
                                                    ->label('Courier Partner Name')
                                                    ->placeholder('Enter Courier Partner Name (e.g. SMSA, Aramex, DTDC)')
                                                    ->live()
                                                    ->visible(fn (Forms\Get $get) => in_array($get('status'), ['order_shipped', 'shipped', 'delivered', 'completed'])),

                                                TextInput::make('tracking_id')
                                                    ->label('Tracking ID')
                                                    ->placeholder('e.g. DTDC12345678, EK123456789IN')
                                                    ->live()
                                                    ->visible(fn (Forms\Get $get) => in_array($get('status'), ['order_shipped', 'shipped', 'delivered', 'completed']))
                                                    ->required(fn (Forms\Get $get) => in_array($get('status'), ['order_shipped', 'shipped'])),
                                            ]),
                                    ]),

                                // Admin Remarks & Internal Comments Section
                                Section::make('Admin Remarks & Internal Comments')
                                    ->description('Internal staff comments and order remarks (not visible to customer).')
                                    ->schema([
                                        Textarea::make('admin_remark')
                                            ->label('Order Remark / Comment')
                                            ->placeholder('Add internal staff notes or comments about this order...')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),

                                // Additional Information
                                Section::make('Additional Information')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Placeholder::make('shipping_method')
                                                    ->label('Shipping Method')
                                                    ->content(function ($record) {
                                                        return $record->shipping_method ?? 'N/A';
                                                    }),
                                                
                                                Placeholder::make('coupon_code')
                                                    ->label('Coupon Code')
                                                    ->content(function ($record) {
                                                        return $record->coupon_code ?? 'N/A';
                                                    }),
                                                
                                                Placeholder::make('notes')
                                                    ->label('Order Notes (Customer)')
                                                    ->content(function ($record) {
                                                        return $record->notes ?? 'No notes';
                                                    })
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        // 3. Payment & Financials Tab
                        Tab::make('Payment & Financials')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                // Payment Details Section
                                Section::make('Payment Details')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Placeholder::make('payment_method_display')
                                                    ->label('Payment Method & Channel')
                                                    ->content(function ($record) {
                                                        if (!$record) return 'N/A';
                                                        $details = $record->getPaymentDetails();
                                                        $output = "<span class='font-bold text-sm text-gray-900 dark:text-gray-100'>{$details['title']}</span>";
                                                        if ($details['gateway'] && strtolower($details['gateway']) !== strtolower($details['title'])) {
                                                            $output .= " <span class='text-xs text-gray-500 font-medium'>(via {$details['gateway']})</span>";
                                                        }
                                                        return new \Illuminate\Support\HtmlString($output);
                                                    }),

                                                Select::make('payment_status')
                                                    ->label('Payment Status')
                                                    ->options([
                                                        "pending" => "Pending",
                                                        "payment_pending" => "Payment Pending",
                                                        "paid" => "Paid",
                                                        "failed" => "Failed",
                                                        "refunded" => "Refunded",
                                                    ])
                                                    ->required()
                                                    ->native(true),

                                                Placeholder::make('card_channel_info')
                                                    ->label('Card / Channel Details')
                                                    ->content(function ($record) {
                                                        if (!$record) return '-';
                                                        $details = $record->getPaymentDetails();
                                                        if (!empty($details['card_last4'])) {
                                                            $cardInfo = "•••• •••• •••• {$details['card_last4']}";
                                                            if (!empty($details['card_type'])) {
                                                                $cardInfo .= " [{$details['card_type']}]";
                                                            }
                                                            if (!empty($details['card_bank'])) {
                                                                $cardInfo .= " | Bank: {$details['card_bank']}";
                                                            }
                                                            if (!empty($details['card_holder'])) {
                                                                $cardInfo .= " | Holder: {$details['card_holder']}";
                                                            }
                                                            return $cardInfo;
                                                        }
                                                        return match(strtolower($details['method_code'])) {
                                                            'apple_pay' => 'Apple Pay Digital Wallet',
                                                            'stc_pay' => 'STC Pay Mobile Wallet',
                                                            'tabby' => 'Tabby Pay in 4 / Installments',
                                                            'tamara' => 'Tamara Split in 3-4 Payments',
                                                            'paypal' => 'PayPal Verified Account',
                                                            'cod' => 'Cash On Delivery (Cash / POS on doorstep)',
                                                            default => 'Direct Gateway Transaction',
                                                        };
                                                    }),

                                                Placeholder::make('payment_info_summary')
                                                    ->label('Payment Timestamp & IP')
                                                    ->content(function ($record) {
                                                        if (!$record) return '-';
                                                        $datePaid = $record->date_paid ? $record->date_paid->format('d M Y, h:i A') : 'Not Recorded';
                                                        $ip = $record->customer_ip ? " | IP: {$record->customer_ip}" : '';
                                                        return "Paid: {$datePaid}{$ip}";
                                                    }),

                                                Placeholder::make('razorpay_order_id')
                                                    ->label('Gateway Reference / Order ID')
                                                    ->content(function ($record) {
                                                        return $record->razorpay_order_id ?? $record->order_number ?? 'N/A';
                                                    })
                                                    ->visible(fn ($record) => $record && ($record->razorpay_order_id || $record->order_number)),

                                                Placeholder::make('razorpay_payment_id')
                                                    ->label('Transaction / Payment ID')
                                                    ->content(function ($record) {
                                                        return $record->razorpay_payment_id ?? 'N/A';
                                                    })
                                                    ->visible(fn ($record) => $record && $record->razorpay_payment_id),
                                            ]),
                                    ]),

                                // Financial Details Section
                                Section::make('Financial Details')
                                    ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Placeholder::make('subtotal')
                                                    ->label('Subtotal')
                                                    ->content(function ($record) {
                                                        return format_currency($record->subtotal ?? 0, 2);
                                                    }),
                                                
                                                Placeholder::make('discount_amount')
                                                    ->label('Discount')
                                                    ->content(function ($record) {
                                                        return format_currency($record->discount_amount ?? 0, 2);
                                                    }),
                                                
                                                Placeholder::make('tax_amount')
                                                    ->label('Tax (15% VAT)')
                                                    ->content(function ($record) {
                                                        return format_currency($record->tax_amount ?? 0, 2);
                                                    }),
                                                
                                                Placeholder::make('shipping_amount')
                                                    ->label('Shipping Cost')
                                                    ->content(function ($record) {
                                                        return format_currency($record->shipping_amount ?? 0, 2);
                                                    }),
                                                
                                                Placeholder::make('delivery_amount')
                                                    ->label('COD')
                                                    ->visible(fn ($record) => $record && strtolower($record->payment_method ?? '') === 'cod' && ($record->delivery_amount ?? 0) > 0)
                                                    ->content(function ($record) {
                                                        return format_currency($record->delivery_amount ?? 0, 2);
                                                    }),
                                                
                                                Placeholder::make('total_amount')
                                                    ->label('Total Amount')
                                                    ->content(function ($record) {
                                                        return format_currency($record->total_amount ?? 0, 2);
                                                    }),

                                                Placeholder::make('currency_breakdown')
                                                    ->label('Payment Currency & Conversion')
                                                    ->content(function ($record) {
                                                        if (!$record) return 'SAR';
                                                        $curr = strtoupper($record->currency ?: 'SAR');
                                                        $isPaypal = strtolower((string)($record->payment_method ?? '')) === 'paypal';
                                                        if ($curr === 'USD' || $isPaypal) {
                                                            $rate = (float)($record->exchange_rate ?: 0.2667);
                                                            if ($rate <= 0 || $rate >= 1.0) {
                                                                $rate = 0.2667;
                                                            }
                                                            $sarTotal = (float)($record->sar_amount ?: $record->total_amount);
                                                            $usdAmount = ($record->currency_amount && (float)$record->currency_amount < $sarTotal)
                                                                ? number_format((float)$record->currency_amount, 2)
                                                                : number_format($sarTotal * $rate, 2);
                                                            $sarAmount = number_format($sarTotal, 2);
                                                            return "Paid in USD: \${$usdAmount} | Base: {$sarAmount} SAR (Locked Rate: 1 SAR = " . number_format($rate, 4) . " USD)";
                                                        }
                                                        return "Paid in SAR: " . format_currency($record->sar_amount ?: $record->total_amount, 2);
                                                    })
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),

                                // PDF Document Barcodes & ZATCA Invoice Box
                                Section::make('PDF Document Barcodes & ZATCA Invoice')
                                    ->description('Official Saudi E-Invoicing QR Code & Quick Print Actions')
                                    ->schema([
                                        Placeholder::make('zatca_qr_preview')
                                            ->label('ZATCA E-Invoice QR Code Preview')
                                            ->content(function ($record) {
                                                if (!$record) return '-';
                                                $settings = \App\Models\GlobalSetting::current();
                                                $sellerName = 'مؤسسة غراس السعودية';
                                                $trn = $settings->vat_registration_number ?: '300553485900003';
                                                $total = (float)$record->total_amount;
                                                $tax = (float)($record->tax_amount ?? ($record->subtotal * 0.15));
                                                
                                                $qrUrl = zatca_qr_image_url(
                                                    sellerName: $sellerName,
                                                    trn: $trn,
                                                    timestamp: $record->created_at ?? now(),
                                                    totalAmount: $total,
                                                    vatAmount: $tax,
                                                    size: 140
                                                );

                                                $taxInvoiceUrl = route('orders.tax_invoice', ['id' => $record->id, 'key' => $record->order_key]);
                                                $giftCardUrl = route('orders.gift_card', $record->id);

                                                return new \Illuminate\Support\HtmlString('
                                                    <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                                        <div class="flex items-center gap-4">
                                                            <img src="' . $qrUrl . '" alt="ZATCA E-Invoice QR" class="w-24 h-24 border border-gray-300 dark:border-gray-600 rounded bg-white p-1" />
                                                            <div class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                                                <p class="font-bold text-sm text-gray-900 dark:text-gray-100">ZATCA Compliant QR Code (Phase 1 TLV)</p>
                                                                <p>Seller: <span class="font-semibold">' . e($sellerName) . '</span></p>
                                                                <p>Tax Registration (TRN): <span class="font-mono font-semibold">' . e($trn) . '</span></p>
                                                                <p>Invoice Total: <span class="font-semibold">' . format_currency($total, 2) . '</span> (incl. ' . format_currency($tax, 2) . ' 15% VAT)</p>
                                                            </div>
                                                        </div>
                                                        <div class="flex flex-wrap gap-2">
                                                            <a href="' . $taxInvoiceUrl . '" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 text-white rounded-md text-xs font-semibold hover:bg-emerald-700 transition shadow-sm">
                                                                Print Tax Invoice
                                                            </a>
                                                            <a href="' . $giftCardUrl . '" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-800 text-white rounded-md text-xs font-semibold hover:bg-gray-900 transition shadow-sm">
                                                                Print Florist Gift Card
                                                            </a>
                                                        </div>
                                                    </div>
                                                ');
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // 4. Gift & Delivery Details Tab
                        Tab::make('Gift & Delivery Details')
                            ->icon('heroicon-o-gift')
                            ->schema([
                                Section::make('Florist & Gift Delivery Details')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('recipient_name')
                                                    ->label('Recipient Name')
                                                    ->placeholder('e.g. Sara Al-Otaibi'),

                                                TextInput::make('recipient_phone')
                                                    ->label('Recipient Phone')
                                                    ->tel()
                                                    ->placeholder('e.g. +966 50 123 4567'),

                                                TextInput::make('sender_name')
                                                    ->label('Sender Name on Card')
                                                    ->placeholder('e.g. Mohammed'),

                                                Forms\Components\DatePicker::make('delivery_date')
                                                    ->label('Delivery Date')
                                                    ->prefixIcon('heroicon-o-calendar'),

                                                TextInput::make('delivery_time')
                                                    ->label('Delivery Time / Slot')
                                                    ->placeholder('e.g. 04:00 PM - 08:00 PM'),

                                                TextInput::make('order_language')
                                                    ->label('Language')
                                                    ->placeholder('en / ar')
                                                    ->maxLength(10),

                                                Textarea::make('delivery_message')
                                                    ->label('Gift Card Message')
                                                    ->placeholder('Message written by sender on the gift card...')
                                                    ->rows(3)
                                                    ->columnSpanFull(),

                                                TextInput::make('song_link')
                                                    ->label('Spotify / Audio Song Link')
                                                    ->prefixIcon('heroicon-o-musical-note')
                                                    ->url()
                                                    ->columnSpan(2),

                                                TextInput::make('source_id')
                                                    ->label('WooCommerce Order ID')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->visible(fn (?Order $record) => filled($record?->source_id)),
                                            ]),
                                    ]),
                            ]),
                        
                        // 5. Products / Items Tab
                        Tab::make('Products / Items')
                            ->icon('heroicon-o-shopping-bag')
                            ->badge(fn (?Order $record) => $record?->items?->count())
                            ->schema([
                                Section::make('Order Items')
                                    ->schema([
                                        // Display items as a formatted table with proper styling
                                        Placeholder::make('items')
                                            ->label('')
                                            ->content(function ($record) {
                                                if (!$record || !$record->items || $record->items->count() == 0) {
                                                    return '<div class="text-center text-gray-500 py-8">No items found in this order</div>';
                                                }
                                                
                                                $isVendor = auth()->user()?->isVendor() ?? false;
                                                $vendor = auth()->user()?->vendor;

                                                $items = ($isVendor && $vendor)
                                                    ? $record->items->where('vendor_id', $vendor->id)
                                                    : $record->items;

                                                if ($items->isEmpty()) {
                                                    return '<div class="text-center text-gray-500 py-8">No products belonging to your store found in this order.</div>';
                                                }

                                                $html = '<style>
                                                    .order-items-wrapper {
                                                        background: #ffffff;
                                                        border: 1px solid #e5e7eb;
                                                        border-radius: 0.75rem;
                                                        overflow: hidden;
                                                        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                                                    }
                                                    .dark .order-items-wrapper {
                                                        background: #18181b;
                                                        border-color: #27272a;
                                                    }
                                                    .order-items-table {
                                                        width: 100%;
                                                        border-collapse: collapse;
                                                    }
                                                    .order-items-table thead tr {
                                                        background: #f8fafc;
                                                        border-bottom: 1px solid #e2e8f0;
                                                    }
                                                    .dark .order-items-table thead tr {
                                                        background: #27272a;
                                                        border-bottom-color: #3f3f46;
                                                    }
                                                    .order-items-table th {
                                                        padding: 0.75rem 1rem;
                                                        font-size: 0.75rem;
                                                        font-weight: 600;
                                                        text-transform: uppercase;
                                                        letter-spacing: 0.05em;
                                                        color: #475569;
                                                    }
                                                    .dark .order-items-table th {
                                                        color: #94a3b8;
                                                    }
                                                    .order-items-table tbody tr {
                                                        border-bottom: 1px solid #f1f5f9;
                                                        transition: background-color 0.15s ease;
                                                    }
                                                    .dark .order-items-table tbody tr {
                                                        border-bottom: 1px solid #27272a;
                                                    }
                                                    .order-items-table tbody tr:hover {
                                                        background: #f8fafc;
                                                    }
                                                    .dark .order-items-table tbody tr:hover {
                                                        background: rgba(255, 255, 255, 0.03);
                                                    }
                                                    .order-items-table td {
                                                        padding: 0.75rem 1rem;
                                                        font-size: 0.875rem;
                                                        color: #334155;
                                                        vertical-align: middle;
                                                    }
                                                    .dark .order-items-table td {
                                                        color: #cbd5e1;
                                                    }
                                                    .order-item-title {
                                                        font-weight: 600;
                                                        font-size: 0.875rem;
                                                        line-height: 1.3;
                                                        color: #0f172a;
                                                    }
                                                    .dark .order-item-title {
                                                        color: #f8fafc;
                                                    }
                                                    .order-item-sku {
                                                        font-size: 0.75rem;
                                                        color: #64748b;
                                                        font-family: monospace;
                                                        margin-top: 0.25rem;
                                                    }
                                                    .dark .order-item-sku {
                                                        color: #94a3b8;
                                                    }
                                                    .order-item-thumb {
                                                        width: 52px;
                                                        height: 52px;
                                                        min-width: 52px;
                                                        max-width: 52px;
                                                        min-height: 52px;
                                                        max-height: 52px;
                                                        flex-shrink: 0;
                                                        overflow: hidden;
                                                        border-radius: 6px;
                                                        border: 1px solid #e2e8f0;
                                                        background: #f8fafc;
                                                        display: flex;
                                                        align-items: center;
                                                        justify-content: center;
                                                    }
                                                    .dark .order-item-thumb {
                                                        border-color: #3f3f46;
                                                        background: #27272a;
                                                    }
                                                    .order-summary-card {
                                                        margin-top: 1.5rem;
                                                        background: #f8fafc;
                                                        border: 1px solid #e2e8f0;
                                                        border-radius: 0.75rem;
                                                        padding: 1.5rem;
                                                    }
                                                    .dark .order-summary-card {
                                                        background: #18181b;
                                                        border-color: #27272a;
                                                    }
                                                    .order-summary-row {
                                                        display: flex;
                                                        justify-content: space-between;
                                                        padding: 0.5rem 0;
                                                        border-bottom: 1px solid #e2e8f0;
                                                        font-size: 0.875rem;
                                                        color: #475569;
                                                    }
                                                    .dark .order-summary-row {
                                                        border-bottom-color: #27272a;
                                                        color: #94a3b8;
                                                    }
                                                    .order-summary-row .row-val {
                                                        font-weight: 600;
                                                        color: #1e293b;
                                                    }
                                                    .dark .order-summary-row .row-val {
                                                        color: #f1f5f9;
                                                    }
                                                    .order-summary-grand {
                                                        display: flex;
                                                        justify-content: space-between;
                                                        align-items: center;
                                                        padding-top: 0.875rem;
                                                        margin-top: 0.25rem;
                                                    }
                                                    .order-summary-grand .grand-label {
                                                        font-size: 1rem;
                                                        font-weight: 700;
                                                        color: #0f172a;
                                                    }
                                                    .dark .order-summary-grand .grand-label {
                                                        color: #f8fafc;
                                                    }
                                                    .order-summary-grand .grand-val {
                                                        font-size: 1.25rem;
                                                        font-weight: 800;
                                                        color: #10b981;
                                                    }
                                                </style>
                                                <div class="order-items-wrapper">
                                                    <table class="order-items-table">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 40px; text-align: left;">#</th>
                                                                <th style="text-align: left;">Item / SKU</th>
                                                                <th style="width: 90px; text-align: center;">Quantity</th>
                                                                <th style="width: 120px; text-align: right;">Unit Price</th>
                                                                <th style="width: 130px; text-align: right;">Saudi VAT (15%)</th>
                                                                <th style="width: 120px; text-align: right;">Subtotal</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>';
                                                
                                                $totalItems = $items->sum('quantity');
                                                $totalAmount = (float)$items->sum(function ($item) {
                                                    return ($item->quantity ?? 1) * ($item->price ?? 0);
                                                });

                                                $counter = 1;
                                                foreach ($items as $item) {
                                                    $productName = $item->product
                                                        ? (format_translatable($item->product->name, 'en') ?: (format_translatable($item->product->name, 'ar') ?: ($item->product->sku ?: 'Product')))
                                                        : (format_translatable($item->product_name, 'en') ?: (format_translatable($item->product_name, 'ar') ?: ($item->product_name ?: ('Product #' . $item->product_id))));
                                                    
                                                    $sku = $item->sku ?: ($item->product?->sku ?: 'N/A');
                                                    $img = $item->product_image ?? ($item->product?->image ?? ($item->product?->thumbnail_image ?? null));
                                                    $imgHtml = '';
                                                    if ($img) {
                                                        $imgSrc = str_starts_with($img, 'http') ? $img : \Illuminate\Support\Facades\Storage::disk('public')->url($img);
                                                        $imgHtml = '<div class="order-item-thumb"><img src="' . e($imgSrc) . '" alt="' . e((string)$productName) . '" style="width: 100%; height: 100%; object-fit: cover; display: block;" /></div>';
                                                    } else {
                                                        $imgHtml = '<div class="order-item-thumb" style="font-size: 11px; color: #94a3b8;">No Img</div>';
                                                    }

                                                    $subtotal = ($item->quantity ?? 1) * ($item->price ?? 0);
                                                    $itemTax = (float)($item->tax ?? 0);
                                                    if ($itemTax > 0) {
                                                        $itemVat = $itemTax;
                                                    } elseif ($record && (float)($record->tax_amount ?? 0) > 0 && $totalAmount > 0) {
                                                        $itemVat = round(((float)$record->tax_amount * ($subtotal / $totalAmount)), 2);
                                                    } else {
                                                        $itemVat = round($subtotal * 0.15, 2);
                                                    }

                                                    $html .= '<tr>
                                                        <td style="text-align: left;">' . $counter . '</td>
                                                        <td>
                                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                                ' . $imgHtml . '
                                                                <div>
                                                                    <div class="order-item-title">' . e((string) $productName) . '</div>
                                                                    <div class="order-item-sku">SKU: ' . e((string) $sku) . '</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td style="text-align: center; font-weight: 500;">' . ($item->quantity ?? 1) . '</td>
                                                        <td style="text-align: right; font-weight: 500;">' . format_currency($item->price ?? 0, 2, 'SAR') . '</td>
                                                        <td style="text-align: right; font-weight: 500; color: #10b981;">' . format_currency($itemVat, 2, 'SAR') . '</td>
                                                        <td style="text-align: right; font-weight: 700;">' . format_currency($subtotal, 2, 'SAR') . '</td>
                                                    </tr>';
                                                    $counter++;
                                                }
                                                
                                                $html .= '</tbody></table></div>';
                                                
                                                if ($isVendor) {
                                                    $commissionRate = $vendor->commission_rate;
                                                    $gstRate = $vendor->commission_gst_rate;
                                                    $commissionAmount = $vendor->calculateCommissionFee($totalAmount);
                                                    $gstAmount = $vendor->calculateCommissionGst($totalAmount);
                                                    $totalDeduction = $vendor->calculateTotalDeduction($totalAmount);
                                                    $netEarnings = $vendor->calculateVendorPayout($totalAmount);

                                                    $html .= '<div class="order-summary-card">
                                                        <div style="max-width: 28rem; margin-left: auto;">
                                                            <div class="order-summary-row">
                                                                <span>Your Items Total:</span>
                                                                <span class="row-val">' . $totalItems . ' item(s)</span>
                                                            </div>
                                                            <div class="order-summary-row">
                                                                <span>Gross Sales Amount:</span>
                                                                <span class="row-val">' . format_currency($totalAmount, 2) . '</span>
                                                            </div>
                                                            <div class="order-summary-row" style="color: #d97706;">
                                                                <span>Platform Fee (' . $commissionRate . '%):</span>
                                                                <span class="row-val" style="color: #d97706;">-' . format_currency($commissionAmount, 2) . '</span>
                                                            </div>
                                                            <div class="order-summary-row" style="color: #d97706;">
                                                                <span>GST on Fee (' . $gstRate . '%):</span>
                                                                <span class="row-val" style="color: #d97706;">-' . format_currency($gstAmount, 2) . '</span>
                                                            </div>
                                                            <div class="order-summary-row" style="color: #ef4444;">
                                                                <span style="font-weight: 600;">Total Platform Charges:</span>
                                                                <span class="row-val" style="color: #ef4444; font-weight: 700;">-' . format_currency($totalDeduction, 2) . '</span>
                                                            </div>
                                                            <div class="order-summary-grand">
                                                                <span class="grand-label">Your Net Payout:</span>
                                                                <span class="grand-val">' . format_currency($netEarnings, 2) . '</span>
                                                            </div>
                                                        </div>
                                                    </div>';
                                                } else {
                                                    $html .= '<div class="order-summary-card">
                                                        <div style="max-width: 28rem; margin-left: auto;">
                                                            <div class="order-summary-row">
                                                                <span>Total Items:</span>
                                                                <span class="row-val">' . $totalItems . '</span>
                                                            </div>
                                                            <div class="order-summary-row">
                                                                <span>Items Subtotal:</span>
                                                                <span class="row-val">' . format_currency($totalAmount, 2) . '</span>
                                                            </div>
                                                            <div class="order-summary-row">
                                                                <span>Shipping:</span>
                                                                <span class="row-val">' . format_currency($record->shipping_amount ?? 0, 2) . '</span>
                                                            </div>';

                                                    if ($record && strtolower($record->payment_method ?? '') === 'cod' && ($record->delivery_amount ?? 0) > 0) {
                                                        $html .= '<div class="order-summary-row">
                                                            <span>COD:</span>
                                                            <span class="row-val">' . format_currency($record->delivery_amount ?? 0, 2) . '</span>
                                                        </div>';
                                                    }

                                                    $html .= '<div class="order-summary-row">
                                                                <span>Discount:</span>
                                                                <span class="row-val" style="color: #ef4444;">-' . format_currency($record->discount_amount ?? 0, 2) . '</span>
                                                            </div>
                                                            <div class="order-summary-row">
                                                                <span>Tax (15% VAT):</span>
                                                                <span class="row-val">' . format_currency($record->tax_amount ?? 0, 2) . '</span>
                                                            </div>
                                                            <div class="order-summary-grand">
                                                                <span class="grand-label">Grand Total:</span>
                                                                <span class="grand-val">' . format_currency($record->total_amount ?? 0, 2) . '</span>
                                                            </div>
                                                        </div>
                                                    </div>';
                                                }
                                                
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        
                        // 6. Activity & Timeline Tab (سجل النشاط والعمليات)
                        Tab::make('Activity & Timeline')
                            ->icon('heroicon-o-clock')
                            ->badge(function (?Order $record) {
                                if (!$record) return null;
                                $actCount = \App\Models\OrderActivity::where('order_id', $record->id)->count();
                                $notifCount = \App\Models\NotificationLog::where('order_id', $record->id)->count();
                                $total = $actCount + $notifCount;
                                return $total > 0 ? (string)$total : null;
                            })
                            ->schema([
                                Section::make('Order Activity & Notification Timeline (سجل العمليات والإشعارات)')
                                    ->description('Complete chronological lifecycle of this order: Creation, Payment, Status Changes, and All Triggered SMS & Emails.')
                                    ->schema([
                                        Placeholder::make('activity_timeline')
                                            ->label('')
                                            ->content(fn (?Order $record) => $record ? view('filament.components.order-activity-timeline', ['order' => $record]) : '')
                                            ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->sortable(),
                
                // Tables\Columns\TextColumn::make('order_number')
                //     ->label('Order ID')
                //     ->sortable()
                //     ->searchable(),
                
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer Name')
                    ->getStateUsing(function ($record) {
                        $first = trim((string)($record->first_name ?? ''));
                        $last = trim((string)($record->last_name ?? ''));
                        if (!empty($first) && !empty($last) && str_ends_with(strtolower($first), strtolower($last))) {
                            return $first;
                        }
                        return trim($first . ' ' . $last) ?: ($record->sender_name ?: '-');
                    })
                    ->sortable(['first_name', 'last_name'])
                    ->searchable(['first_name', 'last_name'])
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('recipient_name')
                    ->label('Recipient')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Delivery Date')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('-')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Phone')
                    ->formatStateUsing(fn ($state) => Order::formatPhoneNumber($state))
                    ->searchable(query: function (Builder $query, string $search) {
                        $clean = preg_replace('/\D/', '', $search);
                        if (str_starts_with($clean, '91') && strlen($clean) > 10) {
                            $clean = substr($clean, 2);
                        }
                        $clean = substr($clean, -10);
                        return $query->where(function ($q) use ($search, $clean) {
                            $q->where('customer_phone', 'like', "%{$search}%");
                            if (!empty($clean)) {
                                $q->orWhere('customer_phone', 'like', "%{$clean}%");
                            }
                        });
                    })
                    ->copyable()
                    ->copyMessage('Phone number copied')
                    ->toggleable(),
                
                // Tables\Columns\TextColumn::make('city')
                //     ->label('City')
                //     ->toggleable(),
                
                // Tables\Columns\TextColumn::make('country')
                //     ->label('Country')
                //     ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn ($state): string => match (strtolower((string) $state)) {
                        'new' => 'gray',
                        'pending_payment', 'pending' => 'warning',
                        'processing' => 'info',
                        'printed' => 'primary',
                        'shipped', 'order_shipped' => 'info',
                        'delivered', 'completed' => 'success',
                        'refunded' => 'purple',
                        'failed', 'declined', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn ($state): ?string => match (strtolower((string) $state)) {
                        'new' => 'heroicon-o-clock',
                        'pending_payment' => 'heroicon-o-credit-card',
                        'pending' => 'heroicon-o-exclamation-circle',
                        'processing' => 'heroicon-o-arrow-path',
                        'printed' => 'heroicon-o-printer',
                        'shipped', 'order_shipped' => 'heroicon-o-truck',
                        'delivered' => 'heroicon-o-gift',
                        'completed' => 'heroicon-o-check-circle',
                        'refunded' => 'heroicon-o-arrow-uturn-left',
                        'failed' => 'heroicon-o-x-circle',
                        'declined' => 'heroicon-o-x-circle',
                        'cancelled' => 'heroicon-o-x-mark',
                        default => null,
                    })
                    ->description(function ($record) {
                        if ($record->status === 'failed' && !empty($record->failed_order_reason)) {
                            return 'Failure: ' . Str::limit($record->failed_order_reason, 35);
                        }
                        if ((in_array($record->status, ['cancelled', 'declined', 'payment_cancelled']) || str_contains((string)$record->status, 'cancel')) && !empty($record->cancellation_reason)) {
                            $prefix = $record->cancelled_by === 'customer' 
                                ? 'By User: ' 
                                : ($record->cancelled_by === 'admin' 
                                    ? 'By Admin: ' 
                                    : ($record->cancelled_by === 'payment_failed' ? 'Payment Failed: ' : 'Reason: '));
                            return $prefix . Str::limit($record->cancellation_reason, 35);
                        }
                        if (in_array($record->status, ['order_shipped', 'shipped', 'Shipped', 'completed', 'Complete']) && (!empty($record->tracking_id) || !empty($record->courier_partner))) {
                            $parts = array_filter([$record->courier_partner, $record->tracking_id]);
                            return implode(' - ', $parts);
                        }
                        return null;
                    })
                    ->tooltip(function ($record) {
                        if ($record->status === 'failed' && !empty($record->failed_order_reason)) {
                            return 'Failed Reason: ' . $record->failed_order_reason;
                        }
                        if ((in_array($record->status, ['cancelled', 'declined', 'payment_cancelled']) || str_contains((string)$record->status, 'cancel')) && !empty($record->cancellation_reason)) {
                            $prefix = $record->cancelled_by === 'customer' 
                                ? 'Cancelled by Customer: ' 
                                : ($record->cancelled_by === 'admin' 
                                    ? 'Cancelled by Admin: ' 
                                    : ($record->cancelled_by === 'payment_failed' ? 'Payment Failed / Cancelled: ' : 'Reason: '));
                            return $prefix . $record->cancellation_reason;
                        }
                        if (in_array($record->status, ['order_shipped', 'shipped', 'Shipped', 'completed', 'Complete']) && (!empty($record->tracking_id) || !empty($record->courier_partner))) {
                            return 'Courier: ' . ($record->courier_partner ?? 'N/A') . ' | Tracking: ' . ($record->tracking_id ?? 'N/A');
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('courier_partner')
                    ->label('Courier')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tracking_id')
                    ->label('Tracking ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Tracking ID copied')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('admin_remark')
                    ->label('Admin Remark')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->admin_remark)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->getStateUsing(function ($record) {
                        return $record->getPaymentDetails()['title'];
                    })
                    ->description(function ($record) {
                        $details = $record->getPaymentDetails();
                        $parts = [];
                        if (!empty($details['card_last4'])) {
                            $parts[] = '•••• ' . $details['card_last4'];
                        }
                        if (!empty($details['gateway']) && !in_array(strtolower($details['gateway']), ['cod', strtolower($details['title'])])) {
                            $parts[] = $details['gateway'];
                        }
                        return !empty($parts) ? implode(' • ', $parts) : null;
                    })
                    ->badge()
                    ->color(fn ($record) => $record->getPaymentDetails()['badge_color'])
                    ->icon(fn ($record) => $record->getPaymentDetails()['icon'])
                    ->tooltip(function ($record) {
                        $details = $record->getPaymentDetails();
                        $tip = "Method: {$details['title']}";
                        if ($details['gateway']) $tip .= " via {$details['gateway']}";
                        if ($details['card_last4']) $tip .= " (Card ending in {$details['card_last4']}";
                        if ($details['card_bank']) $tip .= ", {$details['card_bank']}";
                        if ($details['card_last4']) $tip .= ")";
                        return $tip;
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('payment_method', 'like', "%{$search}%")
                              ->orWhere('meta_data', 'like', "%{$search}%");
                        });
                    }),
                
                // Tables\Columns\TextColumn::make('payment_status')
                //     ->label('Payment Status')
                //     ->badge()
                //     ->colors([
                //         'warning' => 'pending',
                //         'success' => 'paid',
                //         'danger' => 'failed',
                //         'info' => 'refunded',
                //     ]),
                
                // Tables\Columns\TextColumn::make('subtotal')
                //     ->label('Subtotal')
                //     ->money('INR')
                //     ->sortable(),
                
                Tables\Columns\TextColumn::make('vendor_items_count')
                    ->label('Your Items')
                    ->getStateUsing(function ($record) {
                        $vendor = auth()->user()?->vendor;
                        if (!$vendor) return '0';
                        return $record->items->where('vendor_id', $vendor->id)->sum('quantity') . ' item(s)';
                    })
                    ->visible(fn () => auth()->user()?->isVendor() ?? false),

                Tables\Columns\TextColumn::make('vendor_earnings')
                    ->label('Your Net Payout')
                    ->getStateUsing(function ($record) {
                        $vendor = auth()->user()?->vendor;
                        if (!$vendor) return '₹0.00';
                        $gross = $record->items->where('vendor_id', $vendor->id)->sum(function ($item) {
                            return ($item->quantity ?? 1) * ($item->price ?? 0);
                        });
                        $net = $vendor->calculateVendorPayout($gross);
                        return format_currency($net, 2);
                    })
                    ->description(function ($record) {
                        $vendor = auth()->user()?->vendor;
                        if (!$vendor) return '';
                        return "after {$vendor->commission_rate}% fee + {$vendor->commission_gst_rate}% GST";
                    })
                    ->badge()
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isVendor() ?? false),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->getStateUsing(function ($record) {
                        $isPaypal = strtolower((string)($record->payment_method ?? '')) === 'paypal';
                        
                        if ($isPaypal) {
                            $rate = (float)($record->exchange_rate ?: 0.2667);
                            $sarTotal = (float)($record->sar_amount ?: $record->total_amount);
                            $usdVal = ($record->currency_amount && (float)$record->currency_amount < $sarTotal)
                                ? (float)$record->currency_amount
                                : round($sarTotal * $rate, 2);
                                
                            return '$' . number_format($usdVal, 2) . ' USD';
                        }

                        $sarVal = (float)($record->sar_amount ?: $record->total_amount);
                        return number_format($sarVal, 2) . ' SAR';
                    })
                    ->tooltip(function ($record) {
                        $isPaypal = strtolower((string)($record->payment_method ?? '')) === 'paypal';
                        if ($isPaypal) {
                            $sarTotal = (float)($record->sar_amount ?: $record->total_amount);
                            return 'Original Total: ' . number_format($sarTotal, 2) . ' SAR (Rate: ' . ($record->exchange_rate ?: 0.2667) . ')';
                        }
                        return null;
                    })
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('total_amount', $direction);
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->where('total_amount', 'like', "%{$search}%");
                    })
                    ->visible(fn () => auth()->user()?->isAdmin() ?? false),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->color(fn ($record) => (strtoupper((string)($record?->currency ?: 'SAR')) === 'USD' || strtolower((string)($record?->payment_method ?? '')) === 'paypal') ? 'success' : 'gray')
                    ->formatStateUsing(fn ($record) => (strtoupper((string)($record?->currency ?: 'SAR')) === 'USD' || strtolower((string)($record?->payment_method ?? '')) === 'paypal') ? 'USD' : 'SAR')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                // Only import action for admin
                Action::make('import')
                    ->label('Import Orders')
                    ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                    ->action(function (array $data) {
                        $file = storage_path('app/public/' . $data['file']);
                        if (!file_exists($file)) {
                            throw new \Exception("File not found: " . $file);
                        }

                        Excel::import(new OrdersImport(), $file);
                    })
                    ->form([
                        FileUpload::make('file')
                            ->label('CSV File')
                            ->required()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                '.csv',
                            ]),
                    ]),
                Tables\Actions\Action::make('sync_wp_orders')
                    ->label('Sync from WooCommerce')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Orders from WooCommerce')
                    ->modalDescription('Do you want to fetch and sync orders directly from grassflorist.com?')
                    ->action(function (\App\Services\StoreImportService $importService) {
                        try {
                            $initial = $importService->importOrdersChunk(1, 50);
                            $pagesToRun = min(5, $initial['total_pages']);
                            for ($p = 2; $p <= $pagesToRun; $p++) {
                                $importService->importOrdersChunk($p, 50);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Orders Synced Successfully!')
                                ->body("Synced orders from grassflorist.com.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Sync Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('razorpay_pending')
                    ->label('Razorpay Pending Payments')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('payment_method', 'razorpay')
                        ->whereIn('payment_status', ['payment_pending', 'created'])
                        ->whereNotNull('razorpay_order_id')
                        ->where('razorpay_order_id', '!=', '')
                    ),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        "pending_payment" => "Pending payment",
                        "processing" => "Processing",
                        "printed" => "Printed",
                        "shipped" => "Shipped",
                        "delivered" => "Delivered",
                        "completed" => "Completed",
                        "cancelled" => "Cancelled",
                        "refunded" => "Refunded",
                        "failed" => "Failed",
                    ]),
                
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        "pending"=>"Pending",
                        "paid"=>"Paid",
                        "failed"=>"Failed",
                        "refunded"=>"Refunded",
                    ]),
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        "cod"=>"Cash on Delivery",
                        "card"=>"Credit Card",
                        "razorpay"=>"Razorpay",
                    ]),
                
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DateTimePicker::make('created_from'),
                        DateTimePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View'),
                    Tables\Actions\EditAction::make()
                        ->label('Edit / Fulfill'),
                    Tables\Actions\Action::make('paymentLink')
                        ->label('Payment Link / رابط الدفع')
                        ->icon('heroicon-o-credit-card')
                        ->color('success')
                        ->modalHeading(fn ($record) => 'Payment Link - Order #' . ($record->order_number ?: $record->id))
                        ->modalDescription('Share this direct payment link with customer via WhatsApp, SMS, or Email to complete payment.')
                        ->form(function ($record) {
                            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
                            $orderKey = $record->order_number ?: $record->id;
                            $payLinkAr = "{$frontendUrl}/order-pay/{$orderKey}";
                            $payLinkEn = "{$frontendUrl}/en/order-pay/{$orderKey}";
                            $phone = preg_replace('/[^0-9]/', '', (string)($record->customer_phone ?: $record->phone));
                            if (!empty($phone) && !str_starts_with($phone, '966')) {
                                if (str_starts_with($phone, '05')) {
                                    $phone = '966' . substr($phone, 1);
                                } elseif (str_starts_with($phone, '5')) {
                                    $phone = '966' . $phone;
                                }
                            }
                            $amount = number_format((float)$record->total_amount, 2);
                            $waMsg = urlencode("مرحباً بك عزيزنا العميل 🌸\nيمكنك إتمام سداد قيمة طلبك رقم {$record->order_number} بمبلغ {$amount} ريال عبر الرابط المباشر:\n{$payLinkAr}\n\nشكراً لاختيارك متجر غراس.");
                            $waUrl = !empty($phone) ? "https://wa.me/{$phone}?text={$waMsg}" : "https://wa.me/?text={$waMsg}";

                            return [
                                Forms\Components\Placeholder::make('order_status_info')
                                    ->label('Payment Status')
                                    ->content(fn () => new \Illuminate\Support\HtmlString(
                                        $record->payment_status === 'paid'
                                            ? "<span class='inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800'>✓ Order is Paid in Full (مسدد بالكامل)</span>"
                                            : "<span class='inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800'>⏳ Awaiting Payment (في انتظار السداد) - {$amount} SAR</span>"
                                    )),

                                Forms\Components\TextInput::make('payment_link_ar')
                                    ->label('Arabic Payment Link (رابط السداد العربي)')
                                    ->default($payLinkAr)
                                    ->readOnly()
                                    ->helperText('Copy or share this link directly with the customer.'),

                                Forms\Components\TextInput::make('payment_link_en')
                                    ->label('English Payment Link (رابط السداد الإنجليزي)')
                                    ->default($payLinkEn)
                                    ->readOnly(),

                                Forms\Components\Placeholder::make('whatsapp_share')
                                    ->label('Quick Share')
                                    ->content(fn () => new \Illuminate\Support\HtmlString(
                                        "<div class='flex items-center gap-3 pt-1'>
                                            <a href='{$waUrl}' target='_blank' class='inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm'>
                                                <span>💬 Send via WhatsApp (إرسال عبر واتساب)</span>
                                            </a>
                                            <a href='{$payLinkAr}' target='_blank' class='inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs transition border border-gray-300'>
                                                <span>🔗 Open Page in New Tab</span>
                                            </a>
                                        </div>"
                                    )),
                            ];
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                    Tables\Actions\Action::make('printGiftCard')
                        ->label('Print Gift Card')
                        ->icon('heroicon-o-gift')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('recipient_name')
                                ->label('Recipient Name (To)')
                                ->default(fn ($record) => $record->recipient_name ?? $record->first_name),
                            Forms\Components\TextInput::make('sender_name')
                                ->label('Sender Name (From)')
                                ->default(fn ($record) => $record->sender_name),
                            Forms\Components\Textarea::make('delivery_message')
                                ->label('Gift Card Message')
                                ->rows(4)
                                ->default(fn ($record) => $record->delivery_message ?? 'With all love and best wishes'),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'recipient_name' => $data['recipient_name'],
                                'sender_name' => $data['sender_name'],
                                'delivery_message' => $data['delivery_message'],
                            ]);
                            $url = route('orders.gift_card', $record->id);
                            return redirect()->away($url);
                        })
                        ->modalHeading('Preview & Print Gift Card')
                        ->modalSubmitActionLabel('Open & Print Gift Card'),

                    Tables\Actions\Action::make('printTaxInvoice')
                        ->label('Print Tax Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->url(fn ($record) => route('orders.tax_invoice', $record->id))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('printInvoice')
                        ->label('Print Slip')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->url(fn ($record) => "javascript:window.open('" . route('orders.print', $record->id) . "', 'Print', 'width=800,height=600'); void(0);"),
                    Tables\Actions\DeleteAction::make()
                        ->label('Delete')
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false),

                    // Quick action to edit shipping address & phone
                    Tables\Actions\Action::make('editAddress')
                        ->label('Edit Address & Phone')
                        ->icon('heroicon-o-map-pin')
                        ->color('warning')
                        ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                            $form->fill([
                                'first_name' => $record->first_name,
                                'last_name' => $record->last_name,
                                'customer_phone' => $record->customer_phone,
                                'address' => $record->address,
                                'address_2' => $record->address_2,
                                'city' => $record->city,
                                'state' => $record->state,
                                'zip_code' => $record->zip_code,
                                'country' => $record->country ?? 'Saudi Arabia',
                            ]);
                        })
                        ->form([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('first_name')->label('First Name')->maxLength(100),
                                    TextInput::make('last_name')->label('Last Name')->maxLength(100),
                                    TextInput::make('customer_phone')->label('Phone Number / Mobile')->tel()->maxLength(20)->columnSpanFull(),
                                    TextInput::make('address')->label('Address Line 1')->columnSpanFull(),
                                    TextInput::make('address_2')->label('Address Line 2')->columnSpanFull(),
                                    TextInput::make('city')->label('City'),
                                    TextInput::make('state')->label('State/Province'),
                                    TextInput::make('zip_code')->label('Postal Code / PIN'),
                                    TextInput::make('country')->label('Country')->default('Saudi Arabia'),
                                ]),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update($data);

                            \Filament\Notifications\Notification::make()
                                ->title('Customer address & phone updated successfully')
                                ->success()
                                ->send();
                        }),

                    // Custom action to update status quickly
                    Tables\Actions\Action::make('updateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-arrow-path')
                        ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                            $form->fill([
                                'status' => $record->status,
                                'tracking_id' => $record->tracking_id,
                                'courier_partner' => $record->courier_partner,
                                'cancellation_reason' => $record->cancellation_reason,
                                'admin_remark' => $record->admin_remark,
                            ]);
                        })
                        ->form([
                            Select::make('status')
                                ->options([
                                    "pending_payment" => "Pending payment",
                                    "processing" => "Processing",
                                    "printed" => "Printed",
                                    "shipped" => "Shipped",
                                    "delivered" => "Delivered",
                                    "completed" => "Completed",
                                    "cancelled" => "Cancelled",
                                    "refunded" => "Refunded",
                                    "failed" => "Failed",
                                ])
                                ->required()
                                ->live(),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    TextInput::make('tracking_id')
                                        ->label('Tracking ID')
                                        ->placeholder('e.g. SMSA12345678')
                                        ->visible(fn (Forms\Get $get) => in_array($get('status'), ['order_shipped', 'shipped', 'delivered', 'completed'])),

                                    TextInput::make('courier_partner')
                                        ->label('Courier Partner Name')
                                        ->placeholder('Enter Courier Partner Name (e.g. SMSA, Aramex)')
                                        ->visible(fn (Forms\Get $get) => in_array($get('status'), ['order_shipped', 'shipped', 'delivered', 'completed'])),
                                ])
                                ->visible(fn (Forms\Get $get) => in_array($get('status'), ['order_shipped', 'shipped', 'delivered', 'completed'])),

                            Textarea::make('failed_order_reason')
                                ->label('Failed Order Reason')
                                ->placeholder('Enter reason why order or payment failed...')
                                ->visible(fn (Forms\Get $get) => $get('status') === 'failed')
                                ->rows(2),

                            Select::make('cancellation_reason_preset')
                                ->label('Cancellation Reason Preset')
                                ->options([
                                    'Customer requested cancellation' => 'Customer requested cancellation',
                                    'Product out of stock' => 'Product out of stock',
                                    'Customer unreachable on phone' => 'Customer unreachable on phone',
                                    'Incorrect or incomplete delivery address' => 'Incorrect or incomplete delivery address',
                                    'Payment issue / Unpaid COD' => 'Payment issue / Unpaid COD',
                                    'Duplicate order placed by mistake' => 'Duplicate order placed by mistake',
                                    'Other reason' => 'Other reason (Specify in remarks below)',
                                ])
                                ->visible(fn (Forms\Get $get) => in_array($get('status'), ['cancelled', 'declined']))
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state && $state !== 'Other reason') {
                                        $set('cancellation_reason', $state);
                                    }
                                })
                                ->dehydrated(false),

                            Textarea::make('cancellation_reason')
                                ->label('Cancellation Reason / Remark')
                                ->placeholder('Enter reason why order is cancelled...')
                                ->visible(fn (Forms\Get $get) => in_array($get('status'), ['cancelled', 'declined']))
                                ->required(fn (Forms\Get $get) => in_array($get('status'), ['cancelled', 'declined']))
                                ->rows(2),

                            Textarea::make('admin_remark')
                                ->label('Admin Remark / Note (Optional)')
                                ->placeholder('Add internal staff note...')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $updateData = ['status' => $data['status']];
                            if (isset($data['tracking_id'])) {
                                $updateData['tracking_id'] = $data['tracking_id'];
                            }
                            if (isset($data['courier_partner'])) {
                                $updateData['courier_partner'] = $data['courier_partner'];
                            }
                            if ($data['status'] === 'failed' && isset($data['failed_order_reason'])) {
                                $updateData['failed_order_reason'] = $data['failed_order_reason'];
                            }
                            if (in_array($data['status'], ['cancelled', 'declined'])) {
                                $updateData['cancellation_reason'] = $data['cancellation_reason'] ?? null;
                                if (empty($record->cancelled_by)) {
                                    $updateData['cancelled_by'] = 'admin';
                                }
                            }
                            if (isset($data['admin_remark'])) {
                                $updateData['admin_remark'] = $data['admin_remark'];
                            }
                            $record->update($updateData);

                            \Filament\Notifications\Notification::make()
                                ->title('Order status updated successfully')
                                ->success()
                                ->send();
                        }),

                    // Quick action to view/add remark
                    Tables\Actions\Action::make('addRemark')
                        ->label('Remark')
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->color('gray')
                        ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                            $form->fill([
                                'admin_remark' => $record->admin_remark,
                            ]);
                        })
                        ->form([
                            Textarea::make('admin_remark')
                                ->label('Order Remark / Internal Comment')
                                ->placeholder('Enter internal notes or comments for this order...')
                                ->rows(4)
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'admin_remark' => $data['admin_remark'],
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Order remark updated successfully')
                                ->success()
                                ->send();
                        }),
                    
                    // Custom action to update payment status (Admin only)
                    Tables\Actions\Action::make('updatePayment')
                        ->label('Update Payment')
                        ->icon('heroicon-o-credit-card')
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                        ->form([
                            Select::make('payment_status')
                                ->options([
                                    "pending"=>"Pending",
                                    "payment_pending"=>"Payment Pending",
                                    "paid"=>"Paid",
                                    "failed"=>"Failed",
                                    "refunded"=>"Refunded",
                                ])
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update(['payment_status' => $data['payment_status']]);
                        }),

                    // Verify & Recover Razorpay Payment action (Admin only)
                    Tables\Actions\Action::make('verifyRazorpay')
                        ->label('Verify / Recover Razorpay')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('warning')
                        ->visible(fn ($record) => (auth()->user()?->isAdmin() ?? false) 
                            && $record->payment_method === 'razorpay' 
                            && $record->payment_status !== 'paid' 
                            && !empty($record->razorpay_order_id))
                        ->requiresConfirmation()
                        ->modalHeading('Verify Razorpay Payment')
                        ->modalDescription(fn ($record) => "Query Razorpay API to check if payment for Order #{$record->order_number} ({$record->razorpay_order_id}) has been captured?")
                        ->action(function ($record) {
                            $razorpayService = new \App\Services\RazorpayService();
                            try {
                                $payments = $razorpayService->fetchOrderPayments($record->razorpay_order_id);
                                $paymentItems = $payments['items'] ?? [];
                                $capturedPayment = null;

                                foreach ($paymentItems as $payment) {
                                    if (($payment['status'] ?? '') === 'captured') {
                                        $capturedPayment = $payment;
                                        break;
                                    }
                                }

                                if ($capturedPayment) {
                                    $result = $razorpayService->markOrderAsPaid($record, $capturedPayment['id'], 'manual_recovery', $capturedPayment);
                                    \Filament\Notifications\Notification::make()
                                        ->title('Payment Verified & Recovered!')
                                        ->body("Order #{$record->order_number} marked as Paid. Payment ID: {$capturedPayment['id']}")
                                        ->success()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('No Captured Payment Found')
                                        ->body("Razorpay reported no captured payment for Order #{$record->order_number}.")
                                        ->warning()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Razorpay Verification Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false),
                    Tables\Actions\BulkAction::make('updateStatusBulk')
                        ->label('Update Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Select::make('status')
                                ->options([
                                    "pending_payment" => "Pending Payment",
                                    "processing" => "Processing",
                                    "printed" => "Printed",
                                    "shipped" => "Shipped",
                                    "delivered" => "Delivered",
                                    "completed" => "Completed",
                                    "cancelled" => "Cancelled",
                                    "refunded" => "Refunded",
                                    "failed" => "Failed",
                                    "new" => "New",
                                    "pending" => "Pending",
                                    "order_shipped" => "Order Shipped",
                                    "declined" => "Declined",
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                $record->update(['status' => $data['status']]);
                            }
                        }),

                    // Bulk Recover Razorpay Payments
                    Tables\Actions\BulkAction::make('recoverRazorpayPayments')
                        ->label('Recover Razorpay Payments')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('warning')
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Recover Razorpay Payments')
                        ->modalDescription('Query Razorpay API for all selected orders. Any orders with captured payments will be marked as Paid and moved to Processing.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $razorpayService = new \App\Services\RazorpayService();
                            $recovered = 0;
                            $skipped = 0;
                            $noPayment = 0;
                            $errors = 0;

                            foreach ($records as $record) {
                                if ($record->payment_method !== 'razorpay' || empty($record->razorpay_order_id)) {
                                    $skipped++;
                                    continue;
                                }

                                if ($record->payment_status === 'paid') {
                                    $skipped++;
                                    continue;
                                }

                                try {
                                    $payments = $razorpayService->fetchOrderPayments($record->razorpay_order_id);
                                    $paymentItems = $payments['items'] ?? [];
                                    $capturedPayment = null;

                                    foreach ($paymentItems as $payment) {
                                        if (($payment['status'] ?? '') === 'captured') {
                                            $capturedPayment = $payment;
                                            break;
                                        }
                                    }

                                    if ($capturedPayment) {
                                        $razorpayService->markOrderAsPaid($record, $capturedPayment['id'], 'manual_recovery', $capturedPayment);
                                        $recovered++;
                                    } else {
                                        $noPayment++;
                                    }
                                } catch (\Exception $e) {
                                    $errors++;
                                    \Illuminate\Support\Facades\Log::error("Manual recovery failed for Order #{$record->order_number}: " . $e->getMessage());
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Razorpay Recovery Complete')
                                ->body("{$recovered} recovered, {$noPayment} unpaid, {$skipped} skipped, {$errors} errors.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
