#!/usr/bin/env bash
# Update test: what the administrator typed in Options (custom CSS, menu entries, contact, sender, map look) must survive installing a new package over the old one.
# Needs SITE (the disposable Joomla) and the same PostgreSQL variables as the other live tests.
set -euo pipefail
cd "$(dirname "$0")"
q() { PGPASSWORD="${MV_DBPASS:-x}" psql -h 127.0.0.1 -p 5433 -U joomla joomla -Atc "$1"; }
SEL="select params::jsonb->>'custom_css', params::jsonb->>'menu_extras', params::jsonb->>'contact_email', params::jsonb->>'sender_line', params::jsonb->>'map_style' from jt_extensions where element='com_mapaviajes' and type='component'"
OLD="$(q "$SEL")"
q "UPDATE jt_extensions SET params=(params::jsonb || '{\"custom_css\":\".x{color:red}\",\"menu_extras\":\"Cookies | .fab\",\"contact_email\":\"a@b.es\",\"sender_line\":\"Yo\",\"map_style\":\"atlas\"}'::jsonb)::text WHERE element='com_mapaviajes' AND type='component'" >/dev/null
BEFORE="$(q "$SEL")"
bash ./reinstall.sh >/dev/null 2>&1
AFTER="$(q "$SEL")"
if [ "$BEFORE" = "$AFTER" ]; then echo "  ok    la actualizacion conserva lo que hay en Opciones"; echo "1 correctas, 0 fallos"; else echo "  FALLA antes [$BEFORE] despues [$AFTER]"; echo "0 correctas, 1 fallos"; fi
# restore what was there
q "UPDATE jt_extensions SET params=(params::jsonb || jsonb_build_object('custom_css','','menu_extras','','contact_email','','sender_line','','map_style','pergamino'))::text WHERE element='com_mapaviajes' AND type='component'" >/dev/null
[ "$BEFORE" = "$AFTER" ]
