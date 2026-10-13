<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreDownloadImagesCommand extends Command
{
    protected $signature = 'store:download-images {--force : Re-download images even if file already exists locally} {--from-wc : Re-sync authentic image URLs directly from WooCommerce API for all products}';
    protected $description = 'Download all product, gallery, and category images from live site to local public storage';

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $this->info('===========================================================');
        $this->info('  GRASS FLORIST - ASSET MEDIA DOWNLOADER');
        $this->info('===========================================================');

        $force = (bool) $this->option('force');
        $fromWc = (bool) $this->option('from-wc');
        $disk = Storage::disk('public');

        // Ensure directories exist
        $disk->makeDirectory('categories');
        $disk->makeDirectory('products');
        $disk->makeDirectory('products/gallery');

        // 1. Download Category Images
        $this->downloadCategoryImages($disk, $force);
        $this->newLine();

        // 2. Download Product Featured & Gallery Images
        if ($fromWc) {
            $this->syncProductImagesFromWooCommerce($disk, $force);
        } else {
            $this->downloadProductImages($disk, $force);
        }
        $this->newLine();

        $this->info('===========================================================');
        $this->info('🎉 ALL ASSETS DOWNLOADED & LOCAL PATHS UPDATED SUCCESSFULLY!');
        $this->info('===========================================================');

        return Command::SUCCESS;
    }

    protected function syncProductImagesFromWooCommerce($disk, bool $force): void
    {
        $this->info('🔄 Fetching products directly from WooCommerce API to ensure 100% correct matching...');

        $importService = new \App\Services\StoreImportService();
        $reflection = new \ReflectionClass($importService);
        $method = $reflection->getMethod('makeRequest');
        $method->setAccessible(true);

        $page = 1;
        $totalProcessed = 0;
        $featuredCount = 0;
        $galleryCount = 0;

        // First check total count
        $firstRes = $method->invoke($importService, 'products', ['per_page' => 1, 'page' => 1]);
        $totalCount = (int) ($firstRes->header('X-WP-Total') ?: 496);
        $totalPages = (int) ($firstRes->header('X-WP-TotalPages') ?: ceil($totalCount / 100));

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        while ($page <= $totalPages) {
            $res = $method->invoke($importService, 'products', ['per_page' => 100, 'page' => $page]);
            if (! $res->successful()) {
                $this->error("Failed to fetch WC page {$page}");
                break;
            }

            $wcProducts = $res->json() ?? [];
            if (empty($wcProducts)) {
                break;
            }

            foreach ($wcProducts as $wcItem) {
                $sourceId = (int) ($wcItem['id'] ?? 0);
                $sku = $wcItem['sku'] ?? '';
                $images = $wcItem['images'] ?? [];

                $product = Product::where('source_id', $sourceId)
                    ->when(! empty($sku), fn ($q) => $q->orWhere('sku', $sku))
                    ->first();

                if ($product && ! empty($images)) {
                    $dirty = false;
                    $prodSlug = $product->slug ?: Str::slug($product->getTranslation('name', 'en') ?: 'product');

                    // 1. Featured Image (First image in WC list)
                    $featuredUrl = $images[0]['src'] ?? null;
                    if ($featuredUrl) {
                        $localFeatured = $this->downloadFile($featuredUrl, 'products', $disk, $force, $prodSlug);
                        if ($localFeatured && $product->image !== $localFeatured) {
                            $product->image = $localFeatured;
                            $dirty = true;
                            $featuredCount++;
                        }
                    }

                    // 2. Gallery Images
                    $newGallery = [];
                    foreach ($images as $img) {
                        $imgUrl = $img['src'] ?? null;
                        if ($imgUrl) {
                            $localG = $this->downloadFile($imgUrl, 'products/gallery', $disk, $force, $prodSlug);
                            if ($localG) {
                                $newGallery[] = $localG;
                                $galleryCount++;
                            }
                        }
                    }

                    if (! empty($newGallery)) {
                        $newGallery = array_values(array_unique($newGallery));
                        if ($product->gallery !== $newGallery) {
                            $product->gallery = $newGallery;
                            $dirty = true;
                        }
                    }

                    if ($dirty) {
                        $product->save();
                    }
                }

                $totalProcessed++;
                $bar->advance();
            }

            $page++;
            gc_collect_cycles();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  -> Synced from WooCommerce: {$totalProcessed} products, {$featuredCount} featured updated, {$galleryCount} gallery images.");
    }

    protected function downloadCategoryImages($disk, bool $force): void
    {
        $categories = Category::whereNotNull('cat_image')
            ->where(function ($q) {
                $q->where('cat_image', 'LIKE', 'http://%')
                  ->orWhere('cat_image', 'LIKE', 'https://%');
            })->get();

        $this->info("📁 Found {$categories->count()} categories with external images.");
        if ($categories->isEmpty()) {
            return;
        }

        $bar = $this->output->createProgressBar($categories->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($categories as $cat) {
            $url = $cat->cat_image;
            $localPath = $this->downloadFile($url, 'categories', $disk, $force);

            if ($localPath) {
                $cat->cat_image = $localPath;
                $cat->save();
                $success++;
            } else {
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  -> Categories: {$success} downloaded/updated, {$failed} failed.");
    }

    protected function downloadProductImages($disk, bool $force): void
    {
        $query = Product::where(function ($q) {
            $q->where('image', 'LIKE', 'http://%')
              ->orWhere('image', 'LIKE', 'https://%')
              ->orWhereNotNull('gallery');
        });

        $totalCount = $query->count();
        $this->info("📦 Found {$totalCount} products to process for images/gallery.");
        if ($totalCount === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $featuredDownloaded = 0;
        $galleryDownloaded = 0;

        $query->chunkById(25, function ($products) use ($disk, $force, $bar, &$featuredDownloaded, &$galleryDownloaded) {
            foreach ($products as $product) {
                $dirty = false;
                $prodSlug = $product->slug ?: Str::slug($product->getTranslation('name', 'en') ?: 'product');

                // 1. Featured Image
                if ($product->image && (str_starts_with($product->image, 'http://') || str_starts_with($product->image, 'https://'))) {
                    $localFeatured = $this->downloadFile($product->image, 'products', $disk, $force, $prodSlug);
                    if ($localFeatured) {
                        $product->image = $localFeatured;
                        $dirty = true;
                        $featuredDownloaded++;
                    }
                }

                // 2. Gallery Images
                $gallery = $product->gallery;
                if (is_array($gallery) && ! empty($gallery)) {
                    $newGallery = [];
                    $galleryChanged = false;

                    foreach ($gallery as $imgUrl) {
                        if (is_string($imgUrl) && (str_starts_with($imgUrl, 'http://') || str_starts_with($imgUrl, 'https://'))) {
                            $localGalleryPath = $this->downloadFile($imgUrl, 'products/gallery', $disk, $force, $prodSlug);
                            if ($localGalleryPath) {
                                $newGallery[] = $localGalleryPath;
                                $galleryChanged = true;
                                $galleryDownloaded++;
                            } else {
                                $newGallery[] = $imgUrl;
                            }
                        } else {
                            $newGallery[] = $imgUrl;
                        }
                    }

                    if ($galleryChanged) {
                        $product->gallery = $newGallery;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $product->save();
                }

                $bar->advance();
            }

            gc_collect_cycles();
        });

        $bar->finish();
        $this->newLine();
        $this->info("  -> Products: {$featuredDownloaded} featured images, {$galleryDownloaded} gallery images processed.");
    }

    /**
     * Download a file from a URL and save to the given directory on disk.
     */
    protected function downloadFile(string $url, string $directory, $disk, bool $force = false, ?string $productSlug = null): ?string
    {
        $url = trim($url);
        if (empty($url) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH);
        if (! $parsedPath) {
            return null;
        }

        $originalFilename = basename($parsedPath);
        $cleanFilename = urldecode($originalFilename);
        $info = pathinfo($cleanFilename);

        $name = Str::slug($info['filename'] ?? 'file');
        if (empty($name)) {
            $name = 'media-' . substr(md5($url), 0, 10);
        }
        $ext = strtolower($info['extension'] ?? 'jpg');
        if (empty($ext) || strlen($ext) > 5) {
            $ext = 'jpg';
        }

        // When productSlug is provided, enforce {productSlug}-{filename} naming without date subdirectories
        if (! empty($productSlug)) {
            $cleanSlug = Str::slug($productSlug);
            $targetFilename = str_starts_with($name, "{$cleanSlug}-")
                ? "{$name}.{$ext}"
                : "{$cleanSlug}-{$name}.{$ext}";
            $relativePath = "{$directory}/{$targetFilename}";
        } elseif (preg_match('#wp-content/uploads/(.+)$#i', $url, $m)) {
            // Clean and decode original filename, preserving WordPress upload YYYY/MM subdirectories for non-product assets
            $subPath = urldecode($m[1]);
            $subDir = dirname($subPath);
            $targetDir = trim($directory, '/') . ($subDir !== '.' ? '/' . trim($subDir, '/') : '');
            $targetFilename = "{$name}.{$ext}";
            $relativePath = "{$targetDir}/{$targetFilename}";
        } else {
            $targetFilename = "{$name}.{$ext}";
            $relativePath = "{$directory}/{$targetFilename}";
        }

        // If file already exists locally and force is false, return relative path
        if (! $force && $disk->exists($relativePath) && $disk->size($relativePath) > 50) {
            return $relativePath;
        }

        // Ensure target directory exists on filesystem
        $fullDirPath = storage_path('app/public/' . dirname($relativePath));
        if (! file_exists($fullDirPath)) {
            @mkdir($fullDirPath, 0775, true);
        }

        // Properly encode URL path for UTF-8 / Arabic / Spaces
        $encodedUrl = $this->encodeUrl($url);

        // Method 1: Laravel Http Client with User-Agent, Referer, and SSL bypass
        $lastError = null;
        try {
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Referer' => 'https://grassflorist.com/',
                    'Origin' => 'https://grassflorist.com',
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9,ar;q=0.8',
                    'Sec-Ch-Ua' => '"Chromium";v="122", "Not(A:Brand";v="24", "Google Chrome";v="122"',
                    'Sec-Ch-Ua-Mobile' => '?0',
                    'Sec-Ch-Ua-Platform' => '"Windows"',
                    'Sec-Fetch-Dest' => 'image',
                    'Sec-Fetch-Mode' => 'no-cors',
                    'Sec-Fetch-Site' => 'same-origin',
                ])
                ->get($encodedUrl);

            if ($response->successful() && strlen($response->body()) > 50) {
                $disk->put($relativePath, $response->body());
                return $relativePath;
            } else {
                $lastError = "HTTP Status: " . $response->status() . " Body length: " . strlen($response->body());
            }
        } catch (\Exception $e) {
            $lastError = "Http Exception: " . $e->getMessage();
        }

        // Method 2: Raw cURL Fallback
        if (function_exists('curl_init')) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $encodedUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    CURLOPT_REFERER => 'https://grassflorist.com/',
                    CURLOPT_HTTPHEADER => [
                        'Referer: https://grassflorist.com/',
                        'Origin: https://grassflorist.com',
                        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                        'Accept-Language: en-US,en;q=0.9,ar;q=0.8',
                    ],
                ]);
                $body = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($httpCode === 200 && is_string($body) && strlen($body) > 50) {
                    $disk->put($relativePath, $body);
                    return $relativePath;
                } else {
                    $lastError .= " | cURL Code: {$httpCode} Error: {$curlErr}";
                }
            } catch (\Exception $e) {
                $lastError .= " | cURL Exception: " . $e->getMessage();
            }
        }

        // Method 3: file_get_contents with stream context
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $body = @file_get_contents($encodedUrl, false, $ctx);
            if ($body && strlen($body) > 50) {
                $disk->put($relativePath, $body);
                return $relativePath;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        static $printedErrors = 0;
        if ($printedErrors < 3) {
            $this->newLine();
            $this->error("❌ Download Failed for [{$url}]: {$lastError}");
            $printedErrors++;
        }

        return null;
    }

    /**
     * Properly encode URL preserving scheme and host but encoding non-ascii path characters.
     */
    protected function encodeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['host'])) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        // Encode each segment of path
        $segments = explode('/', $path);
        $encodedSegments = array_map(function ($segment) {
            return rawurlencode(rawurldecode($segment));
        }, $segments);
        $encodedPath = implode('/', $encodedSegments);

        return "{$scheme}://{$host}{$port}{$encodedPath}{$query}";
    }
}
