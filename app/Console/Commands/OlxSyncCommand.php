<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CategorySyncService;

class OlxSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --force to bypass cache and force refresh
     */
    protected $signature = 'olx:sync {--force : Force refresh from remote API}';

    /**
     * The console command description.
     */
    protected $description = 'Sync categories, fields and options from OLX API (uses CategorySyncService)';

    public function handle(CategorySyncService $syncService)
    {
        $force = $this->option('force') ? true : false;

        $this->info('Starting OLX sync' . ($force ? ' (force refresh)' : ''));

        try {
            $stats = $syncService->syncAll($force);

            $this->info('Sync completed.');
            $this->table(['Metric', 'Count'], [
                ['Categories', $stats['categories'] ?? 0],
                ['Fields', $stats['fields'] ?? 0],
                ['Options', $stats['options'] ?? 0],
            ]);

            return 0;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}
