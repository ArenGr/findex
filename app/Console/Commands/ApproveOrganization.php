<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;

class ApproveOrganization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:approve {slug : Slug of the organization to approve}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve a pending self-registered organization, making its public page visible';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $organization = Organization::where('slug', $this->argument('slug'))->first();

        if (!$organization) {
            $this->error("Organization with slug '{$this->argument('slug')}' not found.");

            return self::FAILURE;
        }

        $organization->update(['is_active' => true]);

        $this->info("Approved {$organization->name} ({$organization->slug}). Its public page is now visible.");

        return self::SUCCESS;
    }
}
