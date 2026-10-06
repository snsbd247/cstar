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
    public function __construct(private SiteSettings $settings, private SystemSettings $system) {}

    public function render(string $view, array $data, string $title): string
    {
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'tempDir' => $tempDir,
            'format' => $this->system->get('pdf', 'paper_size'),
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
        $doc = $this->letterhead();
        $mpdf->SetAuthor($doc['short_name']);

        $site = $doc;
        $mpdf->SetHTMLHeader(view('pdf.partials.header', ['site' => $site, 'title' => $title])->render());
        $mpdf->SetHTMLFooter(view('pdf.partials.footer', ['site' => $site])->render());
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output('', 'S');
    }

    /**
     * Letterhead for every PDF: names from Settings → General, contact lines from Center Information
     * (falling back to the public website details), colour and footer from PDF Settings.
     */
    public function letterhead(): array
    {
        $web = $this->settings->all();
        $center = $this->system->group('center');
        $pick = fn (string $key) => $center[$key] !== '' ? $center[$key] : ($web[$key] ?? '');

        return [
            'short_name' => $this->system->get('general', 'center_short_name'),
            'full_name' => $center['legal_name'] !== '' ? $center['legal_name'] : $this->system->get('general', 'center_full_name'),
            'phone' => $pick('phone'), 'email' => $pick('email'), 'address' => $pick('address'),
            'website' => $center['website'], 'registration_no' => $center['registration_no'], 'tin' => $center['tin'],
            'color' => $this->system->get('pdf', 'brand_color'),
            'footer_note' => $this->system->get('pdf', 'footer_note'),
            'show_printed_date' => $this->system->flag('pdf', 'show_printed_date'),
        ];
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
