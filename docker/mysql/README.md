# MySQL Init Strategy (Task 1.3)

Per `delivery-plan.md` §3 task 1.3, the simplest reliable option for a clean-Docker
bootstrap is used: the official `mysql:8.0` image auto-runs every `*.sql` file
mounted under `/docker-entrypoint-initdb.d/` on first container start (empty
data volume only).

`compose.yaml` mounts:
- `database/schema.sql` → `/docker-entrypoint-initdb.d/01-schema.sql`
- `database/seed.sql` → `/docker-entrypoint-initdb.d/02-seed.sql`

No separate `docker/mysql/init.sql` is needed — this folder is kept for
structure/documentation purposes only. If a genuinely separate bootstrap
script is needed later (e.g. creating additional DB users/grants), add it
here as `00-init.sql` so it runs before schema/seed alphabetically.
