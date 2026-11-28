# Banco de preguntas

Ficheros con las preguntas del juego. **Se cargan solos** en la base de datos cada vez que arranca el contenedor de PHP (`bin/import-questions.php`): las preguntas nuevas entran y las que ya estaban (mismo texto) se saltan. Para cargar a mano sin reiniciar:

```bash
docker compose exec php php bin/import-questions.php
```

La carpeta está fuera de `data/` a propósito: `data/` es un volumen de Docker y taparía estos ficheros.

| Fichero | Preguntas | Contenido |
|---|---|---|
| `local.json` | 105 | España, sobre todo Andalucía (`origin: "local"`) |
| `general.json` | 226 | Cultura general escrita para el proyecto, en lugar de Open Trivia DB (`origin: "general"`) |
| `preguntados.json` | 60 | Selección del repositorio [danb0219/preguntados](https://github.com/danb0219/preguntados), licencia MIT (`origin: "general"`) |
| `opentdb.json` | 332 | Selección de [Open Trivia DB](https://opentdb.com) traducida al español, licencia CC BY-SA 4.0 (`origin: "general"`) |

Total: 723 preguntas. Todas se mezclan en el mismo banco al jugar.

## Formato

Cada fichero es una lista JSON. Cada pregunta:

Los nombres de los campos van en inglés (norma del proyecto); el contenido, en español.

```json
{
  "text": "¿Cuántas provincias tiene Andalucía?",
  "category": "Geografía",
  "difficulty": "easy",
  "options": [
    { "text": "9", "is_correct": false },
    { "text": "6", "is_correct": false },
    { "text": "8", "is_correct": true },
    { "text": "7", "is_correct": false }
  ],
  "origin": "local",
  "source": "Redacción propia (Claude)",
  "needs_review": true
}
```

- `text`, `category`, `difficulty` (`easy` = fácil, `medium` = media, `hard` = difícil) y `options` van a las tablas `questions` y `answer_options`.
- Siempre hay 4 opciones y solo una tiene `is_correct: true`. En el juego se barajan en cada partida.
- `origin` (`general` o `local`) y `source` (de dónde sale) se guardan también.
- `needs_review: true` solo aparece en las preguntas cuyo dato conviene comprobar a mano (es discutible o cambia con el tiempo). Al revisarla, se quita el campo. El juego las usa igualmente.
- Si una pregunta tiene un error de formato (no tiene 4 opciones, o no hay exactamente una correcta…), no se carga y sale un aviso en `docker compose logs php`.
- Si corriges el texto de una pregunta ya cargada, entra como pregunta nueva (la vieja se queda). Para empezar de cero: `docker compose down -v` (borra también las salas).

Categorías usadas: Geografía, Historia, Arte y cultura, Ciencia, Naturaleza, Deporte, Entretenimiento, Gastronomía, Tecnología y Mitología.

## Preguntas de danb0219/preguntados

De sus 110 preguntas se han tomado 60. Se descartaron las repetidas (entre ellas o con las nuestras), las que tenían datos desactualizados (por ejemplo, China como país más poblado) y las que no funcionan al barajar las opciones («Todas las anteriores»). Se cambió «bombillo» por «bombilla» y las comillas simples por «». El campo `source` indica el número de la pregunta original.

Licencia: MIT, Copyright (c) 2025 danb0219. El texto completo está en [`LICENSE-preguntados.txt`](LICENSE-preguntados.txt) y debe acompañar a esas preguntas.

## Preguntas de Open Trivia DB

Se descargaron las 4.496 preguntas de opción múltiple de Open Trivia DB (28-11-2026) y se tradujeron 332, elegidas entre ciencia, geografía, historia, arte, mitología, animales, deporte, cine, música, libros, informática y matemáticas. Se dejaron fuera anime, videojuegos, cómics, tele y famosos de EE. UU., y las preguntas con datos erróneos o desfasados. Algunas se han adaptado al traducir (por ejemplo, el máximo goleador de Inglaterra ahora es Harry Kane). La descarga completa en inglés está en la carpeta del proyecto `opentdb/` por si se quieren traducir más.

Licencia: las preguntas de Open Trivia DB se publican bajo [Creative Commons Atribución-CompartirIgual 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.es). `opentdb.json` es una obra derivada (traducción y adaptación) y se distribuye con la misma licencia. Atribución: «Preguntas de Open Trivia DB (https://opentdb.com), traducidas y adaptadas al español». Conviene mostrar esa atribución también en el juego (por ejemplo, en el pie de página o en una página «Créditos»).
