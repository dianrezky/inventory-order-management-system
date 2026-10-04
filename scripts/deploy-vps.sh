#!/usr/bin/env bash
# One-shot, re-runnable deploy of IOMS onto the VPS that already runs the shared
# Caddy proxy and the portfolio MinIO.
#
#   curl -fsSL https://raw.githubusercontent.com/dianrezky/inventory-order-management-system/main/scripts/deploy-vps.sh | sudo bash
#
# What it does: clones/updates the repo, writes .env (generated secrets; asks for
# the MinIO key pair on the terminal, never via chat), builds and starts the stack
# with the prod overlay, adds a Caddy site block for the domain, then smoke-tests
# MySQL, Redis, Memcached and a real signed MinIO upload/read/delete.
#
# Overrides (environment variables): IOMS_DIR, IOMS_DOMAIN, IOMS_CADDY_CONTAINER,
# IOMS_MINIO_CONTAINER, IOMS_MINIO_PUBLIC_URL, IOMS_MINIO_ENDPOINT,
# IOMS_MINIO_ACCESS_KEY, IOMS_MINIO_SECRET_KEY, IOMS_REPO.
set -euo pipefail

DIR="${IOMS_DIR:-/opt/ioms}"
DOMAIN="${IOMS_DOMAIN:-ioms.aetherxusory.my.id}"
CADDY="${IOMS_CADDY_CONTAINER:-deploy-caddy-1}"
MINIO="${IOMS_MINIO_CONTAINER:-portfolio-minio}"
MINIO_PUBLIC="${IOMS_MINIO_PUBLIC_URL:-https://s3.aetherxusory.my.id}"
REPO="${IOMS_REPO:-https://github.com/dianrezky/inventory-order-management-system.git}"
COMPOSE=(docker compose -f docker-compose.yaml -f docker-compose.prod.yml)

step() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
ok()   { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
warn() { printf '  \033[33mWARN\033[0m  %s\n' "$*"; }
die()  { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; exit 1; }

# ── 0. Preflight ─────────────────────────────────────────────────────────────
step "Preflight"
for bin in docker git curl openssl; do
    command -v "$bin" >/dev/null || die "'$bin' is required but not installed"
done
docker info >/dev/null 2>&1 || die "cannot talk to the Docker daemon (run as root / with sudo)"

compose_ver="$(docker compose version --short 2>/dev/null | sed 's/^v//')" || die "docker compose plugin missing"
if [ "$(printf '%s\n2.24.4\n' "$compose_ver" | sort -V | head -1)" != "2.24.4" ]; then
    die "Docker Compose $compose_ver is too old; the prod overlay needs >= 2.24.4 (uses !reset)"
fi
ok "docker compose $compose_ver"

docker inspect "$CADDY" >/dev/null 2>&1 || die "Caddy container '$CADDY' not found (set IOMS_CADDY_CONTAINER)"
docker inspect "$MINIO" >/dev/null 2>&1 || die "MinIO container '$MINIO' not found (set IOMS_MINIO_CONTAINER)"

caddy_nets="$(docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$CADDY")"
CADDY_NET="$(printf '%s\n' $caddy_nets | grep -E 'internal$' | head -1 || true)"
CADDY_NET="${CADDY_NET:-$(printf '%s\n' $caddy_nets | head -1)}"
[ -n "$CADDY_NET" ] || die "could not determine Caddy's Docker network"
ok "Caddy network: $CADDY_NET"

if docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$MINIO" | tr ' ' '\n' | grep -qx "$CADDY_NET"; then
    MINIO_ENDPOINT="${IOMS_MINIO_ENDPOINT:-http://$MINIO:9000}"
else
    MINIO_ENDPOINT="${IOMS_MINIO_ENDPOINT:-$MINIO_PUBLIC}"
fi
ok "MinIO endpoint used by PHP: $MINIO_ENDPOINT"

code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$MINIO_PUBLIC/minio/health/live" 2>&1 || true)"
if [ "$code" = "200" ]; then ok "public MinIO health: HTTP 200 ($MINIO_PUBLIC)"; else warn "public MinIO health returned: $code ($MINIO_PUBLIC/minio/health/live)"; fi

# ── 1. Source ────────────────────────────────────────────────────────────────
step "Source in $DIR"
if [ -d "$DIR/.git" ]; then
    git config --global --get-all safe.directory | grep -qx "$DIR" || git config --global --add safe.directory "$DIR"
    git -C "$DIR" pull --ff-only
else
    mkdir -p "$(dirname "$DIR")"
    git clone "$REPO" "$DIR"
    git config --global --get-all safe.directory | grep -qx "$DIR" || git config --global --add safe.directory "$DIR"
