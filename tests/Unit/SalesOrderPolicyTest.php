<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\SalesOrder;
use App\Service\Exception\InvalidStateException;
use App\Service\Exception\SalesApprovalForbiddenException;
use App\Service\SalesOrderPolicy;
use PHPUnit\Framework\TestCase;

// DEC-012 (Segregation of Duties), unit level with pure domain entities: SalesOrderPolicy must deny Sales approving or rejecting a Sales Order at all, regardless of who created it.
final class SalesOrderPolicyTest extends TestCase
{
    private function makePolicy(): SalesOrderPolicy
    {
        return new SalesOrderPolicy();
    }

    private function makeDraftSo(int $id, int $createdBy): SalesOrder
    {
        return new SalesOrder(
            id: $id,
            customerId: 1,
            sourceWarehouseId: 1,
            status: SalesOrder::STATUS_DRAFT,
            orderDate: '2026-09-01',
            note: null,
            createdBy: $createdBy,
        );
    }

    private function makeApprovedSo(int $id, int $createdBy, int $approvedBy): SalesOrder
    {
        return new SalesOrder(
            id: $id,
            customerId: 1,
            sourceWarehouseId: 1,
            status: SalesOrder::STATUS_APPROVED,
            orderDate: '2026-09-01',
            note: null,
            createdBy: $createdBy,
            approvedBy: $approvedBy,
        );
    }

    public function testAdminCanApprove(): void
    {
        // DEC-012: assertCanDecide() is a pure role check - Admin is permitted, and the method takes no SalesOrder or actor id at all.
        // It replaced the narrower prd.md BR-001 draft assertCanBeApprovedBy(SalesOrder, actorId); see docs/quality/refactor-log.md.
        $policy = $this->makePolicy();

        // Should not throw
        $policy->assertCanDecide(isActorAdmin: true);

        $this->assertTrue(true); // reached here = no exception
    }

    public function testNonAdminCannotApproveEvenSomeoneElsesSo(): void
    {
        // DEC-012: denial is total — it does not matter whose order it is.
        $policy = $this->makePolicy();

        $this->expectException(SalesApprovalForbiddenException::class);
        $policy->assertCanDecide(isActorAdmin: false);
    }

    public function testAdminCanApproveTheirOwnSelfCreatedSo(): void
    {
        // DEC-012: "creator identity plays no part in the decision" — an
        // Admin approving an order they personally created is PERMITTED.
        // (This method takes no SalesOrder at all, so there is nothing here
        // to compare against a creator id in the first place.)
        $policy = $this->makePolicy();

        $policy->assertCanDecide(isActorAdmin: true);

        $this->assertTrue(true);
    }

    public function testWarehouseStaffCannotApprove(): void
    {
        // WarehouseStaff is not Admin → denied, same as Sales (DEC-012).
        $policy = $this->makePolicy();

        $this->expectException(SalesApprovalForbiddenException::class);
        $policy->assertCanDecide(isActorAdmin: false);
    }

    public function testApprovalForbiddenExceptionCarriesTheNewMessageKey(): void
    {
        $policy = $this->makePolicy();

        try {
            $policy->assertCanDecide(isActorAdmin: false);
            $this->fail('Expected SalesApprovalForbiddenException was not thrown');
        } catch (SalesApprovalForbiddenException $e) {
            $this->assertSame('Only an administrator can approve a sales order.', $e->getMessage());
        }
    }

    public function testCreatorCanCancelOwnDraftSo(): void
    {
        $policy = $this->makePolicy();
        $so = $this->makeDraftSo(1, createdBy: 5);

        // isAdmin=false, actor is creator → allowed
        $policy->assertCanCancel($so, actorId: 5, isAdmin: false);
        $this->assertTrue(true);
    }

