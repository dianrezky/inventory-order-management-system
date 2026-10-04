<?php

namespace App\Service;

// REPORT-01. Locale-aware CSV export; injection prevention (FR-11.4) prefixes values starting with =, +, -, @, TAB, CRLF with a single quote.
class CsvExportService
{
    public function exportStockLedger($rows, $locale = 'en')
    {
        // Column order/labels per PROJECT_REFERENCE.md REPORT-01 CSV Columns:
        // Tanggal, Produk, SKU, Gudang, Tipe, Qty, Ref Type, Ref ID, Dilakukan Oleh.
        $headers = $locale === 'id'
            ? ['Waktu', 'Produk', 'SKU', 'Gudang', 'Tipe', 'Kuantitas', 'Referensi Tipe', 'Referensi ID', 'Dilakukan Oleh']
            : ['Date', 'Product', 'SKU', 'Warehouse', 'Type', 'Quantity', 'Ref Type', 'Ref ID', 'Done By'];

        $lines = [$this->csvLine($headers)];

        foreach ($rows as $row) {
            [$doneAt, $productName, $sku, $warehouseName, $type, $refType, $refId, $userName] = array_map(
                [$this, 'escapeCsvField'],
                [
                    (string) ($row['done_at'] ?? ''),
                    (string) ($row['product_name'] ?? ''),
                    (string) ($row['product_sku'] ?? ''),
                    (string) ($row['warehouse_name'] ?? ''),
                    $this->tType($row['type'], $locale),
                    (string) ($row['ref_type'] ?? ''),
                    (string) ($row['ref_id'] ?? ''),
                    (string) ($row['user_name'] ?? ''),
                ]
            );

            // Quantity bypasses the injection prefix on purpose: it is a system-computed signed integer, and Issue rows legitimately carry a negative value that the prefix would corrupt into the text '-5
            $quantity = $this->rawNumericField($row['qty']);

            $lines[] = implode(',', [
                $doneAt, $productName, $sku, $warehouseName, $type, $quantity, $refType, $refId, $userName,
            ]);
        }

        return implode("\r\n", $lines);
    }

    public function exportCategories($rows, $locale = 'en')
    {
        $headers = $locale === 'id'
            ? ['Kode', 'Nama', 'Deskripsi', 'SKU Terkait', 'Status', 'Terakhir Diperbarui']
            : ['Code', 'Name', 'Description', 'Assigned SKUs', 'Status', 'Last Updated'];

        $lines = [$this->csvLine($headers)];

        foreach ($rows as $row) {
            $lines[] = $this->csvLine([
                (string) $row['code'],
                (string) $row['name'],
                (string) ($row['description'] ?? ''),
                $this->rawNumericField($row['assigned_sku_count'] ?? 0),
                $row['is_active'] ? ($locale === 'id' ? 'Aktif' : 'Active') : ($locale === 'id' ? 'Nonaktif' : 'Inactive'),
                (string) ($row['updated_at'] ?? ''),
            ]);
        }

        return implode("\r\n", $lines);
    }

    public function exportOrderStatus($rows, $locale = 'en')
    {
        // Column order/labels per PROJECT_REFERENCE.md REPORT-01 CSV Columns:
        // No. Order, Tanggal, Customer/Supplier, Gudang, Status, Items Count,
        // Total Nilai, Dibuat Oleh. Type is kept as a leading extra column —
        // it's not in the documented spec, but PO and SO rows are merged into
        // one file here, so dropping it would make "No. Order" ambiguous
        // (SO #12 and PO #12 both exist).
        $typeLabel = $locale === 'id' ? 'Tipe' : 'Type';
        $headers = [
            $typeLabel,
            $locale === 'id' ? 'No. Pesanan' : 'Order No.',
            $locale === 'id' ? 'Tanggal' : 'Date',
            $locale === 'id' ? 'Customer/Supplier' : 'Customer/Supplier',
            $locale === 'id' ? 'Gudang' : 'Warehouse',
            $locale === 'id' ? 'Status' : 'Status',
            $locale === 'id' ? 'Jumlah Item' : 'Items Count',
            $locale === 'id' ? 'Nilai Total' : 'Total Value',
            $locale === 'id' ? 'Dibuat Oleh' : 'Created By',
        ];

        $lines = [$this->csvLine($headers)];

        foreach ($rows as $row) {
            $lines[] = $this->csvLine([
                $row['order_type'],
                (string) $row['id'],
                (string) $row['order_date'],
                (string) ($row['party_name'] ?? ''),
                (string) ($row['warehouse_name'] ?? ''),
                $this->tStatus($row['status'], $locale),
                (string) ($row['items_count'] ?? 0),
                ($row['total_value'] ?? '') !== '' ? ($row['total_value'] ?? '') : '—',
                (string) ($row['creator'] ?? ''),
            ]);
        }

        return implode("\r\n", $lines);
    }

    private function csvLine($fields)
    {
        // RFC 4180 line: comma-joined, each field escaped and injection-prefixed
        return implode(',', array_map([$this, 'escapeCsvField'], $fields));
    }

    private function escapeCsvField($field)
    {
        // Trailing whitespace only: a leading =/+/-/@/tab/CR/LF must survive into the check below, or trimming deletes the very character the injection check exists to catch
        $field = rtrim($field);

        // RFC 4180 injection prevention. Leading spaces are skipped for the test
        // (" =cmd" is still evaluated as a formula by spreadsheets).
        if ($field !== '' && preg_match('/^[ ]*[=+\-@\t\r\n]/', $field)) {
            $field = "'" . $field;
        }

        // Quote any field containing a quote, comma or line break: an unquoted
        // CR/LF (e.g. from a textarea description) would start a new CSV row
        // whose first cell could begin with "=" and bypass the check above.
        if (str_contains($field, '"') || str_contains($field, ',') || str_contains($field, "\n") || str_contains($field, "\r")) {
            $field = '"' . str_replace('"', '""', $field) . '"';
        }

        return $field;
    }

    private function rawNumericField($value)
    {
        // A system-generated signed integer never needs comma/quote/injection escaping
        return (string) $value;
    }

    private function tType($type, $locale)
    {
        if ($locale !== 'id') {
            return $type;
        }

        $labels = [
            'Receipt' => 'Penerimaan',
            'Issue' => 'Pengeluaran',
            'Adjustment' => 'Penyesuaian',
        ];

        return $labels[$type] ?? $type;
    }

    private function tStatus($status, $locale)
    {
        if ($locale !== 'id') {
            if ($status === 'PartiallyReceived') {
                return 'Partially Received';
            }

            return $status;
        }

        $labels = [
            'Draft' => 'Draft',
            'Ordered' => 'Dipesan',
            'PendingApproval' => 'Menunggu Persetujuan',
            'Approved' => 'Disetujui',
            'Fulfilled' => 'Terealisasi',
            'Cancelled' => 'Dibatalkan',
            'PartiallyReceived' => 'Diterima Sebagian',
            'Received' => 'Diterima',
        ];

        return $labels[$status] ?? $status;
    }
}
