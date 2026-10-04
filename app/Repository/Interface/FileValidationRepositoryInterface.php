<?php

namespace App\Repository\Interface;

// file_validation_rules is reference/lookup data (permitted upload extensions
// and their magic-byte signatures), not a user-editable entity — like
// PermissionRepositoryInterface it declares only the one read it needs.
interface FileValidationRepositoryInterface
{
    // Returns active rules keyed by lowercase extension, e.g.
    // ['jpg' => ['extension' => 'jpg', 'mime_type' => 'image/jpeg',
    //            'header_hex' => 'FFD8FF', 'footer_hex' => 'FFD9',
    //            'read_bytes' => 3], ...]
    public function findActiveRules();
}
