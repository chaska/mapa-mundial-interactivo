# Registro de cambios

## 1.11.0 — 2026-10-14 (borrar viajes, mapas oscuros, estilos del administrador siempre al día)
- **Cada persona puede eliminar sus viajes** (también los pendientes) desde la ficha del viaje: botón «Eliminar viaje» → ventana de confirmación propia → se borran el viaje, sus fotos (filas y archivos), sus «me gusta» y su hilo de comentarios. **Los administradores** (permiso «Eliminar» del componente) pueden eliminar cualquiera desde el mapa, además de «Borrar con fotos» y «Eliminar definitivamente» en el administrador. Nadie puede borrar el viaje de otro (mismo 404 que un viaje inexistente); POST + cabecera AJAX + token; queda anotado el código del borrado (propio / por administrador), nunca el contenido.
- **Mapas oscuros** («Océano de noche» y «Plano técnico»): los botones de la ficha (me gusta, soy fan, comentarios, cerrar, flechas) vuelven a leerse: antes llevaban letra clara sobre fondo claro. Ahora los paneles conservan el papel claro con su letra oscura y solo el mapa, la cabecera y los mandos van en oscuro. Prueba nueva de contraste en los seis estilos.
- **Arrastre del mapa**: mientras se arrastra, nada bajo el dedo reacciona (se desactivan el «hover» de los países y las transiciones en el mapa vectorial) y se quitan los filtros de brillo al pasar el ratón de «Político» y «Acuarela». Un repintado por «hover» del mapa entero hacía que el mapa fuera por detrás de las chinchetas.
- **Los estilos del administrador no se quedan en caché**: los archivos del administrador llevaban una versión fija (1.3.0) en `joomla.asset.json`, así que el navegador o el servidor podían seguir sirviendo el CSS antiguo y la pantalla se veía sin diseño. Ahora usan la versión automática de Joomla, que cambia en cada instalación; los del mapa también la piden explícitamente.
- Pruebas nuevas: `105-borrar-viaje.php` (32 comprobaciones) y `102-borrar-ficha-navegador.js` (14).

## 1.10.0 — 2026-10-14 (administrador con diseño propio, menú del mapa, podio)
- **El administrador ya no parece Joomla**: todas las pantallas del componente (Panel, Viajes, Álbumes, Edición, Ranking, Sorteo y Opciones) se muestran sin el menú ni la barra de Joomla, con menú lateral propio (con «Volver a Joomla» y «Ver el mapa»), tarjetas, «chips», botones redondos y modo oscuro automático. Se ve igual en el móvil (el menú pasa a una franja superior).
- **Viajes en tarjetas** con foto grande, filtros por estado con su recuento, acciones por tarjeta (Publicar, Ocultar, Papelera, Restaurar) y barra flotante para lo seleccionado (Publicar, Ocultar, Spam, Papelera, Borrar con fotos, Eliminar definitivamente) con ventana de confirmación propia (sin avisos del navegador).
- **Opciones en pantalla propia** (fotos, mapa, moderación, ranking, privacidad, correos), con los mismos límites y validaciones que antes; los permisos de grupos siguen en la pantalla de permisos de Joomla (botón «Permisos»).
- **Mapa: botones en un menú** (tres rayitas): Podio, Descubrir, Volver al sitio y el selector de álbum van en un desplegable; «Añadir mi viaje» y tu nombre siguen a la vista.
- **Podio: los escalones vacíos ya no flotan** (la clase `mv-empty`, pensada para «sin resultados», les añadía 28 px de relleno inferior; ahora el puesto vacío usa `mv-pnoone`).
- Pruebas nuevas: `101-admin-navegador.js` (22 comprobaciones) y una comprobación de que los tres escalones del podio apoyan en el suelo.

