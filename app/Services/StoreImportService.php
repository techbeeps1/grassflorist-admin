<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreImportService
{
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.woocommerce.url', 'https://grassflorist.com'), '/') . '/wp-json/wc/v3';
        $this->consumerKey = (string) config('services.woocommerce.consumer_key');
        $this->consumerSecret = (string) config('services.woocommerce.consumer_secret');
        $this->verifySsl = (bool) config('services.woocommerce.verify_ssl', false);
    }

    /**
     * Helper to make authenticated HTTP GET requests to WooCommerce.
     */
    protected function makeRequest(string $endpoint, array $params = [])
    {
        $url = "{$this->baseUrl}/{$endpoint}";

        $request = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->timeout(60);

        if (! $this->verifySsl) {
            $request = $request->withoutVerifying();
        }

        return $request->get($url, $params);
    }

    /**
     * Wipe old store data cleanly.
     */
    public function cleanOldData(array $entities = ['categories', 'products', 'orders', 'customers']): array
    {
        $summary = [];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        if (in_array('orders', $entities)) {
            $orderItemCount = OrderItem::count();
            $orderCount = Order::count();
            OrderItem::truncate();
            Order::truncate();
            $summary['orders_deleted'] = $orderCount;
            $summary['order_items_deleted'] = $orderItemCount;
        }

        if (in_array('products', $entities)) {
            $productCount = Product::count();
            if (DB::getSchemaBuilder()->hasTable('category_product')) {
                DB::table('category_product')->truncate();
            }
            Product::truncate();
            $summary['products_deleted'] = $productCount;
        }

        if (in_array('categories', $entities)) {
            $catCount = Category::count();
            Category::truncate();
            $summary['categories_deleted'] = $catCount;
        }

        if (in_array('customers', $entities)) {
            $custCount = Customer::count();
            Customer::truncate();
            $summary['customers_deleted'] = $custCount;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        return $summary;
    }

    /* -------------------------------------------------------------------------- */
    /*                              1. CATEGORIES                                 */
    /* -------------------------------------------------------------------------- */

    public function fetchCategories(int $page = 1, int $perPage = 100, ?string $lang = null)
    {
        $params = [
            'per_page' => $perPage,
            'page' => $page,
            'hide_empty' => false,
            'orderby' => 'id',
            'order' => 'asc',
        ];

        if ($lang) {
            $params['lang'] = $lang;
        }

        return $this->makeRequest('products/categories', $params);
    }

    public function importCategoriesChunk(int $page = 1, int $perPage = 100): array
    {
        // Fetch both English and Arabic categories for complete bilingual data
        $responseEn = $this->fetchCategories($page, $perPage, 'en');
        $responseAr = $this->fetchCategories($page, $perPage, 'ar');

        if (! $responseEn->successful() && ! $responseAr->successful()) {
            throw new \Exception("Failed to fetch categories: " . ($responseEn->body() ?: $responseAr->body()));
        }

        $itemsEn = $responseEn->successful() ? ($responseEn->json() ?? []) : [];
        $itemsAr = $responseAr->successful() ? ($responseAr->json() ?? []) : [];

        $arById = [];
        foreach ($itemsAr as $arItem) {
            $arById[$arItem['id']] = $arItem;
        }

        $enById = [];
        foreach ($itemsEn as $enItem) {
            $enById[$enItem['id']] = $enItem;
        }

        $totalPages = (int) ($responseEn->header('X-WP-TotalPages') ?: $responseAr->header('X-WP-TotalPages', 1));
        $totalCount = (int) ($responseEn->header('X-WP-Total') ?: $responseAr->header('X-WP-Total', count($itemsEn)));
        $imported = 0;

        // Process English list as primary, matching paired Arabic translations
        $processedArIds = [];

        foreach ($itemsEn as $enItem) {
            $sourceId = (int) ($enItem['id'] ?? 0);
            $arId = (int) ($enItem['translations']['ar'] ?? 0);

            $arItem = ($arId > 0 && isset($arById[$arId])) ? $arById[$arId] : null;
            if ($arId > 0) {
                $processedArIds[] = $arId;
            }

            $enName = trim(html_entity_decode((string) ($enItem['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $arName = $arItem
                ? trim(html_entity_decode((string) ($arItem['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : $enName;

            $enSlug = (string) ($enItem['slug'] ?? '');
            $arSlug = $arItem ? (string) ($arItem['slug'] ?? '') : $enSlug;

            $enDesc = trim(html_entity_decode((string) ($enItem['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $arDesc = $arItem ? trim(html_entity_decode((string) ($arItem['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : $enDesc;

            $image = ! empty($enItem['image']['src']) ? $enItem['image']['src'] : (! empty($arItem['image']['src']) ? $arItem['image']['src'] : null);

            $category = Category::where('source_id', $sourceId)
                ->orWhere(function ($q) use ($arId) {
                    if ($arId > 0) {
                        $q->where('source_id', $arId);
                    }
                })->first();

            if (! $category) {
                $category = new Category();
                $category->source_id = $sourceId;
            }

            $category->slug = $enSlug ?: ('cat-' . $sourceId);
            $category->slug_ar = urldecode($arSlug) ?: $category->slug;
            $category->name = [
                'en' => $enName,
                'ar' => $arName,
            ];
            if (filled($enDesc) || filled($arDesc)) {
                $category->description = [
                    'en' => $enDesc,
                    'ar' => $arDesc,
                ];
            }
            if ($image) {
                if (!empty($category->cat_image) && !str_starts_with($category->cat_image, 'http')) {
                    // Preserve existing local storage image
                } else {
                    $category->cat_image = $this->resolveLocalImagePath($image, 'categories');
                }
            }
            $category->is_visible = true;
            $category->meta_data = [
                'wp_parent_id' => $enItem['parent'] ?? ($arItem['parent'] ?? 0),
                'wp_ar_id' => $arId,
                'wp_en_id' => $sourceId,
                'count' => $enItem['count'] ?? ($arItem['count'] ?? 0),
            ];

            $category->save();
            $imported++;
        }

        // Also process any Arabic categories that had no English pair
        foreach ($itemsAr as $arItem) {
            $arSourceId = (int) ($arItem['id'] ?? 0);
            if (in_array($arSourceId, $processedArIds)) {
                continue;
            }

            $arName = trim(html_entity_decode((string) ($arItem['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $arSlug = (string) ($arItem['slug'] ?? '');
            $arDesc = trim(html_entity_decode((string) ($arItem['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $image = ! empty($arItem['image']['src']) ? $arItem['image']['src'] : null;

            $category = Category::where('source_id', $arSourceId)->first();
            if (! $category) {
                $category = new Category();
                $category->source_id = $arSourceId;
            }

            $category->slug = urldecode($arSlug) ?: ('cat-' . $arSourceId);
            $category->slug_ar = urldecode($arSlug);
            $category->name = [
                'en' => $arName,
                'ar' => $arName,
            ];
            if (filled($arDesc)) {
                $category->description = [
                    'en' => $arDesc,
                    'ar' => $arDesc,
                ];
            }
            if ($image) {
                if (!empty($category->cat_image) && !str_starts_with($category->cat_image, 'http')) {
                    // Preserve existing local storage image
                } else {
                    $category->cat_image = $this->resolveLocalImagePath($image, 'categories');
                }
            }
            $category->is_visible = true;
            $category->meta_data = [
                'wp_parent_id' => $arItem['parent'] ?? 0,
                'wp_ar_id' => $arSourceId,
                'count' => $arItem['count'] ?? 0,
            ];

            $category->save();
            $imported++;
        }

        // Link parent categories based on wp_parent_id
        if ($page >= $totalPages) {
            $this->resolveCategoryParentHierarchy();
        }

        return [
            'imported' => $imported,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalCount,
        ];
    }

    protected function resolveCategoryParentHierarchy(): void
    {
        $categories = Category::whereNotNull('source_id')->get();
        $sourceMap = $categories->pluck('id', 'source_id');

        foreach ($categories as $cat) {
            $meta = $cat->meta_data;
            $wpParentId = $meta['wp_parent_id'] ?? 0;
            if ($wpParentId > 0 && isset($sourceMap[$wpParentId])) {
                $cat->parent_id = $sourceMap[$wpParentId];
                $cat->save();
            }
        }
    }

    /* -------------------------------------------------------------------------- */
    /*                              2. CUSTOMERS                                  */
    /* -------------------------------------------------------------------------- */

    public function fetchCustomers(int $page = 1, int $perPage = 100)
    {
        return $this->makeRequest('customers', [
            'per_page' => $perPage,
            'page' => $page,
            'orderby' => 'id',
            'order' => 'asc',
        ]);
    }

    public function importCustomersChunk(int $page = 1, int $perPage = 100): array
    {
        $response = $this->fetchCustomers($page, $perPage);

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch customers: " . $response->body());
        }

        $items = $response->json() ?? [];
        $totalPages = (int) $response->header('X-WP-TotalPages', 1);
        $totalCount = (int) $response->header('X-WP-Total', count($items));
        $imported = 0;

        foreach ($items as $item) {
            $sourceId = (int) ($item['id'] ?? 0);
            $email = trim((string) ($item['email'] ?? $item['billing']['email'] ?? ''));
            if (empty($email)) {
                $email = "customer_{$sourceId}@grassflorist.local";
            }

            $firstName = $item['first_name'] ?: ($item['billing']['first_name'] ?? null);
            $lastName = $item['last_name'] ?: ($item['billing']['last_name'] ?? null);
            $phone = $item['billing']['phone'] ?? ($item['shipping']['phone'] ?? null);
            $address = $item['billing']['address_1'] ?? ($item['shipping']['address_1'] ?? null);
            $city = $item['billing']['city'] ?? ($item['shipping']['city'] ?? null);
            $state = $item['billing']['state'] ?? ($item['shipping']['state'] ?? null);
            $zip = $item['billing']['postcode'] ?? ($item['shipping']['postcode'] ?? null);
            $country = $item['billing']['country'] ?? ($item['shipping']['country'] ?? 'SA');

            $registeredAt = null;
            if (! empty($item['date_created'])) {
                try {
                    $registeredAt = Carbon::parse($item['date_created']);
                } catch (\Exception $e) {
                }
            }

            $customer = Customer::where('source_id', $sourceId)->first()
                ?? Customer::where('email', $email)->first();

            if (! $customer) {
                $customer = new Customer();
                $customer->source_id = $sourceId;
            }

            $customer->username = $item['username'] ?? null;
            $customer->first_name = $firstName;
            $customer->last_name = $lastName;
            $customer->email = $email;
            $customer->phone = $phone;
            $customer->address = $address;
            $customer->city = $city;
            $customer->state = $state;
            $customer->zip_code = $zip;
            $customer->country = $country ?: 'SA';
            $customer->registered_at = $registeredAt;
            $customer->meta_data = [
                'role' => $item['role'] ?? 'customer',
                'is_paying_customer' => $item['is_paying_customer'] ?? false,
                'avatar_url' => $item['avatar_url'] ?? null,
                'billing' => $item['billing'] ?? [],
                'shipping' => $item['shipping'] ?? [],
            ];

            $customer->save();
            $imported++;
        }

        return [
            'imported' => $imported,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalCount,
        ];
    }

    /* -------------------------------------------------------------------------- */
    /*                              3. ORDERS & ITEMS                             */
    /* -------------------------------------------------------------------------- */

    public function fetchOrders(int $page = 1, int $perPage = 50)
    {
        return $this->makeRequest('orders', [
            'per_page' => $perPage,
            'page' => $page,
            'status' => 'any',
            'orderby' => 'id',
            'order' => 'asc',
        ]);
    }

    public function importOrdersChunk(int $page = 1, int $perPage = 50): array
    {
        $response = $this->fetchOrders($page, $perPage);

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch orders: " . $response->body());
        }

        $items = $response->json() ?? [];
        $totalPages = (int) $response->header('X-WP-TotalPages', 1);
        $totalCount = (int) $response->header('X-WP-Total', count($items));
        $imported = 0;

        foreach ($items as $item) {
            $sourceId = (int) ($item['id'] ?? 0);
            $orderNumber = (string) ($item['number'] ?? $item['id']);

            // Parse Meta Data Key-Value map
            $metaMap = [];
            foreach (($item['meta_data'] ?? []) as $meta) {
                if (isset($meta['key'])) {
                    $metaMap[$meta['key']] = $meta['value'];
                }
            }

            // Florist Special Custom Fields Extraction
            $deliveryDate = $this->parseDateValue($metaMap['_delivery_date'] ?? $metaMap['delivery_date'] ?? null);
            $deliveryTime = $metaMap['_delivery_time'] ?? $metaMap['delivery_time'] ?? null;
            $deliveryMessage = $metaMap['_delivery_message'] ?? $metaMap['delivery_message'] ?? $metaMap['gift_message'] ?? null;
            $senderName = $metaMap['_sender_name'] ?? $metaMap['sender_name'] ?? null;
            $songLink = $metaMap['_song_link'] ?? $metaMap['song_link'] ?? null;
            $locationLink = $metaMap['_shipping_my_address_link'] ?? $metaMap['location_link'] ?? $metaMap['_location_link'] ?? null;

            $recipientName = trim(($metaMap['_shipping_firstname_custom'] ?? '') . ' ' . ($metaMap['_shipping_lastname_custom'] ?? ''));
            if (empty($recipientName)) {
                $recipientName = trim(($item['shipping']['first_name'] ?? '') . ' ' . ($item['shipping']['last_name'] ?? ''));
            }

            $recipientPhone = $metaMap['_shipping_country_code_phone'] ?? '';
            $phonePart = $metaMap['_shipping_phone_1'] ?? $metaMap['_shipping_phone'] ?? ($item['shipping']['phone'] ?? null);
            if ($phonePart) {
                $recipientPhone = trim($recipientPhone . ' ' . $phonePart);
            }

            $orderLang = $metaMap['wpml_language'] ?? $metaMap['_order_language'] ?? $metaMap['language'] ?? 'ar';

            // Dates
            $orderedAt = null;
            if (! empty($item['date_created'])) {
                try {
                    $orderedAt = Carbon::parse($item['date_created']);
                } catch (\Exception $e) {
                }
            }

            $datePaid = null;
            if (! empty($item['date_paid'])) {
                try {
                    $datePaid = Carbon::parse($item['date_paid']);
                } catch (\Exception $e) {
                }
            }

            // Customer link
            $wpCustomerId = (int) ($item['customer_id'] ?? 0);
            $customerEmail = $item['billing']['email'] ?? "order_{$sourceId}@grassflorist.local";
            $userId = null;

            if ($wpCustomerId > 0) {
                $user = Customer::where('source_id', $wpCustomerId)->first();
                $userId = $user?->id;
            }
            if (! $userId && $customerEmail) {
                $user = Customer::where('email', $customerEmail)->first();
                $userId = $user?->id;
            }

            // Totals
            $total = (float) ($item['total'] ?? 0);
            $shippingTotal = (float) ($item['shipping_total'] ?? 0);
            $discountTotal = (float) ($item['discount_total'] ?? 0);
            $taxTotal = (float) ($item['total_tax'] ?? 0);
            $subtotal = max(0, $total - $shippingTotal - $taxTotal + $discountTotal);

            // Payment status
            $paymentMethod = $item['payment_method'] ?? 'cod';
            $wpStatus = strtolower((string) ($item['status'] ?? 'pending'));
            $paymentStatus = in_array($wpStatus, ['completed', 'processing']) && ! str_contains($paymentMethod, 'cod')
                ? 'paid'
                : 'pending';

            $order = Order::where('source_id', $sourceId)->first()
                ?? Order::where('order_number', $orderNumber)->first();

            if (! $order) {
                $order = new Order();
                $order->source_id = $sourceId;
            }

            $order->order_number = $orderNumber;
            $order->user_id = $userId;
            $order->email = $customerEmail;
            $order->first_name = $item['billing']['first_name'] ?? '';
            $order->last_name = $item['billing']['last_name'] ?? '';
            $order->customer_phone = $item['billing']['phone'] ?? null;
            $order->subtotal = $subtotal;
            $order->shipping_amount = $shippingTotal;
            $order->discount_amount = $discountTotal;
            $order->tax_amount = $taxTotal;
            $order->total_amount = $total;
            $order->currency = $item['currency'] ?? 'SAR';
            $order->payment_method = $paymentMethod;
            $order->payment_status = $paymentStatus;
            $order->status = $wpStatus;
            $order->notes = $item['customer_note'] ?? null;
            $order->address = $item['shipping']['address_1'] ?? ($item['billing']['address_1'] ?? null);
            $order->city = $item['shipping']['city'] ?? ($item['billing']['city'] ?? null);
            $order->state = $item['shipping']['state'] ?? ($item['billing']['state'] ?? null);
            $order->zip_code = $item['shipping']['postcode'] ?? ($item['billing']['postcode'] ?? null);
            $order->country = $item['shipping']['country'] ?? ($item['billing']['country'] ?? 'SA');

            // Florist Fields
            $order->delivery_date = $deliveryDate;
            $order->delivery_time = $deliveryTime;
            $order->delivery_message = $deliveryMessage;
            $order->sender_name = $senderName;
            $order->recipient_name = $recipientName;
            $order->recipient_phone = $recipientPhone;
            $order->song_link = $songLink;
            $order->location_link = $locationLink;
            $order->order_language = $orderLang;
            $order->ordered_at = $orderedAt;
            $order->date_paid = $datePaid;
            $order->meta_data = $metaMap;

            $order->save();

            // Line Items
            $this->syncOrderLineItems($order, $item['line_items'] ?? []);

            $imported++;
        }

        return [
            'imported' => $imported,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalCount,
        ];
    }

    protected function syncOrderLineItems(Order $order, array $lineItems): void
    {
        $existingItemIds = [];

        foreach ($lineItems as $item) {
            $sourceItemId = (int) ($item['id'] ?? 0);
            $sourceProductId = (int) ($item['product_id'] ?? 0);
            $sourceVarId = (int) ($item['variation_id'] ?? 0);
            $productName = html_entity_decode((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $sku = $item['sku'] ?? null;
            $qty = (int) ($item['quantity'] ?? 1);
            $total = (float) ($item['total'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? $total);
            $tax = (float) ($item['total_tax'] ?? 0);
            $price = (float) ($item['price'] ?? ($qty > 0 ? $total / $qty : 0));

            $localProduct = null;
            if ($sourceProductId > 0) {
                $localProduct = Product::where('source_id', $sourceProductId)->first();
            }
            if (! $localProduct && $sku) {
                $localProduct = Product::where('sku', $sku)->first();
            }

            $orderItem = OrderItem::where('order_id', $order->id)
                ->where(function ($q) use ($sourceItemId, $sourceProductId, $productName) {
                    if ($sourceItemId > 0) {
                        $q->where('source_item_id', $sourceItemId);
                    } else {
                        $q->where('source_product_id', $sourceProductId)
                          ->where('product_name', $productName);
                    }
                })->first();

            if (! $orderItem) {
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
            }

            $orderItem->source_item_id = $sourceItemId;
            $orderItem->source_product_id = $sourceProductId;
            $orderItem->source_variation_id = $sourceVarId ?: null;
            $orderItem->product_id = $localProduct?->id;
            $orderItem->product_name = $productName;
            $orderItem->product_image = $localProduct?->image;
            $orderItem->sku = $sku;
            $orderItem->quantity = $qty;
            $orderItem->unit_price = $price;
            $orderItem->price = $price;
            $orderItem->subtotal = $subtotal;
            $orderItem->tax = $tax;
            $orderItem->total = $total;
            $orderItem->meta_data = $item['meta_data'] ?? [];

            $orderItem->save();
            $existingItemIds[] = $orderItem->id;
        }

        // Cleanup removed items
        if (! empty($existingItemIds)) {
            OrderItem::where('order_id', $order->id)->whereNotIn('id', $existingItemIds)->delete();
        }
    }

    protected function parseDateValue(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim($raw);

        // Handle DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }

        // Handle standard YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected ?array $cachedArabicProducts = null;

    public function getArabicProductsMap(): array
    {
        if ($this->cachedArabicProducts !== null) {
            return $this->cachedArabicProducts;
        }

        $map = [];
        $totalPages = 1;

        for ($p = 1; $p <= $totalPages; $p++) {
            $res = $this->fetchProducts($p, 100, 'ar');
            if (! $res->successful()) {
                break;
            }
            $totalPages = (int) $res->header('X-WP-TotalPages', 1);
            $items = $res->json() ?? [];
            if (empty($items)) {
                break;
            }
            foreach ($items as $item) {
                $map[$item['id']] = $item;
            }
        }

        $this->cachedArabicProducts = $map;
        return $this->cachedArabicProducts;
    }

    public function fetchProducts(int $page = 1, int $perPage = 50, ?string $lang = null)
    {
        $params = [
            'per_page' => $perPage,
            'page' => $page,
            'status' => 'any',
            'orderby' => 'id',
            'order' => 'asc',
        ];

        if ($lang) {
            $params['lang'] = $lang;
        }

        return $this->makeRequest('products', $params);
    }

    public function importProductsChunk(int $page = 1, int $perPage = 50): array
    {
        $responseEn = $this->fetchProducts($page, $perPage, 'en');

        if (! $responseEn->successful()) {
            throw new \Exception("Failed to fetch products: " . $responseEn->body());
        }

        $itemsEn = $responseEn->json() ?? [];
        $arById = $this->getArabicProductsMap();

        $totalPages = (int) $responseEn->header('X-WP-TotalPages', 1);
        $totalCount = (int) $responseEn->header('X-WP-Total', count($itemsEn));
        $imported = 0;
        $processedArIds = [];

        foreach ($itemsEn as $enItem) {
            $sourceId = (int) ($enItem['id'] ?? 0);
            $arId = (int) ($enItem['translations']['ar'] ?? 0);

            $arItem = ($arId > 0 && isset($arById[$arId])) ? $arById[$arId] : null;
            if ($arId > 0) {
                $processedArIds[] = $arId;
            }

            $rawEnName = trim(html_entity_decode((string) ($enItem['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $rawArName = $arItem
                ? trim(html_entity_decode((string) ($arItem['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : $rawEnName;

            $rawEnSlug = (string) ($enItem['slug'] ?? '');
            $rawArSlug = $arItem ? (string) ($arItem['slug'] ?? '') : $rawEnSlug;
            $decodedArSlug = urldecode($rawArSlug);

            $rawEnDesc = trim((string) ($enItem['description'] ?? ''));
            $rawArDesc = $arItem ? trim((string) ($arItem['description'] ?? '')) : $rawEnDesc;

            $cleanShortEn = trim(html_entity_decode(strip_tags((string) ($enItem['short_description'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $cleanShortAr = $arItem ? trim(html_entity_decode(strip_tags((string) ($arItem['short_description'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : $cleanShortEn;

            $nameTranslations = [
                'en' => $rawEnName,
                'ar' => $rawArName,
            ];

            $finalEnDesc = filled($rawEnDesc) ? $rawEnDesc : $cleanShortEn;
            $finalArDesc = filled($rawArDesc) ? $rawArDesc : $cleanShortAr;

            $images = ! empty($enItem['images']) ? $enItem['images'] : ($arItem['images'] ?? []);
            $featuredImage = ! empty($images[0]['src']) ? $images[0]['src'] : null;
            $gallery = array_values(array_filter(array_map(fn ($img) => $img['src'] ?? null, $images)));

            $price = (float) ($enItem['price'] ?? ($arItem['price'] ?? 0));
            $regularPrice = (float) ($enItem['regular_price'] ?? ($arItem['regular_price'] ?? $price));
            $sku = $enItem['sku'] ?: ($arItem['sku'] ?? ('GF-WP-' . $sourceId));
            $qty = $enItem['manage_stock'] ? (int) ($enItem['stock_quantity'] ?? 0) : 100;

            $product = Product::where('source_id', $sourceId)
                ->orWhere(function ($q) use ($arId) {
                    if ($arId > 0) {
                        $q->where('source_id', $arId);
                    }
                })
                ->orWhere('sku', $sku)
                ->first();

            if (! $product) {
                $product = new Product();
                $product->source_id = $sourceId;
            }

            $product->name = $nameTranslations;
            $product->slug = $this->getUniqueProductSlug($rawEnSlug ?: ('product-' . $sourceId), $product->id);
            $product->slug_ar = $decodedArSlug ?: $product->slug;
            $product->sku = $sku;
            $product->price = $price;
            $product->mrp = $regularPrice;
            $product->quantity = $qty;
            if (filled($finalEnDesc) || filled($finalArDesc)) {
                $product->description = [
                    'en' => $finalEnDesc,
                    'ar' => $finalArDesc,
                ];
            }
            if (filled($cleanShortEn) || filled($cleanShortAr)) {
                $product->sub_title = [
                    'en' => $cleanShortEn,
                    'ar' => $cleanShortAr,
                ];
            }
            if ($featuredImage) {
                if (!empty($product->image) && !str_starts_with($product->image, 'http')) {
                    // Preserve existing local storage image
                } else {
                    $product->image = $this->resolveLocalImagePath($featuredImage, 'products');
                }
            }
            if (!empty($gallery)) {
                $product->gallery = array_map(fn ($g) => $this->resolveLocalImagePath($g, 'products/gallery'), $gallery);
            }
            $product->is_visible = ($enItem['status'] ?? 'publish') === 'publish';
            $product->type = 'Other';
            $product->meta_data = [
                'type' => $enItem['type'] ?? 'simple',
                'featured' => $enItem['featured'] ?? false,
                'weight' => $enItem['weight'] ?? null,
                'dimensions' => $enItem['dimensions'] ?? [],
                'categories' => $enItem['categories'] ?? ($arItem['categories'] ?? []),
                'tags' => $enItem['tags'] ?? [],
                'wp_ar_id' => $arId,
                'wp_en_id' => $sourceId,
            ];

            $product->save();

            // Link categories (both en and ar category IDs)
            $wpCatIds = array_map(fn ($c) => (int) $c['id'], array_merge($enItem['categories'] ?? [], $arItem['categories'] ?? []));
            if (! empty($wpCatIds)) {
                $localCatIds = Category::whereIn('source_id', $wpCatIds)->pluck('id')->toArray();
                if (! empty($localCatIds)) {
                    $product->category_id = $localCatIds;
                    $product->save();
                }
            }

            $imported++;
        }

        // Process standalone Arabic products not paired (only on the final page)
        if ($page >= $totalPages) {
            foreach ($arById as $arItem) {
                $arSourceId = (int) ($arItem['id'] ?? 0);
                if (in_array($arSourceId, $processedArIds)) {
                    continue;
                }

            $rawArName = trim(html_entity_decode((string) ($arItem['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $rawArSlug = (string) ($arItem['slug'] ?? '');
            $decodedArSlug = urldecode($rawArSlug);
            $rawArDesc = trim((string) ($arItem['description'] ?? ''));
            $rawArShortDesc = trim((string) ($arItem['short_description'] ?? ''));
            $finalArDesc = filled($rawArDesc) ? $rawArDesc : $rawArShortDesc;

            $images = $arItem['images'] ?? [];
            $featuredImage = ! empty($images[0]['src']) ? $images[0]['src'] : null;
            $gallery = array_values(array_filter(array_map(fn ($img) => $img['src'] ?? null, $images)));

            $price = (float) ($arItem['price'] ?? 0);
            $regularPrice = (float) ($arItem['regular_price'] ?? $price);
            $sku = $arItem['sku'] ?: ('GF-WP-' . $arSourceId);
            $qty = $arItem['manage_stock'] ? (int) ($arItem['stock_quantity'] ?? 0) : 100;

            $product = Product::where('source_id', $arSourceId)
                ->orWhere('sku', $sku)
                ->first();

            if (! $product) {
                $product = new Product();
                $product->source_id = $arSourceId;
            }

            $product->name = ['en' => $rawArName, 'ar' => $rawArName];
            $product->slug = $this->getUniqueProductSlug($decodedArSlug ?: ('product-' . $arSourceId), $product->id);
            $product->slug_ar = $decodedArSlug;
            $product->sku = $sku;
            $product->price = $price;
            $product->mrp = $regularPrice;
            $product->quantity = $qty;
            if (filled($finalArDesc)) {
                $product->description = ['en' => $finalArDesc, 'ar' => $finalArDesc];
            }
            if (filled($rawArShortDesc)) {
                $product->sub_title = ['en' => $rawArShortDesc, 'ar' => $rawArShortDesc];
            }
            if ($featuredImage) {
                $product->image = $featuredImage;
            }
            $product->gallery = $gallery;
            $product->is_visible = ($arItem['status'] ?? 'publish') === 'publish';
            $product->type = 'Other';
            $product->meta_data = [
                'type' => $arItem['type'] ?? 'simple',
                'featured' => $arItem['featured'] ?? false,
                'weight' => $arItem['weight'] ?? null,
                'dimensions' => $arItem['dimensions'] ?? [],
                'categories' => $arItem['categories'] ?? [],
                'tags' => $arItem['tags'] ?? [],
                'wp_ar_id' => $arSourceId,
            ];

            $product->save();

            $wpCatIds = array_map(fn ($c) => (int) $c['id'], $arItem['categories'] ?? []);
            if (! empty($wpCatIds)) {
                $localCatIds = Category::whereIn('source_id', $wpCatIds)->pluck('id')->toArray();
                if (! empty($localCatIds)) {
                    $product->category_id = $localCatIds;
                    $product->save();
                }
            }

            $imported++;
        }
    }

        return [
            'imported' => $imported,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalCount,
        ];
    }

    protected function getUniqueProductSlug(string $slug, ?int $ignoreId = null): string
    {
        $originalSlug = $slug ?: 'product';
        $currentSlug = $originalSlug;
        $counter = 1;

        while (Product::where('slug', $currentSlug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $currentSlug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $currentSlug;
    }

    /**
     * Import Blog Categories from WordPress REST API (EN + AR).
     */
    public function importBlogCategories(): array
    {
        $siteUrl = rtrim(config('services.woocommerce.url', 'https://grassflorist.com'), '/');

        // Fetch Arabic (Default) WP Categories
        $arUrl = "{$siteUrl}/wp-json/wp/v2/categories?per_page=100";
        $enUrl = "{$siteUrl}/en/wp-json/wp/v2/categories?per_page=100";

        $resAr = Http::withoutVerifying()->timeout(30)->get($arUrl);
        $resEn = Http::withoutVerifying()->timeout(30)->get($enUrl);

        $catsAr = $resAr->successful() ? $resAr->json() : [];
        $catsEn = $resEn->successful() ? $resEn->json() : [];

        $arById = [];
        foreach ($catsAr as $c) {
            $arById[(int) ($c['id'] ?? 0)] = $c;
        }

        $imported = 0;
        $processedArIds = [];

        // Process English list with translation pairing
        foreach ($catsEn as $enCat) {
            $sourceId = (int) ($enCat['id'] ?? 0);
            $arId = (int) ($enCat['translations']['ar'] ?? 0);

            $arCat = ($arId > 0 && isset($arById[$arId])) ? $arById[$arId] : null;
            if ($arId > 0) {
                $processedArIds[] = $arId;
            }

            $rawEnName = trim(html_entity_decode((string) ($enCat['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $rawArName = $arCat
                ? trim(html_entity_decode((string) ($arCat['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : $rawEnName;

            $rawEnSlug = (string) ($enCat['slug'] ?? '');
            $rawArSlug = $arCat ? (string) ($arCat['slug'] ?? '') : $rawEnSlug;
            $decodedArSlug = urldecode($rawArSlug);

            $rawEnDesc = trim((string) ($enCat['description'] ?? ''));
            $rawArDesc = $arCat ? trim((string) ($arCat['description'] ?? '')) : $rawEnDesc;

            $yoastEn = $enCat['yoast_head_json'] ?? [];
            $yoastAr = $arCat['yoast_head_json'] ?? $yoastEn;

            $metaTitleEn = $yoastEn['title'] ?? ($yoastEn['og_title'] ?? $rawEnName);
            $metaTitleAr = $yoastAr['title'] ?? ($yoastAr['og_title'] ?? $rawArName);

            $metaDescEn = $yoastEn['description'] ?? ($yoastEn['og_description'] ?? $rawEnDesc);
            $metaDescAr = $yoastAr['description'] ?? ($yoastAr['og_description'] ?? $rawArDesc);

            $cat = CmsCategory::where('source_id', $sourceId)
                ->orWhere(function ($q) use ($arId) {
                    if ($arId > 0) {
                        $q->where('source_id', $arId);
                    }
                })
                ->orWhere('slug', $rawEnSlug)
                ->first();

            if (! $cat) {
                $cat = new CmsCategory();
                $cat->source_id = $sourceId;
            }

            $cat->name = ['en' => $rawEnName, 'ar' => $rawArName];
            $cat->slug = $rawEnSlug ?: Str::slug($rawEnName);
            $cat->slug_ar = $decodedArSlug ?: $cat->slug;
            $cat->content = ['en' => $rawEnDesc, 'ar' => $rawArDesc];
            $cat->meta_tag_title = ['en' => $metaTitleEn, 'ar' => $metaTitleAr];
            $cat->meta_tag_description = ['en' => $metaDescEn, 'ar' => $metaDescAr];
            $cat->meta_tag_keywords = ['en' => $rawEnName, 'ar' => $rawArName];
            $cat->is_active = true;
            $cat->save();

            $imported++;
        }

        // Process leftover Arabic categories
        foreach ($catsAr as $arCat) {
            $arSourceId = (int) ($arCat['id'] ?? 0);
            if (in_array($arSourceId, $processedArIds)) {
                continue;
            }

            $rawArName = trim(html_entity_decode((string) ($arCat['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $decodedArSlug = urldecode((string) ($arCat['slug'] ?? ''));
            $rawArDesc = trim((string) ($arCat['description'] ?? ''));

            $yoastAr = $arCat['yoast_head_json'] ?? [];
            $metaTitleAr = $yoastAr['title'] ?? ($yoastAr['og_title'] ?? $rawArName);
            $metaDescAr = $yoastAr['description'] ?? ($yoastAr['og_description'] ?? $rawArDesc);

            $cat = CmsCategory::where('source_id', $arSourceId)
                ->orWhere('slug', $decodedArSlug)
                ->first();

            if (! $cat) {
                $cat = new CmsCategory();
                $cat->source_id = $arSourceId;
            }

            $cat->name = ['en' => $rawArName, 'ar' => $rawArName];
            $cat->slug = Str::slug($rawArName) ?: ('blog-category-' . $arSourceId);
            $cat->slug_ar = $decodedArSlug ?: $cat->slug;
            $cat->content = ['en' => $rawArDesc, 'ar' => $rawArDesc];
            $cat->meta_tag_title = ['en' => $metaTitleAr, 'ar' => $metaTitleAr];
            $cat->meta_tag_description = ['en' => $metaDescAr, 'ar' => $metaDescAr];
            $cat->meta_tag_keywords = ['en' => $rawArName, 'ar' => $rawArName];
            $cat->is_active = true;
            $cat->save();

            $imported++;
        }

        return [
            'imported' => $imported,
            'total_categories' => count($catsAr) + count($catsEn),
        ];
    }

    /**
     * Import Blog Posts from WordPress REST API (EN + AR).
     */
    public function importBlogPosts(): array
    {
        $siteUrl = rtrim(config('services.woocommerce.url', 'https://grassflorist.com'), '/');

        // Fetch Arabic (Default) and English WP Posts with embedded media
        $arUrl = "{$siteUrl}/wp-json/wp/v2/posts?per_page=100&_embed=1";
        $enUrl = "{$siteUrl}/en/wp-json/wp/v2/posts?per_page=100&_embed=1";

        $resAr = Http::withoutVerifying()->timeout(30)->get($arUrl);
        $resEn = Http::withoutVerifying()->timeout(30)->get($enUrl);

        $postsAr = $resAr->successful() ? $resAr->json() : [];
        $postsEn = $resEn->successful() ? $resEn->json() : [];

        $arById = [];
        foreach ($postsAr as $p) {
            $arById[(int) ($p['id'] ?? 0)] = $p;
        }

        $imported = 0;
        $processedArIds = [];

        foreach ($postsEn as $enPost) {
            $sourceId = (int) ($enPost['id'] ?? 0);
            $arId = (int) ($enPost['translations']['ar'] ?? 0);

            $arPost = ($arId > 0 && isset($arById[$arId])) ? $arById[$arId] : null;
            if ($arId > 0) {
                $processedArIds[] = $arId;
            }

            $rawEnTitle = trim(html_entity_decode((string) ($enPost['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $rawArTitle = $arPost
                ? trim(html_entity_decode((string) ($arPost['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : $rawEnTitle;

            $rawEnSlug = (string) ($enPost['slug'] ?? '');
            $rawArSlug = $arPost ? (string) ($arPost['slug'] ?? '') : $rawEnSlug;
            $decodedArSlug = urldecode($rawArSlug);

            $rawEnContent = trim((string) ($enPost['content']['rendered'] ?? ''));
            $rawArContent = $arPost ? trim((string) ($arPost['content']['rendered'] ?? '')) : $rawEnContent;

            $rawEnExcerpt = trim(strip_tags((string) ($enPost['excerpt']['rendered'] ?? '')));
            $rawArExcerpt = $arPost ? trim(strip_tags((string) ($arPost['excerpt']['rendered'] ?? ''))) : $rawEnExcerpt;

            // Featured Image
            $featuredImage = null;
            if (! empty($enPost['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                $featuredImage = $enPost['_embedded']['wp:featuredmedia'][0]['source_url'];
            } elseif ($arPost && ! empty($arPost['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                $featuredImage = $arPost['_embedded']['wp:featuredmedia'][0]['source_url'];
            }

            // Category Matching
            $wpCatIds = array_map('intval', $enPost['categories'] ?? ($arPost['categories'] ?? []));
            $cmsCategoryId = null;
            if (! empty($wpCatIds)) {
                $matchedCat = CmsCategory::whereIn('source_id', $wpCatIds)->first();
                $cmsCategoryId = $matchedCat?->id;
            }

            // SEO Metadata
            $yoastEn = $enPost['yoast_head_json'] ?? [];
            $yoastAr = $arPost['yoast_head_json'] ?? $yoastEn;

            $metaTitleEn = $yoastEn['title'] ?? ($yoastEn['og_title'] ?? $rawEnTitle);
            $metaTitleAr = $yoastAr['title'] ?? ($yoastAr['og_title'] ?? $rawArTitle);

            $metaDescEn = $yoastEn['description'] ?? ($yoastEn['og_description'] ?? $rawEnExcerpt);
            $metaDescAr = $yoastAr['description'] ?? ($yoastAr['og_description'] ?? $rawArExcerpt);

            $post = CmsPost::where('source_id', $sourceId)
                ->orWhere(function ($q) use ($arId) {
                    if ($arId > 0) {
                        $q->where('source_id', $arId);
                    }
                })
                ->orWhere('slug', $rawEnSlug)
                ->first();

            if (! $post) {
                $post = new CmsPost();
                $post->source_id = $sourceId;
            }

            $post->title = ['en' => $rawEnTitle, 'ar' => $rawArTitle];
            $post->slug = $rawEnSlug ?: Str::slug($rawEnTitle);
            $post->slug_ar = $decodedArSlug ?: $post->slug;
            $post->short_description = ['en' => $rawEnExcerpt, 'ar' => $rawArExcerpt];
            $post->content = ['en' => $rawEnContent, 'ar' => $rawArContent];
            $post->cms_category_id = $cmsCategoryId;
            if ($featuredImage) {
                $post->image = $featuredImage;
            }
            $post->meta_title = ['en' => $metaTitleEn, 'ar' => $metaTitleAr];
            $post->meta_description = ['en' => $metaDescEn, 'ar' => $metaDescAr];
            $post->meta_keywords = ['en' => $rawEnTitle, 'ar' => $rawArTitle];
            $post->is_active = ($enPost['status'] ?? 'publish') === 'publish';
            $post->published_at = isset($enPost['date']) ? Carbon::parse($enPost['date']) : now();
            $post->save();

            $imported++;
        }

        // Process leftover Arabic posts
        foreach ($postsAr as $arPost) {
            $arSourceId = (int) ($arPost['id'] ?? 0);
            if (in_array($arSourceId, $processedArIds)) {
                continue;
            }

            $rawArTitle = trim(html_entity_decode((string) ($arPost['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $decodedArSlug = urldecode((string) ($arPost['slug'] ?? ''));
            $rawArContent = trim((string) ($arPost['content']['rendered'] ?? ''));
            $rawArExcerpt = trim(strip_tags((string) ($arPost['excerpt']['rendered'] ?? '')));

            $featuredImage = ! empty($arPost['_embedded']['wp:featuredmedia'][0]['source_url'])
                ? $arPost['_embedded']['wp:featuredmedia'][0]['source_url']
                : null;

            $wpCatIds = array_map('intval', $arPost['categories'] ?? []);
            $cmsCategoryId = null;
            if (! empty($wpCatIds)) {
                $matchedCat = CmsCategory::whereIn('source_id', $wpCatIds)->first();
                $cmsCategoryId = $matchedCat?->id;
            }

            $yoastAr = $arPost['yoast_head_json'] ?? [];
            $metaTitleAr = $yoastAr['title'] ?? ($yoastAr['og_title'] ?? $rawArTitle);
            $metaDescAr = $yoastAr['description'] ?? ($yoastAr['og_description'] ?? $rawArExcerpt);

            $post = CmsPost::where('source_id', $arSourceId)
                ->orWhere('slug', $decodedArSlug)
                ->first();

            if (! $post) {
                $post = new CmsPost();
                $post->source_id = $arSourceId;
            }

            $post->title = ['en' => $rawArTitle, 'ar' => $rawArTitle];
            $post->slug = Str::slug($rawArTitle) ?: ('post-' . $arSourceId);
            $post->slug_ar = $decodedArSlug ?: $post->slug;
            $post->short_description = ['en' => $rawArExcerpt, 'ar' => $rawArExcerpt];
            $post->content = ['en' => $rawArContent, 'ar' => $rawArContent];
            $post->cms_category_id = $cmsCategoryId;
            if ($featuredImage) {
                $post->image = $featuredImage;
            }
            $post->meta_title = ['en' => $metaTitleAr, 'ar' => $metaTitleAr];
            $post->meta_description = ['en' => $metaDescAr, 'ar' => $metaDescAr];
            $post->meta_keywords = ['en' => $rawArTitle, 'ar' => $rawArTitle];
            $post->is_active = ($arPost['status'] ?? 'publish') === 'publish';
            $post->published_at = isset($arPost['date']) ? Carbon::parse($arPost['date']) : now();
            $post->save();

            $imported++;
        }

        return [
            'imported' => $imported,
            'total_posts' => count($postsAr) + count($postsEn),
        ];
    }

    /**
     * Convert an external image URL to a local public storage path if the image exists locally.
     */
    public function resolveLocalImagePath(?string $url, string $directory): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return $url;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH);
        if (!$parsedPath) {
            return $url;
        }

        $cleanFilename = urldecode(basename($parsedPath));
        $info = pathinfo($cleanFilename);
        $name = Str::slug($info['filename'] ?? 'file');
        if (empty($name)) {
            $name = 'media-' . substr(md5($url), 0, 10);
        }
        $ext = strtolower($info['extension'] ?? 'jpg');
        if (empty($ext) || strlen($ext) > 5) {
            $ext = 'jpg';
        }

        $relativePath = "{$directory}/{$name}.{$ext}";

        if (Storage::disk('public')->exists($relativePath)) {
            return $relativePath;
        }

        return $url;
    }
}
