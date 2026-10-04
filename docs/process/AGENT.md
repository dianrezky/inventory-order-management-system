# AGENT.md
# Inventory & Order Management System — Engineering and Secure Coding Rules

Authoritative policy for every human and AI agent working in this repository.

---

## 1. Purpose

This document is the mandatory implementation guide for every human or agent working in this
repository. It formalizes the writing style already dominant in the codebase and strengthens it
with static-quality and security requirements.

The goals are:

- preserve existing behavior and architecture;
- keep changes readable, traceable, and maintainable;
- prevent static-analysis and quality issues from being introduced;
- make validation, authorization, data protection, and auditing explicit;
- avoid parallel implementations when an equivalent mechanism already exists;
- keep database state, repository history, and quality-gate execution under human control;
- make agent behavior predictable, bounded, and auditable.

---

## 2. Document Authority and Terminology

### 2.1 Authority hierarchy

```
AGENT.md            authoritative policy — the only source of normative rules
  ↓
.claude/skills/     procedural instructions — how to carry out a kind of work
  ↓
references/         reusable technical knowledge — patterns, catalogs, templates
  ↓
docs/               application and domain documentation — what the system does
```

- `AGENT.md` is the **single authoritative policy** for agent behavior. Nothing else may grant
  permission, relax a constraint, or define a boundary.
- A skill **MUST NOT** override, reinterpret, or weaken this document. A skill translates policy
  into ordered steps; it holds no authority of its own.
- A reference has **no normative authority**. It describes what an approved implementation looks
  like. If a reference conflicts with this document, this document governs.
- Project documentation describes domain and application behavior. It never overrides policy, and
  it is not evidence that a described behavior is correct or approved.
- When any conflict is found between these layers, **this document wins and the conflict MUST be
  reported** in the delivery output. It MUST NOT be resolved silently.

### 2.2 Normative keywords

| Keyword | Meaning |
|---|---|
| `MUST` / `MUST NOT` | Absolute requirement or prohibition. No discretion. |
| `SHOULD` | Strong default. Deviation requires a stated reason in the delivery output. |
| `MAY` | Permitted. The agent may proceed without asking. |
| `ASK` | **Stop.** Do not proceed. State the situation, options, and impact, then wait for a user decision. |
| `REPORT` | Proceed, but the fact MUST appear in the delivery output. Reporting is not permission to act on the finding. |
| `HUMAN-OWNED` | The agent MUST NOT execute this operation under any circumstance. The agent MAY prepare the artifact for a human to execute. |

`ASK` and `REPORT` are not interchangeable. `ASK` blocks work. `REPORT` does not.

`HUMAN-OWNED` restricts **execution only**. It never restricts preparation, authoring, review, or
explanation.

### 2.3 Conflict handling

When rules appear to conflict, apply §3 Rule Priority. If the conflict survives that ordering, the
agent MUST `ASK` rather than choose. Silently weakening a higher-priority rule is prohibited.

---

## 3. Rule Priority

When rules conflict, use this priority:

1. Security, privacy, and data-integrity requirements.
2. Human-owned boundaries (§8) — database, access-control provisioning, Git, deployment.
3. Explicit business requirements and existing API or database contracts.
4. Existing framework and project architecture.
5. Static-analysis quality rules and code constraints.
6. Conventions in this document.
7. Local style in the file being changed.

Do not silently weaken a higher-priority rule. Explain the conflict and the tradeoff before
implementation.

---

## 4. Risk Tiers

Risk determines how deeply the procedures in §6 and §7 are applied. **Risk is determined by blast
radius, compatibility risk, security impact, and coordination requirement — not by line count.**

### 4.1 Tier definitions

**`lightweight`** — all of the following are true:

- the change is inert: a comment, formatting, or a text or label change that alters no behaviour
  and no user procedure;
- it affects a single file;
- it has no behavioural, shared-contract, or security impact;
- there is no external consumer of the changed text, key, or identifier;
- a **consumer probe has been performed and recorded**, and it found no consumer.

An unperformed consumer probe disqualifies `lightweight`.

**`standard`** — a behavioural change that is neither `lightweight` nor `high-risk`.

An additive database column is `standard` when **all six** of the following hold:

1. the change only adds a column or field;
2. the column is nullable or carries a backward-compatible default;
3. existing reads and writes remain valid without modification;
4. a runtime schema-tolerance guard is in place where deployment may precede DDL execution;
5. no existing payload, `Result`, or API contract changes;
6. no coordinated change across systems is required.

SQL and migration execution remains `HUMAN-OWNED` at every tier (§8.3). Tier governs procedure
depth, never execution rights.

**`high-risk`** — **any one** of the following is sufficient:

- rename, drop, or semantic change of an existing column;
- constraint, index, or data migration that can alter existing behaviour;
- a breaking data-contract change;
- a change to a shared or public API, payload key, route, permission or grant code,
  DOM selector consumed elsewhere, or shared mutable state;
- security-sensitive behaviour;
- an external integration;
- a background job, queue, cron, or machine-to-machine endpoint;
- a coordinated deployment across systems is required;
- a destructive or high-volume migration;
- a known sensitive legacy weakness sits directly on the change path.

