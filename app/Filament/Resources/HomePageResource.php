<?php
// app/Filament/Resources/HomePageResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\HomePageResource\Pages;
use App\Filament\Resources\HomePageResource\RelationManagers;
use App\Models\HomePage;
use App\Models\Product;
use App\Models\Category;
use App\Models\Production;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;


class HomePageResource extends Resource
{
    protected static ?string $model = HomePage::class;
    protected static ?string $navigationGroup = 'Pages';
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $modelLabel = 'Home Page';
    protected static ?string $navigationLabel = 'Home Page';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('home_page') ?? false;
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return 'Home Page';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Update Information')
                    ->schema([
                        Forms\Components\Placeholder::make('last_updated_at')
                            ->label('Last Updated')
                            ->content(function (?HomePage $record) {
                                return $record?->updated_at
                                    ? $record->updated_at->format('d M Y, h:i A')
                                    : 'Not updated yet';
                            }),

                        Forms\Components\Placeholder::make('updated_by')
                            ->label('Updated By')
                            ->content(function (?HomePage $record) {
                                return $record?->updatedBy?->name ?? 'Not available';
                            }),
                    ])
                    ->columns(2)
                    ->visible(fn (string $operation) => $operation === 'edit'),

                // 1. Desktop Sliders
                Forms\Components\Section::make('Desktop Sliders & Banners')
                    ->description('Main banners displayed on desktop screens with language-specific images')
                    ->schema([
                        Forms\Components\Repeater::make('slider_section')
                            ->label('Desktop Sliders')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('slider_image_en')
                                            ->label('Slider Image (English - LTR)')
                                            ->helperText('Banner displayed on English version of the site')
                                            ->image()
                                            ->disk('public')
                                            ->directory('home-page/banner'),

                                        Forms\Components\FileUpload::make('slider_image_ar')
                                            ->label('Slider Image (Arabic - عربي RTL)')
                                            ->helperText('Banner displayed on Arabic version of the site')
                                            ->image()
                                            ->disk('public')
                                            ->directory('home-page/banner'),
                                    ]),

                                Forms\Components\TextInput::make('slider_url')
                                    ->label('Redirect URL / Link')
                                    ->placeholder('e.g. /category/occasions or /products')
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(fn (array $state): ?string => 
                                !empty($state['slider_url']) ? "Banner Link: {$state['slider_url']}" : 'Desktop Slide'
                            )
                            ->collapsible()
                            ->reorderable()
                            ->addActionLabel('+ Add Desktop Slider')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // 2. Mobile Sliders
                Forms\Components\Section::make('Mobile Sliders')
                    ->description('Banners optimized for mobile screens with language-specific images')
                    ->schema([
                        Forms\Components\Repeater::make('mslider_section')
                            ->label('Mobile Sliders')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('mslider_image_en')
                                            ->label('Mobile Image (English - LTR)')
                                            ->helperText('Displayed on mobile devices in English')
                                            ->image()
                                            ->disk('public')
                                            ->directory('home-page/banner'),

                                        Forms\Components\FileUpload::make('mslider_image_ar')
                                            ->label('Mobile Image (Arabic - عربي RTL)')
                                            ->helperText('Displayed on mobile devices in Arabic')
                                            ->image()
                                            ->disk('public')
                                            ->directory('home-page/banner'),
                                    ]),

                                Forms\Components\TextInput::make('mslider_url')
                                    ->label('Redirect URL / Link')
                                    ->placeholder('e.g. /category/occasions or /products')
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(fn (array $state): ?string => 
                                !empty($state['mslider_url']) ? "Mobile Link: {$state['mslider_url']}" : 'Mobile Slide'
                            )
                            ->collapsible()
                            ->reorderable()
                            ->addActionLabel('+ Add Mobile Slider')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // 3. Popular Categories Section (Titles + Categories together)
                Forms\Components\Section::make('Popular Categories Section')
                    ->description('Configure titles and choose the categories to display in this section')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('popular_title.en')
                                    ->label('Section Title (English)')
                                    ->default('Popular Categories')
                                    ->placeholder('e.g. Popular Categories'),
                                Forms\Components\TextInput::make('popular_title.ar')
                                    ->label('Section Title (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('التصنيفات الأكثر طلباً')
                                    ->placeholder('مثال: التصنيفات الأكثر طلباً'),
                                Forms\Components\TextInput::make('popular_subtitle.en')
                                    ->label('Subtitle (English)')
                                    ->default('Explore fresh bouquets by category'),
                                Forms\Components\TextInput::make('popular_subtitle.ar')
                                    ->label('Subtitle (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('استكشف باقات الزهور حسب التصنيف'),
                            ]),

