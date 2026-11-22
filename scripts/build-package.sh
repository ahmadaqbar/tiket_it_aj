#!/usr/bin/env bash
# =====================================================================
# Membuat paket distribusi untuk shared hosting (tanpa Composer/Node di server).
#
#   bash scripts/build-package.sh [almega|product] [output_dir]
#
# Hasil: dist/<nama>-<versi>.zip berisi kode + vendor/ (--no-dev) + public/build/,
#        TANPA .env, .git, node_modules, tests, file development.
# Windows: jalankan lewat Git Bash / WSL (butuh php, composer, npm, zip).
# =====================================================================
set -euo pipefail

EDITION="${1:-almega}"
OUT_DIR="${2:-dist}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(cat "$ROOT/VERSION")"
case "$EDITION" in
  almega)  NAME="almega-it-ticketing" ;;
  product) NAME="ai-it-ticketing-helpdesk-kit" ;;
  *) echo "Edisi tidak dikenal: $EDITION (almega|product)"; exit 1 ;;
esac

for bin in php composer npm zip git; do command -v "$bin" >/dev/null || { echo "Perintah '$bin' tidak ditemukan"; exit 1; }; done

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
STAGE="$WORK/$NAME"

echo "→ Mengekspor kode dari git (HEAD) ..."
git -C "$ROOT" archive --format=tar HEAD | (mkdir -p "$STAGE" && tar -x -C "$STAGE")

echo "→ composer install --no-dev ..."
( cd "$STAGE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --quiet )
# Bila Composer terpaksa memasang dari source (git clone), buang metadata git agar paket tidak membengkak.
find "$STAGE/vendor" -name .git -type d -prune -exec rm -rf {} +

echo "→ Build asset (npm ci && npm run build) ..."
( cd "$STAGE" && npm ci --no-audit --no-fund --silent && npm run build --silent && rm -rf node_modules )

echo "→ Menyesuaikan .env.example untuk edisi $EDITION ..."
sed -i.bak "s/^HELPDESK_EDITION=.*/HELPDESK_EDITION=$EDITION/" "$STAGE/.env.example" && rm -f "$STAGE/.env.example.bak"
if [ "$EDITION" = "product" ]; then
  sed -i.bak 's/^APP_NAME=.*/APP_NAME="AI IT Ticketing \& Helpdesk"/; s/^DB_DATABASE=.*/DB_DATABASE=helpdesk/; s/^MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS="it@example.com"/' "$STAGE/.env.example" && rm -f "$STAGE/.env.example.bak"
fi

echo "→ Membersihkan file development ..."
( cd "$STAGE" && rm -rf tests phpunit.xml .editorconfig .styleci.yml .gitattributes .npmrc database/database.sqlite \
    storage/logs/*.log bootstrap/cache/*.php public/hot )
mkdir -p "$STAGE"/storage/{app/private,framework/{cache/data,sessions,views},logs} "$STAGE/bootstrap/cache"
find "$STAGE/storage" "$STAGE/bootstrap/cache" -type d -exec sh -c 'touch "$0/.gitkeep"' {} \;
echo "$EDITION" > "$STAGE/EDITION"

mkdir -p "$ROOT/$OUT_DIR"
ZIP="$ROOT/$OUT_DIR/$NAME-$VERSION.zip"
rm -f "$ZIP"
( cd "$WORK" && zip -qr "$ZIP" "$NAME" )
( cd "$ROOT/$OUT_DIR" && sha256sum "$(basename "$ZIP")" > "$(basename "$ZIP").sha256" 2>/dev/null || shasum -a 256 "$(basename "$ZIP")" > "$(basename "$ZIP").sha256" )

echo "✓ Paket: $ZIP ($(du -h "$ZIP" | cut -f1))"
echo "  Langkah berikutnya: lihat INSTALLATION_GUIDE.md / DEPLOYMENT_SHARED_HOSTING.md"
