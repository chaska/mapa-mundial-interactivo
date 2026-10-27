# Registro de cambios

## 1.18.1 — 2026-10-27 (auditoría completa de seguridad)
Auditoría con seis revisiones independientes (fotos y amigos, ranking y plugin, JavaScript, instalación y privacidad, revisión de código y revisión del diff). Informe: `docs/AUDITORIA-SEGURIDAD-1.18.md`.
- **Permiso propio «Moderar en el mapa»** (`mapaviajes.moderate`). Antes, ver todas las fotos «solo amigos» o pendientes, borrar viajes ajenos y abrir/cerrar comentarios ajenos dependía de «Editar estado» / «Eliminar», que Joomla hereda de serie a los grupos **Publisher y Manager**: cualquiera de esos grupos veía las fotos privadas de todo el mundo. Ahora solo moderan los Superusuarios y quien tenga el permiso nuevo. Al instalar se da al grupo Administrator; al **actualizar** se conserva lo que ya tuvieras marcado en «Editar estado» y se añade Administrator, pero se pierde lo heredado de Publisher y Manager. Revísalo en Opciones → Permisos. El panel de administración sigue usando los permisos normales de Joomla.
- **Amistad: bloquear y desbloquear ya no salta la espera.** Quien pedía amistad, era rechazada y se bloqueaba a sí misma y desbloqueaba podía volver a pedirla al instante y mandar otro correo (hasta 10 al día). Ahora el bloqueo conserva la fila de rechazada/retirada.
- **Denuncias**: solo se puede denunciar un viaje que la persona podría ver (mapa publicado y con su nivel de acceso); antes servía para sondear números de viaje.
- **CSS propio**: ya no admite `@import` ni `url()` remotos ni escapes de CSS (hacían que el navegador de todo visitante llamara a otra web, contra lo que promete la política).
- **Ranking**: los viajes de un mapa despublicado ya no puntúan. Se quita `lastClosedWeek()` (sin uso). Los textos de privacidad dejan de prometer un interruptor «no aparecer» que ya no existe.
- **Fallos que antes eran mudos y ahora dejan rastro** en el registro de Joomla (`com_mapaviajes`): MiFoto que falla o cambia de API, y borrado de datos de un usuario que falla al eliminar su cuenta. «Descartar denuncias» queda anotado en el registro de eventos (quién y cuántas).
- Miniaturas: se borran también si la foto original ya no existe.
- Empaquetador: ya no deja pasar direcciones con «example» u «openfontlicense» en el nombre, y revisa también `.html` y `.svg`.
- Pruebas: 113 (+4: bloqueo, permisos de Publisher/Manager/Administrator), 108 (+1), 110 (+1), vistos en rojo sin el arreglo; 114 ya no falla por las miniaturas falsas que deja la prueba del podio.

## 1.18.0 — 2026-10-27 (fotos de perfil con MiFoto)
- **Fotos de perfil** (opcional, necesita el componente **MiFoto** ≥ 0.2.0): quien haya subido su foto la enseña en el **podio** (semana, semana pasada y General), en la **lista de amigos y solicitudes** y junto al nombre del autor en la **ficha del viaje**. Quien no tiene foto sigue con el círculo de color con la inicial; si la foto falla al cargar, también vuelve la inicial.
- **Quién la ve lo decide MiFoto** (permiso de quien mira): el mapa le pregunta con una sola consulta por pantalla (`ProfilePhoto::urls()`), no guarda la dirección en la base de datos ni en cachés compartidas y no distingue «sin foto» de «no puedes verla». Sin MiFoto, o si algo falla, no pasa nada: se ven las iniciales.
- **Interruptor del administrador** «Mostrar las fotos de perfil» (Panel de control y Opciones → Privacidad; por defecto sí). Apagado = todo el mapa con iniciales.
- Seguridad: solo se aceptan direcciones del propio sitio que empiezan por una sola `/`, sin espacios ni comillas; la imagen va sin `referer` y con texto alternativo vacío (decorativa). El navegador nunca recibe el id de Joomla.
- **Auditoría 1.16–1.18** (`docs/AUDITORIA-SEGURIDAD-1.18.md`): sin hallazgos medios ni altos. El empaquetador ahora fija el SHA-256 del lector HEIC incluido y se niega a construir si cambia. `docs/PRIVACIDAD.md` recoge las fotos de perfil.
- Pruebas nuevas: `tests/05-foto-perfil.php` (los filtros de direcciones y el servicio, 33) y `118-foto-perfil-navegador.js` (podio, General, amigos, ficha, foto que falla y ninguna petición a otro dominio, 14).

