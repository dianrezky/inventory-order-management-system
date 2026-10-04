<?php

namespace App\Service\Exception;

// Goods Issue blocked by insufficient available stock (ARCH-02 / BR-010 / BR-002); the service rolls back and the controller returns HTTP 400.
class InsufficientStockException extends DomainException
{
}
