<?php

namespace App\Service\Exception;

// Invalid state transition under BR-011 / BR-012, e.g. cancelling a Fulfilled SO or approving a Cancelled SO.
class InvalidStateException extends DomainException
{
}
