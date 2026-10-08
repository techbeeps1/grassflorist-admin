<?php

namespace App\Console\Commands;

use App\Services\StoreImportService;
use Illuminate\Console\Command;

class StoreImportProductsCommand extends Command
{
    protected $signature = 'store:import-products 
                            {--per-page=50 : Number of products per page} 
                            {--start-page=1 : Starting page number} 
                            {--max-pages=0 : Maximum pages to import (0 for all)}';
    protected $description = 'Import products, images, and category associations from WooCommerce REST API';

    public function handle(StoreImportService $importService): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->info('💐 Starting Products Sync from WooCommerce...');
        $perPage = (int) ($this->option('per-page') ?: 50);
        $startPage = max(1, (int) ($this->option('start-page') ?: 1));
        $maxPages = (int) ($this->option('max-pages') ?: 0);
        $totalImported = 0;

        try {
            $initial = $importService->importProductsChunk($startPage, $perPage);
            $totalPages = $initial['total_pages'];
            $totalRecords = $initial['total_records'];
            $totalImported += $initial['imported'];

            $endPage = ($maxPages > 0 && ($startPage + $maxPages - 1) < $totalPages)
                ? ($startPage + $maxPages - 1)
                : $totalPages;

            $this->info("Found {$totalRecords} total products. Processing pages {$startPage} to {$endPage} (batch size: {$perPage}).");
            $this->line("  -> [Page {$startPage}/{$totalPages}] Imported/Updated {$initial['imported']} products.");

            for ($page = $startPage + 1; $page <= $endPage; $page++) {
                $res = $importService->importProductsChunk($page, $perPage);
                $totalImported += $res['imported'];
                $this->line("  -> [Page {$page}/{$totalPages}] Imported/Updated {$res['imported']} products.");
                gc_collect_cycles();
            }

            $this->newLine();
            $this->info("✅ Successfully imported/updated {$totalImported} products!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Products sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
