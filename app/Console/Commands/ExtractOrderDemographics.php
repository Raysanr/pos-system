<?php

namespace App\Console\Commands;

use App\Support\DemographicsExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtractOrderDemographics extends Command
{
    protected $signature = 'app:extract-order-demographics';
    protected $description = 'Backfill customer_age and health_condition from order notes';

    public function handle(): int
    {
        $total = DB::table('orders')->whereNotNull('extra_note')->count();
        $this->info("Processing {$total} orders with notes…");

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $updated = 0;

        DB::table('orders')
            ->whereNotNull('extra_note')
            ->select('id', 'extra_note')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($bar, &$updated) {
                foreach ($rows as $row) {
                    $age   = DemographicsExtractor::age($row->extra_note);
                    $conds = DemographicsExtractor::conditions($row->extra_note);
                    DB::table('orders')->where('id', $row->id)->update([
                        'customer_age'     => $age,
                        'health_condition' => $conds ? json_encode($conds) : null,
                    ]);
                    $updated++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Done! Updated {$updated} of {$total} orders with demographic data.");
        return self::SUCCESS;
    }
}