## 1.17.2 — 2026-10-26
- **Podio: la semana se cierra al acabar** (domingo 24:00 UTC), sin esperar los 2 días de margen. En la primera petición después, la semana pasada queda fijada y suma al ranking General.

## 1.17.1 — 2026-10-26
- **Podio: «Semana pasada» se quedaba en la primera semana.** Enseñaba la última semana *cerrada*, y una semana solo se cierra 2 días después de acabar (para dar tiempo a moderar), así que cada lunes seguía saliendo la semana anterior a la anterior. Ahora «Semana pasada» es siempre la semana natural anterior: si aún no está cerrada se calcula en vivo y se avisa de que es provisional; al cerrarse queda fija. «Esta semana» y «General» no cambian.

## 1.17.0 — 2026-10-26 (fotos HEIC)
- **Fotos HEIC / HEIF** (iPhone y algunos Xiaomi): se pueden elegir en el formulario de crear y en el de editar un álbum. Se leen **en el navegador** con `libheif` (WebAssembly), que viaja dentro del paquete (`media/js/vendor/libheif-bundle.js`, LGPL-3.0, anotado en `NOTICE.md`) y solo se carga cuando alguien elige un HEIC: nada se descarga de internet y el servidor nunca abre un HEIC. La foto se reduce a 1800 px como cualquier otra y sigue el camino normal (el servidor la guarda en WebP, sin EXIF). Mientras se prepara, la tarjeta muestra un marcador y no se puede enviar hasta que acabe.
- **Menos pérdida al subir**: el paso intermedio en el navegador pasa de JPEG calidad 88 a **WebP calidad 92** cuando el navegador sabe escribir WebP (el servidor también guarda WebP, así que la segunda conversión casi no pierde), y a JPEG calidad 92 si no. Un JPEG pequeño sigue yendo tal cual, sin reconvertir.
- Prueba nueva: `117-heic-navegador.js` (12), con un HEIC real y un JPEG grande: vista previa, reducción, envío, WebP en disco sin EXIF y ninguna petición a internet.

## 1.16.2 — 2026-10-25
- **Miniaturas que no cargaban en las listas** (Favoritos, Últimos, Siguiendo, ventanas del mapa): al abrir una lista de 50 viajes el navegador pedía a la vez 50 fotos en tamaño completo y un hosting compartido rechazaba parte de las peticiones. Ahora las listas piden una **copia pequeña** (320 px de lado, `&t=1`) que el servidor crea la primera vez y guarda en el disco privado (`thumbs/`, se borra con la foto); las imágenes se cargan **solo al aparecer en pantalla** y, si una falla, se **piden de nuevo hasta 3 veces**. La copia pequeña puede reutilizarse 10 minutos; la foto completa se sigue revalidando siempre. La puerta de acceso es la misma: una foto «solo amigos» da 404 a quien no es amigo también en miniatura.
- Pruebas: `113` ampliada (100), `114` independiente de los datos que dejen otras pruebas (33).

## 1.16.1 — 2026-10-26
- **Nuevo código `{mapaviajes photos}`**: escribe el total de fotografías (las ya aprobadas de los viajes publicados de mapas publicados, también las marcadas «solo amigos»; solo se enseña el número). Admite `album=ID` como los demás. Guía actualizada en `docs/CODIGOS-ARTICULO.md`.
- Prueba `116` ampliada (8).