                        Forms\Components\Repeater::make('popular_category')
                            ->label('Selected Categories (Drag & Drop to set display order)')
                            ->simple(
                                Forms\Components\Select::make('category_id')
                                    ->label('Category')
                                    ->options(function () {
                                        return Category::all()->mapWithKeys(function ($cat) {
                                            $en = $cat->getTranslation('name', 'en') ?: $cat->slug;
                                            $ar = $cat->getTranslation('name', 'ar') ?: '';
                                            $label = ($en && $ar && $en !== $ar) ? "{$en} ({$ar})" : ($en ?: $ar);
                                            return [(string) $cat->id => $label];
                                        })->toArray();
                                    })
                                    ->searchable()
                                    ->required()
                            )
                            ->addActionLabel('+ Add Category to Section')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                // 4. Category Product Carousels Repeater
                Forms\Components\Section::make('Category Product Sections (Carousels)')
                    ->description('Add multiple product category sections (carousels) to the homepage')
                    ->schema([
                        Forms\Components\Repeater::make('category_sections')
                            ->label('Homepage Category Sections')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('title.en')
                                            ->label('Section Title (English)')
                                            ->placeholder('e.g. Handcrafted Bouquets')
                                            ->required(),
                                        Forms\Components\TextInput::make('title.ar')
                                            ->label('Section Title (Arabic - عربي)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->placeholder('مثال: باقات زهور منسقة')
                                            ->required(),
                                        Forms\Components\TextInput::make('subtitle.en')
                                            ->label('Subtitle (English)')
                                            ->placeholder('e.g. Delivering emotions through flowers with carefully crafted arrangements'),
                                        Forms\Components\TextInput::make('subtitle.ar')
                                            ->label('Subtitle (Arabic - عربي)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->placeholder('مثال: نوصل مشاعرك من خلال زهورنا المتميزة التي تناسب جميع الأذواق والمناسبات'),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('category_id')
                                            ->label('Select Product Category')
                                            ->options(function () {
                                                return Category::all()->mapWithKeys(function ($cat) {
                                                    $en = $cat->getTranslation('name', 'en') ?: $cat->slug;
                                                    $ar = $cat->getTranslation('name', 'ar') ?: '';
                                                    $label = ($en && $ar && $en !== $ar) ? "{$en} ({$ar})" : ($en ?: $ar);
                                                    return [(string) $cat->id => $label];
                                                })->toArray();
                                            })
                                            ->searchable()
                                            ->required(),
                                        Forms\Components\Select::make('limit')
                                            ->label('Number of Products in Carousel')
                                            ->options([
                                                4 => '4 Products',
                                                8 => '8 Products (Recommended)',
                                                12 => '12 Products',
                                                16 => '16 Products',
                                            ])
                                            ->default(8),
                                    ]),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $en = $state['title']['en'] ?? null;
                                $ar = $state['title']['ar'] ?? null;
                                $catId = $state['category_id'] ?? null;
                                $catName = $catId ? (Category::find($catId)?->getTranslation('name', 'en') ?: '') : '';
                                if ($en && $catName) return "{$en}  [Category: {$catName}]";
                                return $en ?: ($ar ?: ($catName ? "Category: {$catName}" : 'New Category Section'));
                            })
                            ->addActionLabel('+ Add New Category Product Section')
                            ->collapsible()
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // 5. Editorial Story Banner Section (Lifestyle Showcase)
                Forms\Components\Section::make('Editorial Story Banner Section')
                    ->description('Lifestyle promo banner displayed between category sections on the homepage')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('banner_title.en')
                                    ->label('Banner Headline (English)')
                                    ->rows(2)
                                    ->default("Same Day Delivery\nFlowers & Gifts")
                                    ->placeholder("Same Day Delivery\nFlowers & Gifts"),

