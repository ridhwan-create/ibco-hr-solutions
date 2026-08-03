<?php

namespace App\Support;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentReferenceGenerator
{
    public function next(string $sequenceKey, ?int $userId = null): string
    {
        return DB::transaction(function () use ($sequenceKey, $userId) {
            $sequence = DocumentSequence::query()
                ->where('sequence_key', strtoupper($sequenceKey))
                ->lockForUpdate()
                ->first();

            if (! $sequence || ! $sequence->is_active) {
                throw ValidationException::withMessages([
                    'sequence_key' => 'Siri nombor rujukan belum dikonfigurasi atau tidak aktif.',
                ]);
            }

            $year = (int) now()->format('Y');
            if ($sequence->reset_annually && $sequence->last_year !== $year) {
                $sequence->next_number = 1;
            }

            $number = (int) $sequence->next_number;
            $reference = $this->format(
                $sequence->format,
                $sequence->prefix,
                $year,
                $number,
            );

            if (mb_strlen($reference) > 120) {
                throw ValidationException::withMessages([
                    'format' => 'Nombor rujukan yang dijana melebihi 120 aksara.',
                ]);
            }

            $sequence->forceFill([
                'next_number' => $number + 1,
                'last_year' => $year,
                'updated_by' => $userId,
            ])->save();

            return $reference;
        }, 3);
    }

    public function preview(DocumentSequence $sequence): string
    {
        $year = (int) now()->format('Y');
        $number = $sequence->reset_annually && $sequence->last_year !== $year
            ? 1
            : (int) $sequence->next_number;

        return $this->format(
            $sequence->format,
            $sequence->prefix,
            $year,
            $number,
        );
    }

    private function format(
        string $format,
        string $prefix,
        int $year,
        int $number,
    ): string {
        $formatted = str_replace(
            ['{{PREFIX}}', '{{YEAR}}', '{{YY}}'],
            [$prefix, (string) $year, substr((string) $year, -2)],
            $format,
        );

        $formatted = preg_replace_callback(
            '/\{\{SEQ(?::(\d{1,2}))?\}\}/',
            fn (array $matches) => str_pad(
                (string) $number,
                min(12, max(1, (int) ($matches[1] ?? 5))),
                '0',
                STR_PAD_LEFT,
            ),
            $formatted,
        ) ?? $formatted;

        return trim($formatted);
    }
}
