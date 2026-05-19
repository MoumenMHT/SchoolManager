<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bill;
use Illuminate\Support\Carbon;

class MarkLateBills extends Command
{
    /**
     * The name and signature of the console command.
     * Run manually: php artisan bills:mark-late
     */
    protected $signature = 'bills:mark-late';

    /**
     * The console command description.
     */
    protected $description = 'Mark all overdue unpaid bills as late';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $today = Carbon::today();

        // Find all bills that are still 'unpaid', have no payment, and whose due_date has passed
        $overdueBills = Bill::where('status', 'unpaid')
            ->where('amount_paid', 0)
            ->whereDate('due_date', '<', $today)
            ->get();

        $count = $overdueBills->count();

        if ($count === 0) {
            $this->info('No overdue bills found.');
            return;
        }

        foreach ($overdueBills as $bill) {
            $bill->status = 'late';
            $bill->save();
        }

        $this->info("Marked {$count} bill(s) as late.");
    }
}
