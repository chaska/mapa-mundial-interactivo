# Estilos de mapa

El mapa es un dibujo vectorial (SVG) pintado con CSS. El estilo «Pergamino viajero» está en `media/com_mapaviajes/css/mapa.css`; **cada estilo adicional es un archivo** en `media/com_mapaviajes/css/maps/`:

    noche.css  atlas.css  politico.css  acuarela.css  plano.css

## Añadir uno nuevo
1. Copia un archivo existente (por ejemplo `atlas.css`) con otro nombre: minúsculas, cifras y `_`, empezando por letra (máx. 24). Ejemplo: `retro.css`.
2. Cambia el nombre `retro` en cada selector `[data-map="retro"]` (el valor de `data-map` es el nombre del archivo).
3. La primera línea del archivo dice cómo se lista y de qué color es su muestra redonda:

       /* mapa: Mi estilo retro; agua: #aabbcc; tierra: #ddeeff */

4. Súbelo a `media/com_mapaviajes/css/maps/`. Aparece solo en el Panel y en Opciones → Mapa. No hay que registrar nada.

## Qué se puede pintar
`.mv-stage` (fondo), `.mv-sphere` (el agua), `.mv-grat` (cuadrícula), `.mv-r1/.mv-r2/.mv-r3` (halo de las costas), `.mv-land path` (cada país; `:nth-child(n)` permite colores distintos), `.mv-olabs text` (nombres de océanos), `.mv-compass`, `.mv-brand h1` (título), `.mv-zoom button` (mandos) y las variables `--panel`, `--ink`, `--line`… del `.mv-root`.

## Qué no se debe hacer
No cargar recursos externos (`url(http…)`, `@import`): el mapa no hace peticiones a terceros. El candado de compilación rechaza esos archivos.

## Dónde se guarda la elección
El estilo por defecto está en las opciones del componente (`map_style`). Si el administrador activa «Dejar que cada persona elija su mapa» (`map_user_choice`), cada persona guarda su elección en `localStorage` de su navegador (`mvMapStyle`); el servidor no la conoce.
