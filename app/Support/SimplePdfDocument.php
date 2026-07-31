<?php

namespace App\Support;

class SimplePdfDocument
{
    /** @var array<int, string> */
    private array $commands = [];

    public function text(
        float $x,
        float $y,
        string $text,
        float $size = 10,
        bool $bold = false,
    ): self {
        $font = $bold ? 'F2' : 'F1';
        $this->commands[] = sprintf(
            "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET",
            $font,
            $size,
            $x,
            $y,
            $this->escape($text),
        );

        return $this;
    }

    public function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $width = .5,
    ): self {
        $this->commands[] = sprintf(
            "%.2F w %.2F %.2F m %.2F %.2F l S",
            $width,
            $x1,
            $y1,
            $x2,
            $y2,
        );

        return $this;
    }

    public function rectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        bool $fill = false,
        float $gray = .94,
    ): self {
        $operator = $fill ? 'f' : 'S';
        $prefix = $fill ? sprintf('%.2F g ', $gray) : '';
        $suffix = $fill ? ' 0 g' : '';
        $this->commands[] = sprintf(
            '%s%.2F %.2F %.2F %.2F re %s%s',
            $prefix,
            $x,
            $y,
            $width,
            $height,
            $operator,
            $suffix,
        );

        return $this;
    }

    public function output(string $title = 'Payslip'): string
    {
        $stream = implode("\n", $this->commands)."\n";
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                .'/Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> '
                .'/Contents 6 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica '
                .'/Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold '
                .'/Encoding /WinAnsiEncoding >>',
            6 => '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
            7 => '<< /Title ('.$this->escape($title).') '
                .'/Creator (IBCO HR Solutions) >>',
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)
            ." /Root 1 0 R /Info 7 0 R >>\n"
            ."startxref\n{$xref}\n%%EOF";

        return $pdf;
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
