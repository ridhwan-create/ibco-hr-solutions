<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClaimRequest;
use App\Models\ClaimApprovalAssignment;
use App\Models\ClaimAttachment;
use App\Models\ClaimNotification;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\EmployeeUserLink;
use App\Support\AuditLogger;
use App\Support\ClaimLimitResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeClaimController extends Controller
{
    public function __construct(
        private readonly ClaimLimitResolver $limitResolver,
    ) {}

    public function index(Request $request): Response
    {
        $link = $this->activeLink($request);

        if (! $link) {
            return Inertia::render('EmployeeSelfService/Claims', [
                'employee' => null,
                'claimTypes' => [],
                'summary' => $this->emptySummary(),
                'requests' => [],
                'notifications' => [],
            ]);
        }

        $employee = $link->employee_source === 'local' && $link->employeeRecord
            ? (object) [
                'id' => $link->employee_id,
                'employee_id' => $link->employeeRecord->employee_number,
                'name' => $link->employeeRecord->name,
            ]
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $link->employee_id)
                ->where('rcd_enable', 1)
                ->first(['id', 'employeeID as employee_id', 'nama as name']);
        $position = $this->activePosition($link->employee_id);
        $claims = ClaimRequest::query()
            ->where('employee_id', $link->employee_id)
            ->with([
                'claimType:id,code,name',
                'attachments:id,claim_request_id,original_name,mime_type,size',
                'supervisorReviewer:id,name',
                'reviewer:id,name',
                'payrollRun:id,period_start,status',
            ])
            ->latest('submitted_at')
            ->limit(50)
            ->get();
        $notifications = ClaimNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(10)
            ->get();
        $today = now();

        return Inertia::render('EmployeeSelfService/Claims', [
            'employee' => $employee,
            'claimTypes' => ClaimType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function (ClaimType $type) use ($link, $position, $today) {
                    $limits = $this->limitResolver->resolve(
                        $type,
                        $link->employee_id,
                        $position?->id,
                    );
                    $usage = $this->limitResolver->usage(
                        $link->employee_id,
                        $type->getKey(),
                        $today,
                    );

                    return [
                        'id' => $type->getKey(),
                        'code' => $type->code,
                        'name' => $type->name,
                        'description' => $type->description,
                        'requires_receipt' => $type->requires_receipt,
                        'requires_receipt_number' => $type->requires_receipt_number,
                        'allow_payroll_reimbursement' => $type->allow_payroll_reimbursement,
                        ...$limits,
                        ...$usage,
                    ];
                }),
            'summary' => [
                'pending' => $claims->where('status', 'pending')->count(),
                'approved' => $claims->where('status', 'approved')->count(),
                'approved_amount' => round((float) $claims
                    ->where('status', 'approved')
                    ->sum('approved_amount'), 2),
                'scheduled_amount' => round((float) $claims
                    ->where('status', 'approved')
                    ->whereNotNull('scheduled_payroll_period')
                    ->whereNull('paid_at')
                    ->sum('approved_amount'), 2),
                'unread_notifications' => $notifications
                    ->whereNull('read_at')
                    ->count(),
            ],
            'requests' => $claims->map(
                fn (ClaimRequest $claim) => $this->requestPayload($claim),
            ),
            'notifications' => $notifications->map(fn (ClaimNotification $notification) => [
                'id' => $notification->getKey(),
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(StoreClaimRequest $request): RedirectResponse
    {
        $link = $this->activeLink($request);

        if (! $link) {
            throw ValidationException::withMessages([
                'claim' => 'Akaun anda belum dipautkan kepada rekod pekerja aktif.',
            ]);
        }

        $employeeExists = $link->employee_source === 'local'
            ? $link->employeeRecord !== null
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $link->employee_id)
                ->where('rcd_enable', 1)
                ->exists();

        if (! $employeeExists) {
            throw ValidationException::withMessages([
                'claim' => 'Rekod pekerja asal tidak aktif atau tidak dijumpai.',
            ]);
        }

        $type = ClaimType::query()
            ->whereKey($request->integer('claim_type_id'))
            ->where('is_active', true)
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'claim_type_id' => 'Jenis tuntutan yang dipilih tidak sah.',
            ]);
        }

        $files = $request->file('receipts', []);

        if ($type->requires_receipt && count($files) === 0) {
            throw ValidationException::withMessages([
                'receipts' => "Resit wajib untuk tuntutan {$type->name}.",
            ]);
        }

        $receiptNumber = $this->normalizeReceiptNumber(
            $request->validated('receipt_number'),
        );

        if ($type->requires_receipt_number && $receiptNumber === null) {
            throw ValidationException::withMessages([
                'receipt_number' => "Nombor resit wajib untuk tuntutan {$type->name}.",
            ]);
        }

        $expenseDate = Carbon::parse($request->validated('expense_date'));
        $amount = round((float) $request->validated('requested_amount'), 2);
        $position = $this->activePosition($link->employee_id);
        $limits = $this->limitResolver->resolve(
            $type,
            $link->employee_id,
            $position?->id,
        );
        $usage = $this->limitResolver->usage(
            $link->employee_id,
            $type->getKey(),
            $expenseDate,
        );
        $this->limitResolver->assertAmountAllowed($amount, $limits, $usage);
        $merchant = trim((string) ($request->validated('merchant_name') ?? ''));
        $fingerprint = $this->receiptFingerprint(
            $link->employee_id,
            $type->getKey(),
            $expenseDate->toDateString(),
            $merchant,
            $receiptNumber,
            $amount,
        );

        if (
            $fingerprint !== null
            && ClaimRequest::query()
                ->where('receipt_fingerprint', $fingerprint)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'receipt_number' => 'Resit ini telah digunakan dalam tuntutan terdahulu.',
            ]);
        }

        $departmentId = $position?->id_department;
        $hasSupervisor = $departmentId !== null
            && ClaimApprovalAssignment::query()
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->exists();
        $storedPaths = [];

        try {
            $claim = DB::transaction(function () use (
                $request,
                $link,
                $position,
                $type,
                $expenseDate,
                $merchant,
                $receiptNumber,
                $fingerprint,
                $amount,
                $hasSupervisor,
                $files,
                &$storedPaths,
            ) {
                $claim = ClaimRequest::query()->create([
                    'user_id' => $request->user()->getAuthIdentifier(),
                    'employee_id' => $link->employee_id,
                    'department_id' => $position?->id_department,
                    'position_id' => $position?->id,
                    'claim_type_id' => $type->getKey(),
                    'expense_date' => $expenseDate,
                    'merchant_name' => $merchant !== '' ? $merchant : null,
                    'receipt_number' => $receiptNumber,
                    'receipt_fingerprint' => $fingerprint,
                    'requested_amount' => $amount,
                    'description' => $request->validated('description'),
                    'status' => 'pending',
                    'approval_stage' => $hasSupervisor ? 'supervisor' : 'finance',
                    'submitted_at' => now(),
                ]);

                foreach ($files as $file) {
                    $path = $file->storeAs(
                        "claim-receipts/{$request->user()->getAuthIdentifier()}",
                        Str::uuid().'.'.$file->getClientOriginalExtension(),
                        'local',
                    );
                    $storedPaths[] = $path;
                    $claim->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'uploaded_by' => $request->user()->getAuthIdentifier(),
                    ]);
                }

                return $claim;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        AuditLogger::record(
            $request,
            'claim.submitted',
            'claim_requests',
            $claim->getKey(),
            newValues: [
                'employee_id' => $claim->employee_id,
                'claim_type' => $type->name,
                'expense_date' => $expenseDate->toDateString(),
                'requested_amount' => $amount,
                'approval_stage' => $claim->approval_stage,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $hasSupervisor
                ? 'Tuntutan dihantar kepada penyelia untuk semakan.'
                : 'Tuntutan dihantar terus kepada HR/Kewangan.',
        ]);
    }

    public function cancel(
        Request $request,
        ClaimRequest $claimRequest,
    ): RedirectResponse {
        abort_unless(
            $claimRequest->user_id === $request->user()->getAuthIdentifier(),
            403,
        );

        if ($claimRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'claim' => 'Hanya tuntutan yang masih menunggu boleh dibatalkan.',
            ]);
        }

        $oldStage = $claimRequest->approval_stage;
        $claimRequest->update([
            'status' => 'cancelled',
            'approval_stage' => 'completed',
        ]);

        AuditLogger::record(
            $request,
            'claim.cancelled_by_employee',
            'claim_requests',
            $claimRequest->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => $oldStage],
            newValues: ['status' => 'cancelled', 'approval_stage' => 'completed'],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Tuntutan telah dibatalkan.',
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
        $ownsClaim = $claimRequest->user_id
            === $request->user()->getAuthIdentifier();
        abort_unless($ownsClaim, 403);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Fail resit tidak dijumpai.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        ClaimNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    private function activeLink(Request $request): ?EmployeeUserLink
    {
        return EmployeeUserLink::query()
            ->with('employeeRecord')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();
    }

    private function activePosition(int $employeeId): ?object
    {
        $local = \App\Models\EmployeeRecord::query()
            ->where('directory_id', $employeeId)
            ->first(['id', 'department_id', 'position_name']);

        if ($local) {
            return (object) [
                'id' => null,
                'id_department' => $local->department_id,
                'jawatan' => $local->position_name,
            ];
        }

        return DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('id_pekerja', $employeeId)
            ->where('rcd_enable', 1)
            ->orderByDesc('id')
            ->first(['id', 'id_department', 'jawatan']);
    }

    private function normalizeReceiptNumber(mixed $value): ?string
    {
        $normalized = Str::upper(
            preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? '',
        );

        return $normalized !== '' ? $normalized : null;
    }

    private function receiptFingerprint(
        int $employeeId,
        int $claimTypeId,
        string $expenseDate,
        string $merchant,
        ?string $receiptNumber,
        float $amount,
    ): ?string {
        if ($receiptNumber === null) {
            return null;
        }

        return hash('sha256', implode('|', [
            $employeeId,
            $claimTypeId,
            $expenseDate,
            Str::upper(trim($merchant)),
            $receiptNumber,
            number_format($amount, 2, '.', ''),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(ClaimRequest $claim): array
    {
        return [
            'id' => $claim->getKey(),
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
            'paid_at' => $claim->paid_at?->toIso8601String(),
            'attachments' => $claim->attachments->map(fn (ClaimAttachment $attachment) => [
                'id' => $attachment->getKey(),
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'download_url' => route('employee-claims.attachment', [
                    $claim,
                    $attachment,
                ]),
            ]),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function emptySummary(): array
    {
        return [
            'pending' => 0,
            'approved' => 0,
            'approved_amount' => 0,
            'scheduled_amount' => 0,
            'unread_notifications' => 0,
        ];
    }
}
