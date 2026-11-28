# Banco de preguntas

Ficheros de datos con las preguntas del juego. Todavía no se cargan solos: la importación a la base de datos llega en la Fase 6.

| Fichero | Preguntas | Contenido |
|---|---|---|
| `local.json` | 105 | España, sobre todo Andalucía (`origen: "local"`) |
| `general.json` | 226 | Cultura general escrita para el proyecto, en lugar de Open Trivia DB (`origen: "general"`) |
| `preguntados.json` | 60 | Selección del repositorio [danb0219/preguntados](https://github.com/danb0219/preguntados), licencia MIT (`origen: "general"`) |
| `opentdb.json` | 332 | Selección de [Open Trivia DB](https://opentdb.com) traducida al español, licencia CC BY-SA 4.0 (`origen: "general"`) |

Total: 723 preguntas. Las tres se mezclan en el mismo banco al jugar.

## Formato

Cada fichero es una lista JSON. Cada pregunta:

```json
{
  "texto": "¿Cuántas provincias tiene Andalucía?",
  "categoria": "Geografía",
  "dificultad": "fácil",
  "opciones": [
    { "texto": "9", "es_correcta": false },
    { "texto": "6", "es_correcta": false },
    { "texto": "8", "es_correcta": true },
    { "texto": "7", "es_correcta": false }
  ],
  "origen": "local",
  "fuente": "Redacción propia (Claude)",
  "revisar": true
}
```

- `texto`, `categoria`, `dificultad` (`fácil`, `media` o `difícil`) y `opciones` van a las tablas `preguntas` y `opciones`.
- Siempre hay 4 opciones y solo una tiene `es_correcta: true`. Ya vienen barajadas.
- `origen` (`general` o `local`) y `fuente` son campos extra. Habrá que añadir esas columnas a la tabla `preguntas` cuando se haga la importación.
- `revisar: true` solo aparece en las preguntas cuyo dato conviene comprobar a mano (es discutible o cambia con el tiempo). Al revisarla, se quita el campo.

Categorías usadas: Geografía, Historia, Arte y cultura, Ciencia, Naturaleza, Deporte, Entretenimiento, Gastronomía, Tecnología y Mitología.

## Preguntas de danb0219/preguntados

De sus 110 preguntas se han tomado 60. Se descartaron las repetidas (entre ellas o con las nuestras), las que tenían datos desactualizados (por ejemplo, China como país más poblado) y las que no funcionan al barajar las opciones («Todas las anteriores»). Se cambió «bombillo» por «bombilla» y las comillas simples por «». El campo `fuente` indica el número de la pregunta original.

Licencia: MIT, Copyright (c) 2025 danb0219. El texto completo está en [`LICENSE-preguntados.txt`](LICENSE-preguntados.txt) y debe acompañar a esas preguntas.

## Preguntas de Open Trivia DB

Se descargaron las 4.496 preguntas de opción múltiple de Open Trivia DB (28-11-2026) y se tradujeron 332, elegidas entre ciencia, geografía, historia, arte, mitología, animales, deporte, cine, música, libros, informática y matemáticas. Se dejaron fuera anime, videojuegos, cómics, tele y famosos de EE. UU., y las preguntas con datos erróneos o desfasados. Algunas se han adaptado al traducir (por ejemplo, el máximo goleador de Inglaterra ahora es Harry Kane). La descarga completa en inglés está en la carpeta del proyecto `opentdb/` por si se quieren traducir más.

Licencia: las preguntas de Open Trivia DB se publican bajo [Creative Commons Atribución-CompartirIgual 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.es). `opentdb.json` es una obra derivada (traducción y adaptación) y se distribuye con la misma licencia. Atribución: «Preguntas de Open Trivia DB (https://opentdb.com), traducidas y adaptadas al español». Conviene mostrar esa atribución también en el juego (por ejemplo, en el pie de página o en una página «Créditos»).
