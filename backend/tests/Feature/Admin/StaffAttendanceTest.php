<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\StaffLeave;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 20: staff check in / out; HR's monthly sheet merges leave, holidays and the weekly day off. */
class StaffAttendanceTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    public function test_self_check_in_out_and_monthly_sheet(): void
    {
        Carbon::setTestNow('2026-10-14 09:30:00');                         // a Wednesday
        $branch = Branch::factory()->create();
        app(SystemSettings::class)->update('staff_attendance', ['office_start' => '09:00', 'late_after_minutes' => '15', 'weekly_off' => '5', 'self_check_in' => '1']);
        $staff = $this->userWithRole(Role::Receptionist, $branch);
        $employee = Employee::create([
            'employee_code' => 'EMP-0001', 'name' => 'Rina', 'department' => 'admin', 'branch_id' => $branch->id,
            'joining_date' => '2026-01-01', 'user_id' => $staff->id,
        ]);

        // Staff without an employee record see nothing; a linked one checks in late and out once.
        $this->actingAs($this->userWithRole(Role::Trainer, $branch))->getJson('/api/v1/me/attendance')->assertOk()->assertJsonPath('data', null);
        $this->actingAs($staff)->getJson('/api/v1/me/attendance')->assertJsonPath('data.today', null);
        $this->postJson('/api/v1/me/attendance/check-out')->assertJsonValidationErrors('action');
        $this->postJson('/api/v1/me/attendance/check-in')->assertOk()->assertJsonPath('data.status', 'late')->assertJsonPath('data.check_in', '09:30');
        $this->postJson('/api/v1/me/attendance/check-in')->assertJsonValidationErrors('action');
        Carbon::setTestNow('2026-10-14 17:05:00');
        $this->postJson('/api/v1/me/attendance/check-out')->assertOk()->assertJsonPath('data.check_out', '17:05');
        $this->getJson('/api/v1/hr/attendance')->assertForbidden();

        // HR: leave, holiday, Friday off, a manual absence and unmarked working days.
        StaffLeave::create(['employee_id' => $employee->id, 'type' => 'sick', 'start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'days' => 2]);
        Holiday::create(['date' => '2026-10-01', 'title' => 'Demo holiday', 'type' => 'public']);
        $hr = $this->userWithRole(Role::Accountant, $branch);
        $this->actingAs($hr)->putJson('/api/v1/hr/attendance', ['employee_id' => $employee->id, 'date' => '2026-10-07', 'status' => 'absent', 'note' => 'No call'])->assertOk();
        $this->putJson('/api/v1/hr/attendance', ['employee_id' => $employee->id, 'date' => '2026-10-20', 'status' => 'present'])->assertJsonValidationErrors('date');
        $this->putJson('/api/v1/hr/attendance', ['employee_id' => $employee->id, 'date' => '2026-10-08', 'status' => 'present', 'check_in' => '10:00', 'check_out' => '09:00'])->assertJsonValidationErrors('check_out');

        $row = $this->getJson('/api/v1/hr/attendance?month=2026-10')->assertOk()->json('data.rows.0');
        $this->assertSame('holiday', $row['cells']['2026-10-01']['code']);
        $this->assertSame('off', $row['cells']['2026-10-02']['code']);
        $this->assertSame('leave', $row['cells']['2026-10-05']['code']);
        $this->assertSame('absent', $row['cells']['2026-10-07']['code']);
        $this->assertSame('late', $row['cells']['2026-10-14']['code']);
        $this->assertNull($row['cells']['2026-10-15']);
        // Working days 1–14 Oct: 14 minus 2 Fridays (2, 9) = 12; minus holiday, 2 leave, 1 absent, 1 late = 7 unmarked.
        $this->assertSame(['present' => 0, 'late' => 1, 'half_day' => 0, 'absent' => 1, 'leave' => 2, 'unmarked' => 7], $row['summary']);

        $this->putJson('/api/v1/hr/attendance', ['employee_id' => $employee->id, 'date' => '2026-10-07', 'status' => 'clear'])->assertOk();
        $this->assertSame('unmarked', $this->getJson('/api/v1/hr/attendance?month=2026-10')->json('data.rows.0.cells.2026-10-07.code'));
    }
}