## 1.16.0 — 2026-10-26 (códigos para artículos)
- **Códigos para insertar en un artículo de Joomla** (los resuelve el plugin «Contenido - Mapa de viajes», que ya viene en el paquete): `{mapaviajes stats}` escribe «51 álbumes · 16 países»; `{mapaviajes albums}` y `{mapaviajes countries}` escriben solo el número; con `album=ID` (por ejemplo `{mapaviajes stats album=3}`) cuentan un solo mapa. Cuentan los viajes publicados de mapas publicados; solo salen totales, nunca nombres, lugares ni fotos. Los países se cuentan igual que en la cabecera del mapa (mismos contornos, comprobado contra el navegador). Se guarda 10 minutos en la caché de Joomla. Si algo falla, el artículo se pinta igual. Guía: `docs/CODIGOS-ARTICULO.md`.
- El plugin ahora se suscribe siempre que el componente esté activo; los eventos de comentarios (Engage) siguen activándose solo si Engage está instalado.
- Prueba nueva: `116-codigos-articulo.js` (7).

## 1.15.2 — 2026-10-25
- **La edición de un álbum permite cambiar la ciudad, el año y el título** (antes solo las fotos). El lugar se cambia igual que al crear: escribiendo el nombre o las coordenadas (también `40°22′14″N 2°48′41″O`) o pinchando el mapa. Si el sitio revisa antes de publicar, cambiar el **título** (texto libre) o poner un **nombre de lugar escrito a mano** devuelve el álbum a revisión; el panel avisa antes de guardar. Cambiar a una ciudad de la lista, a coordenadas o a un punto del mapa no lo necesita.
- **La Antártida ya está dibujada** en todos los mapas (la primera versión del dibujo del mundo la dejaba fuera). Herramienta: `tools/add-antarctica.mjs`.
- **Auditoría de seguridad** (`docs/AUDITORIA-SEGURIDAD-1.15.md`; skills security-audit y seguridad-zero-trust; batería de pruebas en vivo). Sin hallazgos críticos ni altos. Corregido:
  - Rechazar o retirar una solicitud de amistad borraba la fila, así que se podía volver a pedir al instante y cada vez salía un correo a la víctima. Ahora: rechazada = 30 días sin poder volver a pedir (respuesta neutra) y una solicitud retirada no vuelve a mandar correo en 7 días; una solicitud rechazada no se convierte en amistad si luego la pide la otra persona.
  - Un nombre de lugar falsificado en la edición se publicaba sin revisión (ver arriba).
  - Dos guardados simultáneos de la edición de fotos podían dejar un álbum sin foto pública: las reglas se comprueban de nuevo con el bloqueo de la persona cogido.
- Pruebas nuevas: `113` ampliada (93), `112` con la Antártida (24).

## 1.15.1 — 2026-10-25
- **Editar las fotos de un álbum ya publicado usa la MISMA pantalla que crearlo**: el panel de «Añadir mi viaje», con las mismas tarjetas de foto, la misma casilla «Solo amigos», la misma zona para soltar fotos y los mismos botones. Solo se ocultan lugar, año, título y comentarios, que no cambian después de publicar, y los pies de foto ya publicados salen bloqueados. **Nada se guarda hasta pulsar «Guardar cambios»**; entonces se aplica todo junto (quitar, cambiar «solo amigos» y añadir) y el servidor comprueba el resultado final: máximo de fotos, al menos una y al menos una pública, sea cual sea el orden en que se hicieron las cosas en pantalla. La declaración de derechos solo se pide si hay fotos nuevas. Sustituye a la ventanita anterior y a sus tres envíos sueltos por uno solo (`trip.photos`).
- **El cuadro del lugar entiende las coordenadas de Google Maps**: `40°22′14″N 2°48′41″O` (grados, minutos y segundos; N/S/E/W y también O de oeste; letras delante o detrás; comillas rectas o tipográficas), además de los decimales de siempre (`40.41, -3.70`, `40.3706 N, 2.8114 W`). Latitud o longitud imposibles, o minutos y segundos de 60 o más, se rechazan. El navegador solo saca dos números del texto; el servidor los vuelve a validar y los guarda como parámetros.
- Pruebas nuevas: `115-coordenadas-navegador.js` (10); `113` y `114` adaptadas a la nueva pantalla (75 y 28).

