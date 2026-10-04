<?php

namespace App\Service\Exception;

use RuntimeException;

// Base for domain-level service exceptions; all messages are safe to surface to the user (ERR-01 / BR-020).
abstract class DomainException extends RuntimeException
{
}
