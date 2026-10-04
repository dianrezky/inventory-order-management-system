<?php

namespace App\Support;

use App\Entity\Role;

// Pure, request-agnostic normalization for the checkbox-style list filters
// (status[], role[], warehouse_id[]) shared by every master-data list page's
// filter panel. Extracted out of BaseController so these validation rules
// are unit-testable (tests/Unit/ListFiltersTest.php) without a Controller,
// a session, or $_POST — call sites just pass in whatever requestParam()
// already read.
class ListFilters
{
    private const VALID_STATUSES = ['active', 'inactive'];

    // status[]=active&status[]=inactive -> ['active', 'inactive'], or null when none valid.
    public static function status($raw)
    {
        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            $valid = array_values(array_filter(
                $raw,
                static fn ($v) => in_array($v, self::VALID_STATUSES, true)
            ));

            return $valid !== [] ? $valid : null;
        }

        $value = trim((string) $raw);

        return in_array($value, self::VALID_STATUSES, true) ? [$value] : null;
    }

    // role[]=Admin&role[]=Sales -> ['Admin', 'Sales'], or null when none valid.
    public static function role($raw)
    {
        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            $valid = array_values(array_filter(
                $raw,
                static fn ($v) => Role::tryFrom((string) $v) !== null
            ));

            return $valid !== [] ? $valid : null;
        }

        $value = trim((string) $raw);

        return Role::tryFrom($value) !== null ? [$value] : null;
    }

    // warehouse_id[]=1&warehouse_id[]=2 -> [1, 2]; non-numeric/non-positive entries dropped.
    // null when nothing valid is left.
    public static function positiveIntIds($raw)
    {
        if ($raw === null) {
            return null;
        }

        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $value) {
                $id = filter_var($value, FILTER_VALIDATE_INT);
                if ($id !== false && $id > 0) {
                    $ids[] = $id;
                }
            }
        } else {
            $id = filter_var((string) $raw, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                $ids[] = $id;
            }
        }

        return $ids !== [] ? $ids : null;
    }
}