## 1.15.0 — 2026-10-24 (amigos, fotos «solo amigos», editar álbumes)
- **Editar un álbum ya publicado** (botón «Editar fotos» en la ficha de tu viaje): añadir fotos hasta completar las 5, marcar o desmarcar «solo amigos» en cada una (se guarda al momento) y borrar fotos. Siempre tiene que quedar **al menos una foto y una pública**. Los pies de foto ya publicados no se pueden cambiar (sería saltarse la revisión del texto). Para subir fotos nuevas hay que marcar la declaración de derechos, igual que al crear el álbum.
- **Revisión de las fotos añadidas después**: si la revisión previa está activada, las fotos nuevas de un álbum ya publicado quedan **pendientes**: solo las ven su autor/a y los moderadores, no cuentan para el ranking y llega un correo a los moderadores. En el administrador: filtro **«Fotos nuevas»**, etiqueta en cada tarjeta y botones **«Aprobar fotos»** / **«Rechazar fotos»** (borra solo las pendientes). Los álbumes ya aprobados no desaparecen mientras tanto.
- **Fotos «solo amigos»**: casilla en cada foto al subir el álbum (y al editarlo). La protección es del servidor: quien no es amigo recibe un 404 aunque adivine la dirección de la foto, y el mapa solo le dice **cuántas** hay («2 fotos solo para amigos»), nunca cuáles. **La chincheta la ve todo el mundo.** La miniatura (chincheta ampliada, listas, polaroid) es siempre la primera foto pública. Administradores y superadministradores ven **todas** las fotos para poder revisar spam o normas.
- **Amigos**: «Pedir amistad» en la ficha de cada viaje, pestaña **Amigos** en Descubrir y entrada **Amigos** en el menú ☰ (con puntito si hay solicitudes). La otra persona acepta, rechaza o bloquea; cualquiera de las dos puede quitar la amistad en cualquier momento y se pierde el acceso al instante. **Correo de aviso** (sin enlaces) cuando alguien te pide amistad. Si las dos personas se piden amistad a la vez, se aceptan solas. Nadie ve la lista de amigos de otra persona. Límites: 500 amigos, 20 solicitudes pendientes y 10 solicitudes al día por persona. Bloquear impide pedir amistad y ver las fotos privadas. «Seguir» sigue siendo distinto: no necesita permiso y no da acceso a nada.
- **Filtro de personas**: botón **«Solo amigos»**.
- **Ranking**: una foto «solo amigos» vale menos que una pública (nueva opción «Puntos por foto "solo amigos"», por defecto 1 frente a 3) y se explica en «¿Cómo se puntúa?». Las fotos pendientes no puntúan.
- Privacidad: la exportación de datos incluye amistades y bloqueos; borrar la cuenta los elimina en los dos sentidos.
- Pruebas nuevas: `113-amigos-fotos.php` (70), `114-amigos-navegador.js` (23); `70` ampliada.

## 1.14.2 — 2026-10-22
- **Corregido: el correo de «viaje nuevo» a los moderadores llegaba con los textos sin traducir** (`COM_MAPAVIAJES_MAIL_ADMIN_NEW` y `COM_MAPAVIAJES_MAIL_ADMIN_BODY` en lugar del asunto y el cuerpo). El asunto y el cuerpo se componían con el idioma de la página del visitante, que no tiene los textos de los correos. Ahora los compone el propio sistema de correos, con los textos del sitio, igual que el resto de avisos. La prueba `108` comprueba que no quede ninguna clave `COM_MAPAVIAJES_` a la vista.

## 1.14.1 — 2026-10-21 (islas)
- **Islas Canarias, Baleares, Madeira y Azores en TODOS los mapas** (los seis estilos comparten el mismo dibujo). El mapa venía de Natural Earth 1:110m, que se deja esas islas fuera. Se han añadido desde Natural Earth 1:10m (dominio público): 8 islas Canarias, 5 Baleares, 5 de Madeira (con Porto Santo y las Desertas) y las 9 de las Azores. Cada archipiélago cuenta como parte de España o de Portugal, así que un álbum en Tenerife o en Funchal se agrupa con su país igual que uno en la península. El archivo crece unos 14 KB. Herramienta: `tools/add-islands.mjs`.
- Prueba nueva: `112-islas.js`.

