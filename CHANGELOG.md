# Registro de cambios

## 1.6.0 — 2026-10-17
- **El logo del mapa se guarda como PNG** (`logo_<16 hex>.png`, reescalado a ≤ 512 px y re-codificado: transparencia intacta) y **se estampa como marca de agua en cada foto nueva** de ese mapa: esquina al azar, caja de 128 px (menos en fotos pequeñas), 50 % de transparencia, con un margen. Si la foto se descarga de la web se sabe de dónde viene. Si el logo no se puede leer, la foto se guarda igual sin marca. Las fotos ya subidas no se tocan.
- **Comentarios con Engage 3.4.4+**: la vista usa el contrato genérico (`onContentAfterDisplay` con el contexto `com_mapaviajes.trip`, un objeto que solo lleva el `asset_id` y un `Registry` vacío); desaparecen el despachador privado y la fila de artículo de relleno. El plugin de contenido responde a `onAkeebaEngageGetAssetMeta` (añade `comments_show`). Al borrar un viaje se llama a `Meta::deleteAssetData` de Engage. Para verlos: Opciones → Comentarios → proveedor **Engage** (por defecto es «ninguno»), Engage 3.4.4 o posterior, plugin «Content – Engage» activo y permiso «Comentario» para el grupo que comenta. Con Engage instalado, el proveedor «eventos» también hace que Engage responda al evento estándar de Joomla.
- **Ranking**: quitado el interruptor «No aparecer en el ranking» (aparece todo el mundo salvo cuentas bloqueadas). «¿Cómo se puntúa?» sale abierto con todas las puntuaciones: viaje, foto, reacciones, comentarios y el reparto semanal (1.º 3 estrellas y 10 puntos, 2.º 2 y 6, 3.º 1 y 3, del 4.º al 10.º 1 punto).
- **Ventana**: al cambiar el tamaño de la ventana el mapa se reescala con ella (antes se quedaba al tamaño anterior).
- **Móvil**: los mandos (+, −, personas, mundo) suben 40 px para no chocar con el menú inferior de la app.
- **Reacciones a los propios viajes** (solo pruebas): opción en Opciones con tres valores: Nadie / Solo Super Users (por defecto) / Todo el mundo. Los viajes pendientes de moderación no admiten reacciones.
- Pruebas nuevas: marca de agua (unitarias y contra Joomla), `99-ajuste-navegador.js` (reescalado y mandos).

## 1.5.0 — 2026-10-16
- **Fotos ordenadas por persona**: cada foto se guarda en `…/photos/<id de usuario>/viaje_<año>_<n>_<8 hex>.webp` (por ejemplo `12/viaje_2019_1_a3f9c2d1.webp`). Las de cada persona quedan juntas y se localizan años después; el sufijo aleatorio sigue haciendo el nombre inadivinable por si un servidor sirviera la carpeta por error. La carpeta se borra sola con la última foto. La ruta guardada se valida con una expresión estricta (nada de `..`, subcarpetas extra, mayúsculas ni caracteres raros). Las fotos y logos anteriores siguen funcionando. Actualización de base de datos incluida (`filename` a 120 caracteres y `trip_year`).
- **Año del viaje**: el formulario lo pide (obligatorio, de 1900 al año actual; el servidor lo valida) y se muestra en la lista de últimos añadidos.
- **Aviso de ranking** en el formulario: los comentarios, «me gusta» y favoritos cuentan para el ranking semanal y el general.
- **Cabecera**: el título del mapa va arriba y el logo debajo (más el contador); el logo mide 177 px en ordenador (-15 %). Sin solaparse con los botones.
- **Zoom mucho mayor** (hasta 260 veces el mapa completo) y **álbumes del mismo sitio ordenados**: uno cuelga su polaroid; de 2 a 4 forman una «ventana de cuatro cristales» (2×2); de 5 en adelante se sustituyen por **etiquetas** en columna (hasta 6 y «+N más» que abre la lista), que nunca se amontonan. Los álbumes cuyas fotos se pisarían se agrupan automáticamente.
- **Filtro por personas** (botón de las dos personas junto al zoom): lista a quien ha publicado, con casillas; marcadas se ven sus viajes, sin marcar se ocultan. «Todas» y «Ninguna», y un aviso con el estado.
- Móvil: ficha de la foto/álbum **centrada** en vez de pegada abajo; con el teclado abierto el formulario ocupa justo la parte visible; `overscroll-behavior` para que un gesto sobre el mapa no active el «tirar para actualizar» de la app (si la app lo hace por su cuenta, hay que desactivarlo en la propia app).
- Corrección: pestaña Favoritos mostraba «null»; contador en singular («1 álbum · 1 país»).
- Super Users: sus viajes se publican al instante y pueden reaccionar a los suyos (opción en Opciones; desactivar al abrir al público).
- Pruebas nuevas: `98-grupos-navegador.js` (ventana de 4, etiquetas, filtro de personas, en escritorio y móvil) y año/rutas en `10-envio.php` y `02-imagen.php`.

