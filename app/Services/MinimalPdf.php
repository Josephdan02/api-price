<?php

namespace App\Services;

/**
 * Escritor mínimo de PDF 1.4 sin dependencias externas — FASE 6.4.5.
 *
 * Usa la fuente base Helvetica (sin embeber) y streams de contenido
 * sin comprimir para que el texto sea verificable en tests.
 * Soporta múltiples páginas A4 con paginación automática.
 *
 * Cada línea: ['t' => texto, 's' => tamaño].
 */
class MinimalPdf
{
    /** @var array<int, array<int, array{t:string,s:int}>> */
    private array $pages = [];

    /** @var array<int, array{t:string,s:int}> */
    private array $current = [];

    private float $y;

    private const PAGE_W = 595.0;

    private const PAGE_H = 842.0;

    private const MARGIN_X = 50.0;

    private const MARGIN_TOP = 792.0;

    private const MARGIN_BOTTOM = 55.0;

    public function __construct()
    {
        $this->y = self::MARGIN_TOP;
    }

    public function line(string $text, int $size = 10): void
    {
        $height = $size + 5;

        if ($this->y - $height < self::MARGIN_BOTTOM) {
            $this->newPage();
        }

        $this->current[] = ['t' => $text, 's' => $size, 'y' => $this->y];
        $this->y -= $height;
    }

    public function blank(int $size = 10): void
    {
        $this->line(' ', $size);
    }

    public function pageCount(): int
    {
        return count($this->pages) + ($this->current !== [] ? 1 : 0);
    }

    public function render(string $title = 'Acta'): string
    {
        $this->flush();

        $total = count($this->pages);

        // Objetos: 1 catálogo, 2 páginas, luego por página (page, content), font al final.
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        $next = 3;
        $pageObjIds = [];

        foreach ($this->pages as $i => $lines) {
            $pageObjIds[$i] = $next;
            $kids[] = "{$next} 0 R";
            $next += 2;
        }

        $fontId = $next;
        $next++;

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';

        foreach ($this->pages as $i => $lines) {
            $content = $this->renderPage($lines, $title, $i + 1, $total);
            $contentId = $pageObjIds[$i] + 1;
            $objects[$pageObjIds[$i]] = '<< /Type /Page /Parent 2 0 R '
                . '/MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . '] '
                . '/Resources << /Font << /F1 ' . $fontId . ' 0 R >> >> '
                . '/Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        }

        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    // ─── Internos ─────────────────────────────────────────────────────────────

    private function newPage(): void
    {
        $this->flush();
        $this->y = self::MARGIN_TOP;
    }

    private function flush(): void
    {
        if ($this->current !== []) {
            $this->pages[] = $this->current;
            $this->current = [];
        }
    }

    /**
     * @param array<int, array{t:string,s:int,y:float}> $lines
     */
    private function renderPage(array $lines, string $title, int $num, int $total): string
    {
        $out = '';

        foreach ($lines as $line) {
            $text = self::pdfText($line['t']);

            if ($text === '') {
                continue;
            }

            $out .= 'BT /F1 ' . $line['s'] . ' Tf ' . self::MARGIN_X . ' ' . $line['y'] . ' Td (' . $text . ') Tj ET' . "\n";
        }

        // Pie de página con numeración.
        $footer = self::pdfText($title . '  |  Pagina ' . $num . ' de ' . $total);
        $out .= 'BT /F1 8 Tf ' . self::MARGIN_X . ' 30 Td (' . $footer . ') Tj ET' . "\n";

        return $out;
    }

    private static function pdfText(string $text): string
    {
        // Transliterar a ASCII (WinAnsi/Helvetica no soporta tildes UTF-8).
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($ascii === false) {
            $ascii = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        // Recortar a longitud segura por línea PDF.
        $ascii = substr($ascii, 0, 220);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
