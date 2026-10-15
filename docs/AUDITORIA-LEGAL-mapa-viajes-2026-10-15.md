# Pre-auditoría legal (UE + España) — Mapa de viajes 1.12.1

Fecha: 2026-10-15. Alcance: componente `com_mapaviajes` y paquete `pkg_mapaviajes`. Primera pasada: **solo auditoría, sin cambios de código.**
Esto es una pre-auditoría técnica. No sustituye el asesoramiento de un abogado o DPD.

## 1. Resumen

El vídeo habla sobre todo de **leyes de EE. UU.** (COPPA, wiretap de California, CAN-SPAM, DMCA, renovaciones de Stripe en California). Si el sitio opera desde España para público español/UE, esas leyes probablemente no son la vía principal de reclamación. Lo que sí puede aplicar son las **equivalentes europeas** (RGPD, ePrivacy/LSSI, DSA, LOPDGDD).

El componente es limpio en los puntos "famosos" del vídeo. Hay 6 puntos a decidir (ninguno CRÍTICO).

## 2. Los puntos del vídeo, contrastados con el repositorio

| Tema del vídeo | Equivalente UE/España | Lo que hay en el repo | Estado |
|---|---|---|---|
| Google Fonts remotas (IP al visitante) | RGPD art. 6 + cap. V; LG München I 3 O 17493/20 (20/01/2022) | Sin `fonts.googleapis`, sin `@import` ni `url(http` externos; fuentes del sistema/locales | HECHO TÉCNICO CONFIRMADO: no aparece |
| Session replay / analítica | ePrivacy 5.3, LSSI 22.2 | Sin analítica, sin píxeles, sin cookies propias. Solo `localStorage` `mvMapStyle` (preferencia de estilo, estrictamente funcional) y `sessionStorage` en el admin | OK |
| Menores (COPPA) | LOPDGDD art. 7 (14 años), RGPD art. 8 | El componente no pide edad; depende del registro de Joomla | POSIBLE PROBLEMA, MEDIA |
| Correo masivo sin baja ni dirección | LSSI arts. 21-22, RGPD art. 6/21 | Resumen por correo solo si el usuario activa la casilla (opt-in), con baja desde el mapa, sin enlaces, con aviso de confidencialidad. Falta línea de identidad del remitente | POSIBLE PROBLEMA, BAJA |
| DMCA / fotos de usuarios | DSA art. 16 (notificación y acción), art. 17; derechos de imagen/propiedad intelectual | Hay moderación previa y borrado propio, pero **no hay botón "Denunciar" ni punto de contacto visible** | PREOCUPACIÓN FUERTE, ALTA |
| Pagos / renovaciones | CRD, UCPD | Sin pagos | NO APLICA |

## 3. Hallazgos

**H1 — Sin vía para denunciar contenido (ALTA, PREOCUPACIÓN FUERTE).** Si el sitio aloja fotos de terceros, el DSA art. 16 pide un mecanismo de notificación (la exención de microempresas del art. 19 aplica a plataformas, no al deber de alojamiento — confirmar con abogado). Arreglo: botón "Denunciar" en la ficha + correo/punto de contacto en las condiciones.

**H2 — Sin declaración de derechos al subir fotos (MEDIA).** Nada recuerda al usuario que debe tener derechos sobre las fotos, el consentimiento de personas reconocibles y no subir menores identificables. Arreglo: casilla obligatoria en el formulario + texto.

**H3 — Captcha reCAPTCHA v3 envía IP a Google (MEDIA, POSIBLE PROBLEMA).** Es un plugin de Joomla, no del componente. El usuario lo eligió porque Proof of Work no le funcionaba. Si se mantiene, la política de privacidad debe mencionarlo y puede requerir consentimiento. Alternativa: Proof of Work.

**H4 — Engage (comentarios) guarda IP y user-agent (MEDIA).** Es un componente de terceros. Debe constar en la política de privacidad, con plazo de conservación. Gravatar en Engage está condicionado a consentimiento (modo "ask"): correcto.

**H5 — Ranking/podio público sin opción de oposición (MEDIA).** Se muestra el nombre público con puntos. RGPD art. 21 (oposición) y art. 13 (información). Arreglo: opción de aparecer con alias o no aparecer, o informar claramente.

