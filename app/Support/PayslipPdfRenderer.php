<?php

namespace App\Support;

use App\Models\PayrollEntry;
use App\Models\PayrollEntryItem;
use App\Models\PayrollSetting;

class PayslipPdfRenderer
{
    public function render(PayrollEntry $entry): string
    {
        $entry->loadMissing([
            'payrollRun',
            'statutorySnapshot',
            'items' => fn ($query) => $query->orderBy('id'),
        ]);
        $settings = PayrollSetting::query()->firstOrCreate(['id' => 1]);
        $run = $entry->payrollRun;
        $statutory = $entry->statutorySnapshot;
        $earnings = $entry->items
            ->where('type', 'earning')
            ->values();
        $deductions = $entry->items
            ->where('type', 'deduction')
            ->values();
        $pdf = new SimplePdfDocument;

        $pdf->rectangle(36, 742, 523, 64, true, .95)
            ->text(52, 784, $settings->company_name ?: config('app.name'), 16, true)
            ->text(52, 766, $this->oneLine($settings->company_address), 8)
            ->text(
                420,
                784,
                'SLIP GAJI',
                16,
                true,
            )
            ->text(
                420,
                766,
                $run->period_start?->translatedFormat('F Y') ?? '-',
                10,
                true,
            );

        if ($settings->company_registration_no) {
            $pdf->text(
                52,
                752,
                'No. Pendaftaran: '.$settings->company_registration_no,
                8,
            );
        }

        $pdf->text(40, 718, 'MAKLUMAT PEKERJA', 10, true)
            ->line(40, 712, 555, 712)
            ->text(45, 696, 'Nama', 9)
            ->text(145, 696, $entry->employee_name, 9, true)
            ->text(360, 696, 'ID Pekerja', 9)
            ->text(455, 696, $entry->employee_number ?: '-', 9, true)
            ->text(45, 678, 'No. KWSP', 9)
            ->text(145, 678, $statutory?->epf_number ?: '-', 9)
            ->text(360, 678, 'No. PERKESO', 9)
            ->text(455, 678, $statutory?->socso_number ?: '-', 9)
            ->text(45, 660, 'No. Cukai', 9)
            ->text(145, 660, $statutory?->tax_number ?: '-', 9)
            ->text(360, 660, 'Status', 9)
            ->text(455, 660, $this->statusLabel($run->status), 9, true);

        $top = 628;
        $pdf->rectangle(40, $top, 515, 22, true, .92)
            ->text(48, $top + 7, 'PENDAPATAN', 9, true)
            ->text(455, $top + 7, 'AMAUN (RM)', 9, true);
        $y = $top - 17;

        foreach ($earnings->take(14) as $item) {
            $this->itemRow($pdf, $item, $y);
            $y -= 15;
        }

        $pdf->line(40, $y + 5, 555, $y + 5)
            ->text(360, $y - 8, 'Jumlah Pendapatan', 9, true)
            ->text(485, $y - 8, $this->money($entry->gross_pay), 9, true);
        $y -= 42;
        $pdf->rectangle(40, $y, 515, 22, true, .92)
            ->text(48, $y + 7, 'POTONGAN', 9, true)
            ->text(455, $y + 7, 'AMAUN (RM)', 9, true);
        $y -= 17;

        foreach ($deductions->take(14) as $item) {
            $this->itemRow($pdf, $item, $y);
            $y -= 15;
        }

        $pdf->line(40, $y + 5, 555, $y + 5)
            ->text(360, $y - 8, 'Jumlah Potongan', 9, true)
            ->text(485, $y - 8, $this->money($entry->total_deductions), 9, true);
        $summaryY = max(100, $y - 66);
        $pdf->rectangle(40, $summaryY, 515, 46, true, .88)
            ->text(52, $summaryY + 28, 'GAJI BERSIH', 12, true)
            ->text(430, $summaryY + 25, 'RM '.$this->money($entry->net_pay), 14, true);

        if ($statutory) {
            $pdf->text(
                45,
                $summaryY - 18,
                sprintf(
                    'Caruman majikan: KWSP RM %s · PERKESO/SKBBK RM %s · EIS RM %s · Jumlah RM %s',
                    $this->money($statutory->kwsp_employer),
                    $this->money($statutory->socso_employer),
                    $this->money($statutory->eis_employer),
                    $this->money($statutory->total_employer_contributions),
                ),
                8,
            )->text(
                45,
                $summaryY - 31,
                'Rujukan kadar: '.$statutory->rate_version,
                7,
            );
        }

        $pdf->line(40, 61, 555, 61)
            ->text(
                40,
                45,
                $this->oneLine($settings->payslip_note),
                7,
            )
            ->text(
                445,
                45,
                'Dijana: '.now()->format('d/m/Y H:i'),
                7,
            );

        return $pdf->output(
            'Slip Gaji '.$entry->employee_name.' '.$run->period_start?->format('Y-m'),
        );
    }

    private function itemRow(
        SimplePdfDocument $pdf,
        PayrollEntryItem $item,
        float $y,
    ): void {
        $description = $item->name;

        if ($item->quantity && (float) $item->quantity !== 1.0) {
            $description .= ' ('.$this->quantity($item).')';
        }

        $pdf->text(48, $y, $this->oneLine($description, 62), 8)
            ->text(485, $y, $this->money($item->amount), 8);
    }

    private function quantity(PayrollEntryItem $item): string
    {
        if ($item->category === 'overtime') {
            return number_format((float) $item->quantity / 60, 2).' jam';
        }

        return rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.');
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    private function oneLine(?string $value, int $limit = 80): string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit - 3).'...';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'finalized' => 'Dimuktamadkan',
            'approved' => 'Diluluskan',
            'hr_reviewed' => 'Disemak HR',
            default => 'Draf',
        };
    }
}
