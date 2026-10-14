<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\VendorProfileResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->font(family:'Poppins')
            ->navigationGroups([
                NavigationGroup::make('Shop'),
                NavigationGroup::make('Catalog'),
                NavigationGroup::make('Blog'),
                NavigationGroup::make('Inquiries & Leads'),
                NavigationGroup::make('Subscribers'),
                NavigationGroup::make('Shipping & Delivery'),
                NavigationGroup::make('Content & Storefront'),
                NavigationGroup::make('Communications'),
                NavigationGroup::make('Logs'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Settings'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): HtmlString => new HtmlString('
                    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
                    <style>
                        /* FilePond Grid Gallery Layout */
                        .filepond--root[data-style-panel-layout*="grid"] .filepond--item {
                            width: calc(50% - 0.5em) !important;
                        }
                        @media (min-width: 640px) {
                            .filepond--root[data-style-panel-layout*="grid"] .filepond--item {
                                width: calc(33.333% - 0.5em) !important;
                            }
                        }
                        @media (min-width: 1024px) {
                            .filepond--root[data-style-panel-layout*="grid"] .filepond--item {
                                width: calc(25% - 0.5em) !important;
                            }
                        }
                        .filepond--root[data-style-panel-layout*="grid"] .filepond--image-preview-wrapper {
                            border-radius: 8px;
                            overflow: hidden;
                        }
                    </style>
                ')
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label('Store Profile')
                    ->url(fn (): string => VendorProfileResource::getUrl('index'))
                    ->icon('heroicon-o-building-storefront')
                    ->visible(fn (): bool => auth()->user()?->isVendor() ?? false),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
    
}
