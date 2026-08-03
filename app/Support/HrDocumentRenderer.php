<?php

namespace App\Support;

use App\Models\HrDocument;
use Carbon\CarbonInterface;

class HrDocumentRenderer
{
    /**
     * @return array<string, string>
     */
    public function variables(HrDocument $document): array
    {
        return [
            'employee_name' => $document->employee_name,
            'employee_number' => $document->employee_number ?? '-',
            'employee_email' => $document->employee_email ?? '-',
            'department_name' => $document->department_name ?? '-',
            'position_name' => $document->position_name ?? '-',
            'reference_number' => $document->reference_number ?? 'DRAF',
            'issue_date' => $this->date($document->issued_at),
            'effective_date' => $this->date($document->effective_date),
            'expiry_date' => $this->date($document->expiry_date),
            'signatory_name' => $document->signatory_name ?? '-',
            'signatory_position' => $document->signatory_position ?? '-',
            'company_name' => (string) config('app.name', 'IBCO HR Solutions'),
            'today' => now()->format('d/m/Y'),
            ...collect($document->custom_variables ?? [])
                ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
                ->all(),
        ];
    }

    public function renderText(string $text, HrDocument $document): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $matches) => $this->variables($document)[$matches[1]]
                ?? $matches[0],
            $text,
        ) ?? $text;
    }

    public function pdf(HrDocument $document): string
    {
        $subject = $this->renderText($document->subject, $document);
        $body = $this->renderText($document->body, $document);
        $lines = [
            ['text' => (string) config('app.name', 'IBCO HR Solutions'), 'bold' => true, 'size' => 15],
            ['text' => 'DOKUMEN SUMBER MANUSIA', 'bold' => true, 'size' => 9],
            ['text' => ''],
            ['text' => 'Rujukan: '.($document->reference_number ?? 'DRAF'), 'bold' => false, 'size' => 9],
            ['text' => 'Tarikh: '.$this->date($document->issued_at ?? now()), 'bold' => false, 'size' => 9],
            ['text' => 'Klasifikasi: '.strtoupper($document->confidentiality), 'bold' => false, 'size' => 9],
            ['text' => ''],
            ['text' => $document->employee_name, 'bold' => true, 'size' => 10],
            ['text' => ($document->employee_number ?? '-').' · '.($document->position_name ?? '-'), 'bold' => false, 'size' => 9],
            ['text' => $document->department_name ?? '-', 'bold' => false, 'size' => 9],
            ['text' => ''],
        ];

        foreach ($this->wrap($subject, 82) as $line) {
            $lines[] = ['text' => $line, 'bold' => true, 'size' => 11];
        }
        $lines[] = ['text' => ''];

        foreach (preg_split('/\R/u', $body) ?: [] as $paragraph) {
            if (trim($paragraph) === '') {
                $lines[] = ['text' => ''];
                continue;
            }

            foreach ($this->wrap($paragraph, 92) as $line) {
                $lines[] = ['text' => $line, 'bold' => false, 'size' => 10];
            }
        }

        $lines[] = ['text' => ''];
        if ($document->effective_date) {
            $lines[] = ['text' => 'Tarikh kuat kuasa: '.$this->date($document->effective_date), 'bold' => true, 'size' => 9];
        }
        if ($document->expiry_date) {
            $lines[] = ['text' => 'Tarikh tamat: '.$this->date($document->expiry_date), 'bold' => true, 'size' => 9];
        }
        $lines[] = ['text' => ''];
        $lines[] = ['text' => 'Yang benar,', 'bold' => false, 'size' => 10];
        $lines[] = ['text' => $document->signatory_name ?? 'Pihak Pengurusan', 'bold' => true, 'size' => 10];
        $lines[] = ['text' => $document->signatory_position ?? config('app.name'), 'bold' => false, 'size' => 9];
        $lines[] = ['text' => ''];
        $lines[] = ['text' => 'Dokumen ini dijana melalui IBCO HR Solutions.', 'bold' => false, 'size' => 8];

        return $this->buildPdf(array_chunk($lines, 44), $subject);
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text, int $width): array
    {
        $text = trim(preg_replace('/[\t ]+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if (mb_strlen($candidate) <= $width) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param  array<int, array<int, array{text: string, bold?: bool, size?: int}>>  $pages
     */
    private function buildPdf(array $pages, string $title): string
    {
        $pageCount = max(1, count($pages));
        $pageIds = [];
        $contentIds = [];
        $nextObject = 3;

        for ($index = 0; $index < $pageCount; $index++) {
            $pageIds[] = $nextObject++;
            $contentIds[] = $nextObject++;
        }

        $regularFontId = $nextObject++;
        $boldFontId = $nextObject++;
        $infoId = $nextObject;
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids ['
                .implode(' ', array_map(fn (int $id) => "{$id} 0 R", $pageIds))
                ."] /Count {$pageCount} >>",
        ];

        foreach ($pageIds as $index => $pageId) {
            $contentId = $contentIds[$index];
            $commands = [];
            $y = 790;
            foreach ($pages[$index] ?? [] as $line) {
                $font = ($line['bold'] ?? false) ? 'F2' : 'F1';
                $size = (int) ($line['size'] ?? 10);
                $commands[] = sprintf(
                    'BT /%s %d Tf 55 %.2F Td (%s) Tj ET',
                    $font,
                    $size,
                    $y,
                    $this->escape($line['text']),
                );
                $y -= $line['text'] === '' ? 10 : 16;
            }
            if ($pageCount > 1) {
                $commands[] = sprintf(
                    'BT /F1 8 Tf 520 25 Td (%d/%d) Tj ET',
                    $index + 1,
                    $pageCount,
                );
            }
            $stream = implode("\n", $commands)."\n";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                ."/Resources << /Font << /F1 {$regularFontId} 0 R /F2 {$boldFontId} 0 R >> >> "
                ."/Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n{$stream}endstream";
        }

        $objects[$regularFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$boldFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[$infoId] = '<< /Title ('.$this->escape($title).') /Creator (IBCO HR Solutions) >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)
            ." /Root 1 0 R /Info {$infoId} 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function date(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '-';
        }

        return $date instanceof CarbonInterface
            ? $date->format('d/m/Y')
            : date('d/m/Y', strtotime($date));
    }

    private function escape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', '', ' '],
            $encoded,
        );
    }
}
