<?php

namespace App\Service\Exception;

use RuntimeException;

// Upload failed validation (size, real MIME via finfo_file(), dimensions, or decode) — BR-022; message is user-safe.
class InvalidImageException extends RuntimeException
{
}
