<?php

namespace App\Repository\MySQL;

use App\Core\Result;
use App\Repository\Interface\FileValidationRepositoryInterface;

class FileValidationMySQLRepository implements FileValidationRepositoryInterface
{
    private $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function findActiveRules()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'file_validation_rules',
                null,
                'extension, mime_type, header_hex, footer_hex, read_bytes',
                [],
                [],
                null,
                ['is_active' => 1],
                'extension'
            );

            $keyed = [];
            foreach ($rows as $row) {
                $extension = strtolower((string) $row['extension']);
                $keyed[$extension] = [
                    'extension'  => $extension,
                    'mime_type'  => (string) $row['mime_type'],
                    'header_hex' => $row['header_hex'] !== null ? strtoupper((string) $row['header_hex']) : null,
                    'footer_hex' => $row['footer_hex'] !== null ? strtoupper((string) $row['footer_hex']) : null,
                    'read_bytes' => (int) $row['read_bytes'],
                ];
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find file validation rules';
            $result->data = $keyed;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
