<?php

namespace App\Core\Exception;

use RuntimeException;

// Raised when the MinIO/object-storage client is misconfigured or a transport/request error occurs.
class StorageException extends RuntimeException
{
}
