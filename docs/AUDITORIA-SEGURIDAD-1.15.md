# Auditoría de seguridad de lo nuevo en 1.12 – 1.15 (amigos, fotos «solo amigos», edición, denuncias, CSS propio)

Fecha: 2026-10-25. Alcance: código nuevo desde la auditoría 1.8.7 (`FriendService`, `FriendController`, `PhotoEditor`, `TripController::photos|report`, `PhotoController`/`TripRepository::forMap`, `ReportService`, `Settings::customCss|menuExtras`, parser de coordenadas, plugin de privacidad). Método: revisión de fuente con las skills `security-audit` (pasada enfocada, sin pasar de lo local) y `seguridad-zero-trust`, más la batería de pruebas en vivo. Sin tocar el servidor real.

## Resumen
- **Sin hallazgos críticos ni altos.** La frontera principal (quien no es amigo no obtiene fotos «solo amigos») se aplica en el servidor en los dos caminos: la foto (`photo.view`, 404) y la lista del mapa (no incluye ni el id ni el pie de las privadas, solo cuántas hay).
- **3 hallazgos medios/bajos, ya corregidos en 1.15.2** (abajo) y **4 observaciones** sin cambio de código.

## Hallazgos corregidos

| ID | Gravedad | Qué era | Arreglo |
|---|---|---|---|
| S1 | Media (acoso) | Rechazar o retirar una solicitud de amistad **borraba** la fila, así que la misma persona podía volver a pedirla al instante y cada vez salía un correo a la víctima (hasta 10 al día por cuenta, multiplicable por cuentas). | Rechazada = fila conservada 30 días (404 neutro al volver a pedir); retirada = no se vuelve a mandar correo en 7 días. Una solicitud rechazada ya no se convierte en amistad si luego la pide la otra persona. |
| S2 | Media (saltarse la revisión) | Al editar un álbum ya publicado se podía enviar un nombre de lugar escrito a mano (peticiones falsificadas, no la pantalla normal) y publicarlo sin revisión. | El nombre del lugar solo se acepta sin revisión si es uno que el propio mapa genera (punto elegido, coordenadas) o una ciudad de nuestro fichero de lugares cerca de esas coordenadas. Cualquier otro texto devuelve el álbum a revisión (igual que un título nuevo). |
| S3 | Baja (integridad) | Dos guardados simultáneos de la edición de fotos podían dejar un álbum sin ninguna foto pública (se validaba antes del bloqueo). Solo afecta al propio autor, pero rompe la miniatura que ve todo el mundo. | Las reglas (máximo, al menos una foto, al menos una pública) se vuelven a comprobar con el bloqueo de la persona ya cogido. |

## Revisado sin hallazgo
- **Permisos de fotos**: autor, moderadores (`core.admin` o `core.edit.state`) y amigos aceptados. Un bloqueo borra la amistad y corta el acceso al instante; la caché del navegador siempre revalida (`private, no-cache`).
- **Puertas de escritura**: todos los endpoints nuevos exigen POST + `X-Requested-With` + token + sesión; la propiedad del álbum/foto se saca de la sesión, nunca del cuerpo; los ids desconocidos devuelven 404 y las claves de personas son opacas (HMAC).
- **Inyección**: todas las consultas usan parámetros o enteros; el texto de nombres, pies, notas y avisos entra en pantalla con `textContent`/escapado; el asunto de los correos no admite saltos de línea.
- **CSS propio y entradas de menú** (solo las configura un administrador): sin `<` ni `>` no puede cerrar la etiqueta; los enlaces de menú solo admiten `http(s)://` o una ruta local, nunca `javascript:`/`data:`.
- **Coordenadas**: el navegador solo extrae dos números; el servidor los vuelve a validar. Texto de 20.000 caracteres: menos de 20 ms.
- **Abuso de recursos**: subida de fotos limitada a 15 peticiones/hora por persona y contada antes de decodificar; solicitudes de amistad a 10 al día y 20 pendientes; denuncias a 10 por hora y una por viaje.

## Observaciones (no cambian el código)
1. **El ranking público muestra cuántas fotos tiene una persona, incluidas las «solo amigos»** (solo el número). Es informativo; si quieres, se puede contar solo las públicas en lo que se enseña.
2. **Los moderadores ven todo por diseño**: cualquier grupo con el permiso «Editar estado» del componente verá las fotos «solo amigos». Revisa quién lo tiene.
3. **La exportación de datos** de la ficha de privacidad muestra el id interno de Joomla de tus amigos y bloqueados (mismo criterio que los seguimientos).
4. **El captcha y los comentarios** (Joomla y Engage) quedan fuera de este componente; su revisión es la de esos plugins.

Esto es una revisión técnica de código y pruebas locales. No sustituye una prueba de intrusión del servidor real.
