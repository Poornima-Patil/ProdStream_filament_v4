<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Factory;
use Illuminate\Console\Command;

class BackfillUserFactoryPivot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:backfill-factory-pivot {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill factory_user pivot table for users missing the relationship';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all factories
        $factories = Factory::all();
        $this->info("Found {$factories->count()} factories");
        $this->newLine();

        $totalProcessed = 0;

        foreach ($factories as $factory) {
            $this->info("Processing Factory: {$factory->name} (ID: {$factory->id})");

            // Find users with this factory_id who are NOT in the pivot table
            $usersWithoutPivot = User::where('factory_id', $factory->id)
                ->whereDoesntHave('factories', function ($query) use ($factory) {
                    $query->where('factories.id', $factory->id);
                })
                ->get();

            $count = $usersWithoutPivot->count();

            if ($count > 0) {
                $this->line("  Found {$count} users missing pivot table entry");

                foreach ($usersWithoutPivot as $user) {
                    $this->line("    - {$user->email} (ID: {$user->id}, emp_id: {$user->emp_id})");

                    if (!$dryRun) {
                        $user->factories()->syncWithoutDetaching([$factory->id]);
                    }
                }

                $totalProcessed += $count;
                $this->info("  ✅ " . ($dryRun ? "Would add" : "Added") . " {$count} users to pivot table");
            } else {
                $this->line("  ✓ All users already have pivot table entries");
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        if ($totalProcessed > 0) {
            if ($dryRun) {
                $this->warn("🔍 DRY RUN COMPLETE: Would process {$totalProcessed} users across {$factories->count()} factories");
                $this->info("Run without --dry-run to apply changes");
            } else {
                $this->info("✅ SUCCESS: Processed {$totalProcessed} users across {$factories->count()} factories");
            }
        } else {
            $this->info("✅ No users needed to be processed - all pivot table entries are correct");
        }

        return Command::SUCCESS;
    }
}