fi
cd "$DIR"
ok "at $(git rev-parse --short HEAD)"

# ── 2. .env ──────────────────────────────────────────────────────────────────
step "Environment file"
env_get() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | sed -e "s/^'//" -e "s/'\$//"; }

if [ -f .env ]; then
    ok ".env already exists - keeping it (delete it to regenerate)"
    MK="$(env_get MINIO_ACCESS_KEY || true)"; MS="$(env_get MINIO_SECRET_KEY || true)"
else
    MK="${IOMS_MINIO_ACCESS_KEY:-}"; MS="${IOMS_MINIO_SECRET_KEY:-}"
fi

if [ -z "${MK:-}" ] || [ -z "${MS:-}" ]; then
    [ -r /dev/tty ] || die "no terminal for prompts: export IOMS_MINIO_ACCESS_KEY and IOMS_MINIO_SECRET_KEY first"
    echo "  MinIO credentials for the IOMS app account (typed here, never sent to chat)."
    read -r -p "  MINIO_ACCESS_KEY: " MK </dev/tty
    read -r -s -p "  MINIO_SECRET_KEY: " MS </dev/tty; echo
fi
case "$MK$MS" in *"'"*|"") die "MinIO key/secret must be non-empty and contain no single quote";; esac

if [ ! -f .env ]; then
    umask 077
    cat > .env <<EOF
APP_ENV=production
APP_DEBUG=false
DEFAULT_LOCALE=en
SESSION_NAME=iom_session
SESSION_LIFETIME=3600
APP_PORT=8090
CADDY_NETWORK=$CADDY_NET
DB_NAME=inventory_order_management
DB_USER=iom_app
DB_PASSWORD='$(openssl rand -hex 16)'
DB_ROOT_PASSWORD='$(openssl rand -hex 16)'
REDIS_PORT=6379
MEMCACHED_PORT=11211
MINIO_ENDPOINT='$MINIO_ENDPOINT'
MINIO_REGION=us-east-1
MINIO_ACCESS_KEY='$MK'
MINIO_SECRET_KEY='$MS'
MINIO_BUCKET=portfolio-uploads
MINIO_PUBLIC_URL='$MINIO_PUBLIC'
ID_OBFUSCATION_KEY='$(openssl rand -hex 32)'
EOF
    umask 022
    ok ".env written (chmod 600, DB passwords + ID_OBFUSCATION_KEY generated)"
else
    chmod 600 .env
fi

# ── 3. Build + start ─────────────────────────────────────────────────────────
step "Build image and install PHP dependencies"
"${COMPOSE[@]}" build
# The app bind-mounts the checkout over /var/www/html, hiding the image's vendor/.
# Install it once as root so the www-data app/cron containers just read it.
"${COMPOSE[@]}" run --rm --no-deps --user root --entrypoint sh app -c \
    'composer install --no-interaction --no-progress --prefer-dist'

step "Start stack"
"${COMPOSE[@]}" up -d
printf '  waiting for iom_app to become healthy'
for _ in $(seq 1 40); do
    st="$(docker inspect -f '{{.State.Health.Status}}' iom_app 2>/dev/null || echo none)"
    [ "$st" = "healthy" ] && break
    printf '.'; sleep 5
done
echo
[ "$st" = "healthy" ] || { "${COMPOSE[@]}" logs --tail 40 app; die "iom_app is '$st', not healthy"; }
ok "iom_app healthy"

published="$(docker ps --filter name=iom_ --format '{{.Names}} {{.Ports}}' | grep -E '0\.0\.0\.0|:::' || true)"
[ -z "$published" ] && ok "no IOMS container publishes a host port" || warn "published ports: $published"

# ── 4. Caddy site block ──────────────────────────────────────────────────────
step "Caddy route for $DOMAIN"
CFILE_IN="/etc/caddy/Caddyfile"
CFILE="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/etc/caddy/Caddyfile"}}{{.Source}}{{end}}{{end}}' "$CADDY")"
if [ -z "$CFILE" ]; then
    cdir="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/etc/caddy"}}{{.Source}}{{end}}{{end}}' "$CADDY")"
    [ -n "$cdir" ] && CFILE="$cdir/Caddyfile"
fi
if [ -z "$CFILE" ] || [ ! -f "$CFILE" ]; then
    warn "could not locate the Caddyfile on the host. Add this block to it manually, then reload Caddy:"
    printf '\n%s {\n    reverse_proxy iom_app:8080\n}\n\n' "$DOMAIN"
