<?php

namespace App\Support;

use App\Models\PerformanceReview;

class PerformancePdfRenderer
{
    public function render(
        PerformanceReview $review,
        array $employee,
        ?string $departmentName,
    ): string {
        $review->loadMissing(['cycle', 'goals', 'supervisor', 'improvementPlan']);
        $pdf = new SimplePdfDocument;
        $score = $review->moderated_score
            ?? $review->supervisor_score
            ?? $review->self_score;

        $pdf->rectangle(36, 742, 523, 64, true, .95)
            ->text(52, 784, config('app.name'), 15, true)
            ->text(52, 764, 'LAPORAN PRESTASI & KPI', 11, true)
            ->text(400, 784, $review->cycle?->name ?? '-', 10, true)
            ->text(400, 765, strtoupper($review->status), 8);

        $pdf->text(40, 716, 'MAKLUMAT PEKERJA', 10, true)
            ->line(40, 710, 555, 710)
            ->text(45, 692, 'Nama', 9)
            ->text(145, 692, $this->oneLine($employee['name'] ?? '-'), 9, true)
            ->text(360, 692, 'ID Pekerja', 9)
            ->text(455, 692, (string) ($employee['employee_number'] ?? '-'), 9, true)
            ->text(45, 674, 'Jabatan', 9)
            ->text(145, 674, $this->oneLine($departmentName ?: '-'), 9)
            ->text(360, 674, 'Jawatan', 9)
            ->text(455, 674, $this->oneLine($review->position_name ?: '-'), 9);

        $pdf->rectangle(40, 628, 515, 28, true, .90)
            ->text(50, 638, 'SKOR AKHIR', 10, true)
            ->text(205, 638, $score === null ? '-' : number_format((float) $score, 2).' / 5.00', 11, true)
            ->text(375, 638, 'RATING', 10, true)
            ->text(445, 638, $this->oneLine($review->final_rating ?: '-'), 9, true);

        $pdf->rectangle(40, 592, 515, 22, true, .94)
            ->text(48, 600, 'SASARAN / KPI', 9, true)
            ->text(350, 600, 'BERAT', 8, true)
            ->text(425, 600, 'PENYELIA', 8, true)
            ->text(500, 600, 'AKHIR', 8, true);
        $y = 574;

        foreach ($review->goals->take(12) as $goal) {
            $pdf->text(48, $y, $this->oneLine($goal->title, 48), 8)
                ->text(358, $y, number_format((float) $goal->weight, 0).'%', 8)
                ->text(438, $y, $goal->supervisor_score === null ? '-' : number_format((float) $goal->supervisor_score, 2), 8)
                ->text(510, $y, $goal->moderated_score === null ? '-' : number_format((float) $goal->moderated_score, 2), 8);
            $y -= 18;
        }

        $y -= 8;
        $sections = [
            'Kekuatan' => $review->strengths,
            'Ruang Penambahbaikan' => $review->improvement_areas,
            'Pelan Pembangunan' => $review->development_plan,
            'Ulasan HR' => $review->hr_comments,
        ];

        foreach ($sections as $label => $value) {
            if ($y < 115) {
                break;
            }

            $pdf->text(45, $y, strtoupper($label), 8, true)
                ->text(45, $y - 14, $this->oneLine($value ?: '-', 100), 8);
            $y -= 38;
        }

        if ($review->improvementPlan) {
            $pdf->rectangle(40, 75, 515, 28, true, .93)
                ->text(50, 85, 'PIP: '.strtoupper($review->improvementPlan->status), 9, true)
                ->text(
                    220,
                    85,
                    $review->improvementPlan->start_date?->format('d/m/Y')
                        .' - '
                        .$review->improvementPlan->end_date?->format('d/m/Y'),
                    8,
                );
        }

        $pdf->line(40, 58, 555, 58)
            ->text(40, 43, 'Laporan ini dijana daripada rekod penilaian yang dikawal Audit Trail.', 7)
            ->text(450, 43, now()->format('d/m/Y H:i'), 7);

        return $pdf->output(
            'Laporan Prestasi '.($employee['name'] ?? $review->employee_id),
        );
    }

    private function oneLine(?string $value, int $limit = 60): string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';

        return mb_strlen($value) <= $limit
            ? $value
            : mb_substr($value, 0, $limit - 3).'...';
    }
}
