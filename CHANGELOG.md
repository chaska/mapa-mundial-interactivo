# Registro de cambios

## 1.1.0 — 2026-10-12
- Búsqueda de lugares con **todas las ciudades del mundo** (~127 000, **con nombres en español**: Londres, Nueva York, Roma, El Cairo…; datos de GeoNames/Wikidata vía Countries States Cities Database, ODbL; crédito visible en el formulario): archivo propio en tu servidor, búsqueda binaria por prefijo (<1 ms), sin consultar a nadie. Pinchar en el mapa propone la ciudad más cercana. Solo usuarios registrados, solo GET, entrada validada. Pruebas: 28 en PHP (incluye que ningún nombre de lugar sea rechazado por el saneador) y 27 en vivo.
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
