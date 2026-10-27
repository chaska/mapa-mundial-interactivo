# Privacidad y cumplimiento (pre-auditoría técnica, UE + España)

> Esto es una pre-auditoría técnica del componente (versión 1.13.0). **No sustituye el asesoramiento de un abogado o DPD.** Habla de lo que el código hace; qué obligaciones tiene *tu* sitio depende de quién eres y de cómo lo uses. El informe completo está en `docs/AUDITORIA-LEGAL-mapa-viajes-2026-10-15.md`.

## 1. Quién es quién
- El componente es **software** que instalas en tu Joomla. **Tú (quien explota el sitio) eres el responsable del tratamiento** (RGPD art. 4.7). Nada se envía al autor del componente ni a ningún tercero.
- Si alojas el mapa para otra organización, tú eres encargado y necesitas el contrato del art. 28 con ella.
- El mapa solo se ve con sesión iniciada: no hay visitantes anónimos que vean fotos.

## 2. Lo que hace el código (hechos comprobados)
| Hecho | Dónde |
|---|---|
| **Ningún recurso de terceros** en el navegador: sin Google Fonts, sin CDNs, sin mapas externos, sin analítica. Fuentes, mapa y ciudades están en tu servidor; la compilación **rechaza** URLs externas en CSS, JS o PHP | `media/css/fonts.css`, `build/build.php` |
| **Sin cookies propias** (solo la sesión de Joomla). En el navegador solo guarda la preferencia de estilo de mapa (`localStorage` `mvMapStyle`) si la persona elige uno | `media/js/mapa.js` |
| **IP no guardada por el componente**: el registro de seguridad guarda códigos de suceso y el id de usuario; se poda a los 90 días | `EventLog` |
| **Fotos**: se recodifican a WebP y se elimina todo lo que traigan (EXIF, GPS, XMP). Nombre de archivo aleatorio; solo las sirve el componente tras comprobar permisos | `ImageProcessor`, `PhotoServer` |
| **Declaración al subir**: casilla obligatoria de que las fotos son suyas o tiene permiso, que las personas que salen están de acuerdo y que no se ve ningún menor identificable | formulario del mapa, `TripSubmission` |
| **Denunciar**: cualquier persona registrada puede avisar de un viaje; una denuncia por persona y viaje; los moderadores reciben correo y la gestionan en el administrador («Denunciados»). Guarda: viaje, quien denuncia, motivo, nota (500 caracteres) y fecha | `ReportService` |
| **Fotos «solo amigos»**: el servidor las sirve solo a su autor/a, a sus amigos aceptados y a los moderadores (superadministradores y quien tenga el permiso «Moderar en el mapa», por defecto el grupo Administrator, para revisar spam o incumplimientos); a cualquier otra persona responde 404 y el mapa solo le dice cuántas hay. Pueden añadirse fotos después de publicar; las nuevas esperan a un moderador si la revisión previa está activada | `PhotoServer`, `TripRepository`, `PhotoEditor` |
| **Amigos y bloqueos**: una solicitud (aviso por correo sin enlaces) que la otra persona acepta; cualquiera puede quitar la amistad. Nadie ve las amistades de otra persona | `FriendService` |
| **Nombre visible**: el alias (nombre de usuario) salvo que actives «nombre real» | Opciones → Privacidad |
| **Ranking**: se muestra el alias; **aparecen todas las personas con viajes publicados** (no hay interruptor para ocultarse; si quieres ofrecer esa oposición, dilo en tu política y gestiona las peticiones a mano) | `RankingService` |
| **Seguir a viajeros**: se guarda a quién sigue cada persona (nadie ve quién le sigue) | `FollowService` |
| **Correos** (todos en HTML, sin enlaces, con el logo incrustado, el motivo por el que se reciben, el remitente que configures y un aviso de confidencialidad): aviso de publicación o no publicación de su viaje; felicitación del podio (si la activas); aviso a moderadores; y el **resumen semanal opcional** de quien sigue, que solo recibe quien activa la casilla (opt-in) | `Notifier` |
| **Sorteo**: lista de personas con nombre y correo, solo para moderadores; el sorteo queda anotado sin datos personales | `RaffleService` |
| **Acceso y supresión** (RGPD arts. 15 y 17): herramientas de Privacidad de Joomla (plugin `privacy`) y borrado de cuenta (plugin `user`): viajes, fotos (archivos incluidos), reacciones, ranking, ajustes, seguimientos en los dos sentidos, denuncias enviadas y comentarios de sus viajes | `UserData` |

## 3. Terceros y extensiones que NO son este componente (decídelo tú)
| Qué | Qué recibe | Qué hacer |
|---|---|---|
| **Captcha de Joomla** (p. ej. reCAPTCHA v3) | Con reCAPTCHA, la **IP y datos del navegador van a Google**. «Proof of Work» y otros captchas locales no envían nada fuera | Prefiere uno local; si mantienes reCAPTCHA, cítalo en tu política y valora el consentimiento |
| **Comentarios (Engage)** | Guarda texto, usuario y puede guardar IP y navegador de quien comenta. Gravatar solo se carga tras consentimiento (modo «preguntar») | Cítalo en tu política con su plazo de conservación |
| **Fotos de perfil (MiFoto)**, si lo instalas | Una foto de una persona es dato personal. El mapa solo muestra la dirección que MiFoto le da a quien mira (con su permiso) y no guarda nada; la foto y su consentimiento viven en MiFoto | Cítalo en tu política: quién la ve (usuarios con sesión), cómo se borra y dónde se denuncia. El interruptor «Mostrar las fotos de perfil» del mapa la apaga en todo el mapa |
| **Servidor de correo** | Ve los correos | Tu proveedor |
| **Copias de seguridad** | Contienen las fotos hasta que roten | Define la rotación |

