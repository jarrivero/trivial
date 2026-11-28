# 🎯 Trivial multijugador

Trivial para jugar con amigos desde el móvil. Hecho con PHP 8 + Slim 4 + Eloquent + SQLite + Smarty + Bootstrap 5.

> **Estado:** Fase 3. Ya se juega: 10 preguntas con tiempo, puntos por rapidez y clasificación, con rivales o solo.

> **Norma del proyecto:** el código, los comentarios, las variables, los nombres de fichero y las tablas van en **inglés**. Lo que ve el jugador (textos, botones, mensajes de error) y esta documentación van en **español**.

## Cómo se juega

**Jugar solo:** escribes tu nombre, eliges dificultad y empieza al momento.

**Jugar con rivales:**

1. **Crear sala**: escribes tu nombre y te da un código de 4 letras (sin I, L ni O, para que no se confundan al dictarlo).
2. Los amigos entran con **Unirme** (código + nombre), o abriendo el enlace que mandas con **Invitar** (en el móvil abre WhatsApp, Telegram, etc.).
3. En el **lobby** se ve la lista de jugadores (punto verde = conectado, gris = lleva más de 15 s sin dar señales) y un **chat**.
4. Quien crea la sala (👑 anfitrión) elige la **dificultad** (fácil, media, difícil o mezcla) y pulsa **Empezar** (hacen falta al menos 2 jugadores). Si el anfitrión sale, el mando pasa al siguiente jugador.

**La partida** (igual en los dos modos):

- Cuenta atrás de 3 s y luego **10 preguntas** de 4 opciones (botones de colores). Cada pregunta dura **20 segundos**.
- **Puntos:** si aciertas, entre **1000** (contestando al instante) y **500** (en el último segundo). Si fallas o no contestas, 0.
- La pregunta se cierra cuando se acaba el tiempo **o cuando han contestado todos** los jugadores conectados.
- Después se ve la respuesta correcta, cuántos puntos ha ganado cada uno y la **clasificación** durante 6 segundos. Luego pasa sola a la siguiente. Entre preguntas se puede usar el chat.
- Al final, **clasificación final** con medallas. El anfitrión pulsa **Jugar otra vez** y todos vuelven al lobby (pueden entrar nuevos). En una sala no se repiten preguntas de partidas anteriores mientras queden.
- **El servidor manda en el tiempo**: cada móvil solo dibuja la cuenta atrás que le dice el servidor, y los puntos se calculan con el reloj del servidor. Así nadie puede hacer trampa ni influye que un móvil tenga la hora mal.
- **Reconexión**: si recargas o cierras el navegador, vuelves a tu sala o partida con tu nombre y tus puntos (la cookie dura 12 horas). Mientras tanto la partida sigue sin ti.

Límites: 12 jugadores por sala, nombres de hasta 20 letras sin repetir dentro de la sala, mensajes de hasta 200 letras (uno por segundo como mucho). No se puede entrar en una sala con la partida empezada. Las salas abandonadas más de 24 h se borran solas.

## Las preguntas

Están en la carpeta [`questions/`](questions/README.md) (723 preguntas en ficheros JSON). Se cargan solas en la base de datos cada vez que arranca el contenedor; para añadir más, se añaden al JSON y se reinicia (`docker compose restart php`). En `http://IP:8080/salud` el campo `questions` dice cuántas hay cargadas.

## Estructura de carpetas

```
trivial/
├── app/
│   ├── bootstrap.php     # Cableado: sesión, base de datos, Smarty y Slim
│   ├── routes.php        # Todas las direcciones de la web
│   ├── settings.php      # Configuración en un solo sitio
│   ├── Database.php      # Conecta Eloquent a SQLite y activa WAL
│   ├── Schema.php        # Crea las tablas la primera vez
│   ├── View.php          # Pinta plantillas Smarty dentro de Slim
│   ├── Game.php          # Reglas del juego: fases, tiempos, puntos, clasificación
│   ├── QuestionBank.php  # Carga las preguntas de questions/ en la base de datos
│   ├── Controllers/
│   │   └── RoomController.php  # Páginas y API: salas, solo, lobby, chat, empezar, contestar
│   └── Models/           # Room (sala), Player (jugador), Message (mensaje de chat)
├── bin/
│   └── import-questions.php    # Carga preguntas nuevas (lo hace solo al arrancar)
├── questions/            # Banco de preguntas en JSON (ver su README)
├── public/
│   ├── index.php         # Única puerta de entrada (lo único visible desde Internet)
│   └── js/room.js        # Lobby, partida y chat: pregunta al servidor cada 1-2 s
├── templates/
│   ├── layout.tpl        # Plantilla base: Bootstrap, viewport, ajustes móviles
│   ├── home.tpl          # Página de inicio
│   ├── solo.tpl          # Formulario de "Jugar solo"
│   ├── multiplayer.tpl   # Formularios de crear sala / unirme
│   └── room.tpl          # Sala: lobby, preguntas, resultados y clasificación final
├── data/                 # Aquí se crea trivial.sqlite (no se sube a git)
├── var/cache/smarty/     # Plantillas compiladas por Smarty (no se sube a git)
├── docker/
│   ├── php/Dockerfile    # Imagen de PHP 8.3 con Composer
│   ├── php/entrypoint.sh # Al arrancar: librerías, permisos y carga de preguntas
│   ├── php/php.ini       # Ajustes de PHP
│   └── nginx/default.conf# Servidor web
├── docker-compose.yml    # Arranca Nginx + PHP juntos
├── composer.json         # Librerías PHP que usa el proyecto
└── composer.lock         # Versiones exactas de esas librerías
```

