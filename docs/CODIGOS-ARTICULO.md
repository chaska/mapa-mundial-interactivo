# Códigos para artículos de Joomla

Con el plugin «Contenido - Mapa de viajes» activado, puedes escribir estos códigos en cualquier artículo (o módulo de texto que pase por el editor de contenido):

| Código | Resultado de ejemplo |
|---|---|
| `{mapaviajes stats}` | 51 álbumes · 16 países |
| `{mapaviajes albums}` | 51 |
| `{mapaviajes countries}` | 16 |
| `{mapaviajes stats album=3}` | lo mismo, contando solo el mapa nº 3 |

- Cuenta los viajes **publicados** de los mapas **publicados** (da igual el nivel de acceso del mapa: solo se enseñan totales, nunca nombres, lugares ni fotos). Los viajes pendientes de revisión no cuentan.
- Los países salen igual que en la cabecera del mapa (con los mismos contornos). Un viaje en el mar cuenta como un «país» por cada casilla del dibujo, igual que en el mapa.
- El resultado se guarda 10 minutos en la caché de Joomla, así que un viaje nuevo puede tardar hasta 10 minutos en sumarse en el artículo.
- Si el código no se reconoce o algo falla, el artículo se pinta igual (el código desaparece o se queda tal cual, nunca da error).
- Para dar formato, envuélvelo tú: `<strong>{mapaviajes albums}</strong> álbumes en <strong>{mapaviajes countries}</strong> países`. El código `stats` ya lleva su propia etiqueta `<span class="mv-stats">` por si quieres darle estilo con CSS.