When uncertain between two tiers, choose the higher one.

### 4.2 Tier discipline

- When a change matches more than one tier, the **highest** applicable tier governs.
- The agent **MUST** raise the tier when new facts emerge during tracing, pattern analysis, or
  implementation, and MUST state what triggered the escalation.
- The agent **MUST NOT** lower a tier to shorten the workflow. A tier may only be lowered if the
  originally recorded trigger is proven factually wrong, and the correction MUST be reported.
- When uncertain between two tiers, choose the higher one.

---

## 5. Core Workflow

The core sequence is:

```
task-intake → code-tracing → pattern-analysis → change-planning → implementation
            → static-verification → delivery-report → git-handoff
```

This sequence is applied at **risk-based depth**, not uniformly:

| Tier | task-intake | code-tracing | pattern-analysis | change-planning |
|---|---|---|---|---|
| `lightweight` | Required, abbreviated | Only if the changed text is read elsewhere | Not required | Not required |
| `standard` | Required | Required | Required | Required |
| `high-risk` | Required | Required, full depth | Required | Required |

### 5.1 Mandatory closings

The closing chain — `static-verification`, then `delivery-report`, then `git-handoff` — is
**mandatory for every task that changed a file**, at every tier.

---

## 6. Trace Before Change

Every implementation MUST begin from the real current flow. Depth is governed by risk tier (§4).

### 6.1 Universal rules

- Locate existing constants, validators, factories, storage interfaces, response conventions, and
  security mechanisms **before** adding a new mechanism.
- Do not infer authorization, payload ownership, or database meaning from a field name alone.
  Verify the actual producer and the actual consumer.
- Check Git history (read-only) when behavior may originate from a merge or a prior feature.
- Preserve unrelated changes in a dirty worktree. Files already modified or untracked by the user
  are **protected**: they MUST NOT be reverted, reformatted, staged, or committed by the agent.
- Prefer extending the existing mechanism over creating a second source of truth.
- Every traced hop **SHOULD** be supported by a `file:line` citation.

### 6.2 Standard depth

Trace the backend path:

```
route/action → controller → service → repository → database
             → Result → response
```

Trace the frontend path when the change affects anything the browser sends, receives, renders, or
selects:

```
PHTML → emitted data → JavaScript initialization → handler
      → payload builder → AJAX/request → controller → response → UI state
```

### 6.3 High-risk depth

A **downstream consumer sweep** is `MUST`. For every element the change touches, find and cite
all consumers:

- public method;
- payload key;
- database column;
- route;
- DOM selector;
- shared mechanism or shared mutable state.

A sweep result of "none found" is acceptable only when the search method is stated.

---

## 7. Scope and Change Discipline

### 7.1 Scope boundary

- Keep the diff limited to the requested scope.
- Do not rewrite a stable subsystem while fixing a focused defect.
- Do not create a new dependency without demonstrating why the existing stack is insufficient.
- Do not alter database schema before checking existing tables, indexes, foreign keys, data volume,
  and backward compatibility.
- Migrations must be reversible when practical and safe for existing data.
- Do not hide a behavior change inside a commit labelled only as a refactor or a quality cleanup.

### 7.2 Legacy code versus new code

Existing code is evidence of **compatibility, framework integration, historical behavior, and
established convention**.

Existing code is **not** automatically evidence that a pattern is secure, architecturally
preferred, or fit to copy into new code.

| Situation | Rule |
|---|---|
| Writing new functionality | Follow this policy and the closest **valid** established implementation |
| Modifying existing functionality | Preserve compatibility; change only what the scope requires |
| Encountering a legacy weakness | `REPORT` it. Do not silently refactor it |
| A legacy pattern conflicts with this policy | This policy governs the new code; the legacy code stays until a scoped task changes it |

### 7.3 Out-of-scope findings

**A finding is not permission to fix.**

The agent **MUST NOT** fix a bug, security issue, refactor opportunity, cleanup item, or debt item
that was discovered but not requested. This includes obvious-looking fixes, typo corrections, style
sweeps, and opportunistic hardening.

Every out-of-scope finding **MUST** be reported with:

- file;
- line or reference;
- issue;
- risk or impact;
- recommendation, where one is useful.

An out-of-scope finding **MUST NOT** enter the implementation scope without explicit user
authorization.

**Blocking exception.** If the finding genuinely prevents safe implementation of the request, the
agent **MUST** `ASK`: explain why it blocks the work, state the minimum change required, and wait.

---

## 8. Human-Owned Boundaries

Certain operations are `HUMAN-OWNED`. The agent prepares; a human executes. Preparation is always
permitted; execution never is.

### 8.1 What the agent MAY do

- inspect database schema and existing SQL where it is available in the repository;
- author SQL, DDL, and migration files;
- prepare migration and execution instructions, including ordering relative to a deploy;
- inspect Git state (`status`, `log`, `diff`, `check-ignore`) read-only;
- review a diff;
- prepare a recommended commit message and staging list;
- prepare deployment and handoff instructions;
- perform read-only static checks (syntax checks, static analysis, whitespace and diff review);
- reason about static-analysis constraints and likely findings.

### 8.2 What the agent MUST NOT execute

