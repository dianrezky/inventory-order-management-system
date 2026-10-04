# `ioms-e2e` isolated stack

This directory defines a **standalone** Docker Compose project, `ioms-e2e`,
used only by the Playwright suite in Mode A (`E2E_MODE=isolated`). It is
never merged with the developer's `../../docker-compose.yaml` (see the header
comment in `docker-compose.e2e.yaml` for why that merge is unsafe).

Prerequisites (checked by `support/compose.ts` before anything destructive
runs — see `assertPrerequisites()`):
- Docker Compose **v2.24+** (needed for `--wait` with per-service
  `depends_on: condition:` and for `profiles:`). Older versions fail with a
  clear message instead of a confusing compose error.
- ~1.5 GB RAM free for `db` + `redis` + `memcached` + `minio` + `app`.

## Never run docker commands directly against this file

Always go through `support/compose.ts` (or the npm scripts
`e2e:env:up`/`e2e:env:down`), which pins `-p ioms-e2e -f docker-compose.e2e.yaml
--env-file e2e.env`, strips `COMPOSE_PROJECT_NAME`/`COMPOSE_FILE` from the
child environment, and refuses any `-p`/`-f`/`--project-name` argument a
caller tries to smuggle in. A destructive reset (`down -v`) additionally
verifies every volume it is about to remove is named `ioms-e2e_*` before it
runs, and refuses outright in Mode B (`E2E_MODE=existing`).

## What's isolated

Dedicated, disposable: MySQL (+volume), Redis, Memcached, MinIO (+volume,
bucket `ioms-e2e`, anonymous-download policy, own access keys), and the `app`
image itself (built fresh, no bind mount of the developer's working tree or
`.env`). Published ports (loopback only): app `18090`, short-session app
`18091` (profile `session-expiry`, `SESSION_LIFETIME=3`), MinIO `19000`
(console `19001`). None of these collide with the dev stack's `8090` / `3307`
/ `11211`. No service is named `iom_*`/`iom-*`, no service publishes `3307`
or `11211`, and nothing here ever touches the dev volumes `iom_db_data` /
`iom_redis_data`.