## 1.14.0 — 2026-10-17 (CSS propio y entradas extra en el menú)
- **Opciones → Mapa → «CSS propio del mapa»**: un cuadro de texto para quitar o mover lo que otras extensiones (cookies, chat, accesibilidad…) ponen encima del mapa, p. ej. `.nexusconsent-revoke-fab{display:none!important}`. Solo se aplica en la pantalla del mapa; se limita a 5000 caracteres y se le quitan los signos `<` y `>` (no puede sacar etiquetas).
- **Opciones → Mapa → «Entradas extra del menú ☰»**: una por línea, `Texto | qué hacer`. «Qué hacer» es una dirección (`https://…` o `/ruta`) o un **selector CSS**: la entrada pulsa ese elemento de la página aunque esté oculto con el CSS propio. Así se esconde el icono de otra extensión y se ofrece dentro del menú (`Cookies | .nexusconsent-revoke-fab`). Máximo 8; las de selector solo aparecen si el elemento existe; se descartan `javascript:`, `data:` y entradas vacías. Sirve para cualquier módulo, plugin o componente, sin tocar el código del mapa.
- Prueba nueva: `110-css-menu-navegador.js` (8).

## 1.13.1 — 2026-10-16
- **Arrastre del mapa: las chinchetas ya no se adelantan al mapa en ningún estilo.** El mapa y las chinchetas eran dos capas independientes que el navegador movía por separado; en «Pergamino» el papel con textura las unía en una sola superficie y por eso ahí no se notaba. Ahora el mapa, la textura y las chinchetas viven en UNA sola capa (`#mv-pan`) y arrastrar mueve solo esa, así que no pueden separarse. La prueba `99` comprueba la capa única.

## 1.13.0 — 2026-10-15 (cumplimiento: denunciar contenido, derechos de las fotos, remitente)
Segunda pasada de la pre-auditoría legal (`docs/AUDITORIA-LEGAL-mapa-viajes-2026-10-15.md`).
- **Botón «Denunciar» en la ficha de los viajes ajenos** (aviso y acción, DSA art. 16): ventana propia con motivo (contenido ilegal, fotos sin permiso, salgo yo sin permiso, se ve un menor, otro) y nota opcional. Una denuncia por persona y viaje, máximo 10 por hora, mismas puertas que el resto de escrituras (POST + cabecera AJAX + token + sesión). **No oculta nada sola**: avisa por correo (sin enlaces) a los moderadores y aparece en el administrador: filtro **«Denunciados»** con su recuento, etiqueta «N denuncias» en cada tarjeta y botón «Descartar denuncias». Se borran con el viaje, y la persona que denunció las ve en su exportación de datos y se borran al eliminar su cuenta. Nadie denuncia su propio viaje.
- **Opciones → Correos: «Correo de contacto para avisos»** (se enseña en la ventana de denuncia) y **«Quién envía los correos»** (nombre o empresa y contacto, impreso al pie de todos los correos).
- **Declaración al subir fotos**: casilla obligatoria «las fotos son mías o tengo permiso, las personas que salen están de acuerdo y no se ve ningún menor identificable». Si falta, el servidor responde 422 y no se crea nada.
- `docs/PRIVACIDAD.md` reescrito con lo que hace la versión actual (correos HTML, captcha de Joomla, Engage, seguimientos, denuncias).
- Pruebas nuevas: `108-denunciar.php` (28) y `109-denunciar-navegador.js`; el resto añaden la casilla de derechos.

