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
             ->plugins([
            \Biostate\FilamentMenuBuilder\FilamentMenuBuilderPlugin::make(),
        ])
            ->font(family:'Poppins')
            ->navigationGroups([
                NavigationGroup::make('Blog'),
                NavigationGroup::make('Pages'),
                NavigationGroup::make('Shop'),
                NavigationGroup::make('Shipping'),
                NavigationGroup::make('User Management'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Payment Logs'),
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
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function (): HtmlString {
                    $isAr = app()->getLocale() === 'ar';
                    $targetLang = $isAr ? 'en' : 'ar';
                    $targetLabel = $isAr ? 'English (LTR)' : 'عربي (RTL)';
                    $title = $isAr ? 'Switch admin panel to English' : 'تحويل لوحة التحكم إلى العربية';
                    $currentUrl = request()->fullUrlWithQuery(['lang' => $targetLang]);

                    return new HtmlString('
                        <div class="flex items-center px-2">
                            <a href="' . e($currentUrl) . '" 
                               title="' . e($title) . '"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm">
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896 3.025 2.457 5.764 4.512 8.026" />
                                </svg>
                                <span>' . e($targetLabel) . '</span>
                            </a>
                        </div>
                    ');
                }
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'ar' ? 'Switch to English (LTR)' : 'التبديل إلى العربية (RTL)')
                    ->url(fn (): string => request()->fullUrlWithQuery(['lang' => app()->getLocale() === 'ar' ? 'en' : 'ar']))
                    ->icon('heroicon-o-language'),
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
