<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Models\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupWebhookEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:cleanup-webhook-events 
                            {--days=30 : Number of days to keep webhook events}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old webhook events from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info("Cleaning up webhook events older than {$days} days...");
        
        $cutoffDate = now()->subDays($days);
        
        $query = WebhookEvent::query()
            ->where('processed_at', '<', $cutoffDate);
            
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No webhook events to clean up.');
            return Command::SUCCESS;
        }
        
        if ($dryRun) {
            $this->info("Would delete {$count} webhook events.");
            
            // Show sample of what would be deleted
            $sample = $query->limit(10)->get(['provider_code', 'webhook_id', 'processed_at']);
            
            if ($sample->isNotEmpty()) {
                $this->info('Sample of events to be deleted:');
                $this->table(
                    ['Provider', 'Webhook ID', 'Processed At'],
                    $sample->map(fn($event) => [
                        $event->provider_code,
                        $event->webhook_id,
                        $event->processed_at,
                    ])
                );
            }
        } else {
            $deleted = $query->delete();
            $this->info("Deleted {$deleted} webhook events.");
        }
        
        return Command::SUCCESS;
    }
}