## 1.12.1 — 2026-10-14
- **Activar o desactivar los comentarios de tu viaje cuando quieras**: en la ficha de un viaje propio, junto a «Me gusta» y «Soy fan», sale el botón **«Activar comentarios»** (si los cerraste al subirlo) o **«Desactivar comentarios»**. Al activar aparece enseguida el hilo de Engage; al desactivar se conservan los comentarios ya escritos y vuelven si se reactivan. Los administradores (permiso «Editar estado») pueden hacerlo en cualquier viaje. Nadie más (mismo 404 que un viaje inexistente); si el ÁLBUM tiene los comentarios cerrados, el viaje tampoco los muestra. Queda anotado el cambio (solo el código).
- Pruebas nuevas: `107-comentarios-viaje.php` (32) y `104-comentarios-viaje-navegador.js` (9).

## 1.12.0 — 2026-10-14 (seguir a viajeros, correos en HTML)
- **Seguir a una persona**: botón «Seguir a NOMBRE» en la ficha de cada viaje ajeno y botón de seguir en cada fila de la lista de personas del mapa. En «Descubrir» hay una pestaña nueva **«Siguiendo»** con los viajes de la gente a quien sigues (los nuevos llevan la etiqueta «Nuevo») y un puntito en el menú cuando hay novedades sin ver. Nadie puede seguirse a sí mismo ni a quien no ha publicado nada; máximo 300 seguidos por persona; nadie ve quién le sigue.
- **Resumen semanal por correo (opcional, apagado de entrada)**: casilla «Resumen semanal por correo» en la pestaña «Siguiendo». Un correo a la semana como máximo, con los nombres y el número de viajes nuevos de quien sigues (nunca el texto de los viajes). Interruptor general del administrador en Opciones → Correos. Se manda cuando alguien abre el mapa (como máximo una vez cada 6 horas), sin necesidad de tareas programadas.
- **Todos los correos del componente, ahora en HTML y SIN enlaces**: tienen el logo del mapa incrustado en el propio correo (no se descarga de internet), versión en texto plano, y abajo el aviso de confidencialidad y el motivo por el que se recibe. Ningún correo contiene ya una dirección web (antes los avisos de publicación, el podio y el aviso al administrador llevaban una).
- Privacidad: la exportación de datos incluye a quién sigues y el borrado de una cuenta elimina sus seguimientos en los dos sentidos.
- Pruebas nuevas: `106-seguir.php` (40) y `103-seguir-navegador.js` (21); `90` y `95` ampliadas.

## 1.11.3 — 2026-10-14
- **Corregido: «Guardar», «Guardar y cerrar» y «Cancelar» del formulario de álbum no hacían nada.** Esa pantalla no cargaba el JavaScript de los botones. Revisadas todas las pantallas: Panel, Viajes, Álbumes, Edición de álbum, Ranking, Sorteo y Opciones.
- **Álbumes**: al quitar la barra de Joomla se habían perdido sus acciones de estado. Ahora cada tarjeta tiene Publicar / Despublicar / Papelera / Restaurar / Eliminar definitivamente (con ventana de confirmación propia) y hay filtros por estado con su recuento.
- La prueba del administrador (42 comprobaciones) hace clic de verdad en: los tres botones del formulario de álbum, el ciclo completo de un álbum nuevo (crear, papelera, restaurar, publicar, eliminar), «Cerrar semanas pendientes» del ranking y «Sortear».

## 1.11.2 — 2026-10-14
- **Mapas oscuros («Océano de noche» y «Plano técnico»): la pastilla «N álbumes · N países» bajo el logo se leía mal** (letra clara sobre fondo claro). Ahora es una pastilla oscura con letra clara. La prueba de contraste de la ficha comprueba también esa línea en los seis estilos.

## 1.11.1 — 2026-10-14
- **Corregido: ningún enlace del administrador funcionaba** (todos volvían al Panel). Los enlaces y las acciones de los formularios se escapaban DOS veces (`&amp;amp;`), de modo que el navegador perdía el parámetro `view` y `task`. Arreglados el menú lateral, las tarjetas y accesos del Panel, los filtros de Viajes, los botones de Ranking y Sorteo y la pantalla de Opciones. La prueba del administrador ahora **hace clic de verdad** en cada enlace y vigila que ningún enlace ni formulario lleve «amp;».

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
