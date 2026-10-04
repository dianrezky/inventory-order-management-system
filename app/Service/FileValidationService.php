<?php

namespace App\Service;

use App\Core\CacheService;
use App\Core\Result;
use App\Repository\Interface\FileValidationRepositoryInterface;

// DB-backed upload allow-list (mirrors the DMS FileValidationTrait concept:
// getListAllowedExtension / getListMagicBytesFile). Reads file_validation_rules
// once per cache TTL (reference/near-static data) and hands the rule set to
// ImageUploadService. Same repo→Memcached shape as PermissionService.
class FileValidationService
{
    public const CACHE_KEY = 'file_validation.rules';
    public const CACHE_TTL = 3600;

    private $fileValidationRepository;
    private $cacheService;

    public function __construct(FileValidationRepositoryInterface $fileValidationRepository, CacheService $cacheService)
    {
        $this->fileValidationRepository = $fileValidationRepository;
        $this->cacheService = $cacheService;
    }

    // Plain array return (ext => rule config), no Result — a read-only lookup
    // with no failure mode worth modeling, matching PermissionService. An empty
    // array means "no rules available" (DB/cache miss); callers treat that as
    // "reject the upload" rather than "allow everything".
    public function getRules()
    {
        $rules = $this->cacheService->get(self::CACHE_KEY);

        if ($rules === null) {
            $findResult = $this->fileValidationRepository->findActiveRules();
            if ($findResult->code === Result::CODE_SUCCESS && $findResult->data !== null) {
                $rules = $findResult->data;
            } else {
                $rules = [];
            }
            $this->cacheService->set(self::CACHE_KEY, $rules, self::CACHE_TTL);
        }

        return $rules;
    }
}
