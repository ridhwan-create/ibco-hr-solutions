<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ClaimType;
use App\Models\MaklumatPekerja;
use App\Models\OfficeLocation;
use App\Models\OvertimeType;
use App\Models\PayrollComponent;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditTrailController extends Controller
{
    private const ACTIONS = [
        'employee.created' => 'Pekerja Ditambah',
        'employee.updated' => 'Maklumat Dikemas Kini',
        'employee.deactivated' => 'Pekerja Dinyahaktifkan',
        'employee.reactivated' => 'Pekerja Diaktifkan Semula',
        'position.created' => 'Penempatan Ditambah',
        'position.changed' => 'Jawatan Ditukar',
        'position.terminated' => 'Jawatan Ditamatkan',
        'attendance.clocked_in' => 'Kehadiran Masuk Dirakam',
        'attendance.clocked_out' => 'Kehadiran Keluar Dirakam',
        'attendance.manual_created' => 'Kehadiran Manual Ditambah',
        'attendance.corrected' => 'Kehadiran Dibetulkan',
        'attendance.cancelled' => 'Kehadiran Dibatalkan',
        'office.created' => 'Lokasi Pejabat Ditambah',
        'office.updated' => 'Lokasi Pejabat Dikemas Kini',
        'office.activated' => 'Lokasi Pejabat Diaktifkan',
        'office.deactivated' => 'Lokasi Pejabat Dinyahaktifkan',
        'employee_link.created' => 'Pautan Pekerja Ditambah',
        'employee_link.updated' => 'Pautan Pekerja Dikemas Kini',
        'employee_link.deactivated' => 'Pautan Pekerja Dinyahaktifkan',
        'employee_link.deactivated_from_onboarding_correction' => 'Pautan Akaun Dinyahaktifkan daripada Pembetulan Onboarding',
        'user.created' => 'Pengguna Sistem Ditambah',
        'user.updated' => 'Pengguna Sistem Dikemas Kini',
        'user.password.bulk_reset' => 'Kata Laluan Direset Secara Pukal',
        'employee.registered_from_recruitment' => 'Calon Didaftarkan sebagai Pekerja',
        'user.created_from_recruitment' => 'Akaun Pekerja Dicipta daripada Pengambilan',
        'onboarding.employee_linked' => 'Pekerja Sedia Ada Dipautkan kepada Onboarding',
        'onboarding.employee_unlinked' => 'Pautan Pekerja Onboarding Dibatalkan',
        'employee.profile_updated' => 'Profil Sendiri Dikemas Kini',
        'leave.submitted' => 'Permohonan Cuti Dihantar',
        'leave.supervisor_approved' => 'Permohonan Cuti Disokong Penyelia',
        'leave.supervisor_rejected' => 'Permohonan Cuti Ditolak Penyelia',
        'leave.approved' => 'Permohonan Cuti Diluluskan',
        'leave.rejected' => 'Permohonan Cuti Ditolak',
        'leave.cancelled' => 'Permohonan Cuti Dibatalkan',
        'leave.approved_cancelled' => 'Cuti Diluluskan Dibatalkan HR',
        'leave_type.created' => 'Jenis Cuti Ditambah',
        'leave_type.updated' => 'Jenis Cuti Dikemas Kini',
        'leave_type.activated' => 'Jenis Cuti Diaktifkan',
        'leave_type.deactivated' => 'Jenis Cuti Dinyahaktifkan',
        'leave_entitlement.created' => 'Kelayakan Cuti Ditambah',
        'leave_entitlement.updated' => 'Kelayakan Cuti Dikemas Kini',
        'leave_approver.assigned' => 'Penyelia Cuti Ditetapkan',
        'leave_approver.removed' => 'Penyelia Cuti Dibuang',
        'public_holiday.created' => 'Cuti Umum Ditambah',
        'public_holiday.deleted' => 'Cuti Umum Dibuang',
        'overtime.submitted' => 'Permohonan OT Dihantar',
        'overtime.supervisor_approved' => 'Permohonan OT Disokong Penyelia',
        'overtime.supervisor_rejected' => 'Permohonan OT Ditolak Penyelia',
        'overtime.approved' => 'Permohonan OT Diluluskan',
        'overtime.rejected' => 'Permohonan OT Ditolak',
        'overtime.cancelled' => 'Permohonan OT Dibatalkan',
        'overtime.approved_cancelled' => 'OT Diluluskan Dibatalkan HR',
        'overtime_type.created' => 'Jenis OT Ditambah',
        'overtime_type.updated' => 'Jenis OT Dikemas Kini',
        'overtime_type.activated' => 'Jenis OT Diaktifkan',
        'overtime_type.deactivated' => 'Jenis OT Dinyahaktifkan',
        'overtime_approver.assigned' => 'Penyelia OT Ditetapkan',
        'overtime_approver.removed' => 'Penyelia OT Dibuang',
        'shift_template.created' => 'Template Syif Ditambah',
        'shift_template.updated' => 'Template Syif Dikemas Kini',
        'shift_template.activated' => 'Template Syif Diaktifkan',
        'shift_template.deactivated' => 'Template Syif Dinyahaktifkan',
        'schedule_assignment.created' => 'Penetapan Jadual Ditambah',
        'schedule_assignment.activated' => 'Penetapan Jadual Diaktifkan',
        'schedule_assignment.deactivated' => 'Penetapan Jadual Dinyahaktifkan',
        'roster.generated' => 'Roster Dijana',
        'roster.entry_updated' => 'Jadual Pekerja Dikemas Kini',
        'roster.published' => 'Roster Diterbitkan',
        'roster.locked' => 'Roster Dikunci',
        'roster.swap_requested' => 'Pertukaran Syif Dimohon',
        'roster.swap_cancelled' => 'Pertukaran Syif Dibatalkan',
        'roster.swap_approved' => 'Pertukaran Syif Diluluskan',
        'roster.swap_rejected' => 'Pertukaran Syif Ditolak',
        'roster.report_exported' => 'Laporan Roster Dieksport',
        'claim.submitted' => 'Tuntutan Dihantar',
        'claim.cancelled_by_employee' => 'Tuntutan Dibatalkan Pekerja',
        'claim.supervisor_approved' => 'Tuntutan Disokong Penyelia',
        'claim.supervisor_rejected' => 'Tuntutan Ditolak Penyelia',
        'claim.approved' => 'Tuntutan Diluluskan',
        'claim.rejected' => 'Tuntutan Ditolak',
        'claim.payroll_scheduled' => 'Tuntutan Dijadualkan ke Payroll',
        'claim.approved_cancelled' => 'Kelulusan Tuntutan Dibatalkan',
        'claim.report_exported' => 'Laporan Tuntutan Dieksport',
        'claim_type.created' => 'Jenis Tuntutan Ditambah',
        'claim_type.updated' => 'Jenis Tuntutan Dikemas Kini',
        'claim_type.activated' => 'Jenis Tuntutan Diaktifkan',
        'claim_type.deactivated' => 'Jenis Tuntutan Dinyahaktifkan',
        'claim_approver.assigned' => 'Penyelia Tuntutan Ditetapkan',
        'claim_approver.removed' => 'Penyelia Tuntutan Dibuang',
        'claim_limit.saved' => 'Had Tuntutan Khas Disimpan',
        'claim_limit.removed' => 'Had Tuntutan Khas Dibuang',
        'performance_cycle.created' => 'Kitaran Prestasi Ditambah',
        'performance_cycle.updated' => 'Kitaran Prestasi Dikemas Kini',
        'performance_cycle.open' => 'Kitaran Prestasi Dibuka',
        'performance_cycle.in_review' => 'Kitaran Prestasi Masuk Semakan',
        'performance_cycle.finalized' => 'Kitaran Prestasi Dimuktamadkan',
        'performance_template.created' => 'Template KPI Ditambah',
        'performance_template.updated' => 'Template KPI Dikemas Kini',
        'performance_template.activated' => 'Template KPI Diaktifkan',
        'performance_template.deactivated' => 'Template KPI Dinyahaktifkan',
        'performance_supervisor.assigned' => 'Penyelia Prestasi Ditetapkan',
        'performance_supervisor.removed' => 'Penyelia Prestasi Dibuang',
        'performance_review.created' => 'Penilaian Prestasi Dijana',
        'performance_review.bulk_generated' => 'Penilaian Prestasi Dijana Pukal',
        'performance_review.self_saved' => 'Draf Self-Assessment Disimpan',
        'performance_review.self_submitted' => 'Self-Assessment Dihantar',
        'performance_review.supervisor_submitted' => 'Penilaian Penyelia Dihantar',
        'performance_review.moderated' => 'Penilaian Prestasi Dimoderasi',
        'performance_review.finalized' => 'Penilaian Prestasi Dimuktamadkan',
        'performance_evidence.uploaded' => 'Bukti Prestasi Dimuat Naik',
        'performance_evidence.deleted' => 'Bukti Prestasi Dibuang',
        'performance_pip.created' => 'PIP Dibuka',
        'performance_pip.updated' => 'PIP Dikemas Kini',
        'performance_pip.checkin_added' => 'Semakan PIP Direkodkan',
        'performance_report.exported' => 'Laporan Prestasi Dieksport',
        'document_template.created' => 'Template Dokumen Ditambah',
        'document_template.updated' => 'Template Dokumen Dikemas Kini',
        'document_template.activated' => 'Template Dokumen Diaktifkan',
        'document_template.deactivated' => 'Template Dokumen Dinyahaktifkan',
        'document_sequence.created' => 'Siri Dokumen Ditambah',
        'document_sequence.updated' => 'Siri Dokumen Dikemas Kini',
        'hr_document.created' => 'Draf Dokumen HR Dijana',
        'hr_document.updated' => 'Draf Dokumen HR Dikemas Kini',
        'hr_document.submitted' => 'Dokumen HR Dihantar untuk Kelulusan',
        'hr_document.approved' => 'Dokumen HR Diluluskan',
        'hr_document.rejected' => 'Dokumen HR Ditolak',
        'hr_document.issued' => 'Dokumen HR Dikeluarkan',
        'hr_document.acknowledged' => 'Dokumen HR Diperakui Pekerja',
        'hr_document.voided' => 'Dokumen HR Dibatalkan',
        'hr_document.renewal_created' => 'Pembaharuan Dokumen HR Dijana',
        'hr_document.attachment_uploaded' => 'Lampiran Dokumen HR Dimuat Naik',
        'hr_document.attachment_deleted' => 'Lampiran Dokumen HR Dibuang',
        'hr_document.report_exported' => 'Laporan Dokumen HR Dieksport',
        'payroll.generated' => 'Payroll Dijana',
        'payroll.recalculated' => 'Payroll Dikira Semula',
        'payroll.manual_item_added' => 'Pelarasan Payroll Ditambah',
        'payroll.manual_item_removed' => 'Pelarasan Payroll Dibuang',
        'payroll.statutory_overridden' => 'Amaun Statutori Dilaras',
        'payroll.hr_reviewed' => 'Payroll Disemak HR',
        'payroll.approved' => 'Payroll Diluluskan',
        'payroll.finalized' => 'Payroll Dimuktamadkan',
        'payroll.returned_to_draft' => 'Payroll Dikembalikan ke Draf',
        'payroll_settings.updated' => 'Tetapan Payroll Dikemas Kini',
        'payroll_component.created' => 'Komponen Payroll Ditambah',
        'payroll_component.updated' => 'Komponen Payroll Dikemas Kini',
        'payroll_component.activated' => 'Komponen Payroll Diaktifkan',
        'payroll_component.deactivated' => 'Komponen Payroll Dinyahaktifkan',
        'salary_profile.created' => 'Profil Gaji Ditambah',
        'salary_profile.updated' => 'Profil Gaji Dikemas Kini',
        'employee_payroll_component.created' => 'Komponen Gaji Pekerja Ditambah',
        'employee_payroll_component.updated' => 'Komponen Gaji Pekerja Dikemas Kini',
        'employee_payroll_component.activated' => 'Komponen Gaji Pekerja Diaktifkan',
        'employee_payroll_component.deactivated' => 'Komponen Gaji Pekerja Dinyahaktifkan',
        'statutory_settings.updated' => 'Kadar Statutori Dikemas Kini',
        'payslip_settings.updated' => 'Tetapan Slip Gaji Dikemas Kini',
        'statutory_profile.created' => 'Profil Statutori Ditambah',
        'statutory_profile.updated' => 'Profil Statutori Dikemas Kini',
        'report.monthly_exported' => 'Laporan Bulanan Dieksport',
    ];

    private const AUDITABLE_TYPES = [
        'maklumatpekerja',
        'maklumatjawatan',
        'geo_attendance_records',
        'office_locations',
        'employee_user_links',
        'onboarding_cases',
        'users',
        'employee_personal_profiles',
        'employee_leave_requests',
        'leave_types',
        'leave_entitlements',
        'leave_approval_assignments',
        'public_holidays',
        'overtime_requests',
        'overtime_types',
        'overtime_approval_assignments',
        'shift_templates',
        'schedule_assignments',
        'roster_periods',
        'roster_entries',
        'shift_swap_requests',
        'claim_requests',
        'claim_types',
        'claim_approval_assignments',
        'claim_limit_overrides',
        'performance_cycles',
        'performance_templates',
        'performance_supervisor_assignments',
        'performance_reviews',
        'performance_evidence',
        'performance_improvement_plans',
        'performance_pip_checkins',
        'document_templates',
        'document_sequences',
        'hr_documents',
        'hr_document_attachments',
        'payroll_runs',
        'payroll_entry_items',
        'payroll_settings',
        'payroll_components',
        'employee_salary_profiles',
        'employee_payroll_components',
        'statutory_settings',
        'employee_statutory_profiles',
        'payroll_statutory_snapshots',
        'monthly_hr_report',
    ];

    public function index(Request $request): Response
    {
        $dateToRules = ['nullable', 'date'];

        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['audit', 'inactive'])],
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(array_keys(self::ACTIONS))],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => $dateToRules,
            'inactive_search' => ['nullable', 'string', 'max:100'],
        ]);

        $tab = $validated['tab'] ?? 'audit';
        $search = trim($validated['search'] ?? '');
        $action = $validated['action'] ?? '';
        $userId = isset($validated['user_id'])
            ? (int) $validated['user_id']
            : null;
        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';
        $inactiveSearch = trim($validated['inactive_search'] ?? '');

        $matchingEmployeeIds = $this->matchingEmployeeIds($search);
        $matchingPositionIds = $this->matchingPositionIds(
            $search,
            $matchingEmployeeIds,
        );

        $audits = AuditLog::query()
            ->with('user:id,name,email')
            ->whereIn('auditable_type', self::AUDITABLE_TYPES)
            ->when($search !== '', function (Builder $query) use (
                $search,
                $matchingEmployeeIds,
                $matchingPositionIds,
            ) {
                $query->where(function (Builder $query) use (
                    $search,
                    $matchingEmployeeIds,
                    $matchingPositionIds,
                ) {
                    $query->where('auditable_id', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });

                    if ($matchingEmployeeIds !== []) {
                        $query->orWhere(function (Builder $query) use ($matchingEmployeeIds) {
                            $query->where('auditable_type', 'maklumatpekerja')
                                ->whereIn('auditable_id', $matchingEmployeeIds);
                        });
                    }

                    if ($matchingPositionIds !== []) {
                        $query->orWhere(function (Builder $query) use ($matchingPositionIds) {
                            $query->where('auditable_type', 'maklumatjawatan')
                                ->whereIn('auditable_id', $matchingPositionIds);
                        });
                    }
                });
            })
            ->when($action !== '', fn (Builder $query) => $query->where('action', $action))
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(15, ['*'], 'audit_page')
            ->withQueryString();

        $auditEmployees = $this->auditEmployeeMap(collect($audits->items()));
        $referenceLookups = $this->referenceLookups();

        $audits->through(function (AuditLog $audit) use ($auditEmployees, $referenceLookups) {
            $employee = $auditEmployees[
                "{$audit->auditable_type}:{$audit->auditable_id}"
            ] ?? null;

            return [
                'id' => $audit->getKey(),
                'action' => $audit->action,
                'action_label' => self::ACTIONS[$audit->action] ?? $audit->action,
                'auditable_type' => $audit->auditable_type,
                'auditable_id' => (string) $audit->auditable_id,
                'employee' => $employee,
                'user' => $audit->user
                    ? [
                        'id' => $audit->user->getKey(),
                        'name' => $audit->user->name,
                        'email' => $audit->user->email,
                    ]
                    : null,
                'old_values' => $this->resolveAuditValues($audit->old_values, $referenceLookups),
                'new_values' => $this->resolveAuditValues($audit->new_values, $referenceLookups),
                'ip_address' => $audit->ip_address,
                'user_agent' => $audit->user_agent,
                'created_at' => $audit->created_at?->toIso8601String(),
            ];
        });

        $inactiveEmployees = DB::connection('ibco')
            ->table('maklumatpekerja as p')
            ->leftJoin('xstatus as s', 'p.status', '=', 's.id')
            ->where('p.rcd_enable', 0)
            ->when($inactiveSearch !== '', function ($query) use ($inactiveSearch) {
                $query->where(function ($query) use ($inactiveSearch) {
                    $query->where('p.nama', 'like', "%{$inactiveSearch}%")
                        ->orWhere('p.employeeID', 'like', "%{$inactiveSearch}%")
                        ->orWhere('p.nric', 'like', "%{$inactiveSearch}%")
                        ->orWhere('p.email', 'like', "%{$inactiveSearch}%");
                });
            })
            ->select([
                'p.id',
                'p.employeeID as employee_id',
                'p.nama',
                'p.nric',
                'p.notel as no_telefon',
                'p.email',
                's.description as status',
                'p.mdf_dt as tarikh_dikemas_kini',
                'p.mdf_by as dikemas_kini_oleh',
            ])
            ->orderBy('p.nama')
            ->paginate(15, ['*'], 'inactive_page')
            ->withQueryString();

        $actionCounts = AuditLog::query()
            ->whereIn('auditable_type', self::AUDITABLE_TYPES)
            ->whereIn('action', array_keys(self::ACTIONS))
            ->selectRaw('action, COUNT(*) as aggregate')
            ->groupBy('action')
            ->pluck('aggregate', 'action');

        $actorIds = AuditLog::query()
            ->whereIn('auditable_type', self::AUDITABLE_TYPES)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $userOptions = User::query()
            ->whereIn('id', $actorIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'value' => (string) $user->getKey(),
                'label' => $user->name,
                'email' => $user->email,
            ]);

        return Inertia::render('AuditTrail/Index', [
            'audits' => $audits,
            'inactiveEmployees' => $inactiveEmployees,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
                'action' => $action,
                'user_id' => $userId ? (string) $userId : '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'inactive_search' => $inactiveSearch,
            ],
            'actionOptions' => collect(self::ACTIONS)
                ->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),
            'userOptions' => $userOptions,
            'statistics' => [
                'total' => (int) $actionCounts->sum(),
                'created' => (int) (
                    ($actionCounts['employee.created'] ?? 0)
                    + ($actionCounts['position.created'] ?? 0)
                    + ($actionCounts['attendance.manual_created'] ?? 0)
                    + ($actionCounts['office.created'] ?? 0)
                    + ($actionCounts['employee_link.created'] ?? 0)
                    + ($actionCounts['user.created'] ?? 0)
                    + ($actionCounts['leave.submitted'] ?? 0)
                    + ($actionCounts['leave_type.created'] ?? 0)
                    + ($actionCounts['leave_entitlement.created'] ?? 0)
                    + ($actionCounts['public_holiday.created'] ?? 0)
                    + ($actionCounts['overtime.submitted'] ?? 0)
                    + ($actionCounts['overtime_type.created'] ?? 0)
                    + ($actionCounts['shift_template.created'] ?? 0)
                    + ($actionCounts['schedule_assignment.created'] ?? 0)
                    + ($actionCounts['roster.generated'] ?? 0)
                    + ($actionCounts['roster.swap_requested'] ?? 0)
                    + ($actionCounts['claim.submitted'] ?? 0)
                    + ($actionCounts['claim_type.created'] ?? 0)
                    + ($actionCounts['performance_cycle.created'] ?? 0)
                    + ($actionCounts['performance_template.created'] ?? 0)
                    + ($actionCounts['performance_review.created'] ?? 0)
                    + ($actionCounts['performance_pip.created'] ?? 0)
                ),
                'updated' => (int) (
                    ($actionCounts['employee.updated'] ?? 0)
                    + ($actionCounts['position.changed'] ?? 0)
                    + ($actionCounts['attendance.clocked_in'] ?? 0)
                    + ($actionCounts['attendance.clocked_out'] ?? 0)
                    + ($actionCounts['attendance.corrected'] ?? 0)
                    + ($actionCounts['office.updated'] ?? 0)
                    + ($actionCounts['employee_link.updated'] ?? 0)
                    + ($actionCounts['user.updated'] ?? 0)
                    + ($actionCounts['user.password.bulk_reset'] ?? 0)
                    + ($actionCounts['employee.profile_updated'] ?? 0)
                    + ($actionCounts['leave.approved'] ?? 0)
                    + ($actionCounts['leave.rejected'] ?? 0)
                    + ($actionCounts['leave.supervisor_approved'] ?? 0)
                    + ($actionCounts['leave.supervisor_rejected'] ?? 0)
                    + ($actionCounts['leave_type.updated'] ?? 0)
                    + ($actionCounts['leave_entitlement.updated'] ?? 0)
                    + ($actionCounts['leave_approver.assigned'] ?? 0)
                    + ($actionCounts['overtime.approved'] ?? 0)
                    + ($actionCounts['overtime.rejected'] ?? 0)
                    + ($actionCounts['overtime.supervisor_approved'] ?? 0)
                    + ($actionCounts['overtime.supervisor_rejected'] ?? 0)
                    + ($actionCounts['overtime_type.updated'] ?? 0)
                    + ($actionCounts['overtime_approver.assigned'] ?? 0)
                    + ($actionCounts['shift_template.updated'] ?? 0)
                    + ($actionCounts['schedule_assignment.activated'] ?? 0)
                    + ($actionCounts['roster.entry_updated'] ?? 0)
                    + ($actionCounts['roster.published'] ?? 0)
                    + ($actionCounts['roster.locked'] ?? 0)
                    + ($actionCounts['roster.swap_approved'] ?? 0)
                    + ($actionCounts['roster.swap_rejected'] ?? 0)
                    + ($actionCounts['claim.approved'] ?? 0)
                    + ($actionCounts['claim.rejected'] ?? 0)
                    + ($actionCounts['claim.supervisor_approved'] ?? 0)
                    + ($actionCounts['claim.supervisor_rejected'] ?? 0)
                    + ($actionCounts['claim.payroll_scheduled'] ?? 0)
                    + ($actionCounts['claim_type.updated'] ?? 0)
                    + ($actionCounts['claim_approver.assigned'] ?? 0)
                    + ($actionCounts['claim_limit.saved'] ?? 0)
                    + ($actionCounts['performance_cycle.updated'] ?? 0)
                    + ($actionCounts['performance_cycle.open'] ?? 0)
                    + ($actionCounts['performance_cycle.in_review'] ?? 0)
                    + ($actionCounts['performance_cycle.finalized'] ?? 0)
                    + ($actionCounts['performance_template.updated'] ?? 0)
                    + ($actionCounts['performance_supervisor.assigned'] ?? 0)
                    + ($actionCounts['performance_review.self_saved'] ?? 0)
                    + ($actionCounts['performance_review.self_submitted'] ?? 0)
                    + ($actionCounts['performance_review.supervisor_submitted'] ?? 0)
                    + ($actionCounts['performance_review.moderated'] ?? 0)
                    + ($actionCounts['performance_review.finalized'] ?? 0)
                    + ($actionCounts['performance_evidence.uploaded'] ?? 0)
                    + ($actionCounts['performance_pip.updated'] ?? 0)
                    + ($actionCounts['performance_pip.checkin_added'] ?? 0)
                ),
                'deactivated' => (int) (
                    ($actionCounts['employee.deactivated'] ?? 0)
                    + ($actionCounts['position.terminated'] ?? 0)
                    + ($actionCounts['attendance.cancelled'] ?? 0)
                    + ($actionCounts['office.deactivated'] ?? 0)
                    + ($actionCounts['employee_link.deactivated'] ?? 0)
                    + ($actionCounts['employee_link.deactivated_from_onboarding_correction'] ?? 0)
                    + ($actionCounts['onboarding.employee_unlinked'] ?? 0)
                    + ($actionCounts['leave.cancelled'] ?? 0)
                    + ($actionCounts['leave.approved_cancelled'] ?? 0)
                    + ($actionCounts['leave_type.deactivated'] ?? 0)
                    + ($actionCounts['leave_approver.removed'] ?? 0)
                    + ($actionCounts['public_holiday.deleted'] ?? 0)
                    + ($actionCounts['overtime.cancelled'] ?? 0)
                    + ($actionCounts['overtime.approved_cancelled'] ?? 0)
                    + ($actionCounts['overtime_type.deactivated'] ?? 0)
                    + ($actionCounts['overtime_approver.removed'] ?? 0)
                    + ($actionCounts['shift_template.deactivated'] ?? 0)
                    + ($actionCounts['schedule_assignment.deactivated'] ?? 0)
                    + ($actionCounts['roster.swap_cancelled'] ?? 0)
                    + ($actionCounts['claim.cancelled_by_employee'] ?? 0)
                    + ($actionCounts['claim.approved_cancelled'] ?? 0)
                    + ($actionCounts['claim_type.deactivated'] ?? 0)
                    + ($actionCounts['claim_approver.removed'] ?? 0)
                    + ($actionCounts['claim_limit.removed'] ?? 0)
                    + ($actionCounts['performance_template.deactivated'] ?? 0)
                    + ($actionCounts['performance_supervisor.removed'] ?? 0)
                    + ($actionCounts['performance_evidence.deleted'] ?? 0)
                ),
                'reactivated' => (int) (
                    ($actionCounts['employee.reactivated'] ?? 0)
                    + ($actionCounts['office.activated'] ?? 0)
                    + ($actionCounts['leave_type.activated'] ?? 0)
                    + ($actionCounts['overtime_type.activated'] ?? 0)
                    + ($actionCounts['shift_template.activated'] ?? 0)
                    + ($actionCounts['claim_type.activated'] ?? 0)
                    + ($actionCounts['performance_template.activated'] ?? 0)
                ),
                'inactive' => DB::connection('ibco')
                    ->table('maklumatpekerja')
                    ->where('rcd_enable', 0)
                    ->count(),
            ],
        ]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $pekerja = MaklumatPekerja::query()
            ->whereKey($id)
            ->where('rcd_enable', 0)
            ->firstOrFail();

        DB::connection('ibco')->transaction(function () use ($pekerja, $request) {
            $pekerja->forceFill([
                'rcd_enable' => 1,
                'mdf_dt' => now()->toDateString(),
                'mdf_by' => Str::limit($request->user()?->name ?? 'System', 12, ''),
            ])->save();
        });

        AuditLogger::record(
            $request,
            'employee.reactivated',
            'maklumatpekerja',
            $pekerja->getKey(),
            oldValues: ['rcd_enable' => 0],
            newValues: ['rcd_enable' => 1],
        );

        return redirect()
            ->route('audit.index', ['tab' => 'inactive'])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pekerja berjaya diaktifkan semula.',
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function matchingEmployeeIds(string $search): array
    {
        if ($search === '') {
            return [];
        }

        return DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where(function ($query) use ($search) {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('employeeID', 'like', "%{$search}%")
                    ->orWhere('nric', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @param  array<int, string>  $employeeIds
     * @return array<int, string>
     */
    private function matchingPositionIds(string $search, array $employeeIds): array
    {
        if ($search === '') {
            return [];
        }

        return DB::connection('ibco')
            ->table('maklumatjawatan as j')
            ->leftJoin('xdepartment as d', 'j.id_department', '=', 'd.id')
            ->where(function ($query) use ($search, $employeeIds) {
                $query->where('j.jawatan', 'like', "%{$search}%")
                    ->orWhere('d.description', 'like', "%{$search}%");

                if ($employeeIds !== []) {
                    $query->orWhereIn('j.id_pekerja', $employeeIds);
                }
            })
            ->pluck('j.id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @return array<string, array{id: int, employee_id: string|null, nama: string|null}>
     */
    private function auditEmployeeMap($audits): array
    {
        $employeeAuditIds = $audits
            ->where('auditable_type', 'maklumatpekerja')
            ->pluck('auditable_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (string) $id)
            ->values();

        $systemEmployeeIds = $audits
            ->whereIn('auditable_type', [
                'geo_attendance_records',
                'employee_user_links',
                'employee_personal_profiles',
                'employee_leave_requests',
                'employee_salary_profiles',
                'employee_payroll_components',
                'payroll_entry_items',
                'claim_requests',
            ])
            ->map(fn (AuditLog $audit) => (
                data_get($audit->new_values, 'employee_id')
                ?? data_get($audit->old_values, 'employee_id')
                ?? null
            ))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (string) $id)
            ->values();

        $positionIds = $audits
            ->where('auditable_type', 'maklumatjawatan')
            ->pluck('auditable_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (string) $id)
            ->values();

        $positionEmployeeIds = $positionIds->isEmpty()
            ? collect()
            : DB::connection('ibco')
                ->table('maklumatjawatan')
                ->whereIn('id', $positionIds)
                ->pluck('id_pekerja', 'id')
                ->mapWithKeys(fn ($employeeId, $positionId) => [
                    (string) $positionId => (string) $employeeId,
                ]);

        $employeeMap = $this->employeeMap(
            $employeeAuditIds
                ->merge($positionEmployeeIds->values())
                ->merge($systemEmployeeIds)
                ->unique()
                ->values()
                ->all(),
        );

        return $audits
            ->mapWithKeys(function (AuditLog $audit) use ($employeeMap, $positionEmployeeIds) {
                $employeeId = match ($audit->auditable_type) {
                    'maklumatjawatan' => $positionEmployeeIds[(string) $audit->auditable_id] ?? null,
                    'maklumatpekerja' => (string) $audit->auditable_id,
                    'geo_attendance_records',
                    'employee_user_links',
                    'employee_personal_profiles',
                    'employee_leave_requests',
                    'overtime_requests',
                    'claim_requests',
                    'employee_salary_profiles',
                    'employee_payroll_components',
                    'payroll_entry_items' => (
                        data_get($audit->new_values, 'employee_id')
                        ?? data_get($audit->old_values, 'employee_id')
                        ?? null
                    ),
                    default => null,
                };

                return [
                    "{$audit->auditable_type}:{$audit->auditable_id}" =>
                        $employeeId !== null
                            ? ($employeeMap[(string) $employeeId] ?? null)
                            : null,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return array<string, array{id: int, employee_id: string|null, nama: string|null}>
     */
    private function employeeMap(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return DB::connection('ibco')
            ->table('maklumatpekerja')
            ->whereIn('id', $employeeIds)
            ->get(['id', 'employeeID', 'nama'])
            ->mapWithKeys(fn ($employee) => [
                (string) $employee->id => [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employeeID,
                    'nama' => $employee->nama,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function referenceLookups(): array
    {
        $tables = [
            'jantina' => 'xjantina',
            'agama' => 'xagama',
            'bangsa' => 'xbangsa',
            'statusperkahwinan' => 'xstatusperkahwinan',
            'status' => 'xstatus',
            'id_department' => 'xdepartment',
            'id_bank' => 'xbank',
        ];

        $lookups = collect($tables)
            ->mapWithKeys(fn (string $table, string $field) => [
                $field => DB::connection('ibco')
                    ->table($table)
                    ->pluck('description', 'id')
                    ->mapWithKeys(fn ($description, $id) => [
                        (string) $id => (string) $description,
                    ])
                    ->all(),
            ])
            ->all();

        $lookups['id_pekerja'] = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->get(['id', 'employeeID', 'nama'])
            ->mapWithKeys(fn ($employee) => [
                (string) $employee->id => trim(
                    ($employee->employeeID ? "{$employee->employeeID} — " : '')
                    . ($employee->nama ?? "Pekerja #{$employee->id}"),
                ),
            ])
            ->all();

        $lookups['employee_id'] = $lookups['id_pekerja'];
        $lookups['department_id'] = $lookups['id_department'];
        $lookups['office_location_id'] = OfficeLocation::query()
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])
            ->all();
        $lookups['overtime_type_id'] = OvertimeType::query()
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])
            ->all();
        $lookups['claim_type_id'] = ClaimType::query()
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])
            ->all();
        $lookups['payroll_component_id'] = PayrollComponent::query()
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])
            ->all();
        $roleLabels = collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])
            ->all();
        $lookups['role'] = $roleLabels;
        $lookups['primary_role'] = $roleLabels;
        $lookups['roles'] = $roleLabels;

        return $lookups;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @param  array<string, array<string, string>>  $referenceLookups
     * @return array<string, mixed>
     */
    private function resolveAuditValues(?array $values, array $referenceLookups): array
    {
        return collect($values ?? [])
            ->mapWithKeys(function ($value, string $field) use ($referenceLookups) {
                return [
                    $field => $this->resolveAuditValue(
                        $field,
                        $value,
                        $referenceLookups,
                    ),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, array<string, string>>  $referenceLookups
     */
    private function resolveAuditValue(
        string $field,
        mixed $value,
        array $referenceLookups,
    ): string|int|float|bool|null {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $this->resolveAuditValue(
                    $field,
                    $item,
                    $referenceLookups,
                ))
                ->filter(fn ($item) => $item !== null && $item !== '')
                ->map(fn ($item) => is_bool($item)
                    ? ($item ? 'Ya' : 'Tidak')
                    : (string) $item)
                ->implode(', ');
        }

        if ($value === null) {
            return null;
        }

        if (
            in_array($field, ['rcd_enable', 'is_active'], true)
            && is_scalar($value)
        ) {
            return (int) $value === 1 ? 'Aktif' : 'Tidak Aktif';
        }

        if (is_scalar($value)) {
            $lookupKey = (string) $value;

            if (isset($referenceLookups[$field][$lookupKey])) {
                return $referenceLookups[$field][$lookupKey];
            }

            return $value;
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return $encoded === false ? 'Nilai tidak dapat dipaparkan' : $encoded;
    }
}
