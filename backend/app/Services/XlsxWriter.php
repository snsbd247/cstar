<?php

namespace App\Services;

use DOMDocument;
use RuntimeException;
use ZipArchive;

/**
 * A small Excel (.xlsx) writer for report tables (Sprint 22 — Plan §৮, §২০ "Export PDF / Excel"). Numbers stay
 * numbers (Excel can sum them), money gets a thousands format, text is stored as Unicode so Bangla names open
 * correctly, header cells are bold. Needs PHP's zip extension (standard on cPanel; checked by cstar:deploy).
 */
class XlsxWriter
{
    /**
     * @param  list<array{key: string, label: string, type?: string}>  $columns  type: text | number | money | decimal | percent | date
     * @param  iterable<array<string, mixed>|object>  $rows  arrays, models or a collection
     * @param  array<string, mixed>|null  $totals
     * @param  list<string>  $titleLines  shown above the table (report name, period)
     */
    public function build(array $columns, iterable $rows, mixed $totals = null, array $titleLines = [], string $sheetName = 'Report'): string
    {
        $rows = collect($rows)->values()->all();
        $totals = $totals ?: null;
        $grid = [array_map(fn ($c) => ['v' => $c['label'], 'bold' => true], $columns)];
        foreach ([...$rows, ...($totals ? [$totals] : [])] as $n => $row) {
            $bold = $totals && $n === count($rows);
            $grid[] = array_map(function ($c) use ($row, $bold) {
                $v = data_get($row, $c['key']);
                $numeric = in_array($c['type'] ?? 'text', ['number', 'money', 'decimal', 'percent'], true) && is_numeric($v);

                return ['v' => $numeric ? 0 + $v : $v, 'bold' => $bold, 'fmt' => $numeric ? ($c['type'] ?? 'number') : null];
            }, $columns);
        }

        return $this->render($grid, $titleLines, $sheetName, freezeAfter: 1);
    }

    /**
     * Turns the tables of a printable (PDF) view into one sheet — used for the accounts reports, so Excel always
     * matches the PDF. Header cells and <b> cells are bold; amounts like "1,234.00" or "(1,234.00)" become numbers
     * (account codes and dates stay text).
     */
    public function fromHtml(string $html, array $titleLines = [], string $sheetName = 'Report'): string
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $grid = [];
        // Headings and tables in page order, so section titles (Assets, Liabilities …) stay above their rows.
        foreach ((new \DOMXPath($dom))->query('//h1|//h2|//h3|//table') as $node) {
            if ($grid) {
                $grid[] = [];
            }
            if ($node->nodeName !== 'table') {
                $grid[] = [['v' => trim($node->textContent), 'bold' => true]];

                continue;
            }
            foreach ($node->getElementsByTagName('tr') as $tr) {
                $row = [];
                foreach ($tr->childNodes as $td) {
                    if (! in_array($td->nodeName, ['td', 'th'], true)) {
                        continue;
                    }
                    $text = trim((string) preg_replace('/\s+/u', ' ', $td->textContent));
                    $bold = $td->nodeName === 'th' || $td->getElementsByTagName('b')->length > 0;
                    if (preg_match('/^(Tk\s*)?\(?-?[\d,]+\.\d{2}\)?$/', $text)) {
                        $number = (float) str_replace(['Tk', ' ', ',', '(', ')'], '', $text);
                        $row[] = ['v' => str_contains($text, '(') ? -$number : $number, 'bold' => $bold, 'fmt' => 'money'];
                    } else {
                        $row[] = ['v' => $text === '' ? null : $text, 'bold' => $bold];
                    }
                    for ($span = (int) $td->getAttribute('colspan'); $span > 1; $span--) {
                        $row[] = ['v' => null];
                    }
                }
                $grid[] = $row;
            }
        }

        return $this->render($grid, $titleLines, $sheetName, freezeAfter: 0);
    }

    /** @param  list<list<array{v: mixed, bold?: bool, fmt?: ?string}>>  $grid */
    private function render(array $grid, array $titleLines, string $sheetName, int $freezeAfter): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is needed for Excel export.');
        }
        $xml = [];
        $r = 0;
        foreach ($titleLines as $i => $line) {
            $r++;
            $xml[] = $this->row($r, [$this->text('A', $r, $line, $i === 0 ? 1 : 0)]);
        }
        if ($titleLines) {
            $r++;
        }
        $firstRow = $r + 1;
        $widths = [];
        foreach ($grid as $cells) {
            $r++;
            $out = [];
            foreach ($cells as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 10, min(60, mb_strlen((string) ($cell['v'] ?? '')) + 2));
                $out[] = $this->cell($this->colName($i), $r, $cell['v'] ?? null, $cell['fmt'] ?? null, (bool) ($cell['bold'] ?? false));
            }
            $xml[] = $this->row($r, $out);
        }

        ksort($widths);
        $cols = implode('', array_map(fn ($w, $i) => '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>', $widths, array_keys($widths)));
        $frozen = $freezeAfter ? $firstRow + $freezeAfter - 1 : 0;
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .($frozen ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$frozen.'" topLeftCell="A'.($frozen + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '')
            .($cols ? "<cols>{$cols}</cols>" : '').'<sheetData>'.implode('', $xml).'</sheetData></worksheet>';

        // The app's own storage — the system temp folder is often not writable on shared hosting.
        $dir = storage_path('framework/cache');
        is_dir($dir) || mkdir($dir, 0775, true);
        $path = $dir.'/xlsx-'.bin2hex(random_bytes(8)).'.tmp';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->esc(mb_substr((string) preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheetName), 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', self::STYLES);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    // Style ids: 0 normal · 1 bold · 2 money (#,##0.00) · 3 bold money · 4 decimal · 5 bold decimal
    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
        .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
        .'<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="2" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/></cellXfs>'
        .'</styleSheet>';

    private function cell(string $col, int $row, mixed $value, ?string $fmt, bool $bold): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($fmt !== null && is_numeric($value)) {
            $style = match ($fmt) {
                'money' => 2, 'decimal' => 4, default => 0
            } + ($bold ? 1 : 0);

            return '<c r="'.$col.$row.'"'.($style ? ' s="'.$style.'"' : '').'><v>'.(0 + $value).'</v></c>';
        }

        return $this->text($col, $row, (string) $value, $bold ? 1 : 0);
    }

    private function text(string $col, int $row, string $value, int $style = 0): string
    {
        return '<c r="'.$col.$row.'" t="inlineStr"'.($style ? ' s="'.$style.'"' : '').'><is><t xml:space="preserve">'.$this->esc($value).'</t></is></c>';
    }

    private function row(int $n, array $cells): string
    {
        return '<row r="'.$n.'">'.implode('', $cells).'</row>';
    }

    private function colName(int $i): string
    {
        $name = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $name = chr(65 + ($i - 1) % 26).$name;
        }

        return $name;
    }

    private function esc(string $s): string
    {
        // Strip control characters XML does not allow, then escape.
        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
