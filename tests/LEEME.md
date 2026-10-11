# Pruebas

Todo se ejecuta atacando el código, no leyéndolo.

## Sin Joomla (solo PHP 8.1+ con GD/WebP, fileinfo e intl)
```
php tests/01-texto.php     # saneado de texto: código, enlaces, ofuscaciones, límites
php tests/02-imagen.php    # fotos: disfraces, poliglotas, bombas, EXIF, rutas ajenas, almacén privado
php tests/03-idiomas.php   # ES y EN con las mismas claves; ninguna clave sin traducir
php tests/04-lugares.php   # buscador de ciudades (archivo GeoNames/español): orden, búsqueda, cercano, entradas hostiles, todo nombre se puede guardar
php build/build.php        # candado: php -l, strict_types, constructos prohibidos, sin URLs externas; si falla no hay ZIP
```

## Contra un Joomla 6 real (desechable)
`tests/joomla-live/reinstall.sh` reconstruye el paquete (componente + plugins) y lo instala sobre `$SITE`. Las pruebas necesitan usuarios registrados (`ana`, `beto`), un administrador y acceso a la base de datos (variables `MV_*`: `MV_STORAGE` = carpeta privada de fotos, `MV_SITE` = carpeta de Joomla).

Servidor web de pruebas: `php -d sendmail_path=<script que guarda el correo en /tmp/mv-mails> -S 127.0.0.1:8081 -t <joomla> router.php`. Los correos se capturan como archivos para poder leerlos.
```
php tests/joomla-live/10-envio.php        # sesión, CSRF, privacidad de fotos, ataques de texto/fotos/campos, límite, escape, permisos
php tests/joomla-live/20-admin.php        # administración, manipulación de layout/view, moderación, borrado con archivos
php tests/joomla-live/40-reacciones.php   # corazón y estrella: quién, límites, carreras, limpieza
php tests/joomla-live/50-lugares.php      # endpoints del buscador de ciudades
php tests/joomla-live/60-comentarios.php  # puente de comentarios: Engage real y una extensión simulada (necesita Engage instalado)
php tests/joomla-live/70-ranking.php      # ranking semanal: puntuación, topes, exclusiones, empates, cierre, tabla general
php tests/joomla-live/90-panel-sorteo.php # panel, ranking del administrador, sorteo, CSV, correos a autores y al podio
php tests/joomla-live/95-privacidad.php   # acceso y supresión de Joomla, borrado de cuenta
php tests/joomla-live/96-logo.php         # logo del mapa: ajuste, transparencia, archivos hostiles, permisos, cambiar y quitar
node tests/joomla-live/30-navegador.js    # Chromium: formulario en iPhone (táctil) y en escritorio
node tests/joomla-live/31-reacciones-navegador.js
node tests/joomla-live/61-comentarios-navegador.js
node tests/joomla-live/80-podio-navegador.js
```
Engage solo trae tablas para MySQL: `tests/joomla-live/engage-pg.sql` las crea para el Joomla de pruebas en PostgreSQL.
Las pruebas borran tablas del componente de ese Joomla: **no las ejecutes contra un sitio real**.