elif grep -qE "^[[:space:]]*$DOMAIN[[:space:],{]" "$CFILE"; then
    ok "$CFILE already has a block for $DOMAIN"
else
    cp -a "$CFILE" "$CFILE.bak.$(date +%s)"
    printf '\n# IOMS (added by scripts/deploy-vps.sh)\n%s {\n    reverse_proxy iom_app:8080\n}\n' "$DOMAIN" >> "$CFILE"
    if docker exec "$CADDY" caddy validate --config "$CFILE_IN" --adapter caddyfile >/dev/null 2>&1; then
        docker exec "$CADDY" caddy reload --config "$CFILE_IN" --adapter caddyfile
        ok "Caddy reloaded (backup: $CFILE.bak.*)"
    else
        latest="$(ls -1t "$CFILE".bak.* | head -1)"; cp -a "$latest" "$CFILE"
        die "Caddyfile did not validate after edit; restored $latest"
    fi
fi

# ── 5. Smoke tests (inside the app container) ────────────────────────────────
step "Smoke tests"
docker exec -i iom_app php <<'PHP' || warn "smoke test script exited non-zero"
<?php
require '/var/www/html/vendor/autoload.php';
function line($label, $pass, $detail = '') {
    echo '  ' . ($pass ? 'PASS' : 'FAIL') . '  ' . $label . ($detail !== '' ? "  ($detail)" : '') . PHP_EOL;
}
try {
    $pdo = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASSWORD'));
    $n = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    line('MySQL + schema/seed loaded', $n > 0, "$n users");
} catch (Throwable $e) { line('MySQL', false, $e->getMessage()); }
try {
    $r = new Redis(); $r->connect(getenv('REDIS_HOST'), (int) getenv('REDIS_PORT'), 2);
    line('Redis (sessions)', (bool) $r->ping());
} catch (Throwable $e) { line('Redis', false, $e->getMessage()); }
try {
    $m = new Memcached(); $m->addServer(getenv('MEMCACHED_HOST'), (int) getenv('MEMCACHED_PORT'));
    $m->set('ioms_deploy_check', '1', 30);
    line('Memcached (cache)', $m->get('ioms_deploy_check') === '1');
} catch (Throwable $e) { line('Memcached', false, $e->getMessage()); }
try {
    $c = new App\Core\MinioClient(getenv('MINIO_ENDPOINT'), getenv('MINIO_REGION'), getenv('MINIO_ACCESS_KEY'), getenv('MINIO_SECRET_KEY'), getenv('MINIO_BUCKET'), getenv('MINIO_PUBLIC_URL'));
    $key = $c->buildObjectKey('products/_deploycheck/' . bin2hex(random_bytes(4)) . '.txt');
    $c->putObject($key, 'ioms-deploy-check', 'text/plain');
    line('MinIO signed PUT (credentials, region, permission)', true, $key);
    line('MinIO HEAD (object exists)', $c->objectExists($key));
    $url = $c->publicUrl($key);
    $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    line('Anonymous GET of public image URL', $http === 200 && $body === 'ioms-deploy-check', "HTTP $http $url");
    $c->deleteObject($key);
    line('MinIO DELETE (object really gone)', !$c->objectExists($key));
} catch (Throwable $e) { line('MinIO', false, $e->getMessage()); }
PHP

# ── 6. End-to-end from the public side ───────────────────────────────────────
step "Public reachability of https://$DOMAIN"
srv_ip="$(getent hosts "${MINIO_PUBLIC#https://}" | awk '{print $1; exit}' || true)"
dns_ip="$( (command -v dig >/dev/null && dig +short @1.1.1.1 "$DOMAIN" A | head -1) || true)"
if [ -z "$dns_ip" ]; then
    warn "no public DNS A record for $DOMAIN yet - create one pointing to ${srv_ip:-the public IP of this server}, then re-run this script (it is safe to re-run)"
else
    ok "DNS: $DOMAIN -> $dns_ip"
fi
http="000"
for _ in $(seq 1 12); do
    http="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "https://$DOMAIN/login" 2>/dev/null || true)"
    [ "$http" = "200" ] && break
    sleep 5
done
if [ "$http" = "200" ]; then
    ok "https://$DOMAIN/login -> HTTP 200"
else
    warn "https://$DOMAIN/login -> HTTP $http. Recent Caddy log:"
    docker logs --tail 15 "$CADDY" 2>&1 | sed 's/^/        /'
fi

cat <<EOF

Done. Open https://$DOMAIN/login

Seed accounts ship with well-known demo passwords (see README). This site is on the
public internet: sign in and change every password from the profile page right away.
Re-run this script any time to pull the latest main and redeploy.
EOF