- SQL, migrations, schema changes, or any destructive database operation;
- Git state-changing commands, including `add`/stage, commit, merge, push, rebase, cherry-pick,
  reset, stash-drop, history rewrite, and hook bypass;
- deployment or any production mutation;
- PHPUnit, unless the user explicitly requested testing in the current request (§15.1).

### 8.3 Database boundary

```
Agent MAY:  author SQL · review SQL · explain SQL · create migration files · provide execution instructions
Agent MUST NOT: execute SQL · run migrations · apply schema changes · mutate live database state
Human/DBA: executes SQL and migrations · verifies live database state
```

- SQL authored by the agent belongs under `docs/database/`.
- DDL **SHOULD** be idempotent: guarded column, table, and index creation.
- Every change that includes SQL **MUST** end with an explicit handoff statement naming the file
  path, the target tables, the execution order relative to a deploy, and the fact that the
  agent did not execute it.

### 8.4 Git boundary

```
Agent MAY:      git status · git log · git diff · git check-ignore · read-only inspection
Agent MUST NOT: create or delete branches · switch branches for state-changing reasons
                stage/add · commit · merge · push · rebase · cherry-pick · reset
Human:          owns all Git state
```

Because the agent never commits, **all agent output is uncommitted work by definition**. Progress
reporting MUST say so and MUST NOT describe work as committed or deployed.

---

## 9. Project Architecture

This project follows a strict layered architecture:

```
HTTP Request
    ↓
Router / public/index.php
    ↓
Controller  (thin — validates input, calls service, formats response)
    ↓
Service     (business rules, orchestration, Result construction)
    ↓
Repository  (database persistence, all SQL, PDO prepared statements)
    ↓
MySQL 8
```

### 9.1 Controller

Controllers are thin request orchestrators.

- Read route, query, body, header, and published entity data.
- Perform request-boundary authentication and authorization checks **before** processing.
- Reject malformed input before invoking business logic.
- Call a service and translate its `Result` into the framework response.
- Assemble view data without embedding business rules in the view.
- API actions follow the established `*Action()` naming convention.

Controllers MUST NOT contain large SQL statements, business logic, or duplicated validation.

### 9.2 Service

Services own business rules and workflow orchestration.

- Use clear intent-based methods such as `submitOrder()`, `approveOrder()`, `adjustStock()`.
- Validate business preconditions before mutation.
- Use guard-style validation to keep the flow readable, but keep service functions within the
  repository limit of no more than 3 explicit `return` statements.
- Do not call storage/repository methods directly inside `if`, `elseif`, `while`, ternary, or
  boolean expressions. Assign the result to a descriptive variable first.
- Keep state transitions explicit through constants.
- Delegate persistence to a repository.

### 9.3 Repository

Repositories handle all database persistence via PDO prepared statements.

- Use `Storage::factory()` or established repository factory pattern.
- All queries MUST use bound parameters.
- Dynamic identifiers MUST come from a server-owned allowlist.
- Repository methods return the same `Result` convention as their callers.
- The repository layer performs persistence, not business validation.

**CRITICAL RULE — ARCH-02:** Two concurrent goods issues MUST NOT cause overselling.
Stock changes MUST be transactional (ProductStock + StockLedger in one transaction with
pessimistic locking or optimistic locking with retry).

### 9.4 View and PHTML

Views render markup and server-provided state only.

- Keep business decisions in controllers or services.
- Use stable and descriptive IDs/classes because JavaScript depends on them.
- Escape every dynamic value according to its output context.
- Prefer the established structured data-passing mechanism for server-to-browser data. Never
  interpolate a raw server value directly into a JavaScript string literal.

---

## 10. Writing Rules

This section defines the coding standards for PHP, JavaScript, and CSS. These are the **single
source of truth** for writing style. See `references/coding-conventions.md` for additional
examples and patterns.

---

## 10.1 PHP — File Structure

```
<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Neuron\Generic\Result;

class OrderController extends BaseController
{
    // 1. Constants first
    public const MESSAGE_FAILED_FUNCTION = 'Failed: Please try again later.';
    public const STATUS_PENDING = 'pending';

    // 2. Protected/private properties (injected via constructor or setter)
    protected $_orderService;

    // 3. Public methods
    // 4. Private helpers
}
```

Rules:

- Start PHP files with `<?php`, followed by namespace and imports.
- One primary class, interface, or trait per file.
- Remove unused imports.
- Group class constants before properties and methods.
- Use class constants for statuses, roles, repeated messages, limits, and keys.
- Keep public methods before private implementation helpers.

---

## 10.2 PHP — Naming

| Element | Convention | Example |
|---|---|---|
| Namespace | PascalCase | `App\Http\Controllers` |
| Class name | PascalCase | `OrderController`, `OrderService` |
| Method name | camelCase | `createOrder()`, `approveOrder()` |
| Property (private/protected) | `$_underscoreCase` | `$_orderService` |
| Property (public) | camelCase | `$orderData` |
| Constant | UPPER_SNAKE_CASE | `MESSAGE_FAILED_FUNCTION` |
| Local variable | camelCase | `$orderResult`, `$items` |
| Parameter | camelCase | `$orderId`, `$data` |
| Boolean variable | `is`/`has`/`can`/`needs` prefix | `$isAuthorized`, `$hasStock` |

