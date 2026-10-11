# Privacidad y cumplimiento (pre-auditoría técnica, UE + España)

> Esto es una pre-auditoría técnica del componente. **No sustituye el asesoramiento de un abogado o DPD.** Habla de lo que el código hace; qué obligaciones tiene *tu* sitio depende de quién eres y de cómo lo uses.

## 1. Quién es quién
- El componente es **software** que instalas en tu Joomla. **Tú (quien explota el sitio) eres el responsable del tratamiento** (RGPD art. 4.7). Nada se envía al autor del componente ni a ningún tercero.
- Si alojas el mapa para otra organización, tú eres encargado y necesitas el contrato del art. 28 con ella. Eso no se puede resolver con código.

## 2. Lo que hace el código (hechos comprobados)
| Hecho | Dónde | Estado |
|---|---|---|
| **Ningún recurso de terceros** en el navegador: sin Google Fonts, sin CDNs, sin mapas externos, sin captcha, sin Gravatar, sin analítica. Las fuentes, el mapa del mundo y las ciudades están en tu servidor. | `media/css/fonts.css`, `media/data/*`; la compilación **rechaza** cualquier URL externa en CSS, JS o PHP (`build/build.php`) | Bien. Sin avisos de cookies propios porque no hay cookies de terceros |
| **Sin cookies propias**: solo la sesión de Joomla (estrictamente necesaria) | — | Bien |
| **IP no guardada**: el registro de seguridad guarda códigos de suceso y el id de usuario, nunca IP ni cabeceras | `EventLog` | Bien. Se poda a los 90 días |
| **Fotos**: se recodifican a WebP; **se elimina todo lo que traiga la foto** (EXIF, GPS, XMP, comentarios). El nombre del archivo es aleatorio | `ImageProcessor`, `PrivateStorage` | Bien (minimización) |
| Las fotos solo las sirve el componente tras comprobar permisos (no hay URL pública adivinable) | `PhotoServer` | Bien |
| **Nombre visible = nombre de usuario** (alias) salvo que actives «nombre real» | Opciones → Privacidad | Por defecto, alias |
| **Búsqueda de lugares**: la consulta se resuelve en tu servidor y no se guarda | `PlaceSearch` | Bien |
| **Ranking**: se muestra el alias; cada persona puede **no aparecer** (interruptor en el podio) | `RankingService`, `#__mapaviajes_prefs` | Bien |
| **Correos a personas**: solo el aviso de publicación o no publicación de su viaje (y, si lo enciendes, la felicitación del podio). Texto plano, sin datos de otras personas | `Notifier` | Revisar con tus textos legales |
| **Sorteo**: lista de personas con viajes con fotos; contiene nombre y correo; solo para moderadores; la descarga y el sorteo quedan en el registro **sin datos personales** | `RaffleService` | Ver §5 |
| **Acceso y supresión** (RGPD arts. 15 y 17): herramientas de Privacidad de Joomla (plugin `privacy`) y borrado de cuenta (plugin `user`): se borran viajes, fotos (archivos incluidos), reacciones, filas de ranking, ajustes y el hilo de comentarios de sus viajes | `UserData` | Bien, con pruebas |

## 3. Inventario de datos personales
| Dato | Origen | Finalidad | Dónde | Conservación |
|---|---|---|---|---|
| Viajes: título, lugar, coordenadas, fecha | La persona | Publicar en el mapa | `#__mapaviajes_trips` | Hasta que ella o la administración lo borre, o se borre la cuenta |
| Fotos y pies de foto | La persona | Publicar en el mapa | Archivos `media/com_mapaviajes_private/photos` + `#__mapaviajes_photos` | Ídem |
| Reacciones (corazón, estrella) | La persona | Mostrar contadores y favoritos | `#__mapaviajes_reactions` | Hasta borrar la cuenta |
| Puntos del ranking semanal | Calculado | Podio | `#__mapaviajes_ranking` | Hasta borrar la cuenta |
| Preferencia «no aparecer» | La persona | Respetar su elección | `#__mapaviajes_prefs` | Hasta borrar la cuenta |
| Códigos de suceso (límites, rechazos, sorteo) | Sistema | Seguridad | `#__mapaviajes_events` | 90 días; al borrar la cuenta se desvincula |
| Comentarios | La persona | Conversación | Los guarda la extensión de comentarios (p. ej. Engage) | Según esa extensión |
| Nombre y correo de la cuenta | Joomla | Identificar, avisar | Joomla | Joomla |

