<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorProfileResource\Pages;
use App\Models\Setting;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;

class VendorProfileResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Store Profile';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 20;
    protected static ?string $slug = 'vendor-profile';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isVendor() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1. Account Status & Platform Terms (Read-Only Overview)
                Forms\Components\Section::make('Account Status & Platform Terms')
                    ->description('Your verified merchant account status and platform commission terms.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Placeholder::make('status_badge')
                            ->label('Account Status')
                            ->content(function () {
                                $status = auth()->user()?->vendor?->approval_status ?? 'pending';
                                $color = match ($status) {
                                    'approved' => 'background-color: #dcfce7; color: #15803d; border: 1px solid #86efac;',
                                    'suspended' => 'background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;',
                                    default => 'background-color: #fef9c3; color: #854d0e; border: 1px solid #fde047;',
                                };
                                $label = ucfirst($status);
                                return new HtmlString("<span style='display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-weight: 600; font-size: 0.875rem; {$color}'>● {$label}</span>");
                            }),

                        Forms\Components\Placeholder::make('commission_badge')
                            ->label('Platform Commission')
                            ->content(function () {
                                $vendor = auth()->user()?->vendor;
                                $rate = $vendor?->commission_rate ?? Setting::getVendorCommission();
                                $gst = $vendor?->commission_gst_rate ?? Setting::getCommissionGst();
                                return new HtmlString("<span style='display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-weight: 600; font-size: 0.875rem; background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db;'>{$rate}% Platform Fee (+{$gst}% GST on fee)</span>");
                            }),

                        Forms\Components\TextInput::make('user_email')
                            ->label('Login Email')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Primary login email. Contact administrator to change.'),
                    ])
                    ->columns(3),

                // 2. Store Branding & Contact
                Forms\Components\Section::make('Store Information & Branding')
                    ->description('Update your public store name, logo, and representative contact information.')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        Forms\Components\FileUpload::make('vendor_logo')
                            ->label('Store Logo')
                            ->image()
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('200')
                            ->imageResizeTargetHeight('200')
                            ->directory('vendors/logos')
                            ->visibility('public')
                            ->helperText('Upload a square logo (Recommended: 200x200 px).')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('vendor_name')
                            ->label('Store / Business Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_person')
                            ->label('Key Contact Person')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('vendor_phone')
                            ->label('Support Phone / Mobile')
                            ->tel()
                            ->required()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('vendor_website')
                            ->label('Store Website / Portfolio')
                            ->url()
                            ->nullable()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                // 3. Warehouse & Dispatch Address
                Forms\Components\Section::make('Warehouse & Dispatch Location')
                    ->description('Address where courier partners will pick up orders for dispatch.')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Forms\Components\Textarea::make('vendor_address')
                            ->label('Warehouse / Pickup Address')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('State')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('pincode')
                            ->label('Pincode / Postal Code')
                            ->required()
                            ->maxLength(10),
                    ])
                    ->columns(3),

                // 4. Bank & Payout Information
                Forms\Components\Section::make('Payout & Bank Details')
                    ->description('Bank account information for your order earnings payouts.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Contact administrator to update.'),

                        Forms\Components\TextInput::make('account_holder_name')
                            ->label('Account Holder Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Contact administrator to update.'),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Bank Account Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Contact administrator to update.'),

                        Forms\Components\TextInput::make('ifsc_code')
                            ->label('IFSC Code')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Contact administrator to update.'),

                        Forms\Components\TextInput::make('upi_id')
                            ->label('UPI ID (Optional)')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Contact administrator to update.'),
                    ])
                    ->columns(2),

                // 5. Verified Tax & Business Records (Read-Only)
                Forms\Components\Section::make('Verified Tax & Compliance Information')
                    ->description('Legally verified tax identification records. These fields cannot be edited directly.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Forms\Components\TextInput::make('pan_number')
                            ->label('PAN Card Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Verified tax identifier. Contact administrator to update.'),

                        Forms\Components\TextInput::make('gst_number')
                            ->label('GSTIN (GST Number)')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Verified GST record. Contact administrator to update.'),

                        Forms\Components\TextInput::make('isbn_number')
                            ->label('ISBN License / Publisher Reg.')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('🔒 Verified license number. Contact administrator to update.'),
                    ])
                    ->columns(3),

                // 6. Security & Password Change
                Forms\Components\Section::make('Account Security & Password')
                    ->description('Leave password fields blank if you do not wish to change your password.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Forms\Components\TextInput::make('user_name')
                            ->label('User Account Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->nullable()
                            ->helperText('Required only if you are changing your password.'),

                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->nullable()
                            ->helperText('Minimum 8 characters.'),

                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\EditVendorProfile::route('/'),
        ];
    }
}