Las direcciones que ve el jugador siguen en español (`/sala/ABCD`, `/rivales`, `/solo`, `/salud`) para que los enlaces de invitación se entiendan. El resto (formularios y la API en `/api/rooms/...`) está en inglés.

## Ponerlo en marcha (mini PC con Proxmox o cualquier máquina con Docker)

Necesitas una máquina (o contenedor LXC / máquina virtual de Proxmox) con **Docker** y **git**.

```bash
# 1. Descargar el código
git clone https://github.com/jarrivero/trivial.git
cd trivial

# 2. (Opcional) Copiar la configuración de ejemplo
cp .env.example .env

# 3. Construir y arrancar los contenedores
docker compose up -d --build
```

La primera vez, el contenedor de PHP instala solo las librerías (carpeta `vendor/`), así que tarda un minuto en responder. Puedes verlo con `docker compose logs -f php`: cuando ponga `ready to handle connections` ya está listo.

Abre en el navegador `http://IP-DE-LA-MAQUINA:8080` y verás la página de inicio.
Para comprobar la base de datos abre `http://IP-DE-LA-MAQUINA:8080/salud`: debe poner `"journal_mode": "wal"`.

Otros comandos útiles:

```bash
docker compose logs -f          # ver errores en directo (Ctrl+C para salir)
docker compose down             # parar (los datos se conservan)
git pull && docker compose up -d --build   # actualizar a la última versión
docker compose exec php composer install   # reinstalar librerías a mano si hiciera falta
```

Para ver los errores detallados en pantalla mientras pruebas, pon `APP_DEBUG=1` en `.env` y ejecuta `docker compose up -d`. **En el VPS déjalo en 0.**

## Publicarlo en el VPS con HTTPS

Los mismos pasos que arriba. Para tener `https://` con certificado gratuito de Let's Encrypt, lo más sencillo es poner **Caddy** delante, que pide y renueva el certificado solo:

1. Apunta un subdominio a la IP del VPS (registro DNS tipo A), p. ej. `trivial.tudominio.es`.
2. Instala Caddy en el VPS y usa este `Caddyfile`:

   ```
   trivial.tudominio.es {
       reverse_proxy 127.0.0.1:8080
   }
   ```

3. Abre los puertos 80 y 443 del VPS y cierra el 8080 al exterior (cortafuegos), para que solo se entre por HTTPS.

Si ya usas Nginx Proxy Manager o Traefik en el VPS, sirve igual: solo hay que redirigir el dominio al puerto 8080. La app detecta HTTPS (cabecera `X-Forwarded-Proto`) y marca la cookie de sesión como segura.

## Por dónde se puede romper (para tenerlo en cuenta)

**Concurrencia (varios jugadores a la vez):**
- SQLite solo deja escribir a uno a la vez. Con **WAL** (un modo de SQLite) las lecturas del polling no bloquean a quien escribe, y con `busy_timeout = 5000` una escritura espera hasta 5 s en lugar de fallar con *"database is locked"*. Para 4-8 amigos sobra.
- La base de datos debe estar en un **disco local**. WAL no funciona bien sobre carpetas compartidas por red (NFS/SMB). El volumen de Docker ya lo cumple.
- Junto a `trivial.sqlite` aparecerán `trivial.sqlite-wal` y `-shm`. Son normales: si haces copia de seguridad, copia los tres o para antes los contenedores.
- La sesión de PHP bloquea peticiones del mismo jugador mientras está abierta. La API la cierra enseguida (`session_write_close()`) para que el polling, el chat y las respuestas no se esperen entre sí.
- Si dos móviles piden el estado justo cuando se acaba una pregunta, solo uno hace el cambio de fase (la actualización solo se aplica si la sala sigue como estaba).

**Móvil:**
- Sin la etiqueta `viewport` el móvil enseña la web en miniatura; ya está en `layout.tpl`.
- iPhone hace zoom al tocar un campo con letra menor de 16 px; corregido en el CSS.
- `touch-action: manipulation` quita el retardo al tocar botones.
- Bootstrap se carga desde Internet (CDN). Sin conexión a Internet, la página saldrá sin estilos.
- Cuando el móvil bloquea la pantalla, el navegador congela el JavaScript: el polling se para y, al volver, pide el estado al momento y se pone al día. La partida sigue en el servidor mientras tanto.

## Glosario rápido

- **Polling:** el navegador pregunta al servidor cada 1-2 segundos "¿hay algo nuevo?".
- **WAL:** modo de SQLite que permite leer mientras otro escribe.
- **CDN:** servidor público del que se descarga Bootstrap, para no tenerlo en el proyecto.
- **Contenedor (Docker):** una "cajita" con todo lo necesario para ejecutar la app, igual en cualquier máquina.
- **Volumen (Docker):** carpeta que guarda datos fuera del contenedor, para no perderlos al reconstruirlo.
- **Proxy inverso (Caddy):** programa que recibe las visitas por HTTPS y se las pasa a la app.
