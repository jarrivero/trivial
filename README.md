# 🎯 Trivial multijugador

Trivial para jugar con amigos desde el móvil. Hecho con PHP 8 + Slim 4 + Eloquent + SQLite + Smarty + Bootstrap 5.

> **Estado:** Fase 1 (esqueleto). La página de inicio solo muestra el diseño; el juego llega en las siguientes fases.

## Estructura de carpetas

```
trivial/
├── app/
│   ├── bootstrap.php     # Cableado: sesión, base de datos, Smarty y Slim
│   ├── routes.php        # Rutas (de momento: "/" y "/salud")
│   ├── settings.php      # Configuración en un solo sitio
│   ├── Database.php      # Conecta Eloquent a SQLite y activa WAL
│   └── View.php          # Pinta plantillas Smarty dentro de Slim
├── public/
│   └── index.php         # Única puerta de entrada (lo único visible desde Internet)
├── templates/
│   ├── layout.tpl        # Plantilla base: Bootstrap, viewport, ajustes móviles
│   └── home.tpl          # Página de inicio
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

# 4. Instalar las librerías PHP (se hace DENTRO del contenedor)
docker compose exec php composer install
```

Abre en el navegador `http://IP-DE-LA-MAQUINA:8080` y verás la página de inicio.
Para comprobar la base de datos abre `http://IP-DE-LA-MAQUINA:8080/salud`: debe poner `"journal_mode": "wal"`.

Otros comandos útiles:

```bash
docker compose logs -f          # ver errores en directo (Ctrl+C para salir)
docker compose down             # parar (los datos se conservan)
git pull && docker compose exec php composer install   # actualizar a la última versión
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
