<?php

namespace App\Service;

use App\Entity\SalesOrder;
use App\Service\Exception\InvalidStateException;
use App\Service\Exception\SalesApprovalForbiddenException;

// Pure domain policy (no DB, no HTTP): the single home of the Sales Order segregation-of-duties rule and the BR-011/BR-012 state transitions.
class SalesOrderPolicy
{
    public function assertCanDecide($isActorAdmin)
    {
        // Segregation of duties (DEC-012) is a ROLE denial, not a creator comparison: Sales may never approve/reject any
        // order (not even another user's), and an Admin approving an order they created is explicitly permitted. No
        // SalesOrder and no createdBy is consulted here, so the rule cannot be satisfied by identity coincidence.
        if (!$isActorAdmin) {
            throw new SalesApprovalForbiddenException(
                'Only an administrator can approve a sales order.',
            );
        }
    }

    public function assertCanCancel($salesOrder, $actorId, $isAdmin)
    {
        // Nobody can cancel a Fulfilled order (BR-011)
        if ($salesOrder->status === SalesOrder::STATUS_FULFILLED) {
            throw new InvalidStateException('A fulfilled sales order can no longer be cancelled.');
        }

        // Cancelled is terminal: re-cancelling would rewrite the audit trail (new actor/timestamp) for no state change
        if ($salesOrder->status === SalesOrder::STATUS_CANCELLED) {
            throw new InvalidStateException('This sales order is already cancelled.');
        }

        if ($isAdmin) {
            return; // Admin can cancel any non-fulfilled SO
        }

        // Sales/Warehouse can only cancel their own, and only at Draft/PendingApproval
        if ($salesOrder->createdBy !== $actorId) {
            throw new InvalidStateException('Only the sales person who created this order can cancel it.');
        }

        if (!in_array($salesOrder->status, [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_PENDING_APPROVAL], true)) {
            throw new InvalidStateException('This sales order can no longer be cancelled.');
        }
    }

    public function assertCanIssue($salesOrder)
    {
        // BR-013 — only Approved SOs are eligible for goods issue
        if ($salesOrder->status !== SalesOrder::STATUS_APPROVED) {
            throw new InvalidStateException('Goods can only be issued for an approved sales order.');
        }
    }
}
