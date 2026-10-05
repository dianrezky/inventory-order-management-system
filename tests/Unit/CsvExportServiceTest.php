<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\CsvExportService;
use PHPUnit\Framework\TestCase;

final class CsvExportServiceTest extends TestCase
{
    private function makeService(): CsvExportService
    {
        return new CsvExportService();
    }

    public function testExportStockLedgerHeadersInEnglish(): void
    {
        $svc = $this->makeService();
        $csv = $svc->exportStockLedger([], 'en');

        $this->assertStringContainsString('Product', $csv);
        $this->assertStringContainsString('Warehouse', $csv);
        $this->assertStringContainsString('Type', $csv);
        $this->assertStringContainsString('Quantity', $csv);
    }

    public function testExportStockLedgerHeadersInIndonesian(): void
    {
        $svc = $this->makeService();
        $csv = $svc->exportStockLedger([], 'id');

        $this->assertStringContainsString('Produk', $csv);
        $this->assertStringContainsString('Gudang', $csv);
        $this->assertStringContainsString('Kuantitas', $csv);
    }

    public function testExportStockLedgerWithRows(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1,
                'product_name' => 'Widget',
                'warehouse_id' => 1,
                'warehouse_name' => 'WH-JKT',
                'type' => 'Receipt',
                'qty' => 100,
                'ref_type' => 'PO',
                'ref_id' => 5,
                'note' => null,
                'done_by_user_id' => 1,
                'user_name' => 'Admin',
                'done_at' => '2026-09-01 10:00:00',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        $this->assertStringContainsString('Widget', $csv);
        $this->assertStringContainsString('WH-JKT', $csv);
        $this->assertStringContainsString('100', $csv);
        $this->assertStringContainsString('PO', $csv);
        $this->assertStringContainsString('5', $csv);
    }

    public function testTypeTranslationReceipt(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => 'P', 'warehouse_id' => 1,
                'warehouse_name' => 'W', 'type' => 'Receipt', 'qty' => 1,
                'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $en = $svc->exportStockLedger($rows, 'en');
        $id = $svc->exportStockLedger($rows, 'id');

        $this->assertStringContainsString('Receipt', $en);
        $this->assertStringContainsString('Penerimaan', $id);
    }

    public function testTypeTranslationIssue(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => 'P', 'warehouse_id' => 1,
                'warehouse_name' => 'W', 'type' => 'Issue', 'qty' => -5,
                'ref_type' => 'SO', 'ref_id' => 3, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $en = $svc->exportStockLedger($rows, 'en');
        $id = $svc->exportStockLedger($rows, 'id');

        $this->assertStringContainsString('Issue', $en);
        $this->assertStringContainsString('Pengeluaran', $id);
    }

    public function testExportOrderStatusHeadersInEnglish(): void
    {
        $svc = $this->makeService();
        $csv = $svc->exportOrderStatus([], 'en');

        $this->assertStringContainsString('Order No.', $csv);
        $this->assertStringContainsString('Date', $csv);
        $this->assertStringContainsString('Status', $csv);
        $this->assertStringContainsString('Customer/Supplier', $csv);
        $this->assertStringContainsString('Items Count', $csv);
    }

    public function testExportOrderStatusHeadersInIndonesian(): void
    {
        $svc = $this->makeService();
        $csv = $svc->exportOrderStatus([], 'id');

        $this->assertStringContainsString('No. Pesanan', $csv);
        $this->assertStringContainsString('Tanggal', $csv);
        $this->assertStringContainsString('Status', $csv);
        $this->assertStringContainsString('Customer/Supplier', $csv);
        $this->assertStringContainsString('Jumlah Item', $csv);
    }