Names describe **business intent**, not implementation accidents.

---

## 10.3 PHP — Properties Organization

Private/protected properties use `$_underscoreCase` (established project style):

```php
protected $_storage;          // NOSONAR — inherited from framework base
protected $_orderService;
private $_cache;
```

Public properties use camelCase:

```php
public $orderData = [];
```

Static analysis suppression annotations stay on the same line at the end:

```php
$this->_config = $this->_storage->getConfig(); // @phpstan-ignore-line
$agentID = $naf->session()->owner(); // NOSONAR
```

---

## 10.4 PHP — Comments

Use `//` for inline comments placed **above** the relevant code:

```php
// Validate that the user is authorized to create orders
$isAuthorized = $this->authorizationService->canCreateOrder($identity);
if (!$isAuthorized) {
    $result->code = 1;
    $result->info = 'Not authorized to create orders';
    return $result;
}

// Check stock availability before proceeding
$stockAvailable = $this->stockRepository->checkAvailability($productId, $quantity);
```

Use `/** */` PHPDoc only for **class-level documentation** or **complex method contracts**:

```php
/**
 * Submit a sales order for approval.
 *
 * Validates items, checks stock availability, and creates the order
 * with status PENDING_APPROVAL.
 *
 * @param array $data  Sanitized order data from request
 * @return Result
 */
public function submitOrder(array $data): Result
```

**Indonesian comments are acceptable** for business-context notes:

```php
// jika stock tidak mencukupi
// simpan hasil pencarian ke cache
// cek apakah user sudah approve order ini
```

**Rules:**
- Write code comments in **English** (unless documenting Indonesian business context).
- Comments explain business reasons, security constraints, or non-obvious tradeoffs.
- Do NOT narrate obvious syntax. A well-named method does not need a comment to explain its name.
- **Never leave commented-out code.** Git retains history. Use it instead.
- Place important comments on the **line directly above** the relevant code, not at the end of the line.

---

## 10.5 PHP — Control Flow and Spacing

- **Indentation:** 4 spaces (no tabs)
- **Opening brace:** same line as `if`, `for`, `function`, etc.
- **Space after keyword:** `if ($condition) {`
- **Space around operators:** `$quantity > 0`, `$result->code === 0`

```php
// Correct
if ($order !== null) {
    $this->processOrder($order);
}

// Avoid
if($order!==null){
    $this->processOrder($order);
}

// Store dependency call in named variable before checking
$stockAvailable = $this->stockRepository->checkAvailability($productId, $quantity);
if (!$stockAvailable) {
    $result->code = 1;
    $result->info = 'Insufficient stock';
    return $result;
}
```

---

## 10.6 PHP — Functions and Control Flow

- A method SHOULD perform one cohesive responsibility.
- Use guard clauses to reduce nesting.
- Extract repeated or complex conditions into named methods.
- **For service functions, no more than 3 explicit `return` statements.**
- Use early returns deliberately for obvious validation failures, cache hits, and dependency
  failures whose `Result` already carries the correct meaning.
- Wrap service workflow functions in `try/catch` when they orchestrate validation, repository
  calls, or other side effects, and translate exceptions into the established `Result` contract.
- Avoid boolean parameters when separate intent-based methods are clearer.
- Store dependency calls in named variables before checking them.
- Do not leave unreachable code, empty branches, duplicate branches, or commented obsolete
  implementations.
- Avoid nested ternaries and deeply nested `if/else` blocks.

---

## 10.7 PHP — Types and Comparisons

- Do **not** add parameter or return type declarations to new or changed PHP functions. Follow the
  established style.
- Validate expected array shape and scalar values inside the function body.
- Use strict comparisons (`===`, `!==`) in new or changed code unless coercion is intentional.
- Prefer `null` checks that distinguish missing, empty, zero, and false when those values have
  different business meanings.
- Avoid `empty()` in new or changed business logic when an explicit comparison is clearer.
  Use `$value == null`, `$value === ''`, `count($items) === 0`, or explicit boolean comparison.

---

## 10.8 PHP — Result and Error Contract

Services and repositories use a shared `Result` class.

- `code = 0` means success.
- A non-zero code means validation, authorization, dependency, or system failure.
- `info` contains a safe user-facing or caller-facing message.
- `data` contains the successful payload and is normally `null` on failure.

#### 10.8.1 Result assignment MUST use an explicit outcome branch

**This is a hard rule. Ternary is forbidden for Result construction.**

Assign `code`, `info`, and `data` inside one explicit `if/else` block. Do not use ternary
expressions to build a `Result`.

**WRONG:**

```php
$save = $this->repository->save($data);
$result->code = $save ? 0 : 1;
$result->info = $result->code == 0 ? 'Success' : 'Failed';
$result->data = $result->code == 0 ? $save : null;
```

**CORRECT:**

```php
$save = $this->repository->save($data);

if ($save) {
    $result->code = 0;
    $result->info = 'Success to save sales order';
    $result->data = $save;
} else {
    $result->code = 1;
    $result->info = 'Failed to save sales order';
    $result->data = null;
}
```

