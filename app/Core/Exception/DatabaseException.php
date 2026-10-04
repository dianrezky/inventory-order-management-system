<?php

namespace App\Core\Exception;

use RuntimeException;

// Raised when a database connection or driver-level operation fails.
class DatabaseException extends RuntimeException
{
}