## 1.4.2 — 2026-10-16
- Corrección: un PNG con metadatos de herramientas de IA (firmas C2PA, XMP, EXIF, texto) se rechazaba como «contiene datos que no son una imagen» porque el filtro buscaba código en todo el archivo. Ahora un PNG se lee por chunks: se buscan trampas solo en los chunks de píxeles y en lo que haya después de IEND; los chunks descriptivos se ignoran porque el re-codificado a WebP los descarta (se comprueba que el archivo guardado no conserva nada). Un chunk mal formado o sin IEND es ilegible. Código pegado tras IEND o dentro de IDAT sigue rechazado.
- Pruebas nuevas en `02-imagen.php` (4).

## 1.4.1 — 2026-10-16
- **Logo como emblema**: probado con el logo real («el sitio de pruebas», que lleva texto), 52/36 px era ilegible. Ahora se guarda a un máximo de **512 px** y el mapa lo pinta grande: **208 px en ordenador** (≥1101 px), 168 px (≤1100), 132 px (≤900), 120 px (≤720), 108 px (≤400) y 96 px (≤340), siempre entero (`object-fit: contain`, nunca estirado ni recortado).
- Si el mapa tiene logo, el `<h1>` queda en la página solo para lectores de pantalla y buscadores (el logo ya lleva el nombre); el contador de álbumes pasa a una píldora junto al logo. En móvil (≤900 px) los botones pasan a una columna de iconos de 44 px a la derecha, sin tapar el logo.
- Límite de subida del logo de 2 a **5 MB** (un PNG con transparencia de ~1250 px ya pesa 2,2 MB). Mismos topes de 4096 px por lado y 16 MP, y la misma tubería de seguridad. Nota: el `upload_max_filesize` de PHP del servidor también cuenta.
- Pruebas actualizadas (`02`, `96`, `97`).

## 1.4.0 — 2026-10-15
- **Logo del mapa** subido desde el administrador (ficha del mapa → Logo), con la misma tubería de seguridad que las fotos: se identifica por sus bytes (nunca por nombre, extensión ni Content-Type), se rechazan SVG, GIF, poliglotas y PNG con código pegado, las cabeceras se leen **antes** de decodificar (una imagen que dice 60000x60000 se rechaza sin decodificarla), máximo 2 MB y 4096 px por lado (16 MP), y se guarda como una imagen **nueva** en el almacén privado con nombre aleatorio.
- **Ajuste automático al tamaño del diseño**: el mapa pinta el logo en un cuadrado de **52 px en ordenador y 36 px en móvil** (≤720 px); se guarda a un máximo de **160 px** en el lado largo (3 píxeles por píxel CSS en el cuadrado grande: nítido en pantallas retina y de pocos KB), sin ampliar y sin deformar (4000x2000 → 160x80; 4000x4000 y tu 512x512 → 160x160). **PNG con transparencia**: el alfa se conserva y se guarda sin pérdida (WebP VP8L) para que los bordes duros no se emborronen. En la cabecera se muestra entero dentro del mismo cuadrado que el logo por defecto (`object-fit: contain`): nunca se estira ni se recorta.
- Un logo rechazado rechaza todo el guardado con el motivo y **no toca el logo anterior**; al cambiar o quitar el logo, o borrar el mapa, el archivo antiguo se borra. El nombre del archivo nunca viene del formulario. Se sirve solo con sesión y nivel de acceso del mapa (404 vacío en cualquier otro caso).
- El cuadrado del logo está fijado en CSS (`width/height/max-width/max-height`) en cada tamaño de pantalla: da igual lo que se suba, no puede crecer ni empujar nada. Probado en Chromium de 320 a 1920 px de ancho con cuatro formas de logo.
- Cabecera en **dos filas en móviles de ≤400 px** (logo y título arriba, botones debajo): con el botón del podio, el título se quedaba en 0–66 px.
- Corrección: el tamaño mínimo de un archivo aceptado baja de 100 a 60 bytes (un logo plano sin pérdida puede ocupar unas decenas de bytes).
- Pruebas nuevas: 19 de logo sin Joomla (`02-imagen.php`), 44 contra Joomla (`96-logo.php`) y 92 en Chromium (`97-logo-navegador.js`: 11 anchos de pantalla x 4 formas de logo).

