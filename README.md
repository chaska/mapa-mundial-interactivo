# Mapa de viajes para Joomla 6

Un mapa mundi interactivo y propio donde los usuarios registrados de tu web comparten álbumes de fotos de sus viajes. Todo se procesa y se guarda en tu servidor: ni el mapa, ni las tipografías, ni la búsqueda de lugares hablan con terceros, así que la IP de tus usuarios no sale de tu web.

- **Mapa**: pergamino viajero en SVG, chinchetas 3D agrupadas por país (un «27» sobre España) que se separan al acercar. Al acercarte a un álbum, su foto cuelga como una polaroid ladeada sujeta por la chincheta.
- **Fotos**: de 1 a 5 por álbum con pie de foto (256 palabras). Se convierten a WebP con el lado largo a 1000 px sin deformarlas. Visor a pantalla completa con `<` `>`, teclado y deslizar con el dedo.
- **Formulario**: pensado para el móvil. Busca un lugar o pincha en el mapa; las fotos se reducen en el teléfono antes de subirlas.
- **Administración**: publicaciones (publicar, despublicar, ocultar, spam, papelera, eliminar), álbumes del mapa, Opciones con interruptores y aviso por correo.

Requisitos: Joomla **5.4.9 / 6.1.4 o superior** (el instalador se niega en versiones con fallos conocidos), PHP 8.1+ con GD (WebP), fileinfo e intl; MySQL/MariaDB o PostgreSQL.

## Instalar
```
php build/build.php          # genera dist/com_mapaviajes-1.0.0.zip
```
Instala el ZIP desde *Sistema → Instalar → Extensiones*. Crea un elemento de menú «Mapa de viajes» (o enlaza a `index.php?option=com_mapaviajes`). Tras instalar, los usuarios registrados pueden añadir viajes; ajústalo en *Opciones → Permisos*.

**Hazlo bien desde el primer día** (detalle en [SECURITY.md](SECURITY.md)): pon la carpeta privada de fotos *fuera* de la web (Opciones → «Carpeta privada de fotos»), desactiva los plugins *Web Services* y *API Authentication* si no usas la API REST, y mantén Joomla actualizado.

## Estado
Versión 1.0.0: mapa, formulario, subida segura y moderación. Lo que falta está listado en [CHANGELOG.md](CHANGELOG.md). Seguridad y pruebas: [SECURITY.md](SECURITY.md) y [tests/LEEME.md](tests/LEEME.md). Licencia: GPL v3 o posterior, ver [NOTICE.md](NOTICE.md).

Los bocetos de diseño están en `boceto/` (HTML autocontenido: `mapa.html` y `admin-panel.html`).
