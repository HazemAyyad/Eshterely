<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Users\CustomerCodeService;
use Illuminate\Console\Command;

class BackfillCustomerCodesCommand extends Command
{
    protected $signature = 'users:backfill-customer-codes {--chunk=100}';

    protected $description = 'Assign customer_code to users that are missing one (uses current format settings).';

    public function handle(CustomerCodeService $customerCodeService): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $q = User::query()
            ->where(function ($q) {
                $q->whereNull('customer_code')->orWhere('customer_code', '');
            })
            ->orderBy('id');

        $total = (clone $q)->count();
        if ($total === 0) {
            $this->info('No users need a customer code.');

            return self::SUCCESS;
        }

        $this->info("Backfilling {$total} user(s)…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $q->chunkById($chunk, function ($users) use ($customerCodeService, $bar) {
            foreach ($users as $user) {
                $customerCodeService->assignNextCode($user);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