## 1.3.0 — 2026-10-14
- **Ranking semanal con podio de cómic**: botón «Podio» en el mapa; semana en curso, semana pasada y tabla general; estrellas 3/2/1; puntos de viajero 10/6/3 (1 del 4 al 10). Solo cuentan viajes publicados con fotos; reacciones y comentarios suman como mucho lo mismo que viajes y fotos; topes por semana; desempate determinista; semanas cerradas inmutables (cierre perezoso a los 2 días, sin dos cierres a la vez). Cada persona puede **no aparecer**. Pesos y topes en Opciones.
- **Filtro por persona** en el mapa (desde el podio o la lista): «Viajes de …».
- **Panel de administración** con tarjetas e interruptores que se guardan solos (lista blanca de ajustes, token, solo con permiso de Opciones), vista de **Ranking** y vista de **Sorteo**: participantes (viajes publicados con fotos, filtros por fechas, mínimo y mapa), descarga CSV protegida contra inyección de fórmulas, sorteo al azar sin repetir y registro sin datos personales.
- **Correos a personas**: aviso al autor cuando se publica o no se publica su viaje (texto plano, un solo enlace, sin cabeceras inyectables, no a cuentas bloqueadas) y, si se enciende, felicitación al podio.
- **Privacidad (RGPD)**: plugins `privacy` (acceso y supresión desde las herramientas de Joomla) y `user` (al borrar una cuenta se borran viajes, fotos con sus archivos, reacciones, ranking, ajustes y el hilo de comentarios). `docs/PRIVACIDAD.md`.
- Paquete con cuatro piezas: componente + plugins `content`, `user` y `privacy`; los nuevos se activan al actualizar desde versiones anteriores.
- Fuente Bangers (OFL) para el estilo cómic, alojada en el servidor.
- Pruebas nuevas: 30 (ranking), 29 (podio en Chromium), 59 (panel, sorteo, correos), 17 (privacidad).

## 1.2.0 — 2026-10-13
- **Comentarios por viaje** mediante otra extensión (Joomla no trae comentarios). Opciones → Sistema de comentarios: Ninguno / Akeeba Engage-Engage / Eventos de contenido de Joomla. Botón «Comentarios» en el visor que abre un panel del mismo dominio; enlace `…&trip=ID` que abre el mapa en ese viaje.
- Cada viaje tiene su fila de activos `com_mapaviajes.trip.N` y su contexto `com_mapaviajes.trip` (no se hace pasar por artículo). Al borrar el viaje se borran su hilo y su activo.
- Paquete `pkg_mapaviajes` (componente + plugin `plg_content_mapaviajes`, que enseña a Engage qué es un viaje y no entrega el correo del autor).
- Documentación de cómo funcionan los comentarios en Joomla y qué cadenas usa cada sistema: `docs/COMENTARIOS.md` (JComments y Komento sin probar, dicho claramente).
- Pruebas nuevas: 34 en vivo con Engage real y una extensión simulada (`60-comentarios.php`) y 14 en Chromium (`61-comentarios-navegador.js`).

