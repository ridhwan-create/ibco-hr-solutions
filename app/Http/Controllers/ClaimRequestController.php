<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewClaimRequest;
use App\Models\ClaimApprovalAssignment;
use App\Models\ClaimAttachment;
use App\Models\ClaimNotification;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\PayrollRun;
use App\Support\AuditLogger;
use App\Support\ClaimLimitResolver;
use App\Support\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimRequestController extends Controller
{
    public function __construct(
        private readonly ClaimLimitResolver $limitResolver,
        private readonly PayrollCalculator $payrollCalculator,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $query = $this->filteredQuery($request, $filters);
        $claims = (clone $query)
            ->with([
                'claimType:id,code,name,allow_payroll_reimbursement',
                'attachments:id,claim_request_id,original_name,mime_type,size',
                'supervisorReviewer:id,name',
                'reviewer:id,name',
                'payrollRun:id,period_start,status',
            ])
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();
        $employeeMap = $this->employeeMap(
            collect($claims->items())->pluck('employee_id')->all(),
        );
        $claims->through(
            fn (ClaimRequest $claim) => $this->requestPayload(
                $claim,
                $employeeMap,
            ),
        );

        $visibleBase = $this->visibleQuery($request);
        $statusCounts = (clone $visibleBase)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $stageCounts = (clone $visibleBase)
            ->where('status', 'pending')
            ->selectRaw('approval_stage, COUNT(*) as aggregate')
            ->groupBy('approval_stage')
            ->pluck('aggregate', 'approval_stage');
        $month = Carbon::createFromFormat('Y-m', $filters['month']);
        $approvedAmount = (clone $visibleBase)
            ->where('status', 'approved')
            ->whereYear('expense_date', $month->year)
            ->whereMonth('expense_date', $month->month)
            ->sum('approved_amount');
        $scheduledAmount = (clone $visibleBase)
            ->where('status', 'approved')
            ->whereNotNull('scheduled_payroll_period')
            ->whereNull('paid_at')
            ->sum('approved_amount');
        $report = (clone $query)
            ->selectRaw(
                'claim_type_id, status, COUNT(*) as total, '
                .'SUM(requested_amount) as requested_amount, '
                .'SUM(COALESCE(approved_amount, 0)) as approved_amount',
            )
            ->groupBy('claim_type_id', 'status')
            ->get();
        $typeMap = ClaimType::query()->pluck('name', 'id');

        return Inertia::render('ClaimRequests/Index', [
            'requests' => $claims,
            'filters' => $filters,
            'statistics' => [
                'total' => (int) $statusCounts->sum(),
                'pending_supervisor' => (int) ($stageCounts['supervisor'] ?? 0),
                'pending_finance' => (int) ($stageCounts['finance'] ?? 0),
                'approved' => (int) ($statusCounts['approved'] ?? 0),
                'approved_amount' => round((float) $approvedAmount, 2),
                'scheduled_amount' => round((float) $scheduledAmount, 2),
            ],
            'claimTypes' => ClaimType::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => $this->departments(),
            'reportByType' => $report->map(fn ($row) => [
                'claim_type' => $typeMap[$row->claim_type_id] ?? 'Tidak diketahui',
                'status' => $row->status,
                'total' => (int) $row->total,
                'requested_amount' => round((float) $row->requested_amount, 2),
                'approved_amount' => round((float) $row->approved_amount, 2),
            ]),
            'payrollPeriods' => PayrollRun::query()
                ->where('status', 'draft')
                ->latest('period_start')
                ->limit(12)
                ->get(['id', 'period_start'])
                ->map(fn (PayrollRun $run) => [
                    'id' => $run->getKey(),
                    'period' => $run->period_start?->format('Y-m'),
                    'label' => $run->period_start?->translatedFormat('F Y'),
                ]),
            'permissions' => [
                'can_supervise' => $request->user()->hasPermission('claims.supervise'),
                'can_manage' => $request->user()->hasPermission('claims.manage'),
            ],
        ]);
    }

    public function supervisorReview(
        Request $request,
        ClaimRequest $claimRequest,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('claims.supervise'), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:status,rejected',
            ],
        ]);

        $claimRequest = DB::transaction(function () use (
            $request,
            $claimRequest,
            $validated,
        ) {
            $locked = ClaimRequest::query()
                ->lockForUpdate()
                ->findOrFail($claimRequest->getKey());

            if (
                $locked->status !== 'pending'
                || $locked->approval_stage !== 'supervisor'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Tuntutan ini bukan lagi di peringkat penyelia.',
                ]);
            }

            $assigned = ClaimApprovalAssignment::query()
                ->where('department_id', $locked->department_id)
                ->where('approver_user_id', $request->user()->getAuthIdentifier())
                ->where('is_active', true)
                ->exists();
            abort_unless(
                $assigned || $request->user()->hasPermission('claims.manage'),
                403,
            );
            $supported = $validated['status'] === 'approved';
            $locked->update([
                'status' => $supported ? 'pending' : 'rejected',
                'approval_stage' => $supported ? 'finance' : 'completed',
                'supervisor_reviewed_at' => now(),
                'supervisor_reviewed_by' => $request->user()->getAuthIdentifier(),
                'supervisor_review_notes' => $validated['review_notes'] ?? null,
            ]);
            ClaimNotification::query()->create([
                'user_id' => $locked->user_id,
                'claim_request_id' => $locked->getKey(),
                'title' => $supported
                    ? 'Tuntutan disokong penyelia'
                    : 'Tuntutan ditolak penyelia',
                'message' => $supported
                    ? 'Tuntutan anda dihantar kepada HR/Kewangan untuk kelulusan akhir.'
                    : 'Tuntutan anda tidak disokong oleh penyelia.',
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            $claimRequest->status === 'pending'
                ? 'claim.supervisor_approved'
                : 'claim.supervisor_rejected',
            'claim_requests',
            $claimRequest->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => 'supervisor'],
            newValues: [
                'employee_id' => $claimRequest->employee_id,
                'status' => $claimRequest->status,
                'approval_stage' => $claimRequest->approval_stage,
                'review_notes' => $claimRequest->supervisor_review_notes,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $claimRequest->status === 'pending'
                ? 'Tuntutan disokong dan dihantar kepada HR/Kewangan.'
                : 'Tuntutan telah ditolak.',
        ]);
    }

    public function review(
        ReviewClaimRequest $request,
        ClaimRequest $claimRequest,
    ): RedirectResponse {
        $claimRequest->loadMissing('claimType');
        $validated = $request->validated();
        $approved = $validated['status'] === 'approved';
        $approvedAmount = $approved
            ? round((float) $validated['approved_amount'], 2)
            : null;

        if ($approved && $approvedAmount > (float) $claimRequest->requested_amount) {
            throw ValidationException::withMessages([
                'approved_amount' => 'Amaun diluluskan tidak boleh melebihi amaun dipohon.',
            ]);
        }

        if (
            $approved
            && ! $claimRequest->claimType?->allow_payroll_reimbursement
            && ! empty($validated['scheduled_payroll_period'])
        ) {
            throw ValidationException::withMessages([
                'scheduled_payroll_period' => 'Jenis tuntutan ini tidak dibenarkan masuk ke payroll.',
            ]);
        }

        $scheduledPeriod = $approved
            ? $this->validatedPayrollPeriod(
                $validated['scheduled_payroll_period'] ?? null,
            )
            : null;
        $positionId = $claimRequest->position_id;
        $limits = $this->limitResolver->resolve(
            $claimRequest->claimType,
            $claimRequest->employee_id,
            $positionId,
        );
        $usage = $this->limitResolver->usage(
            $claimRequest->employee_id,
            $claimRequest->claim_type_id,
            $claimRequest->expense_date,
            $claimRequest->getKey(),
        );

        if ($approved) {
            $this->limitResolver->assertAmountAllowed(
                $approvedAmount,
                $limits,
                $usage,
                'approved_amount',
            );
        }

        $claimRequest = DB::transaction(function () use (
            $request,
            $claimRequest,
            $approved,
            $approvedAmount,
            $scheduledPeriod,
            $validated,
        ) {
            $locked = ClaimRequest::query()
                ->lockForUpdate()
                ->findOrFail($claimRequest->getKey());

            if (
                $locked->status !== 'pending'
                || ! in_array($locked->approval_stage, ['finance', null], true)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Tuntutan ini belum sampai kepada HR/Kewangan atau telah selesai.',
                ]);
            }

            $locked->update([
                'status' => $approved ? 'approved' : 'rejected',
                'approval_stage' => 'completed',
                'approved_amount' => $approvedAmount,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->getAuthIdentifier(),
                'review_notes' => $validated['review_notes'] ?? null,
                'scheduled_payroll_period' => $scheduledPeriod,
            ]);
            ClaimNotification::query()->create([
                'user_id' => $locked->user_id,
                'claim_request_id' => $locked->getKey(),
                'title' => $approved
                    ? 'Tuntutan diluluskan'
                    : 'Tuntutan ditolak',
                'message' => $approved
                    ? 'HR/Kewangan meluluskan RM'
                        .number_format($approvedAmount, 2)
                        .($scheduledPeriod
                            ? ' untuk dimasukkan ke payroll.'
                            : '.')
                    : 'HR/Kewangan telah menolak tuntutan anda.',
            ]);
            $this->recalculateScheduledDraft($scheduledPeriod, $request);

            return $locked;
        });

        AuditLogger::record(
            $request,
            $claimRequest->status === 'approved'
                ? 'claim.approved'
                : 'claim.rejected',
            'claim_requests',
            $claimRequest->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => 'finance'],
            newValues: [
                'employee_id' => $claimRequest->employee_id,
                'status' => $claimRequest->status,
                'approved_amount' => $claimRequest->approved_amount,
                'scheduled_payroll_period' => $claimRequest
                    ->scheduled_payroll_period?->toDateString(),
                'review_notes' => $claimRequest->review_notes,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $claimRequest->status === 'approved'
                ? 'Tuntutan telah diluluskan.'
                : 'Tuntutan telah ditolak.',
        ]);
    }

    public function schedulePayroll(
        Request $request,
        ClaimRequest $claimRequest,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('claims.manage'), 403);
        $validated = $request->validate([
            'scheduled_payroll_period' => ['nullable', 'date_format:Y-m'],
        ]);

        if ($claimRequest->status !== 'approved' || $claimRequest->paid_at) {
            throw ValidationException::withMessages([
                'scheduled_payroll_period' => 'Hanya tuntutan diluluskan yang belum dibayar boleh dijadualkan.',
            ]);
        }
        $claimRequest->loadMissing('claimType');

        if (! $claimRequest->claimType?->allow_payroll_reimbursement) {
            throw ValidationException::withMessages([
                'scheduled_payroll_period' => 'Jenis tuntutan ini tidak dibenarkan masuk ke payroll.',
            ]);
        }

        $oldPeriod = $claimRequest->scheduled_payroll_period?->toDateString();
        $period = $this->validatedPayrollPeriod(
            $validated['scheduled_payroll_period'] ?? null,
        );
        DB::transaction(function () use (
            $claimRequest,
            $period,
            $oldPeriod,
            $request,
        ) {
            $claimRequest->update([
                'scheduled_payroll_period' => $period,
                'payroll_run_id' => null,
            ]);
            $this->recalculateScheduledDraft($oldPeriod, $request);

            if ($period !== $oldPeriod) {
                $this->recalculateScheduledDraft($period, $request);
            }
        });

        AuditLogger::record(
            $request,
            'claim.payroll_scheduled',
            'claim_requests',
            $claimRequest->getKey(),
            oldValues: ['scheduled_payroll_period' => $oldPeriod],
            newValues: [
                'employee_id' => $claimRequest->employee_id,
                'scheduled_payroll_period' => $period,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $period
                ? 'Bulan bayaran tuntutan telah dikemas kini.'
                : 'Tuntutan dikeluarkan daripada jadual payroll.',
        ]);
    }

    public function cancelApproved(
        Request $request,
        ClaimRequest $claimRequest,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('claims.manage'), 403);
        $validated = $request->validate([
            'cancellation_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($claimRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'cancellation_notes' => 'Hanya tuntutan diluluskan boleh dibatalkan.',
            ]);
        }

        if ($claimRequest->paid_at) {
            throw ValidationException::withMessages([
                'cancellation_notes' => 'Tuntutan telah dibayar melalui payroll dan tidak boleh dibatalkan.',
            ]);
        }

        $run = $claimRequest->payrollRun;

        if ($run && $run->status !== 'draft') {
            throw ValidationException::withMessages([
                'cancellation_notes' => 'Kembalikan payroll berkaitan ke Draf sebelum membatalkan tuntutan.',
            ]);
        }

        $oldValues = $claimRequest->only([
            'status',
            'approved_amount',
            'scheduled_payroll_period',
            'payroll_run_id',
        ]);
        DB::transaction(function () use (
            $claimRequest,
            $validated,
            $run,
            $request,
        ) {
            $claimRequest->update([
                'status' => 'cancelled',
                'approved_amount' => null,
                'scheduled_payroll_period' => null,
                'payroll_run_id' => null,
                'review_notes' => trim(
                    ($claimRequest->review_notes
                        ? $claimRequest->review_notes."\n\n"
                        : '')
                    .'Pembatalan HR/Kewangan: '.$validated['cancellation_notes'],
                ),
            ]);
            ClaimNotification::query()->create([
                'user_id' => $claimRequest->user_id,
                'claim_request_id' => $claimRequest->getKey(),
                'title' => 'Kelulusan tuntutan dibatalkan',
                'message' => 'HR/Kewangan membatalkan tuntutan yang sebelum ini diluluskan.',
            ]);

            if ($run) {
                $this->payrollCalculator->recalculate(
                    $run,
                    $request->user(),
                );
            }
        });

        AuditLogger::record(
            $request,
            'claim.approved_cancelled',
            'claim_requests',
            $claimRequest->getKey(),
            oldValues: $oldValues,
            newValues: [
                'employee_id' => $claimRequest->employee_id,
                'status' => 'cancelled',
                'cancellation_notes' => $validated['cancellation_notes'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kelulusan tuntutan telah dibatalkan.',
        ]);
    }

    public function downloadAttachment(
        Request $request,
        ClaimRequest $claimRequest,
        ClaimAttachment $attachment,
    ): StreamedResponse {
        abort_unless(
            $attachment->claim_request_id === $claimRequest->getKey(),
            404,
        );
        $canManage = $request->user()->hasPermission('claims.manage');
        $canSupervise = $request->user()->hasPermission('claims.supervise')
            && ClaimApprovalAssignment::query()
                ->where('department_id', $claimRequest->department_id)
                ->where('approver_user_id', $request->user()->getAuthIdentifier())
                ->where('is_active', true)
                ->exists();
        abort_unless($canManage || $canSupervise, 403);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Fail resit tidak dijumpai.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    public function reportCsv(Request $request): StreamedResponse
    {
        abort_unless(
            $request->user()->hasPermission('claims.manage')
                || $request->user()->hasPermission('claims.supervise'),
            403,
        );
        $filters = $this->validatedFilters($request);
        $rows = $this->filteredQuery($request, $filters)
            ->with('claimType:id,name')
            ->orderByDesc('expense_date')
            ->limit(5000)
            ->get();
        $employees = $this->employeeMap($rows->pluck('employee_id')->all());

        AuditLogger::record(
            $request,
            'claim.report_exported',
            'claim_requests',
            'export',
            newValues: $filters,
        );

        return response()->streamDownload(function () use ($rows, $employees) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, [
                'ID Pekerja',
                'Nama',
                'Jenis Tuntutan',
                'Tarikh Belanja',
                'Peniaga',
                'Nombor Resit',
                'Amaun Dipohon',
                'Amaun Diluluskan',
                'Status',
                'Peringkat',
                'Bulan Payroll',
                'Status Bayaran',
            ]);

            foreach ($rows as $claim) {
                $employee = $employees[(string) $claim->employee_id] ?? [];
                fputcsv($stream, [
                    $employee['employee_number'] ?? $claim->employee_id,
                    $employee['employee_name'] ?? 'Tidak dijumpai',
                    $claim->claimType?->name,
                    $claim->expense_date?->format('Y-m-d'),
                    $claim->merchant_name,
                    $claim->receipt_number,
                    number_format((float) $claim->requested_amount, 2, '.', ''),
                    $claim->approved_amount === null
                        ? ''
                        : number_format((float) $claim->approved_amount, 2, '.', ''),
                    $claim->status,
                    $claim->approval_stage,
                    $claim->scheduled_payroll_period?->format('Y-m'),
                    $claim->paid_at ? 'Dibayar' : (
                        $claim->scheduled_payroll_period ? 'Dijadualkan' : ''
                    ),
                ]);
            }

            fclose($stream);
        }, 'laporan-tuntutan-'.$filters['month'].'.csv');
    }

    /**
     * @return array<string, string>
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(ClaimRequest::STATUSES)],
            'stage' => ['nullable', Rule::in(['supervisor', 'finance', 'completed'])],
            'claim_type_id' => ['nullable', 'integer', 'exists:claim_types,id'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            'month' => $validated['month'] ?? now()->format('Y-m'),
            'status' => $validated['status'] ?? '',
            'stage' => $validated['stage'] ?? '',
            'claim_type_id' => isset($validated['claim_type_id'])
                ? (string) $validated['claim_type_id']
                : '',
            'department_id' => isset($validated['department_id'])
                ? (string) $validated['department_id']
                : '',
            'search' => trim((string) ($validated['search'] ?? '')),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filteredQuery(Request $request, array $filters): Builder
    {
        $month = Carbon::createFromFormat('Y-m', $filters['month']);
        $query = $this->visibleQuery($request)
            ->whereYear('expense_date', $month->year)
            ->whereMonth('expense_date', $month->month)
            ->when(
                $filters['status'] !== '',
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['stage'] !== '',
                fn ($query) => $query->where('approval_stage', $filters['stage']),
            )
            ->when(
                $filters['claim_type_id'] !== '',
                fn ($query) => $query->where(
                    'claim_type_id',
                    (int) $filters['claim_type_id'],
                ),
            )
            ->when(
                $filters['department_id'] !== '',
                fn ($query) => $query->where(
                    'department_id',
                    (int) $filters['department_id'],
                ),
            );

        if ($filters['search'] !== '') {
            $employeeIds = DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('rcd_enable', 1)
                ->where(function ($query) use ($filters) {
                    $query->where('nama', 'like', "%{$filters['search']}%")
                        ->orWhere('employeeID', 'like', "%{$filters['search']}%");
                })
                ->pluck('id');
            $query->where(function ($query) use ($filters, $employeeIds) {
                $query->whereIn('employee_id', $employeeIds)
                    ->orWhere('receipt_number', 'like', "%{$filters['search']}%")
                    ->orWhere('merchant_name', 'like', "%{$filters['search']}%");
            });
        }

        return $query;
    }

    private function visibleQuery(Request $request): Builder
    {
        $user = $request->user();
        $canManage = $user->hasPermission('claims.manage');
        $canSupervise = $user->hasPermission('claims.supervise');
        abort_unless($canManage || $canSupervise, 403);
        $query = ClaimRequest::query();

        if ($canManage) {
            return $query;
        }

        $departmentIds = ClaimApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->where('approval_stage', 'supervisor')
            ->whereIn('department_id', $departmentIds);
    }

    private function validatedPayrollPeriod(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $period = Carbon::createFromFormat('Y-m', (string) $value)
            ->startOfMonth();
        $run = PayrollRun::query()
            ->whereDate('period_start', $period)
            ->first();

        if ($run && $run->status !== 'draft') {
            throw ValidationException::withMessages([
                'scheduled_payroll_period' => 'Payroll bulan ini telah melalui semakan dan tidak boleh menerima tuntutan baharu.',
            ]);
        }

        return $period->toDateString();
    }

    private function recalculateScheduledDraft(
        ?string $period,
        Request $request,
    ): void {
        if ($period === null || $period === '') {
            return;
        }

        $run = PayrollRun::query()
            ->whereDate('period_start', Carbon::parse($period)->startOfMonth())
            ->where('status', 'draft')
            ->first();

        if ($run) {
            $this->payrollCalculator->recalculate($run, $request->user());
        }
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return array<string, array{employee_number: string|null, employee_name: string}>
     */
    private function employeeMap(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return DB::connection('ibco')
            ->table('maklumatpekerja')
            ->whereIn('id', array_unique($employeeIds))
            ->get(['id', 'employeeID', 'nama'])
            ->mapWithKeys(fn ($employee) => [
                (string) $employee->id => [
                    'employee_number' => $employee->employeeID,
                    'employee_name' => $employee->nama
                        ?? "Pekerja #{$employee->id}",
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function departments(): array
    {
        return DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description'])
            ->map(fn ($department) => [
                'id' => (int) $department->id,
                'name' => $department->description,
            ])
            ->all();
    }

    /**
     * @param  array<string, array{employee_number: string|null, employee_name: string}>  $employees
     * @return array<string, mixed>
     */
    private function requestPayload(
        ClaimRequest $claim,
        array $employees,
    ): array {
        $employee = $employees[(string) $claim->employee_id] ?? [];

        return [
            'id' => $claim->getKey(),
            'employee_id' => $claim->employee_id,
            'employee_number' => $employee['employee_number'] ?? null,
            'employee_name' => $employee['employee_name']
                ?? "Pekerja #{$claim->employee_id}",
            'department_id' => $claim->department_id,
            'claim_type' => $claim->claimType?->name,
            'expense_date' => $claim->expense_date?->toDateString(),
            'merchant_name' => $claim->merchant_name,
            'receipt_number' => $claim->receipt_number,
            'requested_amount' => (float) $claim->requested_amount,
            'approved_amount' => $claim->approved_amount === null
                ? null
                : (float) $claim->approved_amount,
            'description' => $claim->description,
            'status' => $claim->status,
            'approval_stage' => $claim->approval_stage,
            'submitted_at' => $claim->submitted_at?->toIso8601String(),
            'supervisor_name' => $claim->supervisorReviewer?->name,
            'supervisor_review_notes' => $claim->supervisor_review_notes,
            'reviewer_name' => $claim->reviewer?->name,
            'review_notes' => $claim->review_notes,
            'scheduled_payroll_period' => $claim
                ->scheduled_payroll_period?->format('Y-m'),
            'payroll_status' => $claim->paid_at
                ? 'paid'
                : ($claim->payrollRun?->status ?? (
                    $claim->scheduled_payroll_period ? 'scheduled' : null
                )),
            'attachments' => $claim->attachments->map(fn (ClaimAttachment $attachment) => [
                'id' => $attachment->getKey(),
                'name' => $attachment->original_name,
                'size' => $attachment->size,
                'download_url' => route('claim-requests.attachment', [
                    $claim,
                    $attachment,
                ]),
            ]),
        ];
    }
}
