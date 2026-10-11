# Pruebas

Todo se ejecuta atacando el código, no leyéndolo.

## Sin Joomla (solo PHP 8.1+ con GD/WebP, fileinfo e intl)
```
php tests/01-texto.php     # saneado de texto: código, enlaces, ofuscaciones, límites
php tests/02-imagen.php    # fotos: disfraces, poliglotas, bombas, EXIF, rutas ajenas, almacén privado
php tests/03-idiomas.php   # ES y EN con las mismas claves; ninguna clave sin traducir
php build/build.php        # candado: php -l, strict_types, constructos prohibidos, sin URLs externas; si falla no hay ZIP
```

## Contra un Joomla 6 real (desechable)
`tests/joomla-live/reinstall.sh` reconstruye e instala sobre `$SITE`. Las pruebas necesitan dos usuarios registrados (`ana`, `beto`), un administrador y acceso a la base de datos (variables `MV_*` en cada archivo).
```
php tests/joomla-live/10-envio.php     # sesión, CSRF, privacidad de fotos, ataques de texto/fotos/campos, límite, escape de salida, permisos
php tests/joomla-live/20-admin.php     # administración, manipulación de layout/view, moderación, borrado con archivos
node tests/joomla-live/30-navegador.js # Chromium: formulario en iPhone (táctil) y en escritorio, de principio a fin
```
Las pruebas borran las tablas del componente de ese Joomla: **no las ejecutes contra un sitio real**.
