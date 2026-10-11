# Comentarios de cada viaje

## Lo primero: Joomla no tiene comentarios
Joomla (5 y 6) **no incluye ningún sistema de comentarios** para artículos ni para nada más. Lo que existe son extensiones de terceros (Akeeba Engage y su fork Engage, JComments, Komento, Disqus y similares) que se cuelgan de Joomla de la misma manera, así que no hay «el gestor de comentarios de Joomla» al que enlazar: hay que hablar con cada extensión.

## Cómo se cuelgan las extensiones de comentarios
Todas siguen el mismo patrón, que Joomla define en los **eventos de contenido**:

1. Joomla prepara un elemento y lanza `onContentPrepare`, `onContentAfterTitle`, `onContentBeforeDisplay` y `onContentAfterDisplay`, cada uno con un **contexto** (en un artículo, `com_content.article`) y un objeto con al menos un `id`.
2. La extensión de comentarios es un plugin de contenido que escucha `onContentAfterDisplay` (y a veces `onContentBeforeDisplay` para el contador) y **devuelve el HTML** de los comentarios. Lo normal es que solo responda a su contexto conocido, `com_content.article`.
3. Para saber qué es ese elemento (título, enlace, acceso, si está publicado) la extensión consulta el elemento por su `id` en `#__content`, o, si es más moderna, por el **identificador de activo** (`asset_id`, la fila de `#__assets`) y pregunta a otros plugins qué es ese activo.

### Qué «cadenas» van en el artículo
Según lo que he podido comprobar (fuentes al final) cada sistema guarda la opción de activar o cerrar los comentarios de un elemento de una manera distinta:

| Sistema | Dónde guarda «comentarios sí/no» en el artículo | Confianza |
|---|---|---|
| Akeeba Engage / Engage | En los **atributos del artículo** (`attribs`, clave `engage.*`), ajustables por categoría; no hay texto en el cuerpo | Alta (leído en el código de Engage) |
| Komento | Etiquetas en el texto del artículo: `{KomentoDisable}`, `{KomentoEnable}`, `{KomentoLock}` | Media (respuestas del foro del fabricante, 2015–2018) |
| JComments | Ajustes por categoría y por elemento; se citan etiquetas `{jcomments on}` / `{jcomments off}` / `{jcomments lock}` en el texto | Baja (solo foros; en uno de ellos la etiqueta salía como texto plano) |

En un viaje **no hay artículo ni texto donde poner esas cadenas**, y es a propósito (ver abajo). Las decisiones «comentarios sí/no» las toma el propio componente: el álbum (mapa) y el viaje tienen su interruptor, y solo si los dos están en «sí» se llama a la extensión de comentarios.

## Qué hace Mapa de viajes
- **Un viaje no se hace pasar por artículo.** Si lo hiciera, una extensión que busca el artículo número *N* en `#__content` mostraría comentarios del artículo equivocado. En su lugar, cada viaje tiene su fila en `#__assets` llamada `com_mapaviajes.trip.<id>` y su propio contexto: `com_mapaviajes.trip`.
- Nada de crear 1000 artículos: hay una fila de activos por viaje, creada la primera vez que alguien abre sus comentarios, y se borra con el viaje junto con su hilo.
- El visor tiene un botón «Comentarios» que abre un panel con la página `index.php?option=com_mapaviajes&view=tripcomments&tmpl=component&id=<viaje>` (el mismo dominio, sin servicios externos). Solo usuarios registrados, solo viajes publicados de álbumes que el usuario puede ver. El enlace `…&album=A&trip=ID` abre el mapa directamente en ese viaje (lo usan los correos de Engage).
- **Opciones → Sistema de comentarios** (`comments_provider`):
  - **Ninguno** (por defecto).
  - **Akeeba Engage / Engage** — probado contra Engage 3.4.3 (ver abajo).
  - **Eventos de contenido de Joomla** — lanza la secuencia estándar con contexto `com_mapaviajes.trip` y un objeto con `id`, `asset_id`, `title`, `created_by`, `access`… Sirve a cualquier extensión que acepte otros contextos o que se configure para ellos.

### Engage
Dos piezas, ambas en el paquete `pkg_mapaviajes`:
- Con Engage 3.4.4 o posterior (contrato genérico de otros componentes): la vista lanza `onContentAfterDisplay` con el contexto `com_mapaviajes.trip`, un objeto que solo lleva el `asset_id` y un `Registry` vacío; Engage pregunta a nuestro plugin de contenido (`onAkeebaEngageGetAssetMeta`) qué es el elemento, quién puede verlo y si los comentarios están abiertos, y pinta el hilo. Requisitos: proveedor «Engage» en Opciones, plugin «Content – Engage» activo y permiso «Comentario» para quien comenta.
- `plg_content_mapaviajes` responde a `onAkeebaEngageGetAssetMeta` y `onAkeebaEngageGetAssetIDsByTitle` para los activos de los viajes: título, mapa, autor (alias salvo que actives los nombres reales), enlace, si está publicado, nivel de acceso y si los comentarios están abiertos. **No entrega el correo del autor.** Sin Engage instalado no hace nada.
- Con Engage hay que dejar que los usuarios registrados comenten: **Engage → Opciones → Permisos → Registrado → Crear**.
- Los avisos, la moderación, el antispam y las suscripciones los gestiona Engage; el enlace de vuelta del formulario se conserva porque usamos el parámetro `id`, que la lista blanca de Engage respeta.
- Engage solo trae tablas para MySQL/MariaDB. Las pruebas de este repositorio usan PostgreSQL con una copia del esquema (`tests/joomla-live/engage-pg.sql`, solo de pruebas).

### Lo que NO está hecho ni probado
- **JComments y Komento no se han instalado ni probado.** Quien los quiera tiene dos caminos: el proveedor «eventos» si su versión acepta otro contexto, o un adaptador propio de esa extensión (JComments usa un archivo `com_<componente>.plugin.php` en su carpeta de plugins con una clase que da título, enlace y propietario del elemento; Komento usa archivos de integración de su carpeta de plugins). Faltan ejemplos y documentación oficial verificable: no los he podido leer desde aquí.
- Disqus, Facebook Comments y similares **no son compatibles a propósito**: cargan scripts de terceros y rompen la regla «nada sale de tu servidor».

## Fuentes
- Código de Engage (`plugins/content/engage`): eventos `onContentAfterDisplay`, `onAkeebaEngageGetAssetMeta`, contexto `com_content.article`, parámetros en `attribs`.
- Joomla 6.1 `libraries/src/Event/Content/*` y `components/com_content/.../Article/HtmlView.php` (la secuencia de eventos).
- Foros de StackIdeas (Komento): etiquetas `{KomentoDisable}`/`{KomentoEnable}`/`{KomentoLock}`; el evento recomendado para componentes de terceros es `onContentAfterDisplay`; el archivo `komento_plugins/com_content.php` como ejemplo de integración.
- Foros de Joomlaforum.ru, HikaShop y Phoca (JComments): archivo `com_<componente>.plugin.php` con una clase que extiende `JCommentsPlugin` y define título y enlace.
- Hilo 20178 del tracker de Joomla (2018): propuesta de comentarios en el núcleo, sin adoptar.
