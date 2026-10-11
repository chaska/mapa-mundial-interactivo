# Registro de cambios

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
