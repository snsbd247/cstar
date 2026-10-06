<?php

namespace Tests\Feature;

use App\Services\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sequential_patient_codes(): void
    {
        $ids = app(IdGenerator::class);

        $this->assertSame('CSTAR-2026-00001', $ids->next('patient', 'CSTAR', year: 2026));
        $this->assertSame('CSTAR-2026-00002', $ids->next('patient', 'CSTAR', year: 2026));
    }

    public function test_sequence_restarts_each_year_and_is_independent_per_key(): void
    {
        $ids = app(IdGenerator::class);
        $ids->next('patient', 'CSTAR', year: 2026);

        $this->assertSame('CSTAR-2027-00001', $ids->next('patient', 'CSTAR', year: 2027));
        $this->assertSame('INV-2026-000001', $ids->next('invoice', 'INV', 6, 2026));
    }
}
