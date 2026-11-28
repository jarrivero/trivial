/*
 * Lobby de una sala: pregunta al servidor cada 2 segundos ("polling")
 * quién hay en la sala, si ha empezado y si hay mensajes nuevos de chat.
 *
 * Todo lo que escriben los jugadores se pinta con textContent (nunca innerHTML),
 * así nadie puede colar código en el nombre o en el chat.
 */
(function () {
    'use strict';

    const raiz = document.getElementById('lobby');
    const codigo = raiz.dataset.codigo;
    const enlace = raiz.dataset.enlace;
    const api = '/api/sala/' + encodeURIComponent(codigo);
    const CADA_MS = 2000;

    const $ = (id) => document.getElementById(id);
    let ultimoMensaje = 0;   // id del último mensaje de chat recibido
    let temporizador = null;
    let pidiendo = false;

    // --- Polling -------------------------------------------------------------

    async function actualizar() {
        if (pidiendo) return;
        pidiendo = true;
        clearTimeout(temporizador);
        try {
            const r = await fetch(api + '?desde=' + ultimoMensaje, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (r.status === 403) { location.href = '/rivales?codigo=' + encodeURIComponent(codigo); return; }
            if (r.status === 404) { location.href = '/'; return; }
            if (!r.ok) throw new Error('HTTP ' + r.status);
            pintar(await r.json());
            $('sin-conexion').classList.add('d-none');
        } catch (e) {
            $('sin-conexion').classList.remove('d-none');
        } finally {
            pidiendo = false;
            programar();
        }
    }

    function programar() {
        clearTimeout(temporizador);
        // Con la pantalla bloqueada o la pestaña oculta no preguntamos (ahorra batería).
        if (!document.hidden) temporizador = setTimeout(actualizar, CADA_MS);
    }

    // Al volver a la pestaña o desbloquear el móvil: actualizar al momento.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(temporizador);
        else actualizar();
    });

    // --- Pintar el estado ----------------------------------------------------

    function pintar(d) {
        pintarJugadores(d);
        pintarEmpezar(d);
        pintarMensajes(d.mensajes);
    }

    function pintarJugadores(d) {
        const ul = $('jugadores');
        ul.replaceChildren(...d.jugadores.map((j) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center gap-2 py-3';

            const punto = document.createElement('span');
            punto.className = 'rounded-circle flex-shrink-0 ' + (j.conectado ? 'bg-success' : 'bg-secondary-subtle');
            punto.style.cssText = 'width:.7rem;height:.7rem';
            punto.title = j.conectado ? 'Conectado' : 'Desconectado';

            const nombre = document.createElement('span');
            nombre.className = 'flex-grow-1 text-truncate' + (j.conectado ? '' : ' text-body-secondary');
            nombre.textContent = j.nombre;

            li.append(punto, nombre);
            if (j.id === d.yo) li.append(etiqueta('tú', 'text-bg-primary'));
            if (j.creador) li.append(etiqueta('👑 anfitrión', 'text-bg-warning'));
            return li;
        }));
        $('contador').textContent = d.jugadores.length + ' / ' + d.maxJugadores;
    }

    function etiqueta(texto, clase) {
        const s = document.createElement('span');
        s.className = 'badge rounded-pill ' + clase;
        s.textContent = texto;
        return s;
    }

    function pintarEmpezar(d) {
        const empezada = d.estado !== 'lobby';
        const pocos = d.jugadores.length < 2;
        $('aviso-empezada').classList.toggle('d-none', !empezada);
        $('btn-empezar').classList.toggle('d-none', empezada || !d.soyCreador);
        $('btn-empezar').disabled = pocos;
        $('falta-gente').classList.toggle('d-none', empezada || !d.soyCreador || !pocos);
        $('esperando').classList.toggle('d-none', empezada || d.soyCreador);
        const creador = d.jugadores.find((j) => j.creador);
        $('nombre-creador').textContent = creador ? creador.nombre : 'el anfitrión';
    }

    function pintarMensajes(mensajes) {
        if (!mensajes.length) return;
        const chat = $('chat');
        // Solo bajamos solos si ya estabas abajo del todo (si estás leyendo arriba, no te movemos).
        const abajo = chat.scrollHeight - chat.scrollTop - chat.clientHeight < 40;
        $('chat-vacio')?.remove();

        for (const m of mensajes) {
            if (m.id <= ultimoMensaje) continue;
            ultimoMensaje = m.id;

            const fila = document.createElement('div');
            fila.className = 'd-flex mb-2 ' + (m.mio ? 'justify-content-end' : 'justify-content-start');
            const burbuja = document.createElement('div');
            burbuja.className = 'px-3 py-2 rounded-3 ' + (m.mio ? 'bg-primary text-white' : 'bg-body-secondary');
            burbuja.style.maxWidth = '80%';
            burbuja.style.overflowWrap = 'anywhere';

            if (!m.mio) {
                const autor = document.createElement('div');
                autor.className = 'small fw-semibold';
                autor.textContent = m.nombre;
                burbuja.append(autor);
            }
            const texto = document.createElement('div');
            texto.textContent = m.texto;
            const hora = document.createElement('div');
            hora.className = 'text-end opacity-75';
            hora.style.fontSize = '.7rem';
            hora.textContent = m.hora;
            burbuja.append(texto, hora);
            fila.append(burbuja);
            chat.append(fila);
        }
        if (abajo) chat.scrollTop = chat.scrollHeight;
    }

    // --- Acciones ------------------------------------------------------------

    async function enviarJson(url, datos) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(datos || {}),
        });
        let cuerpo = {};
        try { cuerpo = await r.json(); } catch (e) { /* sin cuerpo */ }
        return { ok: r.ok, cuerpo };
    }

    $('form-chat').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const input = $('texto');
        const texto = input.value.trim();
        if (!texto) return;
        const err = $('chat-error');
        try {
            const { ok, cuerpo } = await enviarJson(api + '/mensajes', { texto });
            if (!ok) throw new Error(cuerpo.error || 'No se pudo enviar.');
            input.value = '';
            err.classList.add('d-none');
            const chat = $('chat');
            chat.scrollTop = chat.scrollHeight;   // tu propio mensaje siempre se ve
            actualizar();
        } catch (e) {
            err.textContent = e.message === 'Failed to fetch' ? 'Sin conexión. Inténtalo otra vez.' : e.message;
            err.classList.remove('d-none');
        }
        input.focus();
    });

    $('btn-empezar').addEventListener('click', async () => {
        const b = $('btn-empezar');
        b.disabled = true;
        try { await enviarJson(api + '/empezar'); } finally { actualizar(); }
    });

    // Invitar: en el móvil abre el menú de compartir (WhatsApp, etc.); si no, copia el enlace.
    $('btn-compartir').addEventListener('click', async () => {
        if (navigator.share) {
            try {
                await navigator.share({ title: 'Trivial', text: '¡Únete a mi sala del Trivial! Código ' + codigo, url: enlace });
                return;
            } catch (e) { if (e.name === 'AbortError') return; }
        }
        copiar();
    });
    $('btn-copiar').addEventListener('click', copiar);

    async function copiar() {
        try {
            await navigator.clipboard.writeText(enlace);
        } catch (e) {
            // Sin HTTPS el navegador no deja copiar automáticamente: lo mostramos para copiarlo a mano.
            window.prompt('Copia este enlace:', enlace);
            return;
        }
        $('copiado').classList.remove('d-none');
        setTimeout(() => $('copiado').classList.add('d-none'), 2000);
    }

    actualizar();
})();
