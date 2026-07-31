<?php

namespace App\Http\Controllers;

use App\Models\EmployeeUserLink;
use App\Models\PayrollEntry;
use App\Support\PayslipPdfRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmployeePayslipController extends Controller
{
    public function __construct(
        private readonly PayslipPdfRenderer $renderer,
    ) {}

    public function index(Request $request): Response
    {
        $link = $this->activeLink($request);
        $entries = $link
            ? PayrollEntry::query()
                ->with(['payrollRun', 'statutorySnapshot'])
                ->where('employee_id', $link->employee_id)
                ->whereHas('statutorySnapshot')
                ->whereHas(
                    'payrollRun',
                    fn ($query) => $query->where('status', 'finalized'),
                )
                ->orderByDesc(
                    \App\Models\PayrollRun::query()
                        ->select('period_start')
                        ->whereColumn('payroll_runs.id', 'payroll_entries.payroll_run_id')
                        ->limit(1),
                )
                ->paginate(12)
                ->through(fn (PayrollEntry $entry) => $this->payload($entry))
            : null;

        return Inertia::render('EmployeeSelfService/Payslips', [
            'hasEmployeeLink' => $link !== null,
            'payslips' => $entries,
        ]);
    }

    public function downloadOwn(
        Request $request,
        PayrollEntry $payrollEntry,
    ): HttpResponse {
        $link = $this->activeLink($request);
        abort_unless(
            $link
            && $payrollEntry->employee_id === $link->employee_id
            && $payrollEntry->statutorySnapshot()->exists()
            && $payrollEntry->payrollRun()->where('status', 'finalized')->exists(),
            404,
        );

        return $this->pdfResponse($payrollEntry);
    }

    public function downloadForHr(
        PayrollEntry $payrollEntry,
    ): HttpResponse {
        return $this->pdfResponse($payrollEntry);
    }

    private function pdfResponse(PayrollEntry $entry): HttpResponse
    {
        $entry->loadMissing('payrollRun');
        $filename = sprintf(
            'slip-gaji-%s-%s.pdf',
            $entry->employee_number ?: $entry->employee_id,
            $entry->payrollRun->period_start->format('Y-m'),
        );

        return response($this->renderer->render($entry), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PayrollEntry $entry): array
    {
        return [
            'id' => $entry->getKey(),
            'period' => $entry->payrollRun->period_start?->toDateString(),
            'period_label' => $entry->payrollRun->period_start?->translatedFormat('F Y'),
            'finalized_at' => $entry->payrollRun->finalized_at?->toIso8601String(),
            'gross_pay' => (float) $entry->gross_pay,
            'total_deductions' => (float) $entry->total_deductions,
            'net_pay' => (float) $entry->net_pay,
            'kwsp_employee' => (float) $entry->statutorySnapshot?->kwsp_employee,
            'socso_employee' => (float) $entry->statutorySnapshot?->socso_employee,
            'eis_employee' => (float) $entry->statutorySnapshot?->eis_employee,
            'pcb' => (float) $entry->statutorySnapshot?->pcb,
        ];
    }

    private function activeLink(Request $request): ?EmployeeUserLink
    {
        return EmployeeUserLink::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();
    }
}
