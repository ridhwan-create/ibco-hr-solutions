<?php

use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OfficeLocation;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeType;
use App\Models\RosterEntry;
use App\Models\RosterNotification;
use App\Models\RosterPeriod;
use App\Models\ScheduleAssignment;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-30 10:00:00');

    config()->set('app.timezone', 'Asia/Kuala_Lumpur');
    config()->set('database.connections.ibco', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('ibco');

    Schema::connection('ibco')->create('maklumatpekerja', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('employeeID', 30)->nullable();
        $table->string('nama')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatjawatan', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('id_department')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('xdepartment', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    DB::connection('ibco')->table('xdepartment')->insert([
        'id' => 1,
        'description' => 'Operasi',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createRosterEmployee(
    User $user,
    OfficeLocation $office,
    string $employeeNumber,
    int $departmentId = 1,
): int {
    $employeeId = DB::connection('ibco')
        ->table('maklumatpekerja')
        ->insertGetId([
            'employeeID' => $employeeNumber,
            'nama' => "Pekerja {$employeeNumber}",
            'rcd_enable' => 1,
        ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => $departmentId,
        'rcd_enable' => 1,
    ]);
    EmployeeUserLink::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);

    return $employeeId;
}

function createRosterOffice(): OfficeLocation
{
    return OfficeLocation::query()->create([
        'name' => 'IBCO Operations Centre',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
}

test('hr can configure shift templates and effective department assignments', function () {
    $admin = User::factory()->superAdmin()->create();
    $viewer = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('schedule-settings.templates.store'), [
            'code' => 'SIX_DAY',
            'name' => 'Operasi Enam Hari',
            'description' => 'Jadual Isnin hingga Sabtu.',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'grace_minutes' => 10,
            'early_departure_grace_minutes' => 5,
            'work_days' => [1, 2, 3, 4, 5, 6],
            'is_default' => false,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $template = ShiftTemplate::query()->where('code', 'SIX_DAY')->firstOrFail();
    $this->actingAs($admin)
        ->post(route('schedule-settings.assignments.store'), [
            'shift_template_id' => $template->getKey(),
            'scope_type' => 'department',
            'department_id' => 1,
            'effective_from' => '2026-08-01',
            'priority' => 200,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($template->work_days)->toBe([1, 2, 3, 4, 5, 6]);
    expect(ScheduleAssignment::query()
        ->where('department_id', 1)
        ->where('shift_template_id', $template->getKey())
        ->exists())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'shift_template.created',
        'auditable_type' => 'shift_templates',
    ]);
    $this->actingAs($viewer)
        ->get(route('schedule-settings.index'))
        ->assertForbidden();
});

test('hr can generate publish and lock a monthly roster without writing to db_spp', function () {
    $admin = User::factory()->superAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $employeeUser = User::factory()->employee()->create();
    $office = createRosterOffice();
    $employeeId = createRosterEmployee(
        $employeeUser,
        $office,
        'EMP-ROSTER-001',
    );
    $legacyQueries = [];

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $this->actingAs($admin)
        ->post(route('rosters.generate'), ['month' => '2026-08'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $period = RosterPeriod::query()->sole();
    expect($period->status)->toBe('draft');
    expect($period->entries()->count())->toBe(31);
    expect(
        RosterEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '2026-08-03')
            ->value('day_type'),
    )->toBe('workday');
    expect(
        RosterEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '2026-08-02')
            ->value('day_type'),
    )->toBe('rest_day');

    $this->actingAs($hrManager)
        ->patch(route('rosters.publish', $period))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($employeeUser)
        ->get(route('employee-roster.index', ['month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EmployeeSelfService/Roster')
            ->where('period.status', 'published')
            ->where('summary.workdays', 21)
            ->has('entries', 31));

    $this->actingAs($hrManager)
        ->patch(route('rosters.lock', $period))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $entry = $period->entries()->whereDate('work_date', '2026-08-03')->firstOrFail();
    $this->actingAs($admin)
        ->put(route('rosters.entries.update', $entry), [
            'day_type' => 'off',
        ])
        ->assertSessionHasErrors('roster');

    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
    expect(RosterNotification::query()
        ->where('user_id', $employeeUser->getKey())
        ->where('title', 'Roster baharu diterbitkan')
        ->exists())->toBeTrue();
});

test('night roster drives late and overnight attendance calculations', function () {
    $admin = User::factory()->superAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $employeeUser = User::factory()->employee()->create();
    $office = createRosterOffice();
    $employeeId = createRosterEmployee(
        $employeeUser,
        $office,
        'EMP-NIGHT-001',
    );
    $night = ShiftTemplate::query()->where('code', 'NIGHT')->firstOrFail();

    ScheduleAssignment::query()->create([
        'shift_template_id' => $night->getKey(),
        'scope_type' => 'employee',
        'employee_id' => $employeeId,
        'effective_from' => '2026-08-01',
        'priority' => 500,
        'is_active' => true,
        'created_by' => $admin->getKey(),
        'updated_by' => $admin->getKey(),
    ]);
    $this->actingAs($admin)
        ->post(route('rosters.generate'), ['month' => '2026-08'])
        ->assertSessionDoesntHaveErrors();
    $period = RosterPeriod::query()->sole();
    $this->actingAs($hrManager)
        ->patch(route('rosters.publish', $period))
        ->assertSessionDoesntHaveErrors();

    $weekdayType = OvertimeType::query()
        ->where('code', 'WEEKDAY')
        ->firstOrFail();
    $this->actingAs($employeeUser)
        ->post(route('employee-overtime.store'), [
            'overtime_type_id' => $weekdayType->getKey(),
            'work_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'reason' => 'Sokongan operasi hari rehat',
            'work_description' => 'Melaksanakan semakan sistem pada hari rehat.',
        ])
        ->assertSessionHasErrors('overtime_type_id');

    $location = [
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'accuracy' => 10,
    ];
    Carbon::setTestNow('2026-08-03 23:15:00');
    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-in'), $location)
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $record = GeoAttendanceRecord::query()->sole();
    expect($record->attendance_date->toDateString())->toBe('2026-08-03');
    expect($record->scheduled_start_at?->format('Y-m-d H:i'))->toBe(
        '2026-08-03 23:00',
    );
    expect($record->scheduled_end_at?->format('Y-m-d H:i'))->toBe(
        '2026-08-04 07:00',
    );
    expect($record->scheduled_minutes)->toBe(435);
    expect($record->late_minutes)->toBe(5);
    expect($record->roster_entry_id)->not->toBeNull();

    Carbon::setTestNow('2026-08-04 07:00:00');
    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-out'), $location)
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($record->fresh()->clock_out_at)->not->toBeNull();
    expect($record->fresh()->early_departure_minutes)->toBe(0);
});

test('supervisor can approve a shift swap before the roster is locked', function () {
    $admin = User::factory()->superAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $supervisor = User::factory()->supervisor()->create();
    $requester = User::factory()->employee()->create();
    $target = User::factory()->employee()->create();
    $office = createRosterOffice();
    $requesterEmployeeId = createRosterEmployee(
        $requester,
        $office,
        'EMP-SWAP-001',
    );
    $targetEmployeeId = createRosterEmployee(
        $target,
        $office,
        'EMP-SWAP-002',
    );
    OvertimeApprovalAssignment::query()->create([
        'department_id' => 1,
        'approver_user_id' => $supervisor->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('rosters.generate'), ['month' => '2026-08'])
        ->assertSessionDoesntHaveErrors();
    $period = RosterPeriod::query()->sole();
    $morning = ShiftTemplate::query()->where('code', 'MORNING')->firstOrFail();
    $evening = ShiftTemplate::query()->where('code', 'EVENING')->firstOrFail();
    $requesterEntry = $period->entries()
        ->where('employee_id', $requesterEmployeeId)
        ->whereDate('work_date', '2026-08-05')
        ->firstOrFail();
    $targetEntry = $period->entries()
        ->where('employee_id', $targetEmployeeId)
        ->whereDate('work_date', '2026-08-05')
        ->firstOrFail();
    $requesterEntry->update([
        'shift_template_id' => $morning->getKey(),
        'scheduled_start_at' => '2026-08-05 07:00:00',
        'scheduled_end_at' => '2026-08-05 15:00:00',
        'break_minutes' => 45,
        'source' => 'manual',
    ]);
    $targetEntry->update([
        'shift_template_id' => $evening->getKey(),
        'scheduled_start_at' => '2026-08-05 15:00:00',
        'scheduled_end_at' => '2026-08-05 23:00:00',
        'break_minutes' => 45,
        'source' => 'manual',
    ]);
    $this->actingAs($hrManager)
        ->patch(route('rosters.publish', $period))
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($requester)
        ->post(route('employee-roster.swaps.store'), [
            'requester_roster_entry_id' => $requesterEntry->getKey(),
            'target_roster_entry_id' => $targetEntry->getKey(),
            'reason' => 'Pertukaran diperlukan untuk urusan keluarga yang telah dirancang.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $swap = ShiftSwapRequest::query()->sole();
    expect($swap->status)->toBe('pending');

    $this->actingAs($supervisor)
        ->patch(route('rosters.swaps.review', $swap), [
            'status' => 'approved',
            'review_notes' => 'Diluluskan selepas semakan liputan operasi.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($swap->fresh()->status)->toBe('approved');
    expect($requesterEntry->fresh()->shift_template_id)->toBe(
        $evening->getKey(),
    );
    expect($targetEntry->fresh()->shift_template_id)->toBe(
        $morning->getKey(),
    );
    expect(RosterNotification::query()
        ->whereIn('user_id', [$requester->getKey(), $target->getKey()])
        ->where('title', 'Pertukaran syif diluluskan')
        ->count())->toBe(2);
});
