<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_risk_weights_total_one_hundred_percent(): void
    {
        $weights = [
            'weather' => 25,
            'inflation' => 25,
            'currency' => 20,
            'news' => 20,
            'port' => 10,
        ];

        $this->assertSame(100, array_sum($weights));
    }
}
