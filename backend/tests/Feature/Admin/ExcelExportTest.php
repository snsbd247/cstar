<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Services\XlsxWriter;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/** Sprint 22 (Plan §৮, §২০): reports download as real Excel workbooks, not only CSV. */
class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function sheetXml(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Not a zip (xlsx) file');
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        return $xml;
    }

    public function test_writer_keeps_numbers_numeric_and_text_escaped(): void
    {
        $xml = $this->sheetXml(app(XlsxWriter::class)->build(
            [['key' => 'name', 'label' => 'Child', 'type' => 'text'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'money']],
            [['name' => 'আয়ান <&>', 'amount' => 1500.5]], ['name' => 'Total', 'amount' => 1500.5], ['Collection'],
        ));
        $this->assertStringContainsString('আয়ান &lt;&amp;&gt;', $xml);
        $this->assertStringContainsString('<v>1500.5</v>', $xml);

        $html = '<h2>Assets</h2><table><tr><th>Code</th><th>Amount</th></tr><tr><td>1111</td><td>Tk 1,234.50</td></tr><tr><td>x</td><td>(200.00)</td></tr></table>';
        $xml = $this->sheetXml(app(XlsxWriter::class)->fromHtml($html));
        $this->assertStringContainsString('<v>1234.5</v>', $xml);
        $this->assertStringContainsString('<v>-200</v>', $xml);
        $this->assertStringContainsString('>1111</t>', $xml);                       // account codes stay text
    }

    public function test_operational_and_accounts_reports_download_as_xlsx(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $branch = Branch::factory()->create();
        $this->actingAs($this->userWithRole(Role::Accountant, $branch));

        $report = $this->get('/api/v1/reports/collection?format=xlsx')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $report->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $report->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Collection by payment method', $this->sheetXml($report->getContent()));

        $tb = $this->get('/api/v1/accounts/reports/trial-balance?format=xlsx')->assertOk();
        $this->assertStringContainsString('Trial Balance', $this->sheetXml($tb->getContent()));
        $this->getJson('/api/v1/reports/collection?format=docx')->assertJsonValidationErrors('format');
    }
}
