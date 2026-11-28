# 🎯 Trivial multijugador

Trivial para jugar con amigos desde el móvil. Hecho con PHP 8 + Slim 4 + Eloquent + SQLite + Smarty + Bootstrap 5.

> **Estado:** Fase 2. Ya se pueden crear salas, unirse con un código, ver quién está en el lobby y chatear. El botón **Empezar** marca la partida como empezada, pero las preguntas llegan en la Fase 3.

## Cómo funciona una sala (Fase 2)

1. **Jugar con rivales → Crear sala**: escribes tu nombre y te da un código de 4 letras (sin I, L ni O, para que no se confundan al dictarlo).
2. Los amigos entran con **Unirme** (código + nombre), o abriendo el enlace que mandas con **Invitar** (en el móvil abre WhatsApp, Telegram, etc.).
3. En el **lobby** se ve la lista de jugadores (punto verde = conectado, gris = lleva más de 15 s sin dar señales) y un **chat**. La lista y el chat se actualizan solos cada 2 segundos.
4. Quien crea la sala (👑 anfitrión) ve el botón **Empezar** (hacen falta al menos 2 jugadores). Si el anfitrión sale, el mando pasa al siguiente jugador.
5. **Reconexión**: si recargas o cierras el navegador, vuelves a tu sala con tu nombre (la cookie dura 12 horas).

Límites: 12 jugadores por sala, nombres de hasta 20 letras sin repetir dentro de la sala, mensajes de hasta 200 letras (uno por segundo como mucho). Las salas abandonadas más de 24 h se borran solas.

## Estructura de carpetas

```
trivial/
├── app/
│   ├── bootstrap.php     # Cableado: sesión, base de datos, Smarty y Slim
│   ├── routes.php        # Todas las direcciones de la web
│   ├── settings.php      # Configuración en un solo sitio
│   ├── Database.php      # Conecta Eloquent a SQLite y activa WAL
│   ├── Schema.php        # Crea las tablas la primera vez (salas, jugadores, mensajes)
│   ├── View.php          # Pinta plantillas Smarty dentro de Slim
│   ├── Controllers/
│   │   └── SalaController.php  # Crear sala, unirse, lobby, chat, empezar
│   └── Models/           # Sala, Jugador, Mensaje (tablas de la base de datos)
├── public/
│   ├── index.php         # Única puerta de entrada (lo único visible desde Internet)
│   └── js/lobby.js       # Polling del lobby y chat
├── templates/
│   ├── layout.tpl        # Plantilla base: Bootstrap, viewport, ajustes móviles
│   ├── home.tpl          # Página de inicio
│   ├── rivales.tpl       # Formularios de crear sala / unirme
│   └── lobby.tpl         # Sala de espera con jugadores y chat
├── data/                 # Aquí se crea trivial.sqlite (no se sube a git)
├── var/cache/smarty/     # Plantillas compiladas por Smarty (no se sube a git)
├── docker/
│   ├── php/Dockerfile    # Imagen de PHP 8.3 con Composer
│   ├── php/php.ini       # Ajustes de PHP
│   └── nginx/default.conf# Servidor web
├── docker-compose.yml    # Arranca Nginx + PHP juntos
├── composer.json         # Librerías PHP que usa el proyecto
└── composer.lock         # Versiones exactas de esas librerías
```

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
- La sesión de PHP bloquea peticiones del mismo jugador mientras está abierta. En las fases de polling cerraremos la sesión pronto (`session_write_close()`) para que el chat y el estado no se esperen entre sí.

**Móvil:**
- Sin la etiqueta `viewport` el móvil enseña la web en miniatura; ya está en `layout.tpl`.
- iPhone hace zoom al tocar un campo con letra menor de 16 px; corregido en el CSS.
- `touch-action: manipulation` quita el retardo al tocar botones.
- Bootstrap se carga desde Internet (CDN). Sin conexión a Internet, la página saldrá sin estilos.
- Cuando el móvil bloquea la pantalla, el navegador congela el JavaScript. El polling lo tendrá en cuenta (fases 3-4).

## Glosario rápido

- **Polling:** el navegador pregunta al servidor cada 2-3 segundos "¿hay algo nuevo?".
- **WAL:** modo de SQLite que permite leer mientras otro escribe.
- **CDN:** servidor público del que se descarga Bootstrap, para no tenerlo en el proyecto.
- **Contenedor (Docker):** una "cajita" con todo lo necesario para ejecutar la app, igual en cualquier máquina.
- **Volumen (Docker):** carpeta que guarda datos fuera del contenedor, para no perderlos al reconstruirlo.
- **Proxy inverso (Caddy):** programa que recibe las visitas por HTTPS y se las pasa a la app.
