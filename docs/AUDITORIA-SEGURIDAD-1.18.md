# Auditoría de seguridad de lo nuevo en 1.16 – 1.18 (cerrada en 1.18.1)

Fecha: 2026-10-27. Alcance: código nuevo desde 1.15.2 (miniaturas, códigos de artículo, HEIC en el navegador, podio, fotos de perfil MiFoto) y, donde lo pedía el tema, el código de siempre que lo rodea. Método: seis revisiones independientes de solo lectura (fotos y amigos · ranking, plugin y fotos de perfil · JavaScript y HEIC · instalación, base de datos, empaquetado y privacidad · revisión de código · revisión del diff con las reglas de `security-review`), más las listas de las skills `security-audit` y `seguridad-zero-trust` y la batería de pruebas en vivo. Cada arreglo de seguridad se vio en rojo sin el arreglo y en verde con él. Sin tocar el servidor real. No es la auditoría completa de seis fases de `security-audit` (sin sandbox de ejecución ni verificación independiente de cada registro).

## Resumen
- **Sin hallazgos críticos.** Un hallazgo **medio** y seis **bajos**, todos corregidos en 1.18.1. Algunas decisiones quedan para ti (abajo).
- El control central (quién puede ver cada foto y miniatura) aguantó todos los caminos revisados.

## Corregidos
| ID | Gravedad | Qué era | Arreglo |
|---|---|---|---|
| A1 | **Media** | «Moderar» dependía de «Editar estado» / «Eliminar», que Joomla hereda de serie a los grupos Publisher y Manager: cualquier persona de esos grupos veía TODAS las fotos «solo amigos» y pendientes, podía borrar viajes ajenos y cerrar sus comentarios. | Permiso propio `mapaviajes.moderate` (Administrator por defecto; Superusuarios siempre). Visto en rojo/verde (prueba 113). |
| A2 | Baja | Bloquear → desbloquear → pedir amistad saltaba la espera de 30 días tras un rechazo y volvía a mandar correo. | El bloqueo conserva la fila de rechazada/retirada. Rojo/verde (113). |
| A3 | Baja | Denunciar servía para sondear números de viaje de mapas que no ves. | Solo se denuncia lo que se puede ver. Prueba 108. |
| A4 | Baja | El CSS propio permitía `@import` y `url()` remotos (y escapes de CSS): todo visitante llamaba a otra web. | Se eliminan. Prueba 110. |
| A5 | Baja | Los viajes de un mapa despublicado puntuaban en el ranking. | Solo puntúan los de mapas publicados. |
| A6 | Baja | Fallos silenciosos de MiFoto y del borrado de datos al eliminar una cuenta. | Quedan en el registro de Joomla. «Descartar denuncias» queda anotado. |
| A7 | Baja | El lector HEIC incluido no tenía candado. | Hash SHA-256 fijado en el empaquetador. |

## Decisiones tuyas (no cambian el código sin que lo digas)
1. **Ranking sin opción de ocultarse**: el interruptor «no aparecer» se retiró antes y todas las personas con viajes publicados salen con alias (o nombre real si lo activas). La política de privacidad debe decirlo y tramitar a mano a quien se oponga (RGPD art. 21). Los textos del repositorio ya no prometen el interruptor.
2. **Cierre de la semana al acabar** (decisión tuya): un viaje o foto que se aprueba en lunes ya no cuenta en la semana de su creación ni en la siguiente. Con «revisar antes de publicar» activo, la autora pierde esos puntos. Alternativa: dar 1 día de margen.
3. **Códigos de artículo** enseñan totales a cualquiera aunque el mapa exija sesión y aunque el mapa tenga acceso «Registrado»: es lo que pediste. Si prefieres solo los de acceso público, dime y se filtra (ojo: si tus mapas son «Registrado», el contador saldría a 0).
4. **Nombre de usuario visible** cuando «Mostrar el nombre real» está apagado: es el login de Joomla. Si en tu sitio el login es el correo, se enseña a todo usuario con sesión. Conviene que el login no sea el correo, o activar el nombre real.
5. **Moderación tras actualizar**: al actualizar a 1.18.1 solo moderan Superusuarios, el grupo Administrator y lo que ya tuvieras marcado explícitamente en «Editar estado». Revísalo en Opciones → Permisos.

## Pendiente conocido, sin cambio (bajo)
- Miniaturas en caché del navegador 10 minutos tras quitar a alguien de amigos.
- El borrado de datos de una cuenta no es atómico (se registra si falla; no hay limpieza de huérfanos). Al desinstalar el componente las fotos se conservan a propósito.
- La exportación de datos incluye metadatos de fotos, no los archivos.
- Los ganadores del sorteo (nombre y correo) viven en la sesión del administrador hasta que caduca.
- `storage_path` y `custom_css` los puede cambiar quien tenga «Opciones» sin ser superadministrador.
- El instalador solo acepta Joomla ≥ 5.4.9 / 6.1.4: un sitio más viejo no puede instalar ni las correcciones.
- El SQL de MySQL/MariaDB se ha probado en Joomla 6.2.0 con MariaDB 11.8.9 (instalación manual por el propietario); las pruebas automáticas siguen siendo solo de PostgreSQL.
- El empaquetador es un candado simple (cadenas literales), no un analizador.
- Un bloqueado puede deducir que le bloquearon por la respuesta a su petición (matiz de privacidad).

Esto es una revisión técnica de código y pruebas locales. No sustituye una prueba de intrusión del servidor real ni el asesoramiento de un abogado o DPD.
