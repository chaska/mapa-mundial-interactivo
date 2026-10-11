#!/usr/bin/env bash
# Rebuilds the package and installs it over the disposable Joomla in $SITE (default: the scratch site).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SITE="${SITE:?set SITE to the Joomla folder}"
php "$ROOT/build/build.php"
php "$SITE/cli/joomla.php" extension:install --path="$ROOT/dist/com_mapaviajes-$(php -r '$x=simplexml_load_file("'"$ROOT"'/src/component/mapaviajes.xml");echo $x->version;').zip" | tail -3
rm -rf "$SITE/cache"/* "$SITE/administrator/cache"/* 2>/dev/null || true
