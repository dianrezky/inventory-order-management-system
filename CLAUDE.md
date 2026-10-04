# CLAUDE.md
# INVENTORY & ORDER MANAGEMENT SYSTEM
# PT Neuronworks Indonesia — Intermediate Programmer Final Project

## 1. PROJECT MISSION
Build a production-quality web-based Inventory & Order Management System
that satisfies the official PT Neuronworks Indonesia Intermediate Programmer
Final Project Brief.

The Project Brief is the primary product and assessment authority.

Priorities (in order):
1. Mandatory requirements
2. Architecture requirements
3. Security requirements
4. Testing requirements
5. Documentation and engineering evidence
6. UI/UX quality
7. Optional bonus features

Never sacrifice mandatory requirements for bonus features.

## 2. OFFICIAL DEVELOPMENT LIFECYCLE
Product Vision → PRD → UX/UI Specification → Technical Design → Delivery Plan
→ Implementation → Code Review → QA → Release

## 3. CONSTRAINTS

### Frontend
- REQUIRED: HTML, CSS, Vanilla JavaScript, Fetch API
- FORBIDDEN: React, Vue, Angular, admin templates

### Backend
- REQUIRED: PHP 8.2+ Native, OOP, Controller → Service → Repository layers
- ALLOWED: Composer (autoload & dev dependency)
- FORBIDDEN: Laravel, Symfony, CodeIgniter, ORM, DI container framework

### Database
- REQUIRED: MySQL 8, relational, PDO prepared statements, explicit transactions
- FORBIDDEN: NoSQL, query concatenation with user input

### Deployment
- REQUIRED: Docker + Docker Compose, runnable from clean environment

## 4. CRITICAL RULES
- No `new PDO()` inside Service classes — use Repository interface
- Authorization ALWAYS enforced server-side (UI hiding is NOT authorization)
- All queries parameterized (prepared statements)
- Stock changes ALWAYS transactional (ProductStock + StockLedger in one tx)
- ARCH-02: Two concurrent goods issues MUST NOT cause overselling
- Segregation of Duties: Sales CANNOT approve their own orders

## 5. IMPLEMENTATION PRIORITY
P0 — Critical requirements / critical failures
P1 — Mandatory functional requirements
P2 — Architecture requirements
P3 — Security
P4 — Testing
P5 — Documentation / evidence
P6 — UX/UI polish
P7 — Bonus features

## 6. GOLDEN RULE
"Build an application that works, satisfies the official requirements,
demonstrates sound engineering practices, is testable, secure,
documented, explainable, and defensible during technical assessment."

In doubt:
Correctness > Compliance > Security > Data Integrity > Testability
> Maintainability > UX Polish > Bonus

## 7. COMMUNICATION FORMAT (for reports)
- Summary
- Requirements satisfied
- Files changed
- Tests executed
- Validation
- Risks
- Remaining work

## 8. STOP CONDITIONS (ask, don't guess)
- Ambiguous requirement affecting business behavior
- Conflicting requirements
- Material architecture change needed
- Unclear security or authorization behavior
- Potential data-loss database behavior
- Undetermined concurrency behavior
- Implementation would violate mandatory constraint
