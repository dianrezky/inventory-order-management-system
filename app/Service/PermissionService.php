<?php

namespace App\Service;

use App\Core\CacheService;
use App\Core\Result;
use App\Repository\Interface\PermissionRepositoryInterface;

// DB-backed replacement for hardcoded Role checks. Reads role_permissions once
// per cache TTL (data is reference/near-static) and answers whether a role
// holds a given permission_key.
class PermissionService
{
    public const CACHE_KEY = 'role_permissions.grouped';
    public const CACHE_TTL = 3600;

    private $permissionRepository;
    private $cacheService;

    public function __construct(PermissionRepositoryInterface $permissionRepository, CacheService $cacheService)
    {
        $this->permissionRepository = $permissionRepository;
        $this->cacheService = $cacheService;
    }

    // Plain array return, no Result — a read-only lookup with no failure mode
    // worth modeling, matching WarehouseService::listWarehouses()'s convention.
    public function grantedKeysForRole($role)
    {
        $grouped = $this->cacheService->get(self::CACHE_KEY);

        if ($grouped === null) {
            $findResult = $this->permissionRepository->findAllGrouped();
            $grouped = $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
            $this->cacheService->set(self::CACHE_KEY, $grouped, self::CACHE_TTL);
        }

        $keys = [];
        foreach ($grouped as $key => $roles) {
            if (in_array($role, $roles, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function roleHasPermission($role, $key)
    {
        return in_array($key, $this->grantedKeysForRole($role), true);
    }
}
