# Seguridad

Principio: o es seguro o no está hecho. Cada afirmación de este documento está respaldada por una prueba que ataca el código (carpeta `tests/`); lo que no se ha probado aparece en «Límites».

## Camino de un viaje, de la pantalla a la base de datos

1. **Quién**: solo usuarios con sesión y el permiso `core.create` del componente. El visitante recibe 403 (mapa, datos, fotos y envío).
2. **Cómo**: solo `POST`, con token CSRF de Joomla **en el cuerpo** (no vale en la URL), cabecera `X-Requested-With: XMLHttpRequest` (un formulario de otra web no puede ponerla) y campo trampa vacío.
3. **Cuánto**: tres álbumes por hora y diez por día por usuario (ajustable). Los envíos rechazados por contenido peligroso quedan anotados (código del motivo, usuario y fecha; nunca el contenido ni la IP).
4. **Álbum**: debe existir, estar publicado y ser visible para el nivel de acceso del usuario. Misma respuesta si no existe que si no puede verlo.
5. **Texto** (`TextSanitizer`): solo texto plano. Se **rechaza** (no se «arregla») todo lo que parezca etiqueta, código, sintaxis de plantilla, `javascript:`/`data:`, manejadores `on…=`, `../`, enlaces (esquemas, `www.`, dominios con TLD, correos, IPs, acortadores, ofuscaciones como `[.]`, `(at)`, `punto com`, ancho completo) o texto con codificación inválida. La detección se hace sobre una copia normalizada (NFKC) y decodificada tres veces (entidades, `%xx`, `\u00xx`, `\xNN`), de modo que `&lt;script&gt;` o `%3Cscript%3E` no se cuelan. Se eliminan caracteres de control, invisibles y bidireccionales.
6. **Coordenadas**: número decimal estricto dentro de rango; `1e3`, `NaN`, `INF`, `0x10` y similares se rechazan.
7. **Fotos** (`ImageProcessor`): el nombre, la extensión y el `Content-Type` que declara el navegador **se ignoran**. Decide el contenido real: firma de bytes (JPEG/PNG/WebP), `finfo`, `getimagesize` coherentes entre sí; lista de cadenas que nunca aparecen en una foto (`<?php`, `<script`, `<svg `, `<html`…) para poliglotas; dimensiones leídas de la cabecera **antes** de decodificar (bombas de descompresión) y comprobación de memoria. Se decodifica a píxeles (un JPEG truncado se rechaza), se gira según EXIF con un lector propio de una sola etiqueta, se escala sin deformar (lado largo 1000 px; nunca se amplía) y se **recodifica a WebP**: no sobrevive nada del original (EXIF, GPS, XMP, comentarios, datos tras el final). El resultado se vuelve a verificar. `is_uploaded_file()` real impide `tmp_name` ajenos.
8. **Todo o nada**: si cualquier texto o foto falla, no se escribe ninguna fila ni ningún archivo. Si la base de datos falla, se borran los archivos.
9. **Guardado**: archivos con nombre aleatorio de 128 bits en una carpeta privada con `.htaccess` y `web.config` que lo deniegan todo (se recomienda ponerla fuera de la web). Consultas siempre parametrizadas.
10. **Entrega** (`PhotoServer`): una foto solo se sirve tras comprobar sesión, álbum publicado, nivel de acceso y estado del viaje (los pendientes solo los ve su autor; el moderador ve todo). Cualquier fallo es el mismo 404 vacío. Cabeceras: `nosniff`, `Content-Security-Policy: default-src 'none'; sandbox`, caché privada, nombre de archivo fijo.
11. **Salida**: HTML escapado; JSON con `JSON_HEX_TAG|AMP|APOS|QUOT`; el navegador solo inserta texto ajeno con `textContent` (el candado de compilación rechaza `innerHTML`, `eval`, `document.write` en el JavaScript).

## Lo que cubre de los avisos de Joomla 5 y 6 (2024–oct. 2026)

| Clase de fallo publicada | Cómo se evita aquí |
|---|---|
| LFI por `layout` en vistas (CVE-2026-40383) | Las dos entradas fuerzan el `view` y el `layout` a una lista cerrada; probado con `../../configuration`, `%00`, etc. |
| Subida sin restricción / archivos SHTML (CVE-2025-22213, 2026-73373) | Nada se guarda tal cual: todo se recodifica a WebP con nombre generado por el servidor, y la carpeta niega ejecución |
| Saltos del filtro XSS `InputFilter` (varios) | No se usa `InputFilter`: el texto pasa por un saneador propio más estricto y la salida se escapa siempre |
| Inyección de cabeceras en descargas (CVE-2026-71572) | `Content-Disposition` con nombre fijo; ningún dato de usuario entra en una cabecera |
| SQL injection (varias) | Solo sentencias preparadas; los identificadores dinámicos salen de listas blancas |
| ACL de la API web (más de 15) | El componente no expone API REST. Desactiva los plugins *Web Services* y *API Authentication* si no los usas |
| CORS, SSRF, path traversal en medios | No hace peticiones salientes; sirve las fotos por identificador, nunca por ruta |
| Núcleo desactualizado | El instalador se niega por debajo de 5.4.9 / 6.1.4 y el panel avisa si el núcleo queda atrás |

## Pruebas (resultados a 11/10/2026)
- `tests/01-texto.php`: 81 correctas. `tests/02-imagen.php`: 68 correctas.
- `tests/joomla-live/` contra Joomla 6.1.4 real (PostgreSQL): 131 + 44 correctas por HTTP, 32 en Chromium con iPhone táctil y escritorio.
- Errores que las propias pruebas destaparon y se corrigieron: un controlador que no se ejecutaba, una comparación estricta rota porque `bind()` de Joomla convierte en texto la variable que recibe, un borrado que podía eliminar los archivos de un viaje que Joomla no había borrado, y un estilo global que dejaba el texto de los botones oscuro.

## Límites (lo que NO está probado ni cubierto)
- No se ha probado en MySQL/MariaDB (el SQL existe pero la prueba real fue en PostgreSQL), ni con Apache/LiteSpeed (las pruebas usan el servidor integrado de PHP, que ignora `.htaccess`). Comprueba que `/media/com_mapaviajes_private/…` devuelve 403 en tu servidor, o usa una carpeta fuera de la web.
- Esto no sustituye una auditoría externa independiente.
- Aún no están hechos: me gusta/favoritos/ranking, conexión con Engage, avisos a usuarios (ver `CHANGELOG.md`); cuando existan, tendrán sus propias pruebas.
- Un administrador con permisos de Joomla puede, por diseño, ver todas las fotos y editar los ajustes.
- El texto de tus usuarios es texto plano; si más adelante activas Engage, sus comentarios los filtra Engage (HTMLPurifier), no este componente.

## Despliegue recomendado
1. Joomla 5.4.9 / 6.1.4 o superior.
2. Carpeta privada de fotos **fuera** de la raíz web (Opciones → Carpeta privada).
3. PHP: `expose_php=Off`, `display_errors=Off`, `upload_max_filesize` y `post_max_size` coherentes con los ajustes (el navegador reduce cada foto antes de subirla).
4. Desactivar *Web Services* / *API Authentication* si no usas la API.
5. Revisar el registro de eventos y la papelera de vez en cuando.
