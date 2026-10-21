# Aviso

**Mapa de viajes** (`com_mapaviajes`) es código nuevo, © 2026 ChasKa, bajo **GNU GPL v3 o posterior** (ver `LICENSE`).

## Qué obliga y qué no obliga esa licencia
- Obliga: quien reciba o distribuya el componente, o una versión modificada, debe darlo con esta misma licencia y con su código fuente. Nadie puede hacerlo privado.
- No puede prohibir: que alguien lo venda o cobre por soporte. Ninguna licencia libre (GPL, AGPL, MIT…) puede prohibir el uso comercial; una cláusula así dejaría de ser software libre y sería incompatible con la GPL de Joomla.

## Terceros incluidos
- **Tipografías** Bricolage Grotesque, Figtree, Cinzel y Caveat: SIL Open Font License 1.1, sin modificar, de los paquetes npm `@fontsource/*` (`media/fonts/LICENSES.md`).
- **Geometría de países** (`media/data/world.json`): datos de Natural Earth (dominio público), a través de `world-atlas` y `topojson-client`, proyectados con `d3-geo` (Natural Earth 1). Las islas Canarias, Baleares, Madeira y Azores se añadieron desde Natural Earth 1:10m (también dominio público) con `tools/add-islands.mjs`. El archivo es una salida generada; no contiene código de esas librerías.
- **Lugares** (`media/data/places.tsv`, ~127 000 ciudades y pueblos del mundo, **con nombres en español**): derivado de la base de datos «Countries States Cities Database» (dr5hn, licencia **ODbL 1.0**), que a su vez se apoya en **GeoNames** (https://www.geonames.org, **CC BY 4.0**), Wikidata (CC0) y otras fuentes abiertas. Para las grandes ciudades que esa base guarda como provincia (Shanghái, Bogotá, Buenos Aires…) se usan además datos de **GeoNames** a través del paquete npm `all-the-cities` (código MIT, datos CC BY 4.0). Los nombres españoles escritos a mano (`tools/exonimos.json`: Gante, Brujas, Núremberg, Oporto…) son hechos léxicos, sin derechos propios. Todo se genera con `tools/make-places.mjs`. Cada lugar también se encuentra por su nombre local o inglés (buscar «Gent» o «Mumbai» lleva a Gante o Bombay). **Ese archivo de datos sigue la ODbL** (atribución y, si lo modificas y lo distribuyes, misma licencia); el código del componente sigue siendo GPL v3. Se muestra el crédito en el formulario. El archivo está en este servidor: la búsqueda no consulta a nadie.
- **Estilo del panel de administración**: inspirado en el de Engage (fork comunitario de Akeeba Engage 3.4.2, GPL v3 o posterior). No se ha copiado código de Engage en esta versión.

## Sin afiliación
No es un producto de Joomla, de Akeeba ni del Open Source Matters Project.
