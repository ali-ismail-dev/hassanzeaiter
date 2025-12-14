<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CategorySyncService;
use Illuminate\Support\Facades\Log;

class SyncOlxCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:sync {--force : Force refresh remote cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync categories and category fields from OLX API into local database';

    public function handle(CategorySyncService $syncService): int
    {
        $force = (bool) $this->option('force');

        $this->info('Starting OLX categories sync'.($force ? ' (force)' : ''));

        try {
            $stats = $syncService->syncAll($force);

            $this->info('Sync completed');
            $this->table(['Metric', 'Count'], [
                ['Categories', $stats['categories'] ?? 0],
                ['Fields', $stats['fields'] ?? 0],
                ['Options', $stats['options'] ?? 0],
            ]);

            return 0;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('categories:sync command failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }
    }
}
