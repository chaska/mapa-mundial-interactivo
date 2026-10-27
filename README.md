# Mapa de viajes para Joomla 5 y 6

Un mapa mundi interactivo y propio donde los usuarios registrados de tu web comparten álbumes de fotos de sus viajes. Todo se procesa y se guarda en tu servidor: ni el mapa, ni las tipografías, ni la búsqueda de lugares hablan con terceros, así que la IP de tus usuarios no sale de tu web.

- **Mapa**: seis estilos (pergamino viajero y otros) en SVG, chinchetas 3D agrupadas por país que se separan al acercar, con Canarias, Baleares, Madeira, Azores y la Antártida. Al acercarte a un álbum, su foto cuelga como una polaroid sujeta por la chincheta.
- **Fotos**: de 1 a 5 por álbum con pie de foto (256 palabras), también desde el móvil y en **HEIC** (iPhone, Xiaomi): se leen en el propio teléfono y el servidor nunca abre un HEIC. Se guardan en WebP, sin datos EXIF, en una carpeta privada servida solo con permiso. Visor a pantalla completa con teclado y deslizar con el dedo.
- **Amigos y fotos «solo amigos»**: solicitudes de amistad con aviso por correo, bloqueo, y cada foto puede ser pública o solo para amigos (siempre queda una pública como miniatura). El servidor no entrega nada privado a quien no es amigo.
- **Podio semanal** en estilo cómic (semana, semana pasada y General), con sorteo entre participantes y avisos por correo.
- **Moderación**: los álbumes y las fotos nuevas pueden esperar aprobación; botón **Denunciar**, permiso propio «Moderar en el mapa» y panel de administración con interruptores que se guardan solos.
- **Fotos de perfil** (opcional, con el componente MiFoto): en el podio, los amigos y los viajes, con interruptor del administrador.
- **Códigos para artículos**: `{mapaviajes stats}`, `{mapaviajes albums}`, `{mapaviajes countries}` y `{mapaviajes photos}` ponen los totales en cualquier artículo de Joomla ([`docs/CODIGOS-ARTICULO.md`](docs/CODIGOS-ARTICULO.md)).
- **Privacidad (RGPD)**: sin cookies ni analítica propias, herramientas de acceso y borrado de datos de Joomla, y un texto base en [`docs/PRIVACIDAD.md`](docs/PRIVACIDAD.md).

Requisitos: Joomla **5.4.9 / 6.1.4 o superior** (el instalador se niega en versiones con fallos conocidos), PHP 8.1+ con GD (WebP), fileinfo e intl; MySQL/MariaDB o PostgreSQL. Probado en Joomla 6.1.4 con PostgreSQL y en Joomla 6.2.0 con MariaDB 11.8.9.

## Instalar
Descarga `pkg_mapaviajes-<versión>.zip` de la página de [Releases](../../releases), o genéralo tú:
```
php build/build.php          # genera dist/pkg_mapaviajes-<versión>.zip
```
Instala el ZIP desde *Sistema → Instalar → Extensiones* (el paquete incluye el componente y sus plugins). Crea un elemento de menú «Mapa de viajes» (o enlaza a `index.php?option=com_mapaviajes`). Tras instalar, los usuarios registrados pueden añadir viajes y los grupos Administrator y Super Users moderan; ajústalo en *Opciones → Permisos*.

**Hazlo bien desde el primer día** (detalle en [SECURITY.md](SECURITY.md)): pon la carpeta privada de fotos *fuera* de la web (Opciones → «Carpeta privada de fotos»), desactiva los plugins *Web Services* y *API Authentication* si no usas la API REST, y mantén Joomla actualizado.

## Estado
Versión actual: ver [CHANGELOG.md](CHANGELOG.md). Seguridad, auditorías y pruebas: [SECURITY.md](SECURITY.md), [`docs/AUDITORIA-SEGURIDAD-1.18.md`](docs/AUDITORIA-SEGURIDAD-1.18.md) y [tests/LEEME.md](tests/LEEME.md). Licencia: **GNU GPL v3 o posterior** (archivo [LICENSE](LICENSE)); terceros incluidos y sus licencias en [NOTICE.md](NOTICE.md).

## Comentarios
Cada viaje puede tener comentarios a través de otra extensión (Engage probado; eventos de contenido de Joomla para otras). Joomla no trae comentarios propios. Cómo funciona, qué cadenas usa cada sistema y qué está probado: [`docs/COMENTARIOS.md`](docs/COMENTARIOS.md).

## Estilos del mapa
Cómo añadir o cambiar un estilo: [`docs/ESTILOS-MAPA.md`](docs/ESTILOS-MAPA.md).
