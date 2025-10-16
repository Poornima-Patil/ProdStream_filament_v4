<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixOperatorFactories extends Command
{
    protected $signature = 'app:fix-operator-factories';

    protected $description = 'Attach factories to users via pivot table for multi-tenant access';

    public function handle(): void
    {
        $users = User::whereNotNull('factory_id')->get();

        $fixed = 0;

        foreach ($users as $user) {
            if ($user->factories()->count() === 0) {
                $user->factories()->attach($user->factory_id);
                $this->info("Attached factory {$user->factory_id} to {$user->email}");
                $fixed++;
            }
        }

        $this->info("Fixed {$fixed} users");
    }
}
