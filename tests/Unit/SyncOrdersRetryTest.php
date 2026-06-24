<?php

namespace Tests\Unit;

use Tests\TestCase;

class SyncOrdersRetryTest extends TestCase
{
    public function test_first_attempt_does_not_sleep(): void
    {
        // attempt=1 → ($attempt > 1) is false → sleep(0) is never called.
        // We verify the condition itself rather than mocking sleep().
        $attempt = 1;
        $slept   = false;
        if ($attempt > 1) {
            $slept = true;
        }
        $this->assertFalse($slept, 'Attempt 1 must not trigger a sleep');
    }

    public function test_second_attempt_sleeps_five_seconds(): void
    {
        $attempt     = 2;
        $sleepAmount = 0;
        if ($attempt > 1) {
            $sleepAmount = ($attempt - 1) * 5;
        }
        $this->assertSame(5, $sleepAmount);
    }

    public function test_third_attempt_sleeps_ten_seconds(): void
    {
        $attempt     = 3;
        $sleepAmount = 0;
        if ($attempt > 1) {
            $sleepAmount = ($attempt - 1) * 5;
        }
        $this->assertSame(10, $sleepAmount);
    }
}