Rules for Result assignment:
- Evaluate the outcome condition **exactly once**, in the `if`.
- Assign all three fields (`code`, `info`, `data`) in **both branches**.
- Write both `info` messages as literal strings.
- Do not chain or nest ternaries to select `info` or `data`.

#### 10.8.2 Result variable naming

When a workflow has multiple results, name each by the operation that produced it:

```php
$saveResult = $this->repository->save($data);
$updateResult = $this->repository->updateStatus($orderId, $newStatus);
$validationResult = $this->validateOrderItems($items);
```

Do not reuse `$result` for unrelated intermediate meanings in a way that makes the reader infer
whether it represents validation, storage, or final response state.

---

#### 10.8.3 Exception handling — controller level

This project follows the DMS pattern: **services throw exceptions; controllers catch them.**
All controller actions that call service write methods MUST wrap the call in a try/catch.

**Controller catch pattern — always name the exception variable:**

```php
public function issue(int $id): void
{
    $user = $this->requireRole(Role::Admin);
    $this->requireCsrf();

    try {
        $this->container->getGoodsIssueService()->issue($id, $user->id);
    } catch (InsufficientStockException $e) {
        http_response_code(400);
        echo $this->t('sales.issue.insufficient_stock');
        return;
    } catch (InvalidStateException $e) {
        http_response_code(400);
        echo $this->t($e->getMessage());
        return;
    } catch (RuntimeException $e) {
        http_response_code(500);
        echo $this->t('common.internal_error');
        return;
    }

    $this->redirect('/sales-orders/' . $id);
}
```

**Rules for controller catch blocks:**
- Always use a **named exception variable**: `catch (ExceptionType $e)`, never `catch (ExceptionType)` without `$e`.
- **Catch the most specific exception first** — more general exceptions last.
- **Map each exception to an HTTP response**: 400 for validation/state errors, 500 for internal errors.
- **Always `return` or `redirect` after handling** — never fall through after a catch.
- Use `catch (\Throwable $e)` in controller helpers (not just `Exception`) to catch PHP Errors.
- For best-effort helpers, catch `Throwable` and return `[]`, `null`, or `''` as appropriate.

**Service throw pattern — always validate before persisting:**

```php
public function setActive(int $id, bool $active): void
{
    if ($this->repository->findById($id) === null) {
        throw new InvalidArgumentException('validation.not_found');
    }

    $this->repository->setActive($id, $active);
}
```

**Rules for service throws:**
- Throw `InvalidArgumentException` for not-found and input validation errors.
- Throw `InvalidStateException` for invalid workflow-state transitions.
- Throw `RuntimeException` for unexpected internal failures.
- Throw specific subclasses (`InsufficientStockException`, `SalesApprovalForbiddenException`, etc.)
  for domain failures that callers need to handle distinctly.
- Never silently swallow an exception — always throw or handle it.

**No silent catch blocks.** Every catch block must handle visibly:
set HTTP status, render an error response, or return a documented fallback value.

---

## 10.9 PHP — Critical Business Rules

#### ARCH-02: Concurrent Stock Operations

Two concurrent goods issues **MUST NOT** cause overselling.

```php
$this->repository->beginTransaction();
try {
    // Lock the stock row to prevent overselling
    $stock = $this->productStockRepository->findForUpdate($productId);
    if ($stock->quantity < $quantity) {
        throw new InsufficientStockException();
    }
    $stock->quantity -= $quantity;
    $this->productStockRepository->save($stock);
    $this->stockLedgerRepository->record([
        'product_id' => $productId,
        'type' => 'goods_issue',
        'quantity' => -$quantity,
        'reference_id' => $orderId,
    ]);
    $this->repository->commit();
} catch (\Throwable $e) {
    $this->repository->rollBack();
    throw $e;
}
```

Use pessimistic locking (`SELECT ... FOR UPDATE`) or optimistic locking with retry (max 3 attempts).

#### Segregation of Duties

Sales **CANNOT** approve their own orders. Authorization check is enforced **server-side**,
not just in the UI.

```php
public function approveOrder(int $orderId, int $approverId): Result
{
    $result = new Result();
    $order = $this->orderRepository->findById($orderId);

    // Segregation of Duties — user cannot approve their own order
    if ($order->created_by === $approverId) {
        $result->code = 1;
        $result->info = 'Cannot approve your own order';
        return $result;
    }
    // ... rest of approval logic
}
```

---

## 10.10 JavaScript — File Structure

Every JS file begins with a JSDoc block describing its purpose and features:

```javascript
/**
 * sales-orders.js — Progressive-enhancement JS for Sales Order pages.
 *
 * Features:
 *   - Dynamic line-item row add/remove
 *   - Auto-fill SKU / unit / sale price when a product is selected
 *   - Form validation before submit
 */
(function () {
    'use strict';

    // ─── Product List ──────────────────────────────────────────────────────────

    var productList = window._soProductList || [];

    // ─── HTML Helpers ────────────────────────────────────────────────────────

    function esc(s) {
        return App.esc(String(s));
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        // ...
    });

    // Public API
    window.SalesOrdersPage = {
        buildRow: buildRow,
        addItemRow: addItemRow,
        removeItemRow: removeItemRow,
        init: function () {},
    };
})();
```