    public function testCreatorCanCancelOwnPendingApprovalSo(): void
    {
        $policy = $this->makePolicy();
        $so = new SalesOrder(
            id: 1,
            customerId: 1,
            sourceWarehouseId: 1,
            status: SalesOrder::STATUS_PENDING_APPROVAL,
            orderDate: '2026-09-01',
            note: null,
            createdBy: 5,
        );

        $policy->assertCanCancel($so, actorId: 5, isAdmin: false);
        $this->assertTrue(true);
    }

    public function testApprovedSoCannotBeCancelledByItsNonAdminCreator(): void
    {
        // BR-011/FR-7.4: Sales can only cancel their own SO while it is
        // still Draft/PendingApproval — once Approved, only Admin may
        // cancel it (the creator no longer can).
        $policy = $this->makePolicy();
        $so = $this->makeApprovedSo(1, createdBy: 5, approvedBy: 1);

        $this->expectException(InvalidStateException::class);
        $policy->assertCanCancel($so, actorId: 5, isAdmin: false);
    }

    public function testAdminCanCancelAnApprovedSo(): void
    {
        // BR-011: Cancelled is reachable from any state before Fulfilled,
        // including Approved — but only Admin may do it (see test above).
        $policy = $this->makePolicy();
        $so = $this->makeApprovedSo(1, createdBy: 5, approvedBy: 1);

        $policy->assertCanCancel($so, actorId: 1, isAdmin: true);
        $this->assertTrue(true);
    }

    public function testFulfilledSoCannotBeCancelled(): void
    {
        $policy = $this->makePolicy();
        $so = new SalesOrder(
            id: 1,
            customerId: 1,
            sourceWarehouseId: 1,
            status: SalesOrder::STATUS_FULFILLED,
            orderDate: '2026-09-01',
            note: null,
            createdBy: 5,
        );

        $this->expectException(InvalidStateException::class);
        $policy->assertCanCancel($so, actorId: 1, isAdmin: true);
    }

    public function testAdminCanCancelAnyNonFulfilledSo(): void
    {
        $policy = $this->makePolicy();
        $so = $this->makeDraftSo(1, createdBy: 99);

        // Admin (isAdmin=true) cancels SO they didn't create → allowed
        $policy->assertCanCancel($so, actorId: 1, isAdmin: true);
        $this->assertTrue(true);
    }

    public function testNonCreatorSalesCannotCancelOthersSo(): void
    {
        $policy = $this->makePolicy();
        $so = $this->makeDraftSo(1, createdBy: 5);

        // Grace (id=6) tries to cancel Beni's SO → forbidden
        $this->expectException(InvalidStateException::class);
        $policy->assertCanCancel($so, actorId: 6, isAdmin: false);
    }

    public function testOnlyApprovedSoCanBeIssued(): void
    {
        $policy = $this->makePolicy();
        $approvedSo = $this->makeApprovedSo(1, createdBy: 5, approvedBy: 1);

        // Should not throw
        $policy->assertCanIssue($approvedSo);
        $this->assertTrue(true);
    }

    public function testDraftSoCannotBeIssued(): void
    {
        $policy = $this->makePolicy();
        $so = $this->makeDraftSo(1, createdBy: 5);

        $this->expectException(InvalidStateException::class);
        $policy->assertCanIssue($so);
    }

    public function testPendingApprovalSoCannotBeIssued(): void
    {
        $policy = $this->makePolicy();
        $so = new SalesOrder(
            id: 1,
            customerId: 1,
            sourceWarehouseId: 1,
            status: SalesOrder::STATUS_PENDING_APPROVAL,
            orderDate: '2026-09-01',
            note: null,
            createdBy: 5,
        );

        $this->expectException(InvalidStateException::class);
        $policy->assertCanIssue($so);
    }

    public function testCancelledSoCannotBeIssued(): void
    {
        $policy = $this->makePolicy();
        $so = new SalesOrder(
            id: 1,
            customerId: 1,
            sourceWarehouseId: 1,
            status: SalesOrder::STATUS_CANCELLED,
            orderDate: '2026-09-01',
            note: null,
            createdBy: 5,
        );

        $this->expectException(InvalidStateException::class);
        $policy->assertCanIssue($so);
    }
}