## 1.1.0 — 2026-10-12
- Permisos separados: `mapaviajes.submit` (añadir viajes con fotos; usuarios registrados) y `core.create` (crear mapas/álbumes en el administrador; solo administradores). Al actualizar desde 1.0.x se traspasa el permiso antiguo automáticamente. Prueba: tener solo `core.create` no permite enviar viajes.
- Búsqueda de lugares con **todas las ciudades del mundo** (~127 000, **con nombres en español**: Londres, Nueva York, Roma, El Cairo…; datos de GeoNames/Wikidata vía Countries States Cities Database, ODbL; crédito visible en el formulario). ~600 nombres españoles escritos a mano (Gante, Brujas, Núremberg, Oporto, Pekín…) y búsqueda también por el nombre local o inglés («Gent», «Mumbai», «Cuzco»). Mapa-click propone la ciudad más cercana: archivo propio en tu servidor, búsqueda binaria por prefijo (<1 ms), sin consultar a nadie. Pinchar en el mapa propone la ciudad más cercana. Solo usuarios registrados, solo GET, entrada validada. Pruebas: 28 en PHP (incluye que ningún nombre de lugar sea rechazado por el saneador) y 27 en vivo.
- Reacciones: corazón («Me gusta») que muestra debajo un mensaje amable al azar (diez variantes, ES/EN) y estrella de favoritos que dice «¡Soy tu fan!». Pestaña «Favoritos» en el cajón con contador.
- Solo usuarios registrados; solo viajes publicados de álbumes que el usuario puede ver; nunca en el viaje propio; tope diario de reacciones nuevas (`reactions_per_day`, 300 por defecto); alternar atómico (una sola fila por viaje, usuario y tipo, aguanta clics simultáneos). CSRF + `X-Requested-With`, solo POST.
- Al borrar un viaje se borran sus reacciones. (Pendiente: purgar las de un usuario borrado con el gancho de privacidad de Joomla.)
- Pruebas nuevas: 40 en vivo (`40-reacciones.php`) y 18 en Chromium (`31-reacciones-navegador.js`).

## 1.0.0 — 2026-10-11 (primera versión)
- Componente Joomla 6 (MySQL y PostgreSQL) con mapa mundi propio (SVG, sin servicios externos), chinchetas agrupadas por país que se separan al acercar, polaroid con la chincheta clavada en su esquina más alta, visor de fotos a pantalla completa (flechas, teclado, deslizar con el dedo) y lista de últimos añadidos.
- Formulario de «añadir viaje» que funciona en móvil táctil: búsqueda de lugar propia, 1–5 fotos con pie de foto (256 palabras), reducción de fotos en el navegador antes de subirlas, barra de progreso.
- Canal de subida seguro: texto plano obligatorio (sin código ni enlaces), fotos identificadas por su contenido real y recodificadas a WebP (lado largo 1000 px, sin deformar, sin metadatos), almacén privado con nombres aleatorios y entrega solo tras comprobar permisos, límite de envíos por usuario, CSRF, cabecera `X-Requested-With`, campo trampa.
- Administración: publicaciones (publicar, despublicar, ocultar, spam, papelera, eliminar con sus archivos), álbumes del mapa (crear, editar, ordenar, nivel de acceso), Opciones con interruptores Sí/No, aviso por correo a la administración.
- El instalador se niega a instalarse en un Joomla con fallos de seguridad publicados (menos de 5.4.9 / 6.1.4).
- Revisión de seguridad independiente: 6 hallazgos (3 medios, 3 bajos), todos corregidos y con prueba (ver `SECURITY.md`).
- Pruebas: 103 de texto, 68 de imágenes y 25 de idiomas en PHP, 144 + 46 contra un Joomla 6.1.4 real y 32 en Chromium (móvil táctil y escritorio).

### Pendiente (no incluido en 1.0.0)
ranking; conexión con Akeeba/Engage para comentarios por álbum; importación de GeoNames para la búsqueda de lugares; panel de administración con tarjetas y guardado automático; avisos por correo a los usuarios; filtro por usuario en el mapa.