**Rules:**
- Wrap **all** page-level code in an IIFE with `'use strict'`.
- Every file has a JSDoc block at the top describing purpose and features.
- Use section headers to group related functions: `// ─── Section Name ───`
- Use 4 spaces indentation.
- Semicolons at end of statements.

---

## 10.11 JavaScript — Naming

| Element | Convention | Example |
|---|---|---|
| Module/namespace | PascalCase | `window.App`, `window.ProductsPage` |
| Function name | camelCase | `buildRow()`, `addItemRow()` |
| Variable | `var camelCase` | `var submitBtn`, `var productList` |
| Constant (module-level) | UPPER_SNAKE_CASE | `var API_TIMEOUT = 10000` |
| CSS class in JS | String literal | `'btn--primary'` |

---

## 10.12 JavaScript — Patterns

**Section headers** group related functions:

```javascript
// ─── Line Item Row Management ───────────────────────────────────────────────

function addItemRow() { ... }
function removeItemRow(tr) { ... }

// ─── Form Validation ────────────────────────────────────────────────────────

function validateForm() { ... }

// ─── Init ──────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () { ... });
```

**Loading state cleanup** — always clean up in both success and failure paths:

```javascript
async function submitStatusForm(form) {
    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn ? submitBtn.textContent.trim() : '';

    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '…'; }

    try {
        var result = await App.api(form.action, { method: 'POST', body: new FormData(form) });
        if (result.ok) {
            App.toast('Status updated successfully.', 'success');
            window.location.reload();
        } else {
            App.toast(result.error || 'Failed to update status.', 'error');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
        }
    } catch (_) {
        App.toast('An unexpected error occurred.', 'error');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
    }
}
```

**Namespaced public API** via `window.PageName`:

```javascript
window.ProductsPage = {
    init: function () {},
    buildRow: buildRow,
    addItemRow: addItemRow,
    removeItemRow: removeItemRow,
};
```

**Use shared utilities from `app.js`:**

```javascript
// Instead of raw fetch
var result = await App.api('/api/products/' + encodeURIComponent(sku) + '/availability');
if (result.ok) { /* ... */ }

// Instead of raw alert
App.toast('Order created successfully.', 'success');

// Instead of raw confirm
var confirmed = await App.confirm('Are you sure?', 'Confirm');
if (confirmed) { /* ... */ }
```

---

## 10.13 JavaScript — Forbidden Patterns

- **Do NOT use** `alert()`, `confirm()`, or `prompt()` — use `App.confirm()` instead.
- **Do NOT use** `var` without `'use strict'` in the IIFE.
- **Do NOT add** new global variables or shared mutable state objects.
- **Do NOT use** `const`/`let` unless reassignment is genuinely not needed — the project uses `var`.
- **Do NOT** handle AJAX without using `App.api()` (which handles CSRF injection).

---

## 10.14 CSS — File Structure

CSS files live in `public/assets/css/`. The load order is:

```
tokens.css → components.css → main.css
```

```css
/*
 * main.css — Layout + app-shell + sidebar.
 *
 * Load order: tokens.css → components.css → main.css
 */

/* =====================================================================
   RESET + BASE
   ===================================================================== */

*,
*::before,
*::after {
    box-sizing: border-box;
}

/* =====================================================================
   APP SHELL LAYOUT
   ===================================================================== */

.app-shell {
    display: flex;
    min-height: 100vh;
    background: var(--color-surface);
}
```

---

## 10.15 CSS — Naming (BEM)

Use **kebab-case** for class names. Use **BEM** (Block__Element--Modifier) for component parts:

```css
/* Block */
.app-sidebar { }

/* Element within block */
.app-sidebar__brand { }
.app-sidebar__nav { }
.app-sidebar__toggle { }

/* Modifier — boolean state */
.nav-link.is-active { }
.nav-link.is-disabled { }

/* Modifier — variant */
.nav-link--danger { }
.btn--primary { }
.btn--secondary { }
.badge--active { }
.badge--inactive { }
```

---

## 10.16 CSS — Rules

- Use **CSS custom properties** from `tokens.css` instead of hardcoded values wherever possible.
- **4 spaces** indentation.
- One property per line for multi-property rules.
- Media queries at the **bottom** of the file.
- Breakpoints: **640px** (mobile), **768px** (tablet), **1024px** (desktop).

```css
/* Use design tokens */
.app-content {
    padding: var(--space-6);
    background: var(--color-surface);
    color: var(--color-on-surface);
}

/* JS DOM hooks — empty class, used only as selector */
tr.po-item-row { /* intentionally empty — used as JS DOM hook */ }
.btn.po-item-remove { /* intentionally empty — used as JS event target */ }

/* Responsive — desktop first, mobile override */
.app-sidebar {
    position: sticky;
    width: 256px;
}

@media (max-width: 768px) {
    .app-sidebar {
        position: fixed;
        width: 280px;
        transform: translateX(-100%);
    }

    .app-sidebar.is-open {
        transform: translateX(0);
    }
}
```

---

## 10.17 CSS — Section Organization

Organize CSS files in this order:

1. `@import` statements (if any)
2. `:root { }` — CSS custom properties / design tokens (from tokens.css — do not duplicate)
3. Reset / base styles
4. Component styles (components.css)
5. Layout / page styles (main.css)
6. Responsive overrides (media queries at bottom)

---

## 10.18 Documentation — PHTML / View

Views live in `views/` with subdirectories per module.

- Keep PHP minimal in views — prepare data in controllers/services.
- Use `<?= ... ?>` for output with escaping.
- Keep business logic in controllers or services, not in views.
- Use stable and descriptive IDs/classes — JavaScript depends on them.

---

## 11. Static Quality Rules

### 11.1 Code constraints

- Do not introduce a blocker, critical, major, vulnerability, or security hotspot without review.
- Do not use `// NOSONAR`, `@SuppressWarnings`, or broad exclusions as the default fix. Refactor the
  cause.
- For every new function and every materially changed function, use no more than 3 explicit
  `return` statements.
- Reduce complexity with guard clauses, explicit state transitions, and focused methods.
- Remove duplicated literals by introducing a well-named constant.
- Remove unused variables, parameters, imports, assignments, and dead code.
- Do not keep commented-out code. Git retains history.
- Close or release files, temporary resources, and transactions on every path.
- Use configuration for environment-specific URLs and settings; never hardcode credentials.

### 11.2 What "materially changed" means

A function is **materially changed** when the edit does any of the following:

1. **Control flow** — adds, removes, or reorders a branch, loop, `return`, `throw`, or `catch`.
2. **Contract** — changes the signature, accepted input shape, or semantics of the returned `Result`.
3. **Dependency interaction** — adds, removes, or reorders a call to repository, cache, or another service.
4. **Security decision** — changes validation, sanitization, authentication, or authorization.
5. **State or persistence** — changes what is written, when it is written, or under what condition.

---

## 12. Security Rules

Security is the highest-priority rule class (§3). It is never traded away.

### 12.1 Secrets and Configuration

- Secrets MUST NOT be committed to Git.
- Never print secrets in logs, exceptions, or documentation.
- Keep environment-specific URLs and credentials in approved configuration.

### 12.2 Authentication and Authorization

- Authenticate every protected route at the server boundary.
- Authorize the requested action and resource, not merely the user's login.
- **Authorization ALWAYS enforced server-side. UI hiding is NOT authorization.**
- Never trust identity, role, or permission sent by the browser when it can be derived from the
  authenticated context.
- Protect against IDOR by verifying that the caller may access the exact resource identifier.
- Use deny-by-default behavior when permission data is missing or ambiguous.
- **Segregation of Duties: Sales CANNOT approve their own orders.**

### 12.3 Input Validation and Output Encoding

| Concern | Question it answers |
|---|---|
| Validation | Is this input acceptable at all? |
| Normalization | What is the internal representation of this valid input? |
| Sanitization | Which content does this specific field's policy permit removing? |
| Output encoding | How must this value be escaped for its destination interpreter? |

- Validate input at the API/controller boundary and validate business invariants again in the service.
- Treat all route parameters, query strings, JSON/form bodies as untrusted until verified.
- Prefer allowlists for enum values, statuses, roles, file types.
- `htmlspecialchars()` is HTML output encoding. Apply it at HTML rendering time.
- SQL injection is prevented with bound parameters, not by stripping characters.

### 12.4 Database and Transactions

- Bind all SQL values. Do not concatenate request data into SQL.
- Dynamic table names, column names, and sort directions require a server-owned allowlist.
- **Stock changes ALWAYS transactional** — ProductStock + StockLedger in one transaction.
- **ARCH-02: Two concurrent goods issues MUST NOT cause overselling.**
  Use pessimistic locking (`SELECT ... FOR UPDATE`) or optimistic locking with retry.
- Define idempotency for retryable submission flows.

---

## 13. API and Payload Rules

- Preserve existing route methods and response shapes unless a versioned change is approved.
- Define one canonical payload builder per frontend endpoint.
- Normalize aliases once at the boundary; use one internal name afterward.
- Keep envelope variable names separate from decoded payload variable names.
- Validate arrays for maximum length and duplicate identities.
- Make status transitions explicit and reject invalid transitions.

---

## 14. Frontend Security and UX

- Use `.text()` for untrusted text and avoid string-built HTML.
- If markup must be generated, create elements and set properties separately.
- Do not put secrets or unnecessary personal data in the DOM, URL, or local storage.
- Prevent repeated submissions by disabling controls and by enforcing server-side idempotency.
- Preserve accessibility: labels, focus behavior, keyboard access, semantic controls.
- **FORBIDDEN:** React, Vue, Angular, admin templates.

Frontend validation improves usability but MUST NOT be treated as a security boundary. Repeat all
security and business validation on the server.

---

## 15. Testing and Verification

### 15.1 PHPUnit Is Opt-In

PHPUnit is not part of the default implementation workflow. Do not create, modify, scaffold, or
execute PHPUnit tests unless the user explicitly asks for testing **in the current request**.

### 15.2 Verification sequence

For each change, the agent performs:

- syntax checks on every changed PHP or JavaScript file;
- `git diff --check` (read-only) and removal of trailing whitespace;
- a review of the final diff for accidental secrets, debug code, unrelated changes;
- reasoning about success, validation failure, authorization failure, dependency failure,
  retry/double-submit, empty data, malformed data.