## 1.9.0 — 2026-10-14 (estilos de mapa)
- **Seis estilos de mapa**: Pergamino viajero (el de siempre, sigue por defecto), Océano de noche, Atlas moderno, Mapa político, Acuarela y Plano técnico. El administrador elige el de todos desde el Panel o desde Opciones → Mapa.
- **Que cada persona elija su mapa** (interruptor en el Panel, apagado por defecto): el mapa muestra un botón de paleta junto a los mandos; la elección se guarda solo en el navegador de cada persona (nada en el servidor) y un valor extraño guardado allí se ignora.
- **Añadir un estilo = copiar un archivo CSS** a `media/com_mapaviajes/css/maps/<nombre>.css` (nombre en minúsculas, cifras y `_`). La primera línea dice cómo aparece en la lista: `/* mapa: Mi estilo; agua: #aabbcc; tierra: #ddeeff */`. Ver `docs/ESTILOS-MAPA.md`.
- Solo se carga el CSS del estilo elegido (o los de todos si se deja elegir). Los nombres de estilo se validan (patrón estricto y archivo existente); cualquier otro valor se rechaza con 400.
- Prueba nueva `tests/joomla-live/100-estilos-mapa.js` (14 comprobaciones).

## 1.8.7 — 2026-10-14 (revisión de seguridad)
Revisión de seguridad con tres revisores independientes (frontend, servicios, administración/plugins/JS) y repaso contra los avisos de Joomla 5/6. Sin fallos críticos ni altos. Corregido:
- **Las fotos ya no revelan su nombre interno ni el id del autor**: la cabecera `ETag` llevaba el nombre del archivo (con el número de usuario); ahora es un hash con la clave secreta del sitio.
- **Nombres de foto más difíciles de adivinar**: 96 bits aleatorios (antes 32).
- **Límites a prueba de peticiones en paralelo**: el recuento de intentos de envío y el tope diario de «me gusta»/favoritos se hacen ahora con el usuario bloqueado dentro de una transacción (antes, varias peticiones simultáneas podían saltarse el tope y obligar al servidor a procesar muchas imágenes).
- **Clickjacking**: el mapa y la página de comentarios envían `X-Frame-Options: SAMEORIGIN`.
- **Privacidad (RGPD)**: una petición de exportación con un correo sin cuenta ya no devuelve las filas del registro de seguridad de usuarios borrados.
- **Permisos**: quien solo puede crear/editar mapas ya no puede publicarlos (`core.edit.state`); la acción `core.edit` aparece en la pestaña Permisos; se desactiva el «lote» (copiar/mover en bloque) de viajes y mapas, que se saltaba los límites y filtros de envío.
- El filtro por título que usa Engage en el administrador solo devuelve viajes publicados y escapa `%` y `_`.

## 1.8.5 — 2026-10-14
- Solo administradores: el «Diagnóstico» de la ficha incluye una **prueba en vivo del captcha** (¿se cargó el script de Google?, ¿cuántos campos de captcha hay y qué longitud tiene el código antes de enviar?, errores de JavaScript). Sirve para ver por qué reCAPTCHA v3 no rellena el código sin abrir la consola.

## 1.8.4 — 2026-10-14
- Solo administradores: el diagnóstico de la ficha indica ahora «Captcha activo en Engage» (plugin y «para quién») y, si es un reCAPTCHA, avisa de qué mirar si el comentario se rechaza con «Captcha answer is missing». Probado con «Proof of Work» (iframe oculto y revelado: se guarda). Con reCAPTCHA v3 de terceros no se pudo reproducir aquí (requiere claves reales de Google); no se debilita ninguna comprobación.

## 1.8.3 — 2026-10-14
- El formulario de Engage, mientras no se pide, se oculta con altura 0 y `overflow:hidden` (antes: fuera de la pantalla y `visibility:hidden`), para no estorbar al captcha invisible de Joomla (que se inicializa al cargar y se ejecuta al enviar). Si el sitio exige captcha a quien comenta y el comentario se rechaza con «Captcha answer is missing», ver Engage → Opciones → «Captcha» / «Captcha para».

## 1.8.2 — 2026-10-14
- Solo administradores: bajo «Comentarios» de la ficha, un enlace «Abrir esta página de comentarios en una pestaña nueva» con la dirección REAL de esa página (con el menú y las URL amigables del sitio), para probar los comentarios fuera de la ficha sin tener que adivinar la ruta.

## 1.8.1 — 2026-10-14
- **Los mensajes de Engage ahora se ven**: cuando Engage rechaza un comentario (sin permiso, comentarios cerrados, token caducado…) deja el motivo en la cola de mensajes de Joomla y vuelve a la página; la página de comentarios de la ficha no la imprimía y por eso «no pasaba nada» (el editor solo reponía el texto). Ahora se muestran los mensajes arriba del hilo.
- **Diagnóstico para administradores** al final del hilo (desplegable «Diagnóstico»): proveedor, Engage activo, `asset_id` del viaje, si viaje y álbum están publicados, si los comentarios están abiertos y si tu usuario tiene el nivel de acceso del álbum.

