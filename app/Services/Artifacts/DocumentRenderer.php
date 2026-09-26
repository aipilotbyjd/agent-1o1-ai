<?php

namespace App\Services\Artifacts;

use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

/**
 * Turns content a model can write as text into real binary documents, so an
 * agent can hand over a PDF, spreadsheet or Word file instead of trying to
 * emit the bytes itself — see `ExportArtifactTool`.
 *
 * - `pdf`: Markdown, or a full HTML document, rendered by Dompdf with remote
 *   resources and PHP disabled, so model-written HTML can't fetch anything.
 * - `xlsx`: CSV, or JSON `{"sheets": [{"name": "...", "rows": [[...], ...]}]}`
 *   (a bare list of rows is one sheet).
 * - `docx`: Markdown. Raw HTML in it is stripped, not passed through.
 */
class DocumentRenderer
{
    public const FORMATS = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function render(string $format, string $content): string
    {
        return match ($format) {
            'pdf' => $this->pdf($content),
            'xlsx' => $this->xlsx($content),
            'docx' => $this->docx($content),
            default => throw new InvalidArgumentException("Unsupported format [{$format}]."),
        };
    }

    public function pdf(string $content): string
    {
        $html = $this->looksLikeHtml($content)
            ? $content
            : $this->page($this->markdown($content, allowHtml: true));

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setChroot(sys_get_temp_dir());
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function xlsx(string $content): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($this->sheets($content) as $index => $sheet) {
            $worksheet = $spreadsheet->createSheet($index);
            $worksheet->setTitle($this->sheetTitle($sheet['name'] ?? '', $index));
            $worksheet->fromArray($sheet['rows'], null, 'A1', true);

            if ($sheet['rows'] !== []) {
                $worksheet->getStyle('1:1')->getFont()->setBold(true);
            }

            foreach ($worksheet->getColumnIterator() as $column) {
                $worksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $this->capture(fn (string $path) => (new Xlsx($spreadsheet))->save($path));
    }

    public function docx(string $content): string
    {
        $word = new PhpWord;
        Html::addHtml($word->addSection(), $this->markdown($content, allowHtml: false));

        return $this->capture(fn (string $path) => WordIOFactory::createWriter($word, 'Word2007')->save($path));
    }

    /**
     * @return list<array{name?: string, rows: list<list<mixed>>}>
     */
    private function sheets(string $content): array
    {
        $json = json_decode($content, true);

        if (is_array($json)) {
            if (isset($json['sheets']) && is_array($json['sheets'])) {
                return array_map(fn ($sheet) => [
                    'name' => (string) ($sheet['name'] ?? ''),
                    'rows' => $this->rows($sheet['rows'] ?? []),
                ], array_values($json['sheets']));
            }

            if (array_is_list($json)) {
                return [['rows' => $this->rows($json)]];
            }
        }

        $rows = array_map(
            fn (string $line) => str_getcsv($line, escape: ''),
            preg_split('/\r\n|\n|\r/', trim($content)) ?: [],
        );

        return [['rows' => $rows]];
    }

    /**
     * @return list<list<mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        // A list of objects becomes a header row plus one row per object.
        if ($rows !== [] && is_array($rows[0] ?? null) && ! array_is_list($rows[0])) {
            $headers = array_keys($rows[0]);

            return [$headers, ...array_map(fn ($row) => array_map(fn ($key) => $row[$key] ?? null, $headers), $rows)];
        }

        return array_values(array_map(fn ($row) => is_array($row) ? array_values($row) : [$row], $rows));
    }

    private function sheetTitle(string $name, int $index): string
    {
        $title = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name));

        return mb_substr($title !== '' ? $title : 'Sheet'.($index + 1), 0, 31);
    }

    private function markdown(string $content, bool $allowHtml): string
    {
        return (string) (new GithubFlavoredMarkdownConverter([
            'html_input' => $allowHtml ? 'allow' : 'strip',
            'allow_unsafe_links' => false,
        ]))->convert($content);
    }

    private function looksLikeHtml(string $content): bool
    {
        return (bool) preg_match('/^\s*(<!doctype html|<html[\s>])/i', $content);
    }

    private function page(string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:"DejaVu Sans",sans-serif;font-size:11pt;line-height:1.5;color:#1f2937}'
            .'h1{font-size:20pt}h2{font-size:15pt}h3{font-size:12.5pt}'
            .'table{border-collapse:collapse;width:100%;margin:8pt 0}th,td{border:1px solid #d1d5db;padding:4pt 6pt;text-align:left}th{background:#f3f4f6}'
            .'code{font-family:"DejaVu Sans Mono",monospace;background:#f3f4f6;padding:1pt 3pt}pre{background:#f3f4f6;padding:6pt}'
            .'</style></head><body>'.$body.'</body></html>';
    }

    /**
     * The writers only save to a path; this hands back the bytes instead.
     */
    private function capture(callable $write): string
    {
        $path = tempnam(sys_get_temp_dir(), 'artifact');

        try {
            $write($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
