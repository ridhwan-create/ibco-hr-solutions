<?php

namespace App\Support;

use App\Models\PerformanceReview;
use Illuminate\Validation\ValidationException;

class PerformanceScoreCalculator
{
    public function selfScore(PerformanceReview $review): float
    {
        return $this->weightedScore($review, 'self_score');
    }

    public function supervisorScore(PerformanceReview $review): float
    {
        return $this->weightedScore($review, 'supervisor_score');
    }

    public function moderatedScore(PerformanceReview $review): float
    {
        return $this->weightedScore($review, 'moderated_score');
    }

    public function rating(PerformanceReview $review, float $score): string
    {
        $scale = collect($review->cycle?->rating_scale ?? [])
            ->filter(fn ($item) => is_array($item)
                && isset($item['label'], $item['minimum']))
            ->sortByDesc(fn (array $item) => (float) $item['minimum']);

        foreach ($scale as $item) {
            if ($score >= (float) $item['minimum']) {
                return (string) $item['label'];
            }
        }

        return 'Belum Dinilai';
    }

    private function weightedScore(
        PerformanceReview $review,
        string $column,
    ): float {
        $review->loadMissing('goals');
        $weight = round((float) $review->goals->sum('weight'), 2);

        if (abs($weight - 100) > .01) {
            throw ValidationException::withMessages([
                'goals' => 'Jumlah pemberat sasaran penilaian mesti tepat 100%.',
            ]);
        }

        foreach ($review->goals as $goal) {
            if ($goal->{$column} === null) {
                throw ValidationException::withMessages([
                    'goals' => "Semua sasaran mesti mempunyai {$this->scoreLabel($column)}.",
                ]);
            }

            $score = (float) $goal->{$column};

            if ($score < 1 || $score > 5) {
                throw ValidationException::withMessages([
                    'goals' => 'Skor setiap sasaran mesti antara 1.00 hingga 5.00.',
                ]);
            }
        }

        return round((float) $review->goals->sum(
            fn ($goal) => (float) $goal->{$column} * (float) $goal->weight / 100,
        ), 2);
    }

    private function scoreLabel(string $column): string
    {
        return match ($column) {
            'self_score' => 'skor kendiri',
            'supervisor_score' => 'skor penyelia',
            default => 'skor moderasi',
        };
    }
}
