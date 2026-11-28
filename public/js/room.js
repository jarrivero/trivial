/*
 * Room page: lobby, game and final leaderboard on a single page.
 *
 * The browser asks the server for the state ("polling") every 1-2 seconds.
 * The SERVER owns the clock: it says how many milliseconds the current phase
 * has left, and the browser only draws the countdown.
 *
 * Everything typed by players is rendered with textContent (never innerHTML),
 * so nobody can inject code through their name or the chat.
 */
(function () {
    'use strict';

    const root = document.getElementById('room');
    const code = root.dataset.code;
    const inviteLink = root.dataset.inviteLink;
    const api = '/api/rooms/' + encodeURIComponent(code);

    const LOBBY_POLL_MS = 2000;
    const GAME_POLL_MS = 1000;
    const COLORS = ['danger', 'primary', 'warning', 'success'];
    const SHAPES = ['▲', '◆', '●', '■'];

    const $ = (id) => document.getElementById(id);
    const show = (el, visible) => (typeof el === 'string' ? $(el) : el).classList.toggle('d-none', !visible);

    let lastMessageId = 0;
    let pollTimer = null;
    let polling = false;
    let state = null;         // last answer from the server
    let deadline = null;      // performance.now() when the current phase ends
    let renderedKey = null;   // which question the answer buttons belong to
    let chosenOptionId = null;

    // --- Polling -------------------------------------------------------------

    async function refresh() {
        if (polling) return;
        polling = true;
        clearTimeout(pollTimer);
        try {
            const r = await fetch(api + '?since=' + lastMessageId, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (r.status === 403) { location.href = '/rivales?codigo=' + encodeURIComponent(code); return; }
            if (r.status === 404) { location.href = '/'; return; }
            if (!r.ok) throw new Error('HTTP ' + r.status);
            render(await r.json());
            show('offline', false);
        } catch (e) {
            show('offline', true);
        } finally {
            polling = false;
            schedule();
        }
    }

    function schedule() {
        clearTimeout(pollTimer);
        // With the screen locked or the tab hidden we stop asking (saves battery).
        if (document.hidden) return;
        let wait = state && state.status === 'playing' ? GAME_POLL_MS : LOBBY_POLL_MS;
        // Ask again right when the phase ends, to see the next question at once.
        if (deadline !== null) {
            wait = Math.max(100, Math.min(wait, deadline - performance.now() + 150));
        }
        pollTimer = setTimeout(refresh, wait);
    }

    // When coming back to the tab or unlocking the phone: refresh at once.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(pollTimer);
        else refresh();
    });

    // --- Rendering -----------------------------------------------------------

    function render(d) {
        state = d;
        const g = d.game;
        deadline = g && g.remainingMs !== null ? performance.now() + g.remainingMs : null;

        show('lobby-view', d.status === 'lobby');
        show('game-view', d.status === 'playing');
        show('finished-view', d.status === 'finished');
        const chatTime = d.status !== 'playing' || (g && g.phase === 'results');
        show('chat-block', !d.solo && chatTime);

        const host = d.players.find((p) => p.isHost);
        document.querySelectorAll('.host-name').forEach((el) => { el.textContent = host ? host.name : 'el anfitrión'; });

        if (d.status === 'lobby') renderLobby(d);
        if (d.status === 'playing') renderGame(d, g);
        if (d.status === 'finished') renderFinished(d, g);
        renderMessages(d.messages);
        tick();
    }

    function renderLobby(d) {
        renderedKey = null;
        const ul = $('player-list');
        ul.replaceChildren(...d.players.map((p) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center gap-2 py-3';

            const dot = document.createElement('span');
            dot.className = 'rounded-circle flex-shrink-0 ' + (p.isOnline ? 'bg-success' : 'bg-secondary-subtle');
            dot.style.cssText = 'width:.7rem;height:.7rem';
            dot.title = p.isOnline ? 'Conectado' : 'Desconectado';

            const name = document.createElement('span');
            name.className = 'flex-grow-1 text-truncate' + (p.isOnline ? '' : ' text-body-secondary');
            name.textContent = p.name;

            li.append(dot, name);
            if (p.id === d.me) li.append(badge('tú', 'text-bg-primary'));
            if (p.isHost) li.append(badge('👑 anfitrión', 'text-bg-warning'));
            return li;
        }));
        $('player-count').textContent = d.players.length + ' / ' + d.maxPlayers;

        const tooFew = d.players.length < 2;
        show('host-controls', d.isHost);
        show('waiting-host', !d.isHost);
        $('start-btn').disabled = tooFew;
        show('need-players', d.isHost && tooFew);
    }

    function badge(text, cls) {
        const s = document.createElement('span');
        s.className = 'badge rounded-pill ' + cls;
        s.textContent = text;
        return s;
    }

    function renderGame(d, g) {
        const inRound = g.phase === 'question' || g.phase === 'results';
        show('countdown', g.phase === 'countdown');
        show('question-block', inRound);
        show('results-block', g.phase === 'results');
        $('round-label').textContent = inRound ? 'Pregunta ' + g.round + ' de ' + g.totalRounds : '';
        $('category').textContent = inRound && g.question ? g.question.category : '';
        $('countdown-difficulty').textContent = g.difficulty;

        if (!inRound || !g.question) return;

        // Draw the buttons only when the question changes (not on every poll).
        const key = g.gameNumber + '-' + g.round;
        if (key !== renderedKey) {
            renderedKey = key;
            chosenOptionId = null;
            $('question-text').textContent = g.question.text;
            $('answer-options').replaceChildren(...g.question.options.map((o, i) => {
                const col = document.createElement('div');
                col.className = 'col-6';
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn btn-' + COLORS[i] + ' answer-btn w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-1';
                b.dataset.optionId = o.id;
                const shape = document.createElement('span');
                shape.className = 'shape';
                shape.textContent = SHAPES[i];
                const text = document.createElement('span');
                text.textContent = o.text;
                b.append(shape, text);
                b.addEventListener('click', () => sendAnswer(g.round, o.id));
                col.append(b);
                return col;
            }));
        }

        const myOption = g.myAnswer ? g.myAnswer.optionId : chosenOptionId;
        const correctIds = g.phase === 'results'
            ? g.question.options.filter((o) => o.isCorrect).map((o) => o.id)
            : [];
        document.querySelectorAll('.answer-btn').forEach((b) => {
            const id = Number(b.dataset.optionId);
            b.disabled = g.phase !== 'question' || myOption !== null;
            b.classList.toggle('chosen', id === myOption);
            b.classList.toggle('dimmed', g.phase === 'results' ? !correctIds.includes(id) : (myOption !== null && id !== myOption));
            // Bootstrap fades disabled buttons; we already control that with "dimmed".
            b.style.opacity = '';
            if (b.disabled && !b.classList.contains('dimmed')) b.style.opacity = '1';
        });

        // Timer bar only makes sense while the question is open.
        show($('timer-bar').closest('.d-flex'), g.phase === 'question');

        const status = $('answer-status');
        status.textContent = '';
        if (g.phase === 'question' && myOption !== null) {
            const online = d.players.filter((p) => p.isOnline).length;
            status.textContent = d.solo
                ? 'Respuesta enviada.'
                : 'Respuesta enviada. Han contestado ' + g.answeredCount + ' de ' + online + '.';
        }

        if (g.phase === 'results') renderResults(d, g);
    }

    function renderResults(d, g) {
        const fb = $('feedback');
        if (!g.myAnswer) {
            fb.className = 'alert alert-secondary text-center fw-semibold mb-3';
            fb.textContent = '⏰ Se acabó el tiempo. 0 puntos';
        } else if (g.myAnswer.isCorrect) {
            fb.className = 'alert alert-success text-center fw-semibold mb-3';
            fb.textContent = '✅ ¡Correcto! +' + g.myAnswer.points + ' puntos';
        } else {
            fb.className = 'alert alert-danger text-center fw-semibold mb-3';
            fb.textContent = '❌ ¡Fallaste! 0 puntos';
        }
        $('leaderboard').replaceChildren(...g.leaderboard.map((p) => leaderboardRow(p, 'li', true)));
    }

    function leaderboardRow(p, tag, withRoundPoints, medal) {
        const li = document.createElement(tag);
        li.className = 'list-group-item d-flex align-items-center gap-2' + (p.isMe ? ' list-group-item-primary' : '');
        if (medal !== undefined) {
            const m = document.createElement('span');
            m.className = 'fs-4';
            m.style.minWidth = '2ch';
            m.textContent = medal;
            li.append(m);
        }
        const name = document.createElement('span');
        name.className = 'flex-grow-1 text-truncate' + (p.isMe ? ' fw-semibold' : '');
        name.textContent = p.name + (p.isMe ? ' (tú)' : '');
        li.append(name);
        if (withRoundPoints && p.roundPoints !== null) {
            const plus = document.createElement('span');
            plus.className = 'small ' + (p.roundPoints > 0 ? 'text-success' : 'text-body-secondary');
            plus.textContent = '+' + p.roundPoints;
            li.append(plus);
        }
        const score = document.createElement('span');
        score.className = 'fw-bold font-monospace';
        score.textContent = p.score;
        li.append(score);
        return li;
    }

    function renderFinished(d, g) {
        renderedKey = null;
        const board = g.leaderboard;
        const medals = ['🥇', '🥈', '🥉'];
        $('final-leaderboard').replaceChildren(...board.map((p, i) =>
            leaderboardRow(p, 'li', false, medals[i] || (i + 1) + '.')));

        const me = board.find((p) => p.isMe);
        if (d.solo) {
            $('finished-emoji').textContent = '🎯';
            $('finished-title').textContent = (me ? me.score : 0) + ' puntos';
            $('finished-subtitle').textContent = 'Dificultad: ' + g.difficulty + '. ¿Lo mejoras?';
        } else {
            const winner = board[0];
            const tie = board.length > 1 && board[1].score === winner.score;
            $('finished-emoji').textContent = '🏆';
            $('finished-title').textContent = tie ? '¡Empate!' : (winner.isMe ? '¡Has ganado! 🎉' : '¡Ha ganado ' + winner.name + '!');
            $('finished-subtitle').textContent = 'Dificultad: ' + g.difficulty;
        }
        show('play-again-btn', d.isHost);
        show('waiting-play-again', !d.isHost);
        show('home-btn', true);
    }

    // Countdown numbers are redrawn 10 times per second from the deadline.
    function tick() {
        if (!state || !state.game || deadline === null) return;
        const g = state.game;
        const left = Math.max(0, deadline - performance.now());
        const secs = Math.ceil(left / 1000);
        if (g.phase === 'countdown') {
            $('countdown-number').textContent = Math.max(1, secs);
        } else if (g.phase === 'question') {
            const pct = g.durationMs ? (left / g.durationMs) * 100 : 0;
            const bar = $('timer-bar');
            bar.style.width = pct + '%';
            bar.className = 'progress-bar ' + (secs <= 5 ? 'bg-danger' : 'bg-primary');
            $('seconds-left').textContent = secs;
        } else if (g.phase === 'results') {
            $('next-in').textContent = (g.round >= g.totalRounds ? 'Resultado final en ' : 'Siguiente en ') + secs + ' s';
        }
    }
    setInterval(tick, 100);

    function renderMessages(messages) {
        if (!messages.length) return;
        const chat = $('chat');
        // Only auto-scroll if you were already at the bottom (do not move someone reading above).
        const atBottom = chat.scrollHeight - chat.scrollTop - chat.clientHeight < 40;
        $('chat-empty')?.remove();

        for (const m of messages) {
            if (m.id <= lastMessageId) continue;
            lastMessageId = m.id;

            const row = document.createElement('div');
            row.className = 'd-flex mb-2 ' + (m.isMine ? 'justify-content-end' : 'justify-content-start');
            const bubble = document.createElement('div');
            bubble.className = 'px-3 py-2 rounded-3 ' + (m.isMine ? 'bg-primary text-white' : 'bg-body-secondary');
            bubble.style.maxWidth = '80%';
            bubble.style.overflowWrap = 'anywhere';

            if (!m.isMine) {
                const author = document.createElement('div');
                author.className = 'small fw-semibold';
                author.textContent = m.name;
                bubble.append(author);
            }
            const body = document.createElement('div');
            body.textContent = m.body;
            const time = document.createElement('div');
            time.className = 'text-end opacity-75';
            time.style.fontSize = '.7rem';
            time.textContent = m.time;
            bubble.append(body, time);
            row.append(bubble);
            chat.append(row);
        }
        if (atBottom) chat.scrollTop = chat.scrollHeight;
    }

    // --- Actions -------------------------------------------------------------

    async function postJson(url, data) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data || {}),
        });
        let body = {};
        try { body = await r.json(); } catch (e) { /* no body */ }
        return { ok: r.ok, body };
    }

    async function sendAnswer(round, optionId) {
        if (chosenOptionId !== null) return;
        chosenOptionId = optionId;   // mark it at once, without waiting for the server
        render(state);
        try {
            const { ok, body } = await postJson(api + '/answer', { round, optionId });
            if (!ok) $('answer-status').textContent = body.error || 'No se pudo enviar la respuesta.';
        } catch (e) {
            chosenOptionId = null;   // network error: let them try again
            $('answer-status').textContent = 'Sin conexión. Vuelve a pulsar tu respuesta.';
            render(state);
            return;
        }
        refresh();
    }

    $('chat-form').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const input = $('chat-input');
        const body = input.value.trim();
        if (!body) return;
        const err = $('chat-error');
        try {
            const { ok, body: res } = await postJson(api + '/messages', { body });
            if (!ok) throw new Error(res.error || 'No se pudo enviar.');
            input.value = '';
            show(err, false);
            const chat = $('chat');
            chat.scrollTop = chat.scrollHeight;   // your own message is always visible
            refresh();
        } catch (e) {
            err.textContent = e.message === 'Failed to fetch' ? 'Sin conexión. Inténtalo otra vez.' : e.message;
            show(err, true);
        }
        input.focus();
    });

    async function start(difficulty) {
        const err = $('start-error');
        show(err, false);
        try {
            const { ok, body } = await postJson(api + '/start', { difficulty });
            if (!ok) { err.textContent = body.error || 'No se pudo empezar.'; show(err, true); }
        } finally { refresh(); }
    }

    $('start-btn').addEventListener('click', () => {
        $('start-btn').disabled = true;
        start($('difficulty').value);
    });

    // Play again: solo starts straight away; with rivals, everyone goes back to the lobby.
    $('play-again-btn').addEventListener('click', async () => {
        const b = $('play-again-btn');
        b.disabled = true;
        try {
            if (state.solo) await start(state.difficulty);
            else await postJson(api + '/lobby');
        } finally { b.disabled = false; refresh(); }
    });

    // Invite: on phones opens the share menu (WhatsApp, etc.); otherwise copies the link.
    $('share-btn').addEventListener('click', async () => {
        if (navigator.share) {
            try {
                await navigator.share({ title: 'Trivial', text: '¡Únete a mi sala del Trivial! Código ' + code, url: inviteLink });
                return;
            } catch (e) { if (e.name === 'AbortError') return; }
        }
        copyLink();
    });
    $('copy-btn').addEventListener('click', copyLink);

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(inviteLink);
        } catch (e) {
            // Without HTTPS the browser does not allow automatic copying: show it to copy by hand.
            window.prompt('Copia este enlace:', inviteLink);
            return;
        }
        show('copied', true);
        setTimeout(() => show('copied', false), 2000);
    }

    refresh();
})();
