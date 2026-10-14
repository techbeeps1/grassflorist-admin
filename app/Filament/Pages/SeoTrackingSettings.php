<?php

namespace App\Filament\Pages;

use App\Models\GlobalSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SeoTrackingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'SEO & Tracking';
    protected static ?string $title = 'SEO, AI Discovery & Tracking Pixels';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.settings-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || (auth()->user()?->hasPermission('global_settings') ?? false);
    }

    public function mount(): void
    {
        $settings = GlobalSetting::current();

        $this->form->fill([
            'sitemap_enabled' => (bool)$settings->sitemap_enabled,
            'sitemap_include_products' => (bool)$settings->sitemap_include_products,
            'sitemap_include_categories' => (bool)$settings->sitemap_include_categories,
            'sitemap_include_static_pages' => (bool)$settings->sitemap_include_static_pages,
            'sitemap_include_blog' => (bool)$settings->sitemap_include_blog,
            'sitemap_excluded_paths' => $settings->sitemap_excluded_paths,
            'robots_custom_content' => $settings->robots_custom_content,
            'llms_enabled' => (bool)$settings->llms_enabled,
            'llms_custom_content' => $settings->llms_custom_content,
            'gtm_head_code' => $settings->gtm_head_code,
            'gtm_body_code' => $settings->gtm_body_code,
            'ga_measurement_id' => $settings->ga_measurement_id,
            'meta_pixel_id' => $settings->meta_pixel_id,
            'tiktok_pixel_id' => $settings->tiktok_pixel_id,
            'snapchat_pixel_id' => $settings->snapchat_pixel_id,
            'custom_head_scripts' => $settings->custom_head_scripts,
            'custom_footer_scripts' => $settings->custom_footer_scripts,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('SEO & Tracking Settings')
                    ->tabs([
                        // Tab 1: Sitemap.xml
                        Tab::make('Sitemap.xml')
                            ->icon('heroicon-o-document-chart-bar')
                            ->schema([
                                Section::make('Sitemap.xml Management')
                                    ->description('Control which resources are indexed in sitemap.xml, active status, and custom exclusions.')
                                    ->schema([
                                        Toggle::make('sitemap_enabled')
                                            ->label('Enable Dynamic Sitemap (sitemap.xml)')
                                            ->helperText('Turn on to generate and serve dynamic sitemap.xml to Google and search engines.')
                                            ->default(true)
                                            ->columnSpanFull(),

                                        Section::make('Included Resources')
                                            ->schema([
                                                Toggle::make('sitemap_include_products')
                                                    ->label('Include Products')
                                                    ->helperText('Index all active products')
                                                    ->default(true),

                                                Toggle::make('sitemap_include_categories')
                                                    ->label('Include Categories')
                                                    ->helperText('Index categories in AR & EN')
                                                    ->default(true),

                                                Toggle::make('sitemap_include_static_pages')
                                                    ->label('Include Static Pages')
                                                    ->helperText('Index Home, About, Contact, Events')
                                                    ->default(true),

                                                Toggle::make('sitemap_include_blog')
                                                    ->label('Include Blog Posts')
                                                    ->helperText('Index floral & gift articles')
                                                    ->default(true),
                                            ])
                                            ->columns(4)
                                            ->columnSpanFull(),

                                        Textarea::make('sitemap_excluded_paths')
                                            ->label('Excluded Paths & Routes')
                                            ->placeholder("/cart\n/checkout\n/en/cart\n/en/checkout\n/account\n/api/*\n/admin/*")
                                            ->rows(4)
                                            ->helperText('Enter one URL path per line to exclude from sitemap indexing.')
                                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                                            ->columnSpanFull(),

                                        Placeholder::make('sitemap_live_link')
                                            ->label('Live Sitemap Preview')
                                            ->content(function () {
                                                $url = url('/sitemap.xml');
                                                return new HtmlString("
                                                    <a href='{$url}' target='_blank' class='inline-flex items-center text-primary-600 dark:text-primary-400 hover:underline font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700'>
                                                        <span>Open Live {$url}</span>
                                                        <svg class='w-3.5 h-3.5 ml-1.5 inline' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'/></svg>
                                                    </a>
                                                ");
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // Tab 2: Robots.txt
                        Tab::make('Robots.txt')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Section::make('Robots.txt Editor')
                                    ->description('Directly edit crawler rules, allow/disallow paths, and sitemap directives.')
                                    ->schema([
                                        Textarea::make('robots_custom_content')
                                            ->label('Custom robots.txt Rules & Directives')
                                            ->placeholder("User-agent: *\nAllow: /\nDisallow: /cart\nDisallow: /checkout\n\nSitemap: https://grassflorist.com/sitemap.xml")
                                            ->rows(8)
                                            ->helperText('Define crawler rules for Googlebot, Bingbot, and AI agents.')
                                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                                            ->columnSpanFull(),

                                        Placeholder::make('robots_live_link')
                                            ->label('Live robots.txt Preview')
                                            ->content(function () {
                                                $url = url('/robots.txt');
                                                return new HtmlString("
                                                    <a href='{$url}' target='_blank' class='inline-flex items-center text-primary-600 dark:text-primary-400 hover:underline font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700'>
                                                        <span>Open Live {$url}</span>
                                                        <svg class='w-3.5 h-3.5 ml-1.5 inline' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'/></svg>
                                                    </a>
                                                ");
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // Tab 3: LLMS.txt AI Discovery
                        Tab::make('LLMS.txt (AI Discovery)')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Section::make('AI Discovery & LLMs.txt Full Editor')
                                    ->description('Edit the full Markdown content served to AI answer engines (ChatGPT, Claude, Perplexity) in English and Arabic.')
                                    ->schema([
                                        Toggle::make('llms_enabled')
                                            ->label('Enable LLMs.txt AI Knowledge Feed (/llms.txt)')
                                            ->helperText('Turn on to serve optimized Markdown context to LLM crawlers like GPTBot and ClaudeBot.')
                                            ->default(true)
                                            ->columnSpanFull(),

                                        Textarea::make('llms_custom_content')
                                            ->label('Full llms.txt Content')
                                            ->placeholder("# Grass Florist — Knowledge Specification for LLMs\n\n## 1. Brand Overview...")
                                            ->rows(14)
                                            ->helperText('Complete Markdown content for /llms.txt.')
                                            ->extraInputAttributes(['class' => 'font-mono text-xs leading-relaxed'])
                                            ->columnSpanFull(),

                                        Placeholder::make('llms_live_link')
                                            ->label('Live llms.txt Preview')
                                            ->content(function () {
                                                $url = url('/llms.txt');
                                                return new HtmlString("
                                                    <a href='{$url}' target='_blank' class='inline-flex items-center text-primary-600 dark:text-primary-400 hover:underline font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700'>
                                                        <span>Open Live {$url}</span>
                                                        <svg class='w-3.5 h-3.5 ml-1.5 inline' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'/></svg>
                                                    </a>
                                                ");
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // Tab 4: Tracking & Scripts
                        Tab::make('Tracking & Pixels')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Section::make('Google Tag Manager (GTM)')
                                    ->description('Inject Google Tag Manager code snippets directly into your storefront.')
                                    ->schema([
                                        Textarea::make('gtm_head_code')
                                            ->label('GTM Head Script (<head>)')
                                            ->placeholder("<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){...})(window,document,'script','dataLayer','GTM-XXXXXX');</script>\n<!-- End Google Tag Manager -->")
                                            ->rows(5)
                                            ->columnSpanFull(),

                                        Textarea::make('gtm_body_code')
                                            ->label('GTM Body NoScript (<body>)')
                                            ->placeholder("<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n<!-- End Google Tag Manager (noscript) -->")
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Analytics & Ad Pixels')
                                    ->description('Configure GA4 Measurement ID and advertising tracking pixels.')
                                    ->schema([
                                        TextInput::make('ga_measurement_id')
                                            ->label('GA4 Measurement ID')
                                            ->placeholder('G-XXXXXXXXXX'),

                                        TextInput::make('meta_pixel_id')
                                            ->label('Meta / Facebook Pixel ID')
                                            ->placeholder('123456789012345'),

                                        TextInput::make('tiktok_pixel_id')
                                            ->label('TikTok Pixel ID')
                                            ->placeholder('CXXXXXXXXXXXXX'),

                                        TextInput::make('snapchat_pixel_id')
                                            ->label('Snapchat Pixel ID')
                                            ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                                    ])
                                    ->columns(2),

                                Section::make('Custom Scripts')
                                    ->description('Custom CSS, meta tags, and third-party widgets.')
                                    ->schema([
                                        Textarea::make('custom_head_scripts')
                                            ->label('Custom <head> Scripts')
                                            ->rows(4)
                                            ->columnSpanFull(),

                                        Textarea::make('custom_footer_scripts')
                                            ->label('Custom </body> Footer Scripts')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save SEO & Tracking Settings')
                ->submit('save')
                ->icon('heroicon-o-check'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = GlobalSetting::current();
        $settings->update([
            'sitemap_enabled' => (bool)($data['sitemap_enabled'] ?? false),
            'sitemap_include_products' => (bool)($data['sitemap_include_products'] ?? false),
            'sitemap_include_categories' => (bool)($data['sitemap_include_categories'] ?? false),
            'sitemap_include_static_pages' => (bool)($data['sitemap_include_static_pages'] ?? false),
            'sitemap_include_blog' => (bool)($data['sitemap_include_blog'] ?? false),
            'sitemap_excluded_paths' => $data['sitemap_excluded_paths'] ?? null,
            'robots_custom_content' => $data['robots_custom_content'] ?? null,
            'llms_enabled' => (bool)($data['llms_enabled'] ?? false),
            'llms_custom_content' => $data['llms_custom_content'] ?? null,
            'gtm_head_code' => $data['gtm_head_code'] ?? null,
            'gtm_body_code' => $data['gtm_body_code'] ?? null,
            'ga_measurement_id' => $data['ga_measurement_id'] ?? null,
            'meta_pixel_id' => $data['meta_pixel_id'] ?? null,
            'tiktok_pixel_id' => $data['tiktok_pixel_id'] ?? null,
            'snapchat_pixel_id' => $data['snapchat_pixel_id'] ?? null,
            'custom_head_scripts' => $data['custom_head_scripts'] ?? null,
            'custom_footer_scripts' => $data['custom_footer_scripts'] ?? null,
        ]);

        Notification::make()
            ->title('SEO & Tracking Settings Saved')
            ->body('Sitemap, robots, AI discovery, and tracking pixels updated successfully.')
            ->success()
            ->send();
    }
}
