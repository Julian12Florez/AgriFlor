<?php

namespace Tests\Feature;

use App\Models\AdjustmentReason;
use Database\Seeders\AdjustmentReasonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reasons_seeded(): void
    {
        $this->seed(AdjustmentReasonSeeder::class);

        $this->assertDatabaseHas('adjustment_reasons', ['code' => 'compra_doble', 'direction' => 'exit']);
        $this->assertSame(10, AdjustmentReason::count());
    }
}
