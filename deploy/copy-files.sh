#!/usr/bin/env bash
set -Eeuo pipefail
umask 027
[[ $# == 3 ]] || { echo 'Usage: copy-files.sh REPOSITORY APP_DIR PUBLIC_DIR' >&2; exit 1; }
SOURCE="$(realpath "$1")"
APPLICATION="$(realpath -m "$2")"
WEBROOT="$(realpath -m "$3")"
fail() { printf 'Copy stopped: %s\n' "$*" >&2; exit 1; }
[[ -x /bin/cp ]] || fail '/bin/cp is unavailable'
for target in "$APPLICATION" "$WEBROOT"; do
    [[ "$target" != / && "$target" != "$SOURCE" && "$target/" != "$SOURCE/"* && "$SOURCE/" != "$target/"* ]] || fail 'Source and destination must not overlap'
done
[[ "$APPLICATION" != "$WEBROOT" ]] || fail 'Application and web root must be different'
if [[ "$WEBROOT" != "$APPLICATION/public" ]]; then
    [[ "$APPLICATION/" != "$WEBROOT/"* && "$WEBROOT/" != "$APPLICATION/"* ]] || fail 'External web root overlaps application'
fi
mkdir -p "$APPLICATION" "$WEBROOT"
MANIFEST="$(mktemp "$APPLICATION/.berkay-copy.XXXXXX")"
HOST_CONFIG="$(mktemp "$APPLICATION/.berkay-webconfig.XXXXXX")"
trap 'rm -f -- "$MANIFEST" "$HOST_CONFIG"' EXIT
# Only tracked application files are considered; server runtime files never enter this list.
git -C "$SOURCE" ls-files -z -- app bootstrap config database lang resources routes packages public artisan composer.json composer.lock > "$MANIFEST"
count=0
while IFS= read -r -d '' relative; do
    case "$relative" in
        .env*|*/.env*|*/.gitignore|*/node_modules/*|*/tests/*|*/.git/*|bootstrap/cache/*|public/storage|public/storage/*|public/cache/*|public/hot|public/index.php|*.sql|*.dump) continue ;;
    esac
    base="$APPLICATION"
    destination="$relative"
    if [[ "$relative" == public/* ]]; then
        base="$WEBROOT"
        destination="${relative#public/}"
    fi
    target="$(realpath -m "$base/$destination")"
    [[ "$target" == "$base/"* ]] || fail "Destination escapes application or web root: $relative"
    [[ -f "$SOURCE/$relative" && ! -L "$SOURCE/$relative" ]] || fail "Missing or symbolic-link source: $relative"
    mkdir -p "$(dirname "$target")"
    if [[ "$relative" == public/.htaccess && -f "$target" ]]; then
        awk '/^#.*BEGIN cPanel-generated/ {keep=1} keep {print} /^#.*END cPanel-generated/ {keep=0}' "$target" > "$HOST_CONFIG"
        /bin/cp -p -- "$SOURCE/$relative" "$target"
        if [[ -s "$HOST_CONFIG" ]]; then printf '\n' >> "$target"; cat "$HOST_CONFIG" >> "$target"; fi
    else
        /bin/cp -p -- "$SOURCE/$relative" "$target"
    fi
    if [[ "$relative" == public/berkay/event-banners/*.jpg ]]; then
        chmod 0755 -- "$WEBROOT/berkay" "$WEBROOT/berkay/event-banners"
        chmod 0644 -- "$target"
    fi
    count=$((count + 1))
done < "$MANIFEST"
printf 'Copied %s tracked application files with /bin/cp. Existing uploads and obsolete files were not deleted.\n' "$count"
