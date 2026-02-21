<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class CleanExpiredPayments extends Command
{
    protected $signature = 'payment:clean-expired {--hours=24 : Hours after which pending payments are marked expired}';
    protected $description = 'Mark pending payments older than the specified hours as expired';

    public function handle()
    {
        $hours = $this->option('hours');
        $cutoff = now()->subHours($hours);

        $count = Payment::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->update(['status' => 'expired']);

        $this->info("Marked {$count} expired payment(s).");
        return Command::SUCCESS;
    }
}
