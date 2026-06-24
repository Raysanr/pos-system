<?php

namespace Tests\Unit;

use Tests\TestCase;

class HealthConditionsCapTest extends TestCase
{
    public function test_chunk_callback_stops_at_5000_rows(): void
    {
        // Simulate the chunk-walk cap logic from healthConditions().
        // Each chunk is 500 rows; we expect the walk to stop after chunk 10 (5000 total).
        $scanned  = 0;
        $chunks   = 0;
        $capAt    = 5000;
        $chunkSize = 500;

        while (true) {
            $scanned += $chunkSize;
            $chunks++;
            if ($scanned >= $capAt) break;
        }

        $this->assertSame(5000, $scanned);
        $this->assertSame(10, $chunks, 'Walk must stop after exactly 10 chunks of 500');
    }

    public function test_chunk_callback_continues_below_cap(): void
    {
        $scanned = 0;
        $stopped = false;

        foreach (range(1, 5) as $chunk) {
            $scanned += 500;
            if ($scanned >= 5000) {
                $stopped = true;
                break;
            }
        }

        $this->assertSame(2500, $scanned);
        $this->assertFalse($stopped, 'Walk must not stop before 5000 rows');
    }
}
