#!/usr/bin/env bash
set -Eeuo pipefail
umask 027
REPO_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
CONFIG_FILE="$HOME/.berkay-deploy.conf"
[[ ! -f "$CONFIG_FILE" ]] || source "$CONFIG_FILE"
APP_DIR="${APP_DIR:-$HOME/berkay}"
PUBLIC_DIR="${PUBLIC_DIR:-$APP_DIR/public}"
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
fail() { printf 'Deployment stopped: %s\n' "$*" >&2; exit 1; }
for tool in rsync realpath flock git; do command -v "$tool" >/dev/null || fail "Missing command: $tool"; done
[[ -x "$PHP_BIN" ]] || fail 'Set PHP_BIN to your PHP 8.4 CLI in ~/.berkay-deploy.conf'
[[ -f "$COMPOSER_BIN" ]] || fail 'Set COMPOSER_BIN to the Composer PHP executable/phar'
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION,"8.4.0",">=") ? 0 : 1);' || fail 'PHP 8.4+ is required'
HOME_REAL="$(realpath "$HOME")"
APP_DIR="$(realpath -m "$APP_DIR")"
PUBLIC_DIR="$(realpath -m "$PUBLIC_DIR")"
[[ "$APP_DIR" == "$HOME_REAL/"* && "$APP_DIR" != "$HOME_REAL/public_html" && "$APP_DIR" != "$HOME_REAL/public_html/"* ]] || fail 'APP_DIR must be a private directory beneath your home, not public_html'
[[ "$APP_DIR" != "$REPO_DIR" && "$APP_DIR/" != "$REPO_DIR/"* && "$REPO_DIR/" != "$APP_DIR/"* ]] || fail 'Repository and application paths must be separate'
[[ "$PUBLIC_DIR" == "$HOME_REAL/"* && "$PUBLIC_DIR" != "$APP_DIR" ]] || fail 'PUBLIC_DIR must be beneath your home and distinct from APP_DIR'
[[ "$PUBLIC_DIR/" != "$REPO_DIR/"* && "$REPO_DIR/" != "$PUBLIC_DIR/"* ]] || fail 'Public files and repository must be separate'
if [[ "$PUBLIC_DIR" != "$APP_DIR/public" ]]; then
    [[ "$APP_DIR/" != "$PUBLIC_DIR/"* && "$PUBLIC_DIR/" != "$APP_DIR/"* ]] || fail 'External PUBLIC_DIR must not contain, or be contained in, APP_DIR'
fi
[[ -z "$(git -C "$REPO_DIR" status --porcelain --untracked-files=no)" ]] || fail 'Repository has uncommitted changes'
for manifest in public/themes/shop/default/build/manifest.json public/themes/admin/default/build/manifest.json; do
    [[ -s "$REPO_DIR/$manifest" ]] || fail "Missing compiled assets: $manifest"
done
[[ -s "$APP_DIR/.env" ]] || fail 'Create the server .env in APP_DIR before deploying; see docs/cpanel-deployment.md'
env_value() { sed -n "s/^$1=//p" "$APP_DIR/.env" | tr -d '\r' | sed -e "s/^[\"']//" -e "s/[\"']$//"; }
[[ "$(env_value APP_ENV)" == production ]] || fail 'Server APP_ENV must be production'
[[ "$(env_value APP_DEBUG)" == false ]] || fail 'Server APP_DEBUG must be false'
if [[ "$PUBLIC_DIR" != "$APP_DIR/public" ]]; then
    [[ "$(env_value BERKAY_PUBLIC_PATH)" == "$PUBLIC_DIR" ]] || fail 'Set BERKAY_PUBLIC_PATH in server .env to the exact PUBLIC_DIR'
fi
grep -Eq '^APP_KEY=.{20,}' "$APP_DIR/.env" || fail 'Set a permanent server APP_KEY before deploying'
if [[ "${1:-}" == --check ]]; then
    printf 'Preflight passed. Application: %s\nDocument root: %s\nNo deployment performed.\n' "$APP_DIR" "$PUBLIC_DIR"
    exit 0
fi
exec 9>"$HOME/.berkay-deploy.lock"
flock -n 9 || fail 'Another Berkay deployment is running'
trap 'printf "Deployment failed at line %s. Review this log; maintenance stays enabled if it was started.\n" "$LINENO" >&2' ERR
mkdir -p "$APP_DIR/storage/app/public" "$APP_DIR/storage/app/private" "$APP_DIR/storage/logs" "$APP_DIR/storage/framework/cache/data" "$APP_DIR/storage/framework/sessions" "$APP_DIR/storage/framework/views" "$APP_DIR/bootstrap/cache"
if [[ -f "$APP_DIR/artisan" && -f "$APP_DIR/vendor/autoload.php" ]]; then
    (cd "$APP_DIR" && "$PHP_BIN" artisan down --retry=60)
fi
for directory in app bootstrap config database lang packages resources routes; do
    [[ -d "$REPO_DIR/$directory" ]] || continue
    mkdir -p "$APP_DIR/$directory"
    rsync -rlt --delete --exclude='node_modules/' --exclude='tests/' --exclude='cache/' --exclude='.git/' "$REPO_DIR/$directory/" "$APP_DIR/$directory/"
done
mkdir -p "$PUBLIC_DIR"
# No delete on public: preserve server uploads and the storage symlink.
rsync -rlt --exclude='index.php' --exclude='storage' --exclude='cache/' --exclude='hot' --exclude='.git/' "$REPO_DIR/public/" "$PUBLIC_DIR/"
for file in artisan composer.json composer.lock; do
    rsync -t "$REPO_DIR/$file" "$APP_DIR/$file"
done
cd "$APP_DIR"
# No scripts before clearing old bootstrap caches: dependency discovery follows install.
"$PHP_BIN" "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
"$PHP_BIN" "$COMPOSER_BIN" check-platform-reqs --no-dev
"$PHP_BIN" "$REPO_DIR/deploy/write-public-entry.php" "$APP_DIR" "$PUBLIC_DIR"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan responsecache:clear
"$PHP_BIN" artisan package:discover --ansi
"$PHP_BIN" artisan migrate --force
if [[ ! -e "$PUBLIC_DIR/storage" && ! -L "$PUBLIC_DIR/storage" ]]; then "$PHP_BIN" artisan storage:link; fi
[[ -L "$PUBLIC_DIR/storage" ]] || fail 'public/storage must be the Laravel storage symlink; move imported files into storage/app/public first'
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan queue:restart
"$PHP_BIN" artisan up
REVISION="$(git -C "$REPO_DIR" rev-parse HEAD)"
printf '%s %s\n' "$(date -u +%FT%TZ)" "$REVISION" > "$APP_DIR/.berkay-deployed"
printf 'Berkay deployment complete: %s\nDocument root: %s\n' "$REVISION" "$PUBLIC_DIR"