    public function testExportOrderStatusWithRows(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'order_type' => 'SO',
                'id' => 10,
                'order_date' => '2026-09-01',
                'status' => 'Approved',
                'party_name' => 'CV Toko Elektronik',
                'warehouse_name' => 'WH-JKT',
                'creator' => 'Beni',
                'total_value' => '750000',
            ],
        ];

        $csv = $svc->exportOrderStatus($rows, 'en');

        $this->assertStringContainsString('SO', $csv);
        $this->assertStringContainsString('10', $csv);
        $this->assertStringContainsString('2026-09-01', $csv);
        $this->assertStringContainsString('Approved', $csv);
        $this->assertStringContainsString('CV Toko Elektronik', $csv);
        $this->assertStringContainsString('750000', $csv);
    }

    public function testStatusTranslation(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'order_type' => 'SO', 'id' => 1, 'order_date' => '2026-09-01',
                'status' => 'PendingApproval', 'party_name' => 'P', 'warehouse_name' => 'W',
                'creator' => 'C', 'total_value' => '0',
            ],
        ];

        $en = $svc->exportOrderStatus($rows, 'en');
        $id = $svc->exportOrderStatus($rows, 'id');

        $this->assertStringContainsString('PendingApproval', $en);
        $this->assertStringContainsString('Menunggu Persetujuan', $id);
    }

    public function testEqualsPrefixInjectionIsEscaped(): void
    {
        // FR-11.4: CSV formula/injection prevention.
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => '=HYPERLINK("http://evil.com")',
                'warehouse_id' => 1, 'warehouse_name' => 'W', 'type' => 'Receipt',
                'qty' => 1, 'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        // Must be prefixed with ' to prevent formula injection
        $this->assertStringContainsString("'=", $csv);
    }

    public function testPlusPrefixInjectionIsEscaped(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => '+5-10',
                'warehouse_id' => 1, 'warehouse_name' => 'W', 'type' => 'Receipt',
                'qty' => 1, 'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        $this->assertStringContainsString("'+", $csv);
    }

    public function testMinusPrefixInjectionIsEscaped(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => '-5+10',
                'warehouse_id' => 1, 'warehouse_name' => 'W', 'type' => 'Receipt',
                'qty' => 1, 'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        $this->assertStringContainsString("'-", $csv);
    }

    public function testAtSignPrefixInjectionIsEscaped(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => '@HYPERLINK(...)',
                'warehouse_id' => 1, 'warehouse_name' => 'W', 'type' => 'Receipt',
                'qty' => 1, 'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        $this->assertStringContainsString("'@", $csv);
    }

    public function testTabPrefixInjectionIsEscaped(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => "\tHIDDEN",
                'warehouse_id' => 1, 'warehouse_name' => 'W', 'type' => 'Receipt',
                'qty' => 1, 'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        // Tab prefix → should be prefixed with '
        $this->assertStringContainsString("'\t", $csv);
    }

    public function testDoubleQuoteInsideFieldIsEscaped(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => 'Product "A"',
                'warehouse_id' => 1, 'warehouse_name' => 'W', 'type' => 'Receipt',
                'qty' => 1, 'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        // RFC 4180: " inside field → ""
        $this->assertStringContainsString('""', $csv);
    }

    public function testCommaInsideFieldIsQuoted(): void
    {
        $svc = $this->makeService();
        $rows = [
            [
                'product_id' => 1, 'product_name' => 'Widget, Blue',
                'warehouse_id' => 1, 'warehouse_name' => 'W', 'type' => 'Receipt',
                'qty' => 1, 'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ];

        $csv = $svc->exportStockLedger($rows, 'en');

        // Comma in field → wrapped in double quotes
        $this->assertStringContainsString('"Widget, Blue"', $csv);
    }

    public function testEmptyRowsProduceHeadersOnly(): void
    {
        $svc = $this->makeService();
        $csv = $svc->exportStockLedger([], 'en');

        $lines = explode("\r\n", trim($csv));
        $this->assertCount(1, $lines); // header only
    }

    public function testCrlfLineEndingsUsed(): void
    {
        $svc = $this->makeService();
        $csv = $svc->exportStockLedger([
            [
                'product_id' => 1, 'product_name' => 'P', 'warehouse_id' => 1,
                'warehouse_name' => 'W', 'type' => 'R', 'qty' => 1,
                'ref_type' => null, 'ref_id' => null, 'note' => null,
                'done_by_user_id' => 1, 'user_name' => 'U', 'done_at' => '2026-09-01',
            ],
        ], 'en');

        // Must use CRLF per RFC 4180
        $this->assertStringContainsString("\r\n", $csv);
    }

    public function testOrderStatusTableMatchesCsvColumnsAndValues(): void
    {
        $svc = $this->makeService();
        $rows = [[
            'order_type' => 'SO',
            'id' => 12,
            'order_date' => '2026-10-01',
            'party_name' => 'PT Maju',
            'warehouse_name' => 'WH-JKT',
            'status' => 'Approved',
            'items_count' => 3,
            'total_value' => '150000.00',
            'creator' => 'Beni',
        ], [
            'order_type' => 'PO',
            'id' => 7,
            'order_date' => '2026-10-02',
            'status' => 'PartiallyReceived',
        ]];

        $table = $svc->orderStatusTable($rows);

        $this->assertCount(9, $table['headers']);
        $this->assertSame(['SO', '12', '2026-10-01', 'PT Maju', 'WH-JKT', 'Approved', '3', '150000.00', 'Beni'], $table['rows'][0]);
        $this->assertSame('Partially Received', $table['rows'][1][5]);
        $this->assertSame('—', $table['rows'][1][7]);

        // The CSV is built from the very same table.
        $csv = explode("\r\n", $svc->exportOrderStatus($rows));
        $this->assertCount(3, $csv);
        $this->assertSame(implode(',', $table['headers']), $csv[0]);
    }
}