**H6 — Menores (MEDIA).** Edad mínima 14 en España. Arreglo a nivel de sitio: aviso/casilla en el registro de Joomla y condiciones.

**H7 — Identidad del remitente en los correos (BAJA).** LSSI art. 10/21: añadir una línea configurable con nombre/razón social y contacto (sin enlaces, para respetar tu decisión).

**H8 — `docs/PRIVACIDAD.md` desactualizado (BAJA).** Antes de esta pasada decía: correos en texto plano, interruptor "no aparecer", sin captcha ni Gravatar. Ahora hay HTML, captcha y Gravatar condicionado. Marcado: **SIGUE** (hay que reescribirlo).

## 4. Inventario de datos (resumen)

| Dato | Finalidad | Dónde | Terceros | Conservación |
|---|---|---|---|---|
| Fotos (EXIF eliminado, WebP) | Mostrar el viaje | Carpeta privada del servidor | Ninguno | Hasta borrado del viaje/usuario |
| Pie de foto, año, país | Mostrar el viaje | BD | Ninguno | Idem |
| Clave opaca de usuario (HMAC) | Límites anti-abuso | BD | Ninguno | Mientras exista la cuenta |
| Seguidos / preferencias de aviso | Notificaciones | BD | Ninguno | Hasta baja; exportado y borrado por plugin de privacidad |
| Correo del usuario | Resumen opcional | Joomla | Servidor de correo | Joomla |
| IP | Captcha | Plugin Joomla / Engage | Google (si reCAPTCHA) | Fuera del componente |

El componente **no guarda IP** ni usa cookies propias.

## 5. Matriz de aplicabilidad

| Norma | ¿Aplica? | Confianza | Motivo |
|---|---|---|---|
| RGPD / LOPDGDD | Sí | Fuerte | Datos personales de usuarios registrados |
| ePrivacy / LSSI 22.2 | Sí (parcial) | Fuerte | Solo almacenamiento funcional; sin cookies de seguimiento |
| LSSI 21 (comunicaciones comerciales) | Probablemente no | Posible | El resumen es un servicio solicitado, no publicidad. Confirmar |
| DSA | Sí (alojamiento) | Posible | Aloja contenido de usuarios |
| Consumo (CRD/UCPD) | No aplica | Fuerte | Sin ventas |
| Ley de IA | No aplica | Fuerte | Sin IA |
| EAA / Ley 11/2023 | Probablemente no | Posible | Sin comercio electrónico; confirmar |
| CRA | Probablemente no | Posible | Software propio, no comercializado como producto. Confirmar si se distribuye a terceros |
| NIS2, Data Act, PSD2, DMA | No aplica | Fuerte | — |

## 6. Hoja de ruta (para una segunda pasada, solo si se pide)

1. (ALTA, código + documento) Botón "Denunciar" + punto de contacto.
2. (MEDIA, código) Casilla de declaración de derechos al subir.
3. (MEDIA, documento/ajuste) Elegir Proof of Work o documentar reCAPTCHA.
4. (MEDIA, documento) Política de privacidad: Engage (IP, user-agent), captcha, ranking, plazos.
5. (MEDIA, código) Alias/oposición en el ranking.
6. (MEDIA, sitio) Aviso de edad mínima 14 en el registro.
7. (BAJA, código) Línea de identidad del remitente en correos.
8. (BAJA, documento) Reescribir `docs/PRIVACIDAD.md`.

## 7. Fuentes consultadas (2026-10-15)

- LG München I, 20/01/2022, 3 O 17493/20 (Google Fonts remoto).
- RGPD (UE) 2016/679, arts. 6, 8, 13, 21, cap. V — EUR-Lex.
- LOPDGDD (LO 3/2018) art. 7 — BOE / AEPD.
- LSSI-CE (Ley 34/2002) arts. 10, 21, 22 — BOE (verificar texto vigente).
- DSA (UE) 2022/2065 arts. 16, 17, 19 — EUR-Lex.

Esto es una pre-auditoría técnica. No sustituye el asesoramiento de un abogado o DPD.
