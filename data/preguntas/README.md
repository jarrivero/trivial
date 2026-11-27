# Banco de preguntas

Ficheros de datos con las preguntas del juego. Todavía no se cargan solos: la importación a la base de datos llega en la Fase 6.

| Fichero | Preguntas | Contenido |
|---|---|---|
| `local.json` | 105 | España, sobre todo Andalucía (`origen: "local"`) |
| `general.json` | 226 | Cultura general escrita para el proyecto, en lugar de Open Trivia DB (`origen: "general"`) |
| `preguntados.json` | 60 | Selección del repositorio [danb0219/preguntados](https://github.com/danb0219/preguntados), licencia MIT (`origen: "general"`) |

Total: 391 preguntas. Las tres se mezclan en el mismo banco al jugar.

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
