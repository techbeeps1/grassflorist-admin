<?php

// app/Filament/Resources/UserResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('User Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    
                    Forms\Components\Select::make('role')
                        ->options([
                            'admin' => 'Admin',
                            'vendor' => 'Vendor',
                            'team' => 'Team Member',
                        ])
                        ->required()
                        ->live()
                        ->native(false),
                    
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->maxLength(255)
                        ->helperText(fn (string $context): ?string => $context === 'edit' ? 'Leave blank to keep existing password' : null),
                    
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->required(),
                ])->columns(2),

            // 🔥 Team Member Section-Level Access Control
            Forms\Components\Section::make('Team Member Access & Permissions')
                ->description('Select which sections and modules this team member is allowed to view and access in the admin panel.')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('Allowed Sections / Modules')
                        ->options([
                            'categories' => 'Categories',
                            'cms_posts' => 'CMS Blog Posts',
                            'cms_categories' => 'CMS Categories',
                            'cms_pages' => 'CMS Pages',
                            'contact_page' => 'Contact Page Management',
                            'coupons' => 'Coupons & Discounts',
                            'customers' => 'Customers Management',
                            'global_settings' => 'Global Store Settings',
                            'home_page' => 'Home Page Management',
                            'news' => 'News & Updates',
                            'news_categories' => 'News Categories',
                            'orders' => 'Orders Management',
                            'payment_logs' => 'Payment Logs',
                            'products' => 'Products Management',
                            'production' => 'Publication',
                            'sales_analytics' => 'Sales Analytics',
                            'sales_by_product_category' => 'Sales by products and category',
                            'sales_forecast' => 'Sales Forecast',
                            'sales_orders' => 'Sales Order By Payment',
                            'shipping_methods' => 'Shipping Methods',
                            'sales_top_selling' => 'Top Selling Products',
                            'testimonials' => 'Testimonials (Stories from Clients)',
                            'faqs' => 'FAQs (Frequently Asked Questions)',
                        ])
                        ->columns(2)
                        ->gridDirection('row')
                        ->bulkToggleable()
                        ->helperText('Team members will ONLY see and access the sections selected above. User Management and Team settings are strictly restricted to Admin.')
                        ->columnSpanFull(),
                ])
                ->visible(fn (Forms\Get $get) => $get('role') === 'team'),
            
            // 🔥 Account Status & Dynamic Commission
            Forms\Components\Section::make('Vendor Account & Commission')
                ->schema([
                    Forms\Components\Select::make('vendor.approval_status')
                        ->label('Vendor Approval Status')
                        ->options([
                            'approved' => 'Approved (Active Seller)',
                            'pending' => 'Pending Verification',
                            'suspended' => 'Suspended (Temporary Block)',
                        ])
                        ->default('approved')
                        ->required(fn (Forms\Get $get) => $get('role') === 'vendor')
                        ->native(false),

                    Forms\Components\TextInput::make('vendor.commission_percentage')
                        ->label('Custom Dynamic Commission (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->nullable()
                        ->placeholder('Global Default: ' . \App\Models\Setting::getVendorCommission() . '%')
                        ->helperText('Optional. Leave blank to use platform default (' . \App\Models\Setting::getVendorCommission() . '%). Setting a rate here applies custom commission to this vendor. (18% GST applies on commission).'),
                ])
                ->columns(2)
                ->visible(fn (Forms\Get $get) => $get('role') === 'vendor'),

            // 🔥 Store & Contact Information
            Forms\Components\Section::make('Store & Warehouse Information')
                ->schema([
                    Forms\Components\FileUpload::make('vendor.vendor_logo')
                        ->label('Vendor Logo')
                        ->image()
                        ->imageEditor()
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeTargetWidth('200')
                        ->imageResizeTargetHeight('200')
                        ->directory('vendors/logos')
                        ->visibility('public')
                        ->helperText('Upload square logo (Recommended: 200x200)')
                        ->columnSpanFull(),
                    
                    Forms\Components\TextInput::make('vendor.vendor_name')
                        ->label('Store / Business Name')
                        ->required(fn (Forms\Get $get) => $get('role') === 'vendor')
                        ->maxLength(255)
                        ->placeholder('e.g. Royal Book Store'),

                    Forms\Components\TextInput::make('vendor.contact_person')
                        ->label('Contact Person Name')
                        ->maxLength(255)
                        ->placeholder('e.g. Ramesh Sharma'),
                    
                    Forms\Components\TextInput::make('vendor.vendor_phone')
                        ->label('Support Phone Number')
                        ->tel()
                        ->maxLength(20)
                        ->required(fn (Forms\Get $get) => $get('role') === 'vendor')
                        ->placeholder('e.g. +91 9876543210'),

                    Forms\Components\TextInput::make('vendor.vendor_website')
                        ->label('Website (Optional)')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://example.com'),
                    
                    Forms\Components\Textarea::make('vendor.vendor_address')
                        ->label('Pickup / Warehouse Address')
                        ->rows(2)
                        ->maxLength(500)
                        ->required(fn (Forms\Get $get) => $get('role') === 'vendor')
                        ->placeholder('Shop no, Street, Landmark')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('vendor.city')
                        ->label('City')
                        ->maxLength(100)
                        ->placeholder('e.g. Jaipur'),

                    Forms\Components\TextInput::make('vendor.state')
                        ->label('State')
                        ->maxLength(100)
                        ->placeholder('e.g. Rajasthan'),

                    Forms\Components\TextInput::make('vendor.pincode')
                        ->label('Pincode (Pickup)')
                        ->maxLength(10)
                        ->placeholder('e.g. 302020'),
                ])
                ->columns(3)
                ->visible(fn (Forms\Get $get) => $get('role') === 'vendor')
                ->collapsible(),

            // 🔥 Tax & Legal Verification
            Forms\Components\Section::make('Tax & Legal Verification')
                ->schema([
                    Forms\Components\TextInput::make('vendor.pan_number')
                        ->label('PAN Number')
                        ->maxLength(20)
                        ->placeholder('e.g. ABCDE1234F')
                        ->helperText('Business or Personal PAN for tax verification'),

                    Forms\Components\TextInput::make('vendor.gst_number')
                        ->label('GSTIN / GST Number')
                        ->maxLength(20)
                        ->placeholder('e.g. 08AAAAA0000A1Z5')
                        ->helperText('Optional if turnover under threshold'),

                    Forms\Components\TextInput::make('vendor.isbn_number')
                        ->label('ISBN / Publisher License')
                        ->placeholder('e.g. 978-3-16-148410-0')
                        ->maxLength(50)
                        ->helperText('Publisher registration / ISBN identification'),
                ])
                ->columns(3)
                ->visible(fn (Forms\Get $get) => $get('role') === 'vendor')
                ->collapsible(),

            // 🔥 Bank Account & Payout Details
            Forms\Components\Section::make('Bank Account & Payout Details')
                ->schema([
                    Forms\Components\TextInput::make('vendor.bank_name')
                        ->label('Bank Name')
                        ->placeholder('e.g. State Bank of India')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('vendor.account_holder_name')
                        ->label('Account Holder Name')
                        ->placeholder('Name as per Bank Passbook')
                        ->maxLength(150),

                    Forms\Components\TextInput::make('vendor.account_number')
                        ->label('Bank Account Number')
                        ->placeholder('e.g. 123456789012')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('vendor.ifsc_code')
                        ->label('IFSC Code')
                        ->placeholder('e.g. SBIN0001234')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('vendor.upi_id')
                        ->label('UPI ID (Optional)')
                        ->placeholder('e.g. store@upi')
                        ->maxLength(100),
                ])
                ->columns(3)
                ->visible(fn (Forms\Get $get) => $get('role') === 'vendor')
                ->collapsible(),
        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 🔥 Vendor Logo in Table
                Tables\Columns\ImageColumn::make('vendor.vendor_logo')
                    ->label('Logo')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(function ($record) {
                        if ($record->vendor && $record->vendor->vendor_name) {
                            return 'https://ui-avatars.com/api/?name=' . urlencode($record->vendor->vendor_name) . '&color=7F9CF5&background=EBF4FF';
                        }
                        return 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF';
                    }),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'vendor' => 'success',
                        'team' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Admin',
                        'vendor' => 'Vendor',
                        'team' => 'Team Member',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                // 🔥 Team Permissions Count / Status
                Tables\Columns\TextColumn::make('permissions')
                    ->label('Access Level')
                    ->badge()
                    ->color(fn ($record) => $record->isAdmin() ? 'danger' : ($record->isTeam() ? 'info' : 'success'))
                    ->getStateUsing(function ($record) {
                        if ($record->isAdmin()) return 'Full Admin';
                        if ($record->isVendor()) return 'Vendor Store';
                        if ($record->isTeam()) {
                            $count = is_array($record->permissions) ? count($record->permissions) : 0;
                            return "{$count} Allowed Sections";
                        }
                        return '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                
                // 🔥 Vendor Name
                Tables\Columns\TextColumn::make('vendor.vendor_name')
                    ->label('Store Name')
                    ->placeholder('N/A')
                    ->searchable()
                    ->sortable(),

                // 🔥 Commission Rate (Dynamic + GST)
                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->getStateUsing(function ($record) {
                        if (! $record->isVendor()) return '-';
                        $vendor = $record->vendor;
                        $rate = $vendor?->commission_rate ?? \App\Models\Setting::getVendorCommission();
                        $gst = $vendor?->commission_gst_rate ?? \App\Models\Setting::getCommissionGst();
                        return "{$rate}% (+{$gst}% GST)";
                    })
                    ->badge()
                    ->color('warning'),
                
                // 🔥 Approval Status
                Tables\Columns\TextColumn::make('vendor.approval_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                        default => $state ?? 'N/A',
                    })
                    ->visible(fn () => true)
                    ->toggleable(isToggledHiddenByDefault: false),
                
                // 🔥 Vendor Phone
                Tables\Columns\TextColumn::make('vendor.vendor_phone')
                    ->label('Phone')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d-M-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'vendor' => 'Vendor',
                        'team' => 'Team Member',
                    ])
                    ->placeholder('All Roles'),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Vendor Status')
                    ->relationship('vendor', 'approval_status')
                    ->options([
                        'pending' => 'Pending Verification',
                        'approved' => 'Approved',
                        'suspended' => 'Suspended',
                    ])
                    ->placeholder('All Vendor Statuses'),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active users')
                    ->trueLabel('Only active users')
                    ->falseLabel('Only inactive users')
                    ->placeholder('All users'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_vendor')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Vendor')
                    ->modalDescription('Are you sure you want to approve this vendor and activate their account? They will be allowed to log in.')
                    ->visible(fn (User $record) => $record->isVendor() && ($record->vendor?->approval_status !== 'approved' || ! $record->is_active))
                    ->action(function (User $record) {
                        $record->update(['is_active' => true]);
                        if ($record->vendor) {
                            $record->vendor->update(['approval_status' => 'approved']);
                        }
                        Notification::make()
                            ->title('Vendor Approved')
                            ->body("Vendor \"{$record->name}\" is now approved and active. Notification email sent to vendor.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('suspend_vendor')
                    ->label('Suspend')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Vendor')
                    ->modalDescription('Are you sure you want to suspend this vendor account? They will be blocked from logging in.')
                    ->visible(fn (User $record) => $record->isVendor() && $record->vendor?->approval_status === 'approved' && $record->is_active)
                    ->action(function (User $record) {
                        $record->update(['is_active' => false]);
                        if ($record->vendor) {
                            $record->vendor->update(['approval_status' => 'suspended']);
                        }
                        Notification::make()
                            ->title('Vendor Suspended')
                            ->body("Vendor \"{$record->name}\" has been suspended. Notification email sent to vendor.")
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}