## 4. Inventario de datos personales
| Dato | Finalidad | Dónde | Conservación |
|---|---|---|---|
| Viajes (título, lugar, coordenadas, año) | Publicar en el mapa | `#__mapaviajes_trips` | Hasta que ella o la administración lo borre, o se borre la cuenta |
| Fotos y pies de foto | Publicar | `media/com_mapaviajes_private/photos` + `#__mapaviajes_photos` | Ídem |
| Reacciones | Contadores y favoritos | `#__mapaviajes_reactions` | Hasta borrar la cuenta |
| Puntos del ranking | Podio | `#__mapaviajes_ranking` | Hasta borrar la cuenta |
| Preferencias (resumen semanal) | Respetar su elección | `#__mapaviajes_prefs` | Hasta borrar la cuenta |
| A quién sigue | Avisos y pestaña «Siguiendo» | `#__mapaviajes_follows` | Hasta dejar de seguir o borrar la cuenta (en ambos sentidos) |
| Amistades y solicitudes | Dar acceso a las fotos «solo amigos» | `#__mapaviajes_friends` | Hasta que cualquiera de las dos personas la quite, o se borre una cuenta (en ambos sentidos) |
| Bloqueos | Evitar solicitudes y acceso | `#__mapaviajes_blocks` | Hasta desbloquear o borrar la cuenta |
| Denuncias enviadas | Moderación | `#__mapaviajes_reports` | Hasta que el moderador las descarte, se borre el viaje o la cuenta |
| Códigos de suceso | Seguridad | `#__mapaviajes_events` | 90 días; al borrar la cuenta se desvincula |
| Nombre y correo de la cuenta | Identificar, avisar | Joomla | Joomla |

## 5. Matriz de aplicabilidad (resumen)
| Norma | ¿Aplica? | Nota |
|---|---|---|
| RGPD / LOPDGDD | Sí, si hay usuarios en la UE | Responsable: el sitio |
| ePrivacy 5.3 / LSSI 22.2 | Solo almacenamiento funcional (preferencia de mapa y sesión) | Sin aviso de cookies propio por este componente |
| Google Fonts (LG München I, 20/01/2022) | No aplica | La compilación impide que vuelva |
| LSSI 21 (comunicaciones comerciales) | Probablemente no: el resumen es un servicio que la persona pide | Con baja desde el mapa. Confírmalo |
| DSA (alojamiento) | Sí | Hay botón de denuncia, motivo guardado y moderación. Falta tu **correo de contacto** (Opciones) y las condiciones |
| Menores | **Tu decisión**: el alta es la de Joomla. Con menores de 14 años (LOPDGDD art. 7) el consentimiento no lo da el menor | Añade aviso de edad mínima en el registro |
| Consumo, IA, CRA, NIS2, EAA, VeriFactu | No aplican a este componente | — |

## 6. El sorteo — qué comprobar antes de usarlo
1. **Bases públicas**: cómo participar, fechas, premio, edad mínima, cómo se elige y se avisa a quien gana.
2. **Informar antes** de que nombre y correo se usarán para el sorteo y cuánto tiempo (RGPD art. 13).
3. **Minimizar**: descarga el CSV solo si lo necesitas y bórralo al terminar.
4. **Régimen legal**: comprueba en el BOE, la DGOJ, la Agencia Tributaria y tu comunidad autónoma, o con un abogado, si hay obligaciones fiscales o de autorización (no se ha verificado en fuentes oficiales).
5. Participar en el sorteo no depende de aparecer en el ranking (todo el mundo aparece): dilo en las bases.

## 7. Lo que te falta (documentos y ajustes, no código)
- Política de privacidad que cite este tratamiento, el captcha y Engage (finalidad, base, conservación, derechos).
- Aviso legal con los datos del prestador (LSSI art. 10).
- **Opciones → Correos**: rellenar «Correo de contacto para avisos» y «Quién envía los correos».
- Aviso de edad mínima (14 años) en el registro de Joomla.
- Bases del sorteo; contrato de encargo si lo alojas para otros; protocolo de brechas (AEPD en 72 horas); registro de actividades si procede.

## 8. Lo que NO está cubierto
Edad real de los usuarios, contenido ilegal subido por usuarios (hay moderación y denuncia, no detección automática), los comentarios (los trata la extensión que elijas), las copias de seguridad del servidor y los correos (tu proveedor los ve).

## Fuentes consultadas (a 15/10/2026)
RGPD arts. 4, 5, 6, 13, 15, 17, 21, 28, 30, 32-34; LOPDGDD art. 7; LSSI arts. 10, 21 y 22; DSA arts. 16, 17 y 19; LG München I 20/01/2022 (3 O 17493/20).
