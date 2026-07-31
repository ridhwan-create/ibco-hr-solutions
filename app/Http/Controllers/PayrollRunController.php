<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSalaryProfile;
use App\Models\ClaimRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryItem;
use App\Models\PayrollRun;
use App\Support\AuditLogger;
use App\Support\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(PayrollRun::STATUSES)],
            'year' => ['nullable', 'date_format:Y'],
        ]);
        $status = $validated['status'] ?? '';
        $year = $validated['year'] ?? (string) now()->year;
        $base = PayrollRun::query();
        $runs = (clone $base)
            ->with([
                'generator:id,name',
                'reviewer:id,name',
                'approver:id,name',
                'finalizer:id,name',
                'returnedBy:id,name',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->whereYear('period_start', (int) $year)
            ->latest('period_start')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PayrollRun $run) => $this->runPayload($run));
        $statusCounts = PayrollRun::query()
            ->whereYear('period_start', (int) $year)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('PayrollCore/Index', [
            'runs' => $runs,
            'filters' => [
                'status' => $status,
                'year' => $year,
            ],
            'statistics' => [
                'total' => (int) $statusCounts->sum(),
                'draft' => (int) ($statusCounts['draft'] ?? 0),
                'waiting_approval' => (int) ($statusCounts['hr_reviewed'] ?? 0),
                'finalized' => (int) ($statusCounts['finalized'] ?? 0),
                'finalized_net_pay' => round((float) PayrollRun::query()
                    ->whereYear('period_start', (int) $year)
                    ->where('status', 'finalized')
                    ->sum('total_net_pay'), 2),
            ],
            'salaryProfileCount' => EmployeeSalaryProfile::query()
                ->where('is_active', true)
                ->count(),
            'permissions' => [
                'can_manage' => $request->user()->hasPermission('payroll.manage'),
                'can_approve' => $request->user()->hasPermission('payroll.approve'),
                'can_configure' => $request->user()->hasPermission('payroll.settings'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);
        $periodStart = Carbon::createFromFormat('Y-m', $validated['period'])
            ->startOfMonth();

        if (
            PayrollRun::query()
                ->whereDate('period_start', $periodStart)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'period' => 'Payroll bagi bulan ini telah wujud.',
            ]);
        }

        $run = DB::transaction(function () use ($request, $periodStart) {
            $run = PayrollRun::query()->create([
                'period_start' => $periodStart,
                'status' => 'draft',
                'generated_at' => now(),
                'generated_by' => $request->user()->getAuthIdentifier(),
            ]);

            return $this->calculator->recalculate($run, $request->user());
        });

        AuditLogger::record(
            $request,
            'payroll.generated',
            'payroll_runs',
            $run->getKey(),
            newValues: [
                'period_start' => $run->period_start?->toDateString(),
                'status' => $run->status,
                'employee_count' => $run->employee_count,
                'total_net_pay' => $run->total_net_pay,
            ],
        );

        return to_route('payroll.show', $run)->with('toast', [
            'type' => 'success',
            'message' => 'Payroll bulanan berjaya dijana sebagai Draf.',
        ]);
    }

    public function show(Request $request, PayrollRun $payrollRun): Response
    {
        $search = $request->string('search')->trim()->toString();
        $entries = $payrollRun->entries()
            ->with([
                'items' => fn ($query) => $query->orderBy('type')->orderBy('id'),
                'statutorySnapshot',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('employee_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PayrollEntry $entry) => $this->entryPayload($entry));
        $payrollRun->load([
            'generator:id,name',
            'reviewer:id,name',
            'approver:id,name',
            'finalizer:id,name',
            'returnedBy:id,name',
        ]);

        return Inertia::render('PayrollCore/Show', [
            'payrollRun' => $this->runPayload($payrollRun),
            'entries' => $entries,
            'filters' => ['search' => $search],
            'statistics' => [
                'negative_net_pay' => $payrollRun->entries()
                    ->where('net_pay', '<', 0)
                    ->count(),
                'with_overtime' => $payrollRun->entries()
                    ->where('overtime_amount', '>', 0)
                    ->count(),
                'with_unpaid_leave' => $payrollRun->entries()
                    ->where('unpaid_leave_amount', '>', 0)
                    ->count(),
                'with_claims' => $payrollRun->entries()
                    ->where('claim_reimbursements', '>', 0)
                    ->count(),
            ],
            'manualComponents' => PayrollComponent::query()
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                    'type',
                    'is_epf_wage',
                    'is_socso_wage',
                    'is_eis_wage',
                    'is_pcb_wage',
                ]),
            'permissions' => [
                'can_manage' => $request->user()->hasPermission('payroll.manage'),
                'can_approve' => $request->user()->hasPermission('payroll.approve'),
            ],
        ]);
    }

    public function recalculate(
        Request $request,
        PayrollRun $payrollRun,
    ): RedirectResponse {
        $oldValues = $payrollRun->only([
            'employee_count',
            'total_earnings',
            'total_deductions',
            'total_net_pay',
        ]);
        $payrollRun = $this->calculator->recalculate(
            $payrollRun,
            $request->user(),
        );

        AuditLogger::record(
            $request,
            'payroll.recalculated',
            'payroll_runs',
            $payrollRun->getKey(),
            oldValues: $oldValues,
            newValues: $payrollRun->only([
                'employee_count',
                'total_earnings',
                'total_deductions',
                'total_net_pay',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Payroll dikira semula menggunakan data terkini.',
        ]);
    }

    public function storeManualItem(
        Request $request,
        PayrollRun $payrollRun,
        PayrollEntry $entry,
    ): RedirectResponse {
        $this->ensureDraftEntry($payrollRun, $entry);
        $validated = $request->validate([
            'payroll_component_id' => [
                'nullable',
                'integer',
                'exists:payroll_components,id',
            ],
            'name' => ['required_without:payroll_component_id', 'nullable', 'string', 'max:150'],
            'type' => ['required_without:payroll_component_id', 'nullable', Rule::in(['earning', 'deduction'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_epf_wage' => ['nullable', 'boolean'],
            'is_socso_wage' => ['nullable', 'boolean'],
            'is_eis_wage' => ['nullable', 'boolean'],
            'is_pcb_wage' => ['nullable', 'boolean'],
        ]);
        $component = isset($validated['payroll_component_id'])
            ? PayrollComponent::query()
                ->whereKey($validated['payroll_component_id'])
                ->where('is_active', true)
                ->first()
            : null;

        if (isset($validated['payroll_component_id']) && ! $component) {
            throw ValidationException::withMessages([
                'payroll_component_id' => 'Komponen payroll tidak aktif atau tidak sah.',
            ]);
        }

        $name = $component?->name ?? trim((string) $validated['name']);
        $type = $component?->type ?? $validated['type'];
        $code = $component?->code ?? Str::upper(Str::slug($name, '_'));

        if ($code === '') {
            $code = 'MANUAL';
        }
        $item = $entry->items()->create([
            'payroll_component_id' => $component?->getKey(),
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'category' => 'manual',
            'quantity' => 1,
            'rate' => $validated['amount'],
            'multiplier' => 1,
            'amount' => round((float) $validated['amount'], 2),
            'is_manual' => true,
            'is_epf_wage' => $component?->is_epf_wage
                ?? (bool) ($validated['is_epf_wage'] ?? false),
            'is_socso_wage' => $component?->is_socso_wage
                ?? (bool) ($validated['is_socso_wage'] ?? false),
            'is_eis_wage' => $component?->is_eis_wage
                ?? (bool) ($validated['is_eis_wage'] ?? false),
            'is_pcb_wage' => $component?->is_pcb_wage
                ?? (bool) ($validated['is_pcb_wage'] ?? false),
            'notes' => $validated['notes'] ?? null,
            'added_by' => $request->user()->getAuthIdentifier(),
        ]);
        $entry = $this->calculator->refreshStatutory(
            $entry,
            $request->user(),
        );

        AuditLogger::record(
            $request,
            'payroll.manual_item_added',
            'payroll_entry_items',
            $item->getKey(),
            newValues: [
                'employee_id' => $entry->employee_id,
                'payroll_run_id' => $payrollRun->getKey(),
                'name' => $item->name,
                'type' => $item->type,
                'amount' => $item->amount,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pelarasan payroll telah ditambah.',
        ]);
    }

    public function destroyManualItem(
        Request $request,
        PayrollRun $payrollRun,
        PayrollEntry $entry,
        PayrollEntryItem $item,
    ): RedirectResponse {
        $this->ensureDraftEntry($payrollRun, $entry);
        abort_unless(
            $item->payroll_entry_id === $entry->getKey() && $item->is_manual,
            404,
        );
        $oldValues = [
            'employee_id' => $entry->employee_id,
            'payroll_run_id' => $payrollRun->getKey(),
            ...$item->only(['name', 'type', 'amount', 'notes']),
        ];
        $itemId = $item->getKey();
        $item->delete();
        $this->calculator->refreshStatutory($entry, $request->user());

        AuditLogger::record(
            $request,
            'payroll.manual_item_removed',
            'payroll_entry_items',
            $itemId,
            oldValues: $oldValues,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pelarasan payroll telah dibuang.',
        ]);
    }

    public function review(
        Request $request,
        PayrollRun $payrollRun,
    ): RedirectResponse {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $payrollRun = DB::transaction(function () use (
            $request,
            $payrollRun,
            $validated,
        ) {
            $locked = PayrollRun::query()
                ->lockForUpdate()
                ->findOrFail($payrollRun->getKey());
            $this->ensureStatus($locked, 'draft');

            if ($locked->entries()->doesntExist()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Payroll tidak mempunyai rekod pekerja.',
                ]);
            }

            if ($locked->entries()->whereDoesntHave('statutorySnapshot')->exists()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Kira semula payroll untuk menjana snapshot KWSP, PERKESO, EIS dan PCB sebelum semakan HR.',
                ]);
            }

            if ($locked->entries()->where('net_pay', '<', 0)->exists()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Semakan tidak boleh diselesaikan kerana terdapat gaji bersih negatif.',
                ]);
            }

            if (
                ClaimRequest::query()
                    ->where('status', 'approved')
                    ->whereNull('paid_at')
                    ->whereDate('scheduled_payroll_period', $locked->period_start)
                    ->where(function ($query) use ($locked) {
                        $query->whereNull('payroll_run_id')
                            ->orWhere('payroll_run_id', '!=', $locked->getKey());
                    })
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'payroll' => 'Masih ada tuntutan berjadual yang belum dimasukkan. Kira semula payroll sebelum semakan HR.',
                ]);
            }

            $locked->update([
                'status' => 'hr_reviewed',
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->getAuthIdentifier(),
                'review_notes' => $validated['notes'] ?? null,
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            'payroll.hr_reviewed',
            'payroll_runs',
            $payrollRun->getKey(),
            oldValues: ['status' => 'draft'],
            newValues: [
                'status' => 'hr_reviewed',
                'review_notes' => $payrollRun->review_notes,
                'total_net_pay' => $payrollRun->total_net_pay,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Semakan HR selesai dan payroll dihantar kepada pelulus.',
        ]);
    }

    public function approve(
        Request $request,
        PayrollRun $payrollRun,
    ): RedirectResponse {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $payrollRun = DB::transaction(function () use (
            $request,
            $payrollRun,
            $validated,
        ) {
            $locked = PayrollRun::query()
                ->lockForUpdate()
                ->findOrFail($payrollRun->getKey());
            $this->ensureStatus($locked, 'hr_reviewed');
            $locked->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->getAuthIdentifier(),
                'approval_notes' => $validated['notes'] ?? null,
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            'payroll.approved',
            'payroll_runs',
            $payrollRun->getKey(),
            oldValues: ['status' => 'hr_reviewed'],
            newValues: [
                'status' => 'approved',
                'approval_notes' => $payrollRun->approval_notes,
                'total_net_pay' => $payrollRun->total_net_pay,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Payroll telah diluluskan.',
        ]);
    }

    public function finalize(
        Request $request,
        PayrollRun $payrollRun,
    ): RedirectResponse {
        $payrollRun = DB::transaction(function () use ($request, $payrollRun) {
            $locked = PayrollRun::query()
                ->lockForUpdate()
                ->findOrFail($payrollRun->getKey());
            $this->ensureStatus($locked, 'approved');
            $locked->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => $request->user()->getAuthIdentifier(),
            ]);
            ClaimRequest::query()
                ->where('payroll_run_id', $locked->getKey())
                ->where('status', 'approved')
                ->whereNull('paid_at')
                ->update(['paid_at' => now()]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            'payroll.finalized',
            'payroll_runs',
            $payrollRun->getKey(),
            oldValues: ['status' => 'approved'],
            newValues: [
                'status' => 'finalized',
                'employee_count' => $payrollRun->employee_count,
                'total_net_pay' => $payrollRun->total_net_pay,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Payroll telah dimuktamadkan dan dikunci.',
        ]);
    }

    public function returnToDraft(
        Request $request,
        PayrollRun $payrollRun,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        [$payrollRun, $oldStatus] = DB::transaction(function () use (
            $request,
            $payrollRun,
            $validated,
        ) {
            $locked = PayrollRun::query()
                ->lockForUpdate()
                ->findOrFail($payrollRun->getKey());

            if (! in_array($locked->status, ['hr_reviewed', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'Hanya payroll yang belum dimuktamadkan boleh dikembalikan ke Draf.',
                ]);
            }

            $oldStatus = $locked->status;
            $locked->update([
                'status' => 'draft',
                'returned_to_draft_at' => now(),
                'returned_to_draft_by' => $request->user()->getAuthIdentifier(),
                'return_reason' => $validated['reason'],
                'reviewed_at' => null,
                'reviewed_by' => null,
                'review_notes' => null,
                'approved_at' => null,
                'approved_by' => null,
                'approval_notes' => null,
            ]);

            return [$locked, $oldStatus];
        });

        AuditLogger::record(
            $request,
            'payroll.returned_to_draft',
            'payroll_runs',
            $payrollRun->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: [
                'status' => 'draft',
                'return_reason' => $validated['reason'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Payroll dikembalikan ke Draf untuk pembetulan.',
        ]);
    }

    public function reportCsv(PayrollRun $payrollRun): StreamedResponse
    {
        $entries = $payrollRun->entries()
            ->with('statutorySnapshot')
            ->orderBy('employee_name')
            ->get();
        $filename = 'payroll-'.$payrollRun->period_start->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($entries) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'ID Pekerja',
                'Nama',
                'Gaji Asas',
                'Jam OT',
                'Jumlah OT',
                'Bayaran Balik Tuntutan',
                'Hari Cuti Tanpa Gaji',
                'Potongan Cuti Tanpa Gaji',
                'Pendapatan Kasar',
                'KWSP Pekerja',
                'KWSP Majikan',
                'PERKESO/SKBBK Pekerja',
                'PERKESO/SKBBK Majikan',
                'EIS Pekerja',
                'EIS Majikan',
                'PCB',
                'Jumlah Caruman Majikan',
                'Jumlah Potongan',
                'Gaji Bersih',
            ]);

            foreach ($entries as $entry) {
                fputcsv($stream, [
                    $entry->employee_number,
                    $entry->employee_name,
                    number_format((float) $entry->basic_salary, 2, '.', ''),
                    number_format($entry->overtime_minutes / 60, 2, '.', ''),
                    number_format((float) $entry->overtime_amount, 2, '.', ''),
                    number_format((float) $entry->claim_reimbursements, 2, '.', ''),
                    number_format((float) $entry->unpaid_leave_days, 1, '.', ''),
                    number_format((float) $entry->unpaid_leave_amount, 2, '.', ''),
                    number_format((float) $entry->gross_pay, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->kwsp_employee, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->kwsp_employer, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->socso_employee, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->socso_employer, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->eis_employee, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->eis_employer, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->pcb, 2, '.', ''),
                    number_format((float) $entry->statutorySnapshot?->total_employer_contributions, 2, '.', ''),
                    number_format((float) $entry->total_deductions, 2, '.', ''),
                    number_format((float) $entry->net_pay, 2, '.', ''),
                ]);
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateStatutory(
        Request $request,
        PayrollRun $payrollRun,
        PayrollEntry $entry,
    ): RedirectResponse {
        $this->ensureDraftEntry($payrollRun, $entry);
        $validated = $request->validate([
            'kwsp_employee' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'kwsp_employer' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'socso_employee' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'socso_employer' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'eis_employee' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'eis_employer' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'pcb' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $oldValues = $entry->statutorySnapshot?->only([
            'kwsp_employee',
            'kwsp_employer',
            'socso_employee',
            'socso_employer',
            'eis_employee',
            'eis_employer',
            'pcb',
        ]) ?? [];
        $entry = $this->calculator->refreshStatutory(
            $entry,
            $request->user(),
            $validated,
        );

        AuditLogger::record(
            $request,
            'payroll.statutory_overridden',
            'payroll_statutory_snapshots',
            (int) $entry->statutorySnapshot?->getKey(),
            oldValues: $oldValues,
            newValues: [
                ...($entry->statutorySnapshot?->only([
                    'kwsp_employee',
                    'kwsp_employer',
                    'socso_employee',
                    'socso_employer',
                    'eis_employee',
                    'eis_employer',
                    'pcb',
                ]) ?? []),
                'employee_id' => $entry->employee_id,
                'notes' => $validated['notes'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Amaun statutori pekerja telah dilaras dan dikira semula.',
        ]);
    }

    private function ensureDraftEntry(
        PayrollRun $payrollRun,
        PayrollEntry $entry,
    ): void {
        $payrollRun->refresh();
        $entry->refresh();
        abort_unless($entry->payroll_run_id === $payrollRun->getKey(), 404);
        $this->ensureStatus($payrollRun, 'draft');
    }

    private function ensureStatus(PayrollRun $payrollRun, string $status): void
    {
        if ($payrollRun->status !== $status) {
            throw ValidationException::withMessages([
                'payroll' => "Payroll mesti berstatus {$status} untuk tindakan ini.",
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(PayrollRun $run): array
    {
        return [
            'id' => $run->getKey(),
            'period_start' => $run->period_start?->toDateString(),
            'period_label' => $run->period_start?->translatedFormat('F Y'),
            'status' => $run->status,
            'currency' => $run->currency,
            'employee_count' => $run->employee_count,
            'total_basic_salary' => (float) $run->total_basic_salary,
            'total_earnings' => (float) $run->total_earnings,
            'total_deductions' => (float) $run->total_deductions,
            'total_net_pay' => (float) $run->total_net_pay,
            'total_employee_statutory' => (float) $run->total_employee_statutory,
            'total_employer_statutory' => (float) $run->total_employer_statutory,
            'total_pcb' => (float) $run->total_pcb,
            'generated_at' => $run->generated_at?->toIso8601String(),
            'generated_by' => $run->generator?->name,
            'reviewed_at' => $run->reviewed_at?->toIso8601String(),
            'reviewed_by' => $run->reviewer?->name,
            'review_notes' => $run->review_notes,
            'approved_at' => $run->approved_at?->toIso8601String(),
            'approved_by' => $run->approver?->name,
            'approval_notes' => $run->approval_notes,
            'finalized_at' => $run->finalized_at?->toIso8601String(),
            'finalized_by' => $run->finalizer?->name,
            'returned_to_draft_at' => $run->returned_to_draft_at?->toIso8601String(),
            'returned_to_draft_by' => $run->returnedBy?->name,
            'return_reason' => $run->return_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entryPayload(PayrollEntry $entry): array
    {
        return [
            'id' => $entry->getKey(),
            'employee_id' => $entry->employee_id,
            'employee_number' => $entry->employee_number,
            'employee_name' => $entry->employee_name,
            'basic_salary' => (float) $entry->basic_salary,
            'overtime_minutes' => $entry->overtime_minutes,
            'overtime_amount' => (float) $entry->overtime_amount,
            'claim_reimbursements' => (float) $entry->claim_reimbursements,
            'unpaid_leave_days' => (float) $entry->unpaid_leave_days,
            'unpaid_leave_amount' => (float) $entry->unpaid_leave_amount,
            'recurring_earnings' => (float) $entry->recurring_earnings,
            'recurring_deductions' => (float) $entry->recurring_deductions,
            'manual_earnings' => (float) $entry->manual_earnings,
            'manual_deductions' => (float) $entry->manual_deductions,
            'gross_pay' => (float) $entry->gross_pay,
            'total_deductions' => (float) $entry->total_deductions,
            'net_pay' => (float) $entry->net_pay,
            'calculated_at' => $entry->calculated_at?->toIso8601String(),
            'statutory' => $entry->statutorySnapshot
                ? [
                    'id' => $entry->statutorySnapshot->getKey(),
                    'kwsp_category' => $entry->statutorySnapshot->kwsp_category,
                    'socso_category' => $entry->statutorySnapshot->socso_category,
                    'epf_wages' => (float) $entry->statutorySnapshot->epf_wages,
                    'socso_wages' => (float) $entry->statutorySnapshot->socso_wages,
                    'eis_wages' => (float) $entry->statutorySnapshot->eis_wages,
                    'pcb_wages' => (float) $entry->statutorySnapshot->pcb_wages,
                    'kwsp_employee' => (float) $entry->statutorySnapshot->kwsp_employee,
                    'kwsp_employer' => (float) $entry->statutorySnapshot->kwsp_employer,
                    'socso_employee' => (float) $entry->statutorySnapshot->socso_employee,
                    'socso_employer' => (float) $entry->statutorySnapshot->socso_employer,
                    'eis_employee' => (float) $entry->statutorySnapshot->eis_employee,
                    'eis_employer' => (float) $entry->statutorySnapshot->eis_employer,
                    'pcb' => (float) $entry->statutorySnapshot->pcb,
                    'total_employee_deductions' => (float) $entry->statutorySnapshot->total_employee_deductions,
                    'total_employer_contributions' => (float) $entry->statutorySnapshot->total_employer_contributions,
                    'rate_version' => $entry->statutorySnapshot->rate_version,
                    'is_overridden' => $entry->statutorySnapshot->is_overridden,
                    'override_notes' => $entry->statutorySnapshot->override_notes,
                ]
                : null,
            'items' => $entry->items->map(fn (PayrollEntryItem $item) => [
                'id' => $item->getKey(),
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'category' => $item->category,
                'quantity' => $item->quantity !== null
                    ? (float) $item->quantity
                    : null,
                'rate' => $item->rate !== null ? (float) $item->rate : null,
                'multiplier' => $item->multiplier !== null
                    ? (float) $item->multiplier
                    : null,
                'amount' => (float) $item->amount,
                'is_manual' => $item->is_manual,
                'notes' => $item->notes,
            ]),
        ];
    }
}