## 4. Matriz de aplicabilidad (resumen)
| Norma | ¿Aplica? | Nota |
|---|---|---|
| RGPD / LOPDGDD | Sí, si hay usuarios en la UE | Responsable: el sitio. Base: ejecución del servicio pedido (publicar) y consentimiento para lo opcional |
| ePrivacy art. 5.3 / LSSI art. 22.2 | No hay cookies no esenciales ni almacenamiento propio en el navegador | Sin aviso de cookies propio necesario por este componente (tu sitio puede tener otros) |
| Google Fonts (LG München I, 20/01/2022) | **No aplica**: no se carga nada remoto | El candado de compilación impide que vuelva a entrar |
| DSA | Alojas contenido de terceros (fotos) | Si eres microempresa hay exenciones; en cualquier caso conviene un punto de contacto visible y una vía para avisar de contenido ilegal. **No resuelto por el código**: el administrador puede ocultar o borrar en un clic |
| Menores | **Pendiente de tu decisión**: el formulario de alta es el de Joomla. Si tu sitio admite menores de 14 años (LOPDGDD art. 7), el consentimiento no puede darlo el menor | No hay verificación de edad |
| Consumo, IA, CRA, NIS2, EAA, VeriFactu | No aplican a este componente tal como está | — |

## 5. El sorteo — qué comprobar antes de usarlo
El código solo ofrece la **lista** y un **sorteo al azar** con una fuente de aleatoriedad del sistema, sin repetir personas, y deja constancia sin datos personales. Lo demás es del organizador:
1. **Bases públicas** y una forma clara de participar (aquí: publicar viajes con fotos). Edad mínima, fechas, premio, cómo se elige y cómo se avisa a quien gana.
2. **Informar antes** a quien participa de que su nombre y correo se usarán para el sorteo (RGPD art. 13) y de cuánto tiempo se guardan.
3. **Minimizar**: descarga el CSV solo si lo necesitas, bórralo al terminar.
4. **Régimen legal del sorteo**: según fuentes secundarias consultadas (no oficiales), los sorteos promocionales sin pago ni sobreprecio quedan fuera de la Ley 13/2011 (art. 2.2) y no necesitan autorización estatal; si el premio es de cierto valor puede haber **obligaciones fiscales** (retenciones del IRPF al ganador, impuestos sobre actividades de juego). **Compruébalo en el BOE, la DGOJ, la Agencia Tributaria y tu comunidad autónoma, o con un abogado**: no lo he podido verificar en fuentes oficiales.
5. Las cuentas bloqueadas no participan; las personas que piden **no aparecer en el ranking** sí pueden participar en el sorteo (son cosas distintas): dilo en las bases.

## 6. Lo que te falta (documentos, no código)
- Política de privacidad que cite este tratamiento (finalidad, base, conservación, derechos y cómo ejercerlos: pide el acceso o el borrado desde las herramientas de Privacidad de Joomla).
- Aviso legal con los datos del prestador (LSSI art. 10).
- Texto de las bases del sorteo.
- Un punto de contacto para avisos de contenido (DSA art. 16, si te alcanza).
- Contrato de encargo (art. 28) si lo alojas para otros.
- Protocolo de brechas (avisar a la AEPD en 72 horas, arts. 33-34).
- Registro de actividades del tratamiento (art. 30) si procede por tamaño.

## 7. Lo que NO está cubierto
Edad de los usuarios, contenido ilegal subido por usuarios (hay moderación, no detección automática), los comentarios (los trata la extensión que elijas), copias de seguridad de tu servidor (contienen las fotos hasta que roten) y los correos del servidor (tu proveedor de correo los ve).

## Fuentes consultadas (a 13/10/2026)
RGPD arts. 4, 5, 6, 13, 15, 17, 28, 30, 32-34; LOPDGDD art. 7; LSSI art. 10 y 22; LG München I 20/01/2022 (3 O 17493/20); búsqueda sobre sorteos promocionales (fuentes secundarias, sin confirmar en el BOE).
