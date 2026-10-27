# Auditoría de seguridad de lo nuevo en 1.16 – 1.18

Fecha: 2026-10-27. Alcance: código nuevo desde 1.15.2 (miniaturas 1.16.2, códigos de artículo 1.16.x, HEIC en el navegador 1.17.0, podio 1.17.1/1.17.2, fotos de perfil 1.18.0). Método: lectura del diff (`4f7a0fb..HEAD`) con las listas de las skills `security-audit` (pasada enfocada, sin tocar el servidor real) y `seguridad-zero-trust` (puertas de salida) y la batería de pruebas en vivo. La skill `security-review` no pudo ejecutarse tal cual (la rama no tiene `origin/HEAD`); se hizo la misma revisión a mano sobre el diff. No sustituye una prueba de intrusión del servidor real.

## Resumen
- **Sin hallazgos críticos, altos ni medios.**
- 1 hallazgo bajo, ya corregido en 1.18.0; 3 observaciones sin cambio de código.

## Hallazgo corregido
| ID | Gravedad | Qué era | Arreglo |
|---|---|---|---|
| S1 | Baja (cadena de suministro) | El lector HEIC (`libheif-bundle.js`, 2 MB de WebAssembly) se ejecuta en el navegador de quien elige un HEIC y nada impedía que un cambio accidental o malicioso del archivo llegara al ZIP. | El empaquetador fija su SHA-256 y se niega a construir si cambia (visto en rojo y en verde). Hash anotado en `NOTICE.md`. |

## Revisado sin hallazgo
- **Miniaturas**: la puerta (autor, moderador, amigo, bloqueos) se aplica antes de elegir el archivo; la copia pequeña sale de la foto ya validada, el nombre es `sha1(nombre)` (sin entrada del usuario en la ruta), se escribe en temporal + `rename`, y se borra con la foto. «Solo amigos» da 404 también en miniatura.
- **HEIC**: el servidor nunca abre un HEIC; el navegador lo convierte y el servidor vuelve a validar y recodificar a WebP sin EXIF. Tope de 64 megapíxeles antes de reservar memoria. Sin peticiones a internet.
- **Códigos de artículo**: solo consultas con enteros ligados; salida como números o texto escapado; no muestran nombres, lugares ni fotos; un fallo devuelve cadena vacía.
- **Fotos de perfil**: solo se aceptan direcciones del propio sitio que empiezan por una `/`, sin espacios, comillas ni `\`; la imagen se pinta con `alt` vacío y sin `referer`; el navegador no recibe ids de Joomla; las respuestas JSON van con `no-store`; si MiFoto falla o no existe, no hay foto y no se rompe nada; las escrituras siguen exigiendo POST + token.
- **Podio**: sin entradas nuevas; solo cambia el día de cierre y qué semana se enseña.

## Observaciones (no cambian el código)
1. **Miniaturas en la caché del navegador 10 minutos** (`private, max-age=600`): si alguien te quita de amigos o te bloquea, una miniatura ya cargada puede seguir viéndose en ese navegador hasta 10 minutos. La foto grande revalida siempre.
2. **Los códigos de artículo enseñan totales a cualquiera** que vea el artículo, aunque el mapa exija sesión, e incluyen el número de fotos «solo amigos» (solo la cifra). Es intencionado; quien no lo quiera no usa el código. Con la caché de Joomla apagada, cada visita recalcula los países (milisegundos).
3. **Confianza en MiFoto**: la dirección de la foto viene de otra extensión instalada por el administrador. Si esa extensión se comprometiera, el mapa pondría esa ruta local en un `<img>` (petición GET de la misma web). No amplía lo que ya podría hacer esa extensión.

## Legal (resumen de la skill `auditoria-legal-app-ue-es`, solo lo nuevo)
- La foto de perfil es dato personal: añadida a `docs/PRIVACIDAD.md` (§3) con lo que hay que citar en la política (quién la ve, borrado, denuncia). La foto, su consentimiento y su borrado viven en MiFoto.
- Pendiente tuyo: que MiFoto tenga su propia vía de denuncia y de borrado, y que el aviso al subir la foto diga dónde se verá (ranking, viajes, amigos).

Esto es una revisión técnica de código y pruebas locales. No sustituye una prueba de intrusión del servidor real ni el asesoramiento de un abogado o DPD.
