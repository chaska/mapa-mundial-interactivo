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

## Auditoría de seguridad (14/10/2026, versión 1.8.7)
Tres revisores independientes leyeron el código por zonas (frontend; servicios de imagen, texto, almacenamiento y ranking; administración, plugins y JavaScript) y se contrastó con las clases de fallo de los avisos de Joomla 5 y 6 (2026: ACL de lotes en `com_users` CVE-2026-48898, MFA CVE-2026-48896, ACL de webservices CVE-2026-48950..58, y los ya citados). Sin fallos críticos ni altos. Corregido en 1.8.7: ETag que revelaba el nombre interno de la foto y el id del autor; nombres de foto con más aleatoriedad (96 bits); límites de envío y de reacciones sin carreras entre peticiones paralelas (bloqueo por usuario en transacción); `X-Frame-Options: SAMEORIGIN`; exportación RGPD de un correo sin cuenta; quien solo crea mapas ya no los publica; `core.edit` declarado; lote desactivado; filtro de Engage solo con viajes publicados.

Decisiones que quedan en manos del propietario (no son fallos de código):
- El mapa y el ranking muestran el **nombre de usuario de acceso** salvo que actives «Mostrar el nombre real»: cualquier usuario registrado puede listar los logins de los autores. Recomendado: un alias público (mejora futura) y no usar logins de administradores para publicar.
- `ranking.data` (GET) cierra la semana y manda el correo del podio al primero que lo pida. Es idempotente; si te molesta, se mueve a una tarea programada.
- El listado y CSV del sorteo incluye correos: solo los moderadores con `core.edit.state`. Concede ese permiso solo a quien deba ver esos datos.
- La carpeta de fotos por defecto está dentro de la web (`media/com_mapaviajes_private`) y depende de `.htaccess`/`web.config`; en nginx o con `AllowOverride None` hay que moverla fuera de la web (Opciones → carpeta de fotos).

## Pruebas (resultados a 11/10/2026)
- `tests/01-texto.php`: 103 correctas (ejecutar también con `php -d pcre.jit=0`). `tests/02-imagen.php`: 68. `tests/03-idiomas.php`: 25.
- `tests/joomla-live/` contra Joomla 6.1.4 real (PostgreSQL): 144 + 46 correctas por HTTP (incluye 8 envíos simultáneos contra el límite y una inundación de peticiones rechazadas), 32 en Chromium con iPhone táctil y escritorio.
- Errores que las propias pruebas destaparon y se corrigieron: un controlador que no se ejecutaba, una comparación estricta rota porque `bind()` de Joomla convierte en texto la variable que recibe, un borrado que podía eliminar los archivos de un viaje que Joomla no había borrado, y un estilo global que dejaba el texto de los botones oscuro.

## Revisión independiente (11/10/2026)
Un revisor que no conocía mis conclusiones leyó todo el código buscando fallos reales. No encontró fallos críticos ni altos. Hallazgos y estado:

| # | Gravedad | Hallazgo | Estado |
|---|---|---|---|
| 1 | Media | Enlaces que se colaban: `evil。com` (punto ideográfico), `evil.ninja`, `EXAMPLE.ES`, `evil dot ninja` | Corregido: puntos ideográficos plegados a `.`, y regla general: cualquier palabra pegada a un punto y otra palabra se rechaza (un punto seguido de espacio sigue valiendo). Con 20 casos nuevos |
| 2 | Media | Expresiones regulares cuadráticas ejecutadas antes de comprobar la longitud: 18 s de CPU con 15 KB sin `pcre.jit` (reproducido) | Corregido: límite en caracteres antes de cualquier regex, patrones anclados, y un fallo de `preg_match` cuenta como rechazo. 0,01 s en el mismo caso |
| 3 | Media | Límite de envíos esquivable con peticiones en paralelo y sin límite para los intentos fallidos | Corregido: cada petición cuenta como intento antes de trabajar (20 por hora), y el recuento definitivo se hace dentro de la transacción con la fila del usuario bloqueada. Probado: 8 simultáneos con límite 3 guardan exactamente 3 |
| 4 | Baja | Un invitado podía ver el mapa con una tarea inexistente si el álbum era público (reproducido) | Corregido en la vista, no solo en el controlador |
| 5 | Baja | Al eliminar un álbum quedaban huérfanos sus viajes y archivos | Corregido: un álbum con publicaciones no se puede eliminar |
| 6 | Baja | Foto ocultada seguía viéndose hasta una hora desde la caché del navegador | Corregido: `Cache-Control: private, no-cache` y comprobación antes del 304 |

