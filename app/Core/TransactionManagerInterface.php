<?php

namespace App\Core;

// Callers must follow the AGENT.md §10.9 ARCH-02 pattern: beginTransaction(), then try { ... commit() } catch { rollBack(); throw }.
interface TransactionManagerInterface
{
    public function beginTransaction();

    public function commit();

    public function rollBack();
}