                                Forms\Components\Textarea::make('banner_title.ar')
                                    ->label('Banner Headline (Arabic - عربي)')
                                    ->rows(2)
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default("توصيل في نفس اليوم\nزهور وهدايا فاخرة")
                                    ->placeholder("توصيل في نفس اليوم\nزهور وهدايا فاخرة"),

                                Forms\Components\TextInput::make('banner_button_title.en')
                                    ->label('Button Text (English)')
                                    ->default('Shop Same Day Flowers')
                                    ->placeholder('Shop Same Day Flowers'),

                                Forms\Components\TextInput::make('banner_button_title.ar')
                                    ->label('Button Text (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('تسوق زهور اليوم نفسه')
                                    ->placeholder('تسوق زهور اليوم نفسه'),
                            ]),

                        Forms\Components\TextInput::make('banner_button_url')
                            ->label('Button Redirect URL')
                            ->default('/category/all-flowers')
                            ->placeholder('e.g. /category/all-flowers or /products'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\FileUpload::make('banner_image_en')
                                    ->label('Banner Image (English - LTR)')
                                    ->helperText('Visual shown on English site')
                                    ->image()
                                    ->disk('public')
                                    ->directory('home-page/banner')
                                    ->openable()
                                    ->downloadable(),

                                Forms\Components\FileUpload::make('banner_image_ar')
                                    ->label('Banner Image (Arabic - عربي RTL)')
                                    ->helperText('Visual shown on Arabic site')
                                    ->image()
                                    ->disk('public')
                                    ->directory('home-page/banner')
                                    ->openable()
                                    ->downloadable(),
                            ]),
                    ])
                    ->collapsible(),

                // 6. Brand Benefits Section (3 Cards)
                Forms\Components\Section::make('Brand Benefits Section')
                    ->description('3 highlight cards displayed on the homepage (Assortment, Delivery, Payment)')
                    ->schema([
                        Forms\Components\Repeater::make('benefits_section')
                            ->label('Benefit Cards')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('icon')
                                            ->label('Card Icon')
                                            ->options([
                                                'sprout' => '🌱 Sprout / Assortment',
                                                'package' => '📦 Package / Fast Delivery',
                                                'wallet' => '💳 Wallet / Secure Payment',
                                                'star' => '⭐ Star / Quality',
                                                'flower' => '🌸 Flower / Fresh Blooms',
                                                'shield' => '🛡️ Shield / Guarantee',
                                            ])
                                            ->default('sprout')
                                            ->required(),

                                        Forms\Components\TextInput::make('title.en')
                                            ->label('Title (English)')
                                            ->required(),

                                        Forms\Components\TextInput::make('title.ar')
                                            ->label('Title (Arabic - عربي)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->required(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Textarea::make('description.en')
                                            ->label('Description (English)')
                                            ->rows(2)
                                            ->required(),

                                        Forms\Components\Textarea::make('description.ar')
                                            ->label('Description (Arabic - عربي)')
                                            ->rows(2)
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->required(),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? ($state['title']['ar'] ?? 'Benefit Card'))
                            ->collapsible()
                            ->reorderable()
                            ->addActionLabel('+ Add Benefit Card')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // 7. Events Planning Showcase Section
                Forms\Components\Section::make('Events Planning Showcase Section')
                    ->description('Configure the events booking section and slideshow on the homepage')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('events_section.badge.en')
                                    ->label('Header Badge (English)')
                                    ->default('EVENTS'),

                                Forms\Components\TextInput::make('events_section.badge.ar')
                                    ->label('Header Badge (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('المناسبات'),

                                Forms\Components\TextInput::make('events_section.title_main.en')
                                    ->label('Main Title (English)')
                                    ->default('Blossom your events with our'),

                                Forms\Components\TextInput::make('events_section.title_main.ar')
                                    ->label('Main Title (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('أضف لمسة من السحر لمناسباتك مع'),

                                Forms\Components\TextInput::make('events_section.title_highlight.en')
                                    ->label('Highlight / Accent Word (English)')
                                    ->default('expert touch!'),

                                Forms\Components\TextInput::make('events_section.title_highlight.ar')
                                    ->label('Highlight / Accent Word (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('لمستنا الاحترافية!'),

                                Forms\Components\Textarea::make('events_section.description.en')
                                    ->label('Description (English)')
                                    ->rows(3),

                                Forms\Components\Textarea::make('events_section.description.ar')
                                    ->label('Description (Arabic - عربي)')
                                    ->rows(3)
                                    ->extraInputAttributes(['dir' => 'rtl']),

                                Forms\Components\TextInput::make('events_section.button_text.en')
                                    ->label('Button Text (English)')
                                    ->default('BOOK NOW'),

                                Forms\Components\TextInput::make('events_section.button_text.ar')
                                    ->label('Button Text (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('احجز الآن'),

                                Forms\Components\TextInput::make('events_section.button_url')
                                    ->label('Button Link / URL')
                                    ->default('/event-booking')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Fieldset::make('Pillar Feature Tags')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('events_section.tag1.en')
                                            ->label('Tag 1 (English)')
                                            ->default('Weddings'),
                                        Forms\Components\TextInput::make('events_section.tag2.en')
                                            ->label('Tag 2 (English)')
                                            ->default('Corporate Events'),
                                        Forms\Components\TextInput::make('events_section.tag3.en')
                                            ->label('Tag 3 (English)')
                                            ->default('Special Occasions'),

                                        Forms\Components\TextInput::make('events_section.tag1.ar')
                                            ->label('Tag 1 (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->default('حفلات الزفاف'),
                                        Forms\Components\TextInput::make('events_section.tag2.ar')
                                            ->label('Tag 2 (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->default('فعاليات الشركات'),
                                        Forms\Components\TextInput::make('events_section.tag3.ar')
                                            ->label('Tag 3 (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->default('مناسبات خاصة'),
                                    ]),
                            ]),

                        Forms\Components\Repeater::make('events_section.slides')
                            ->label('Event Slides & Galleries (Drag & Drop to reorder)')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('image')
                                            ->label('Slide Image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('home-page/events')
                                            ->openable()
                                            ->downloadable()
                                            ->required(),

                                        Forms\Components\TextInput::make('url')
                                            ->label('Redirect Link (optional)')
                                            ->placeholder('e.g. /event-booking'),

                                        Forms\Components\TextInput::make('title.en')
                                            ->label('Slide Title (English)')
                                            ->required(),

                                        Forms\Components\TextInput::make('title.ar')
                                            ->label('Slide Title (Arabic - عربي)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->required(),

                                        Forms\Components\TextInput::make('service_tag.en')
                                            ->label('Category Tag (English)')
                                            ->default('EVENT SERVICE'),

                                        Forms\Components\TextInput::make('service_tag.ar')
                                            ->label('Category Tag (Arabic - عربي)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->default('باقة المناسبات'),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? ($state['title']['ar'] ?? 'Event Slide'))
                            ->collapsible()
                            ->reorderable()
                            ->addActionLabel('+ Add Event Slide')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // 8. Floral Inspiration & Care Guides (Blog Section)
                Forms\Components\Section::make('Floral Inspiration & Care Guides (Blog Section)')
                    ->description('Configure section header. Articles are automatically pulled from Blog Posts (/admin/cms-posts)')
                    ->schema([
                        Forms\Components\Placeholder::make('blog_notice')
                            ->label('Blog Posts Source')
                            ->content('Articles are managed dynamically from Blog > Blog Posts (/admin/cms-posts). The latest active posts appear here.'),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('blog_title.en')
                                    ->label('Section Title (English)')
                                    ->default('Floral Inspiration & Care Guides')
                                    ->placeholder('e.g. Floral Inspiration & Care Guides'),
                                Forms\Components\TextInput::make('blog_title.ar')
                                    ->label('Section Title (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('إلهام وأسرار العناية بالزهور')
                                    ->placeholder('مثال: إلهام وأسرار العناية بالزهور'),
                                Forms\Components\TextInput::make('blog_subtitle.en')
                                    ->label('Subtitle (English)')
                                    ->default('Curated articles from master florists to guide your gifting choices and prolong bloom life.')
                                    ->placeholder('Subtitle in English'),
                                Forms\Components\TextInput::make('blog_subtitle.ar')
                                    ->label('Subtitle (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('مقالات حصرية من خبراء تنسيق الزهور لإرشادك في اختيار الهدية المثالية والحفاظ على نضارتها.')
                                    ->placeholder('الوصف بالعربية'),
                            ]),
                    ])
                    ->collapsible(),

                // 8. Stories from Our Clients (Testimonials Section)
                Forms\Components\Section::make('Stories from Our Clients (Testimonials Section)')
                    ->description('Configure section header. Reviews are automatically pulled from Pages > Testimonials (/admin/testimonials)')
                    ->schema([
                        Forms\Components\Placeholder::make('testimonials_notice')
                            ->label('Testimonials Source')
                            ->content('Client reviews are managed from Pages > Testimonials (/admin/testimonials). Active reviews appear in the homepage carousel.'),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('testimonials_title.en')
                                    ->label('Section Title (English)')
                                    ->default('Stories from Our Clients')
                                    ->placeholder('e.g. Stories from Our Clients'),
                                Forms\Components\TextInput::make('testimonials_title.ar')
                                    ->label('Section Title (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('تجارب عملائنا المميزين')
                                    ->placeholder('مثال: تجارب عملائنا المميزين'),
                                Forms\Components\TextInput::make('testimonials_subtitle.en')
                                    ->label('Subtitle (English)')
                                    ->default('Real feedback from those who trusted us with their special moments')
                                    ->placeholder('Subtitle in English'),
                                Forms\Components\TextInput::make('testimonials_subtitle.ar')
                                    ->label('Subtitle (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('آراء وتقييمات حقيقية من عملائنا الكرام الذين شاركونا أجمل لحظاتهم')
                                    ->placeholder('الوصف بالعربية'),
                            ]),
                    ])
                    ->collapsible(),

                // 9. Frequently Asked Questions (FAQs Section)
                Forms\Components\Section::make('Frequently Asked Questions (FAQs Section)')
                    ->description('Configure section header. Questions are automatically pulled from Pages > FAQs (/admin/faqs)')
                    ->schema([
                        Forms\Components\Placeholder::make('faqs_notice')
                            ->label('FAQs Source')
                            ->content('Questions and answers are managed from Pages > FAQs (/admin/faqs). Active FAQs appear in the homepage accordion.'),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('faq_title.en')
                                    ->label('Section Title (English)')
                                    ->default('Frequently Asked Questions')
                                    ->placeholder('e.g. Frequently Asked Questions'),
                                Forms\Components\TextInput::make('faq_title.ar')
                                    ->label('Section Title (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('الأسئلة الأكثر شيوعاً')
                                    ->placeholder('مثال: الأسئلة الأكثر شيوعاً'),
                                Forms\Components\TextInput::make('faq_subtitle.en')
                                    ->label('Subtitle (English)')
                                    ->default('Find quick answers to common questions about our fresh flowers, delivery, and services.')
                                    ->placeholder('Subtitle in English'),
                                Forms\Components\TextInput::make('faq_subtitle.ar')
                                    ->label('Subtitle (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->default('إجابات وافية وشاملة عن أكثر الأسئلة شيوعاً حول باقاتنا وخدمات التوصيل.')
                                    ->placeholder('الوصف بالعربية'),
                            ]),
                    ])
                    ->collapsible(),

                // 10. SEO Metadata Section
                Forms\Components\Section::make('Home Page SEO Metadata')
                    ->description('Search engine optimization title, description, and keywords')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('meta_tag_title.en')
                                    ->label('Meta Title (English)'),
                                Forms\Components\TextInput::make('meta_tag_title.ar')
                                    ->label('Meta Title (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                Forms\Components\TextInput::make('meta_tag_keywords.en')
                                    ->label('Meta Keywords (English)'),
                                Forms\Components\TextInput::make('meta_tag_keywords.ar')
                                    ->label('Meta Keywords (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                Forms\Components\Textarea::make('meta_tag_description.en')
                                    ->label('Meta Description (English)')
                                    ->rows(2),
                                Forms\Components\Textarea::make('meta_tag_description.ar')
                                    ->label('Meta Description (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->rows(2),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                 Tables\Columns\TextColumn::make('page_title')
                    ->label('Page')
                    ->weight('bold')
                    ,
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListHomePages::route('/'),
            'create' => Pages\CreateHomePage::route('/create'),
            'edit' => Pages\EditHomePage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationUrl(): string
{
    $recordId = \App\Models\HomePage::query()->first()?->id;

    return $recordId
        ? static::getUrl('edit', ['record' => $recordId])
        : static::getUrl('index'); // fallback
}

}