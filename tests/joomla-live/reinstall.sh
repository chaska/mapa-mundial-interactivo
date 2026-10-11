#!/usr/bin/env bash
# Rebuilds the package (component + comments bridge plugin) and installs it over the disposable Joomla in $SITE.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SITE="${SITE:?set SITE to the Joomla folder}"
php "$ROOT/build/build.php"
VER="$(php -r '$x=simplexml_load_file("'"$ROOT"'/src/component/mapaviajes.xml");echo $x->version;')"
php "$SITE/cli/joomla.php" extension:install --path="$ROOT/dist/pkg_mapaviajes-$VER.zip" | tail -3
rm -rf "$SITE/cache"/* "$SITE/administrator/cache"/* 2>/dev/null || true
