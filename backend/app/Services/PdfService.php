<?php

namespace App\Services;

use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF reports via mPDF — chosen over dompdf because it shapes Bangla conjuncts correctly
 * (decision in Plan §২৬ #16). Bangla runs pick a Bengali-capable font automatically.
 */
class PdfService
{
    public function __construct(private SiteSettings $settings) {}

    public function render(string $view, array $data, string $title): string
    {
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'tempDir' => $tempDir,
            'format' => 'A4',
            'margin_top' => 28,
            'margin_bottom' => 18,
            'margin_left' => 16,
            'margin_right' => 16,
            'default_font' => 'dejavusans',
            'default_font_size' => 10,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor('C-STAR');

        $site = $this->settings->all();
        $mpdf->SetHTMLHeader(view('pdf.partials.header', ['site' => $site, 'title' => $title])->render());
        $mpdf->SetHTMLFooter(view('pdf.partials.footer', ['site' => $site])->render());
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output('', 'S');
    }

    /** Inline PDF response (opens in the browser's viewer). */
    public function response(string $view, array $data, string $title, string $filename): Response
    {
        return response($this->render($view, $data, $title), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