## 1.8.0 — 2026-10-14
- **Arrastrar el mapa**: el mapa y la capa de chinchetas/polaroids se mueven ahora con UNA transformación de compositor cada uno, en el mismo fotograma, sin volver a pintar el mapa vectorial en cada movimiento (antes las chinchetas llegaban antes que el mapa). La vista real se confirma al soltar. Se dibuja 160 px de mapa de más alrededor de la pantalla para que no asome ningún borde durante el arrastre, y las chinchetas se mantienen hasta 600 px fuera de pantalla para que se muevan con la capa.
- **Formulario de viaje**: «Cancelar» y «Enviar» van al final del propio formulario (se desplazan con él), ya no fijos abajo: con el teclado abierto los campos ocupan todo el espacio.

## 1.7.5 — 2026-10-18
- El mapa y la página de comentarios se sirven con `Cache-Control: no-store` y `X-LiteSpeed-Cache-Control: no-cache`: llevan el token de seguridad de la sesión y contenido de cada persona; una caché del servidor (LiteSpeed, proxy, página en caché de Joomla) con una copia vieja hace que el comentario se rechace por token inválido y que la lista no se actualice.

## 1.7.4 — 2026-10-18
- Lista de viajes del administrador: botón **Comentarios (Engage)** que abre la lista de comentarios de Engage (donde se guardan, en `#__engage_comments`, enlazados al viaje por su `asset_id`). Solo con el proveedor «Engage» y permiso de gestión en Engage.

## 1.7.3 — 2026-10-18
- Corrección: bajo «No hay comentarios» quedaba un hueco en blanco enorme (el marco de los comentarios se medía por la ventana y nunca encogía). Ahora mide lo que mide su contenido.

## 1.7.2 — 2026-10-18
- **Comentarios en la ficha**: se ven los comentarios del hilo y el formulario de Engage queda oculto hasta que se pulsa «Escribir un comentario…» (o «Responder» en un comentario). Tras comentar vuelve a verse el hilo con el formulario cerrado.
- **Compatibilidad con Engage 0.8.3**: la ficha del plugin lleva `type = mapaviajes` (Engage 0.8.3 exige que el tipo case con el componente del activo `com_mapaviajes.…`); con 0.8.2 también vale. Probado contra el commit 32e0429 de Engage.

## 1.7.1 — 2026-10-18
- **Tocar la chincheta de un álbum abre la ficha del viaje** a cualquier zoom (antes, lejos, salía una tarjeta pequeña con «Abrir álbum»). La tarjeta pequeña queda solo para los grupos de varios álbumes, que ofrece su lista.
- **Eliminar definitivamente** (botón nuevo en la lista de viajes del administrador, con confirmación): borra de una vez los viajes marcados con sus fotos, reacciones y comentarios, sin pasar por la papelera. (Mandar a la papelera sigue conservando las fotos para poder recuperar el viaje; «Eliminar de la papelera» las borra.)

## 1.7.0 — 2026-10-18
- **Ficha del viaje**: al tocar una chincheta, una polaroid, una etiqueta o una fila de la lista se abre una ficha con las fotos arriba (flechas, contador, miniaturas y deslizar), debajo el pie de la foto que se ve, la fila «Me gusta · Soy fan · Comentarios» y debajo los **comentarios** (Engage u otro sistema) dentro de la propia ficha, con un campo «Escribir un comentario…» fijo abajo que te lleva al formulario. Tocar la foto la abre a **pantalla completa** (como antes); al cerrarla se vuelve a la ficha. En el móvil la ficha ocupa el 100 % de la pantalla.
- **Sin «tirar para actualizar»**: `overscroll-behavior: none` en la página y `contain` en los contenedores que se desplazan (ficha, listas, paneles), para que el gesto de estirar hacia abajo no llegue a la app.
- Pruebas actualizadas (`31`, `61`).

## 1.6.1 — 2026-10-17
- **Móvil en horizontal**: los botones de arriba van en fila y los mandos (+, −, personas, mundo) pasan al borde derecho (a la izquierda se montaban en el logo); la brújula y la lista de personas se apartan para dejarles sitio. Probado a 667x375, 844x390 y 932x430.

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
