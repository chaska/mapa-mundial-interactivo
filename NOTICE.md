# Aviso

**Mapa de viajes** (`com_mapaviajes`) es código nuevo, © 2026 ChasKa, bajo **GNU GPL v3 o posterior** (ver `LICENSE`).

## Qué obliga y qué no obliga esa licencia
- Obliga: quien reciba o distribuya el componente, o una versión modificada, debe darlo con esta misma licencia y con su código fuente. Nadie puede hacerlo privado.
- No puede prohibir: que alguien lo venda o cobre por soporte. Ninguna licencia libre (GPL, AGPL, MIT…) puede prohibir el uso comercial; una cláusula así dejaría de ser software libre y sería incompatible con la GPL de Joomla.

## Terceros incluidos
- **Tipografías** Bricolage Grotesque, Figtree, Cinzel y Caveat: SIL Open Font License 1.1, sin modificar, de los paquetes npm `@fontsource/*` (`media/fonts/LICENSES.md`).
- **Geometría de países** (`media/data/world.json`): datos de Natural Earth (dominio público), a través de `world-atlas` y `topojson-client`, proyectados con `d3-geo` (Natural Earth 1). El archivo es una salida generada; no contiene código de esas librerías.
- **Lugares** (`media/data/places.tsv`, ~135 000 ciudades del mundo): datos de **GeoNames** (https://www.geonames.org), licencia **Creative Commons Attribution 4.0**; se muestra el crédito en el formulario. Se obtienen del paquete npm `all-the-cities` (MIT) con `tools/make-places.mjs`, más los nombres en español de `tools/places.seed.json`. El archivo está en este servidor: la búsqueda no consulta a nadie.
- **Estilo del panel de administración**: inspirado en el de Engage (fork comunitario de Akeeba Engage 3.4.2, GPL v3 o posterior). No se ha copiado código de Engage en esta versión.

## Sin afiliación
No es un producto de Joomla, de Akeeba ni del Open Source Matters Project.
