<?php

use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\PerformanceCycle;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceReview;
use App\Models\PerformanceSupervisorAssignment;
use App\Models\PerformanceTemplate;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-30 10:00:00');
    Storage::fake('local');

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
        $table->string('jawatan')->nullable();
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
        'description' => 'Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createPerformanceEmployee(
    User $user,
    string $employeeNumber = 'EMP-KPI-001',
): int {
    $employeeId = DB::connection('ibco')
        ->table('maklumatpekerja')
        ->insertGetId([
            'employeeID' => $employeeNumber,
            'nama' => 'Pekerja Prestasi '.$employeeNumber,
            'rcd_enable' => 1,
        ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => 1,
        'jawatan' => 'Eksekutif Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO Performance HQ '.$employeeNumber,
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
    EmployeeUserLink::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);

    return $employeeId;
}

function createPerformanceCycleAndTemplate(User $admin): array
{
    $cycle = PerformanceCycle::query()->create([
        'code' => 'KPI-2026',
        'name' => 'Penilaian Prestasi 2026',
        'cycle_type' => 'annual',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'self_assessment_due_at' => '2026-10-31',
        'supervisor_due_at' => '2026-11-30',
        'moderation_due_at' => '2026-12-15',
        'status' => 'open',
        'rating_scale' => [
            ['label' => 'Sangat Cemerlang', 'minimum' => 4.5],
            ['label' => 'Cemerlang', 'minimum' => 4],
            ['label' => 'Baik', 'minimum' => 3],
            ['label' => 'Perlu Peningkatan', 'minimum' => 2],
            ['label' => 'Tidak Memuaskan', 'minimum' => 1],
        ],
        'created_by' => $admin->getKey(),
        'updated_by' => $admin->getKey(),
        'opened_at' => now(),
    ]);
    $template = PerformanceTemplate::query()->create([
        'code' => 'IT-EXEC',
        'name' => 'KPI Eksekutif IT',
        'department_id' => 1,
        'position_name' => 'Eksekutif Teknologi Maklumat',
        'is_active' => true,
        'created_by' => $admin->getKey(),
        'updated_by' => $admin->getKey(),
    ]);
    $template->items()->createMany([
        [
            'title' => 'Penyampaian Projek',
            'measure_type' => 'quantitative',
            'target_value' => 4,
            'unit' => 'projek',
            'weight' => 60,
            'scoring_guide' => '5 = melebihi sasaran, 3 = mencapai sasaran.',
            'sort_order' => 1,
        ],
        [
            'title' => 'Kualiti Sokongan',
            'measure_type' => 'qualitative',
            'weight' => 40,
            'scoring_guide' => 'Dinilai berdasarkan kualiti dan maklum balas.',
            'sort_order' => 2,
        ],
    ]);

    return [$cycle, $template];
}

test('performance templates require exactly one hundred percent weight', function () {
    $admin = User::factory()->hrAdmin()->create();

    $this->actingAs($admin)
        ->post(route('performance-settings.templates.store'), [
            'code' => 'INVALID',
            'name' => 'Template Tidak Lengkap',
            'department_id' => 1,
            'position_name' => null,
            'description' => null,
            'is_active' => true,
            'items' => [
                [
                    'title' => 'Sasaran A',
                    'measure_type' => 'quantitative',
                    'target_value' => 10,
                    'unit' => 'kes',
                    'weight' => 70,
                ],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(PerformanceTemplate::query()->count())->toBe(0);
});

test('complete appraisal workflow calculates weighted scores and final rating', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $employeeId = createPerformanceEmployee($employee);
    [$cycle] = createPerformanceCycleAndTemplate($hr);
    PerformanceSupervisorAssignment::query()->create([
        'department_id' => 1,
        'supervisor_user_id' => $supervisor->getKey(),
        'is_active' => true,
        'updated_by' => $hr->getKey(),
    ]);
    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (
        &$legacyQueries,
    ) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $this->actingAs($hr)
        ->post(route('performance.generate', $cycle))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $review = PerformanceReview::query()
        ->where('employee_id', $employeeId)
        ->with('goals')
        ->firstOrFail();
    expect($review->status)->toBe('self_assessment');
    expect((float) $review->total_weight)->toBe(100.0);
    expect($review->supervisor_user_id)->toBe($supervisor->getKey());

    $selfGoals = $review->goals->map(fn ($goal, int $index) => [
        'id' => $goal->getKey(),
        'actual_achievement' => $index === 0
            ? 'Empat projek siap mengikut jadual.'
            : 'Maklum balas pengguna sangat baik.',
        'self_score' => $index === 0 ? 4 : 5,
        'self_comments' => 'Bukti dan hasil kerja telah dilampirkan.',
    ])->all();
    $this->actingAs($employee)
        ->patch(route('employee-performance.submit', $review), [
            'goals' => $selfGoals,
            'employee_summary' => 'Sasaran tahunan berjaya dicapai.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($review->fresh()->status)->toBe('supervisor_assessment');
    expect((float) $review->fresh()->self_score)->toBe(4.4);

    $supervisorGoals = $review->fresh('goals')->goals->map(
        fn ($goal, int $index) => [
            'id' => $goal->getKey(),
            'supervisor_score' => $index === 0 ? 4 : 4,
            'supervisor_comments' => 'Prestasi disahkan berdasarkan hasil kerja.',
        ],
    )->all();
    $this->actingAs($supervisor)
        ->put(route('performance.supervisor-review', $review), [
            'goals' => $supervisorGoals,
            'supervisor_summary' => 'Prestasi keseluruhan baik.',
            'strengths' => 'Komited dan responsif.',
            'improvement_areas' => 'Perkukuh dokumentasi.',
            'development_plan' => 'Latihan pengurusan projek.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($review->fresh()->status)->toBe('hr_moderation');
    expect((float) $review->fresh()->supervisor_score)->toBe(4.0);

    $moderatedGoals = $review->fresh('goals')->goals->map(
        fn ($goal, int $index) => [
            'id' => $goal->getKey(),
            'moderated_score' => $index === 0 ? 5 : 4,
            'moderation_comments' => 'Diselaraskan semasa moderasi jabatan.',
        ],
    )->all();
    $this->actingAs($hr)
        ->put(route('performance.moderate', $review), [
            'goals' => $moderatedGoals,
            'hr_comments' => 'Skor telah dimoderasi dan disahkan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect((float) $review->fresh()->moderated_score)->toBe(4.6);
    expect($review->fresh()->final_rating)->toBe('Sangat Cemerlang');

    $this->actingAs($hrManager)
        ->patch(route('performance.finalize', $review))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($review->fresh()->status)->toBe('finalized');
    $this->actingAs($employee)
        ->get(route('employee-performance.pdf', $review))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($hr)
        ->put(route('performance.pip.save', $review), [
            'status' => 'active',
            'start_date' => '2027-01-01',
            'end_date' => '2027-03-31',
            'reason' => 'Pelan pembangunan khusus dipersetujui.',
            'objectives' => 'Meningkatkan dokumentasi projek.',
            'required_actions' => 'Lengkapkan templat dokumentasi bagi setiap projek.',
            'support_required' => 'Bimbingan penyelia setiap dua minggu.',
            'success_criteria' => 'Semua projek mempunyai dokumentasi lengkap.',
            'outcome' => null,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $plan = PerformanceImprovementPlan::query()->firstOrFail();
    $this->actingAs($hr)
        ->post(route('performance.pip.checkin', $plan), [
            'checkin_date' => '2027-01-15',
            'progress_status' => 'on_track',
            'progress_notes' => 'Dua dokumen projek telah lengkap.',
            'next_actions' => 'Teruskan semakan projek seterusnya.',
        ])
        ->assertSessionDoesntHaveErrors();

    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'performance_review.finalized',
        'auditable_id' => (string) $review->getKey(),
    ]);
    $this->assertDatabaseHas('performance_pip_checkins', [
        'performance_improvement_plan_id' => $plan->getKey(),
        'progress_status' => 'on_track',
    ]);
});

test('performance evidence stays private and dashboard is role aware', function () {
    $employee = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $employeeId = createPerformanceEmployee($employee, 'EMP-KPI-PRIVATE');
    createPerformanceEmployee($other, 'EMP-KPI-OTHER');
    [$cycle, $template] = createPerformanceCycleAndTemplate($hr);
    PerformanceSupervisorAssignment::query()->create([
        'department_id' => 1,
        'supervisor_user_id' => $supervisor->getKey(),
        'is_active' => true,
    ]);
    $this->actingAs($hr)->post(route('performance.store'), [
        'performance_cycle_id' => $cycle->getKey(),
        'employee_id' => $employeeId,
        'performance_template_id' => $template->getKey(),
        'supervisor_user_id' => $supervisor->getKey(),
    ])->assertSessionDoesntHaveErrors();
    $review = PerformanceReview::query()->firstOrFail();
    $goal = $review->goals()->firstOrFail();

    $this->actingAs($employee)
        ->post(route('employee-performance.evidence.store', $review), [
            'performance_goal_id' => $goal->getKey(),
            'description' => 'Laporan pencapaian projek.',
            'evidence' => UploadedFile::fake()->create(
                'laporan-kpi.pdf',
                200,
                'application/pdf',
            ),
        ])
        ->assertSessionDoesntHaveErrors();
    $evidence = PerformanceEvidence::query()->firstOrFail();
    Storage::disk('local')->assertExists($evidence->path);

    $this->actingAs($employee)
        ->get(route('employee-performance.evidence.download', [$review, $evidence]))
        ->assertOk();
    $this->actingAs($other)
        ->get(route('employee-performance.evidence.download', [$review, $evidence]))
        ->assertForbidden();
    $this->actingAs($supervisor)
        ->get(route('performance.evidence.download', [$review, $evidence]))
        ->assertOk();
    $this->actingAs($hr)
        ->get(route('performance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PerformanceReviews/Index')
            ->where('statistics.total', 1)
            ->has('departmentPerformance', 1));
});