Además: tope de 24 megapíxeles (antes 40) por la memoria nativa de las librerías de imagen, y el mapa muestra el alias de usuario salvo que actives «Mostrar el nombre real».

Pendiente de validar en tu despliegue: `pcre.jit` (mejor activado), registro abierto sin verificación de correo (el límite es por cuenta: pide verificación o aprobación manual) y la ubicación de la carpeta de fotos.

## Límites (lo que NO está probado ni cubierto)
- No se ha probado en MySQL/MariaDB (el SQL existe pero la prueba real fue en PostgreSQL), ni con Apache/LiteSpeed (las pruebas usan el servidor integrado de PHP, que ignora `.htaccess`). Comprueba que `/media/com_mapaviajes_private/…` devuelve 403 en tu servidor, o usa una carpeta fuera de la web.
- Esto no sustituye una auditoría externa independiente.
- Aún no están hechos: me gusta/favoritos/ranking, conexión con Engage, avisos a usuarios (ver `CHANGELOG.md`); cuando existan, tendrán sus propias pruebas.
- Un administrador con permisos de Joomla puede, por diseño, ver todas las fotos y editar los ajustes.
- El texto de tus usuarios es texto plano. Los comentarios los filtra y modera la extensión que elijas (Engage usa HTMLPurifier), no este componente; el puente (`docs/COMENTARIOS.md`) solo pasa el id del viaje, nunca el correo del autor, y la página de comentarios exige sesión, viaje publicado y nivel de acceso del mapa. El viaje no se hace pasar por artículo (una extensión de comentarios no puede confundirlo con el artículo del mismo número) y el panel es una página del mismo dominio, sin scripts de terceros.

## Despliegue recomendado
1. Joomla 5.4.9 / 6.1.4 o superior.
2. Carpeta privada de fotos **fuera** de la raíz web (Opciones → Carpeta privada).
3. PHP: `expose_php=Off`, `display_errors=Off`, `upload_max_filesize` y `post_max_size` coherentes con los ajustes (el navegador reduce cada foto antes de subirla).
4. Desactivar *Web Services* / *API Authentication* si no usas la API.
5. Revisar el registro de eventos y la papelera de vez en cuando.

## Añadido en 1.3.0
- **Panel de administración**: los interruptores guardan por una acción propia que exige POST, `X-Requested-With`, token CSRF y permiso de Opciones; solo acepta una lista cerrada de nombres y de valores (probado con `storage_path`, valores fuera de lista, listas y vacíos).
- **Sorteo**: solo para quien puede cambiar estados (moderadores); filtros validados y descartados si no son fechas o números; descarga y sorteo exigen POST con token; el CSV neutraliza celdas que empiezan por `= + - @`, tabulador o retorno de carro y duplica comillas; el sorteo usa `random_int` sin repetir; el registro guarda solo recuentos, nunca nombres ni correos.
- **Correos a personas**: texto plano, el nombre y el título se reducen a una línea (no pueden introducir cabeceras), un solo enlace a nuestro propio mapa, nunca el correo de otra persona, nada a cuentas bloqueadas, un fallo de correo no interrumpe la moderación.
- **Ranking**: se calcula con consultas parametrizadas sobre datos ya moderados; las semanas cerradas no cambian; el cierre usa la clave de la fila de semana como cerrojo.
- **Filtro por persona**: usa una clave opaca (HMAC con el secreto de Joomla), no el id de usuario.
- **RGPD**: acceso y supresión mediante las herramientas de Privacidad de Joomla y borrado en cascada al eliminar la cuenta (archivos incluidos). Ver `docs/PRIVACIDAD.md`.

## Añadido en 1.4.0: logo del mapa
Pasa por `ImageProcessor` (el mismo que las fotos) con límites propios: se acepta hasta 5 MB, 4096 px por lado y 16 MP (y se reduce), se guarda a ≤ 512 px como PNG re-codificado (`logo_<aleatorio>.png`, sin pérdida, con transparencia) y se estampa como marca de agua en las fotos nuevas del mapa. Se rechazan SVG (scripts), GIF, BMP, PNG con código pegado o truncados y cabeceras con dimensiones absurdas, y `tmp_name` que no subió PHP. El campo se lee como UN archivo (una lista se ignora), el nombre almacenado lo genera el servidor, la columna `logo` no se puede rellenar desde el formulario, el guardado exige token CSRF y permiso de edición, y se sirve con `Content-Security-Policy: sandbox`, `nosniff` y 404 vacío si no hay permiso.
