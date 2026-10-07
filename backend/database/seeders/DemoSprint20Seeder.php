<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\HomePracticeLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StaffAttendance;
use App\Models\TherapySession;
use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Database\Seeder;

/**
 * Local demo for Sprint 20 — ALL SAMPLE DATA: stock items, two children on the waiting list,
 * this month's staff attendance and a few days of home-practice feedback from Ayan's mother.
 */
class DemoSprint20Seeder extends Seeder
{
    public function run(): void
    {
        if (InventoryItem::exists()) {
            return;
        }
        $branch = Branch::where('code', 'HQ')->first() ?? Branch::firstOrFail();
        $reception = User::where('email', 'reception@cstar.test')->first();

        foreach ([
            ['Picture cards set (demo)', 'therapy_material', 'pack', 8, 3, 450],
            ['Therapy putty (demo)', 'therapy_material', 'tub', 2, 4, 380],
            ['Wooden puzzles (demo)', 'toy', 'pcs', 15, 5, 220],
            ['A4 paper (demo)', 'stationery', 'ream', 6, 2, 420],
            ['Hand sanitiser (demo)', 'cleaning', 'bottle', 3, 4, 150],
            ['First-aid kit refill (demo)', 'medical', 'box', 1, 1, 900],
        ] as [$name, $category, $unit, $stock, $reorder, $cost]) {
            $item = InventoryItem::create(['branch_id' => $branch->id, 'name' => $name, 'category' => $category, 'unit' => $unit, 'stock' => $stock, 'reorder_level' => $reorder, 'unit_cost' => $cost]);
            InventoryMovement::create(['inventory_item_id' => $item->id, 'date' => today()->subDays(20), 'type' => 'in', 'quantity' => $stock + 2, 'balance_after' => $stock + 2, 'unit_cost' => $cost, 'note' => 'Opening stock (demo)', 'created_by' => $reception?->id]);
            InventoryMovement::create(['inventory_item_id' => $item->id, 'date' => today()->subDays(5), 'type' => 'out', 'quantity' => -2, 'balance_after' => $stock, 'reference' => 'Therapy rooms', 'created_by' => $reception?->id]);
        }

        $speech = Service::where('slug', 'speech-therapy')->first() ?? Service::first();
        foreach ([['Sara Islam', 'therapy', 'high', 'Wants a morning speech slot (demo)'], ['Rafi Ahmed', 'training', 'normal', 'Asked for the morning class (demo)']] as $i => [$name, $type, $priority, $notes]) {
            $patient = Patient::where('name', $name)->first();
            if ($patient) {
                WaitingListEntry::create([
                    'patient_id' => $patient->id, 'branch_id' => $branch->id, 'type' => $type, 'service_id' => $type === 'therapy' ? $speech?->id : null,
                    'preferred_time' => 'morning', 'priority' => $priority, 'notes' => $notes, 'status' => 'waiting', 'created_by' => $reception?->id,
                    'created_at' => now()->subDays(9 - $i * 4),
                ]);
            }
        }

        // Staff attendance: working days of this month so far, mostly on time.
        foreach (Employee::whereNotNull('user_id')->get() as $n => $employee) {
            for ($d = today()->startOfMonth(); $d->lt(today()); $d->addDay()) {
                if ($d->isFriday()) {
                    continue;
                }
                $late = ($d->day + $n) % 7 === 0;
                StaffAttendance::create([
                    'employee_id' => $employee->id, 'date' => $d->toDateString(), 'check_in' => $late ? '09:32:00' : '08:5'.($n % 10).':00',
                    'check_out' => '17:0'.($n % 10).':00', 'status' => $late ? 'late' : 'present', 'source' => 'self', 'recorded_by' => $employee->user_id,
                ]);
            }
        }

        // Home practice feedback on Ayan's latest session.
        $session = TherapySession::whereHas('patient', fn ($p) => $p->where('name', 'Ayan Rahman'))->where('status', 'final')->whereNotNull('home_practice')->latest('date')->first();
        $parent = User::where('email', 'parent@cstar.test')->first();
        if ($session) {
            foreach ([[3, 'done', 'Named 4 of 5 pictures (demo)'], [2, 'partly', null], [1, 'done', null]] as [$ago, $status, $comment]) {
                HomePracticeLog::create(['therapy_session_id' => $session->id, 'patient_id' => $session->patient_id, 'date' => today()->subDays($ago), 'status' => $status, 'comment' => $comment, 'user_id' => $parent?->id]);
            }
        }
    }
}
