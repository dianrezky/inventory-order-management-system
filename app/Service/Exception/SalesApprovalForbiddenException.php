<?php

namespace App\Service\Exception;

// Segregation of duties (DEC-012): a non-Admin actor may never approve or reject a Sales Order, regardless of creator; controller returns 403.
class SalesApprovalForbiddenException extends DomainException
{
}
