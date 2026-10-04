<?php

namespace App\Core;

// Test double living in app/Core rather than tests/ so it autoloads under the same PSR-4 root as Database, with no test-only autoload entry.
class FakeTransactionManager implements TransactionManagerInterface
{
    public function beginTransaction()
    {
        // no-op: this fake runs against no real connection; real commit/rollback semantics are covered by tests/Integration/
    }

    public function commit()
    {
        // no-op: this fake runs against no real connection; real commit/rollback semantics are covered by tests/Integration/
    }

    public function rollBack()
    {
        // no-op: this fake runs against no real connection; real commit/rollback semantics are covered by tests/Integration/
    }
}