### 15.3 Evidence levels

```
static reasoning  <  syntax check  <  static analysis  <  unit test
                   <  integration test  <  browser verification
                   <  deployment verification
```

- "Implemented" is not "verified".
- "Uncommitted" is not "delivered".
- One level is never presented as proof of another.

---

## 16. Git Progress and Commit Discipline

Git state is `HUMAN-OWNED` (§8.4). This section defines what the agent inspects, what it prepares,
and how it reports.

### 16.1 Working progress

- Inspect `git status`, the active branch, and the relevant diff before editing.
- Distinguish committed history from current uncommitted work in every progress report.
- Progress updates SHOULD use this compact structure when the work is substantial:
  `Status`, `Scope`, `Changes`, `Verification`, `Risk/Gap`, and `Next`.

### 16.2 Commit message

Commit messages recommended by the agent **MUST be written in English**.

- The subject MUST describe the business or technical outcome.
- Use an imperative, concise subject and include the module or flow when useful.
- Reference the issue or requirement identifier when one exists.

Recommended message shape:

```text
<module>: <outcome>

Why:
- <business or technical reason>

Verification:
- <what was actually run, at what evidence level>

Risk/Compatibility:
- <known impact or none>
```

---

## 17. Documentation and SOP

### 17.1 SOP trigger

The `documentation` surface is **yes** when the change alters any of: user steps, required
authorization, configuration, workflow or state transitions, recovery, rollback, security handling.

The `documentation` surface is **no** when the change is UI wording or a label that does not
alter what the user must do.

### 17.2 Documentation conventions

- Every page has: breadcrumb on the first line, **one H1 title**, nav footer on the last line.
- Technical terms are intentionally left untranslated.
- File naming: `lowercase-kebab-case.md`.
- Every folder pillar has an `index.md`.

---

## 18. Definition of Done

### 18.1 Agent-owned

- The requested scope was stated, and the delivered change stays inside it.
- The real end-to-end flow was traced at the depth required by the risk tier (§4, §6).
- Existing mechanisms were reused, or the reason for a new component is clear.
- Controller, service, repository responsibilities remain separated.
- The `Result` contract and writing rules in §10 were followed.
- Input validation and resource-level authorization exist server-side.
- Authorization is **always server-side**. UI hiding is not authorization.
- Segregation of Duties is enforced: Sales cannot approve their own orders.
- SQL is parameterized and multi-write consistency is protected with transactions.
- Stock changes are transactional (ProductStock + StockLedger).
- No secrets, debug statements, or hardcoded credentials were added.
- The final diff contains only intended changes and passes `git diff --check`.

### 18.2 Human-owned — reported as outstanding

- SQL and migration execution, and live database verification (§8.3).
- Git staging, commit, merge, and push (§8.4).
- PHPUnit — unless explicitly requested (§15.1).
- Deployment and production verification (§8.4).

A change may be agent-complete and still not project-complete. The delivery output MUST say which.

---

## 19. Required Review Questions

Before submitting an implementation, answer these questions internally:

1. Who is allowed to perform this action, and where is that enforced?
2. Can the caller access this exact resource, not just this route?
3. Which fields are trusted, derived, validated, sanitized, and encoded — and at which boundary?
4. What happens if the request is replayed, submitted twice, or interrupted?
5. Can concurrent requests create duplicate or inconsistent state?
6. **For stock changes: are ProductStock and StockLedger updated in one transaction? Is overselling prevented?**
7. **For approvals: does Segregation of Duties prevent Sales from approving their own orders?**
8. Can any filename, SQL fragment, or value cross into a dangerous interpreter context?
9. Does the implementation preserve the existing API and database contract?
10. Which existing implementation was used as the pattern baseline?
11. What evidence proves both the success path and the failure paths — and at which evidence level?
12. Did the refactor genuinely reduce complexity, or only move it elsewhere?
13. Was the risk tier correct, and was it raised when new facts appeared?
14. What was found outside scope, and was it reported rather than fixed?
15. Does anything in this change set a new architectural precedent that should have triggered `ASK`?

---

## 20. Decision Index

Navigation only. The normative text lives in the referenced section.

| Decision | Topic | Section |
|---|---|---|
| G1 | Database execution boundary | §8.3 |
| G2 | Git execution boundary | §8.4 |
| G3 | Result assignment via explicit outcome branch | §10.8.1 |
| G4 | Authorization enforcement | §12.2 |
| G5 | Stock transaction and ARCH-02 | §12.4 |
| G6 | Segregation of Duties | §12.2 |
| G7 | PHP Result assignment — no ternary | §10.8.1 |
| G8 | JS — use App.api(), App.toast(), App.confirm() | §10.12 |
| G9 | JS — IIFE + 'use strict' required | §10.10 |
| G10 | CSS — use design tokens, BEM naming | §10.15 |
| — | Authority hierarchy and keywords | §2 |
| — | Risk tiers | §4 |
| — | Core workflow | §5 |
| — | Out-of-scope discipline | §7.3 |
| — | Evidence levels | §15.3 |
