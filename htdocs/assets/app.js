'use strict';

document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});

const dashboard = document.querySelector('[data-results]');
if (dashboard) {
    const state = document.getElementById('results-state');
    const number = new Intl.NumberFormat('es-PE');
    const percent = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 2 });
    const time = new Intl.DateTimeFormat('es-PE', { timeStyle: 'medium', timeZone: 'America/Lima' });

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function render({ totales, participantes }) {
        const metrics = element('div', 'metrics');
        for (const [key, label] of Object.entries({ estudiantes: 'Estudiantes que votaron', miss: 'Votos para Miss', mister: 'Votos para Mister' })) {
            const metric = element('div', 'metric');
            metric.append(element('strong', '', number.format(totales[key])), label);
            metrics.append(metric);
        }
        const groups = element('div', 'admin-grid grid gap-5 lg:grid-cols-2');
        for (const [category, label] of Object.entries({ miss: 'Miss', mister: 'Mister' })) {
            const panel = element('section', 'panel');
            panel.append(element('h3', '', label));
            const candidates = participantes.filter((row) => row.categoria === category);
            const list = element('ul', 'result-list');
            for (const candidate of candidates) {
                const votes = Number(candidate.votos);
                const ratio = Number(totales[category]) ? votes / Number(totales[category]) * 100 : 0;
                const item = element('li');
                item.append(element('strong', '', candidate.nombre + (Number(candidate.activo) ? '' : ' · Retirado')));
                item.append(element('span', 'result-meta', number.format(votes) + (votes === 1 ? ' voto' : ' votos') + ' · ' + percent.format(ratio) + ' %'));
                const bar = element('progress');
                bar.max = 100;
                bar.value = ratio;
                bar.setAttribute('aria-label', 'Porcentaje de votos de ' + candidate.nombre);
                item.append(bar);
                list.append(item);
            }
            panel.append(candidates.length ? list : element('p', '', 'Aún no hay participantes.'));
            groups.append(panel);
        }
        dashboard.replaceChildren(metrics, groups);
    }

    let previous = '';
    async function refresh() {
        let retry = true;
        const controller = new AbortController();
        const deadline = window.setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(dashboard.dataset.results, { cache: 'no-store', signal: controller.signal });
            if (response.status === 403) {
                dashboard.replaceChildren();
                state.textContent = 'La sesión ya no está activa. Vuelve a ingresar.';
                retry = false;
                return;
            }
            if (!response.ok) throw new Error('Resultados no disponibles');
            const payload = await response.text();
            if (payload !== previous) {
                render(JSON.parse(payload));
                state.textContent = 'Conteo actualizado a las ' + time.format(new Date()) + ' (Lima).';
                previous = payload;
            } else if (state.dataset.error) {
                state.textContent = 'Conteo actualizado a las ' + time.format(new Date()) + ' (Lima).';
            }
            delete state.dataset.error;
        } catch {
            if (!previous) dashboard.textContent = 'Resultados temporalmente no disponibles.';
            state.dataset.error = '1';
            state.textContent = 'Se reintentará la consulta en unos segundos.';
        } finally {
            clearTimeout(deadline);
            dashboard.setAttribute('aria-busy', 'false');
            if (retry) window.setTimeout(refresh, 5000);
        }
    }
    refresh();
}

const photos = document.querySelector('#fotos');
if (photos && photos.firstElementChild) {
    const controls = document.querySelector('.carousel-controls');
    const [previous, next] = controls.querySelectorAll('button');
    function syncPhotos() {
        controls.hidden = photos.scrollWidth <= photos.clientWidth + 1;
        previous.disabled = photos.scrollLeft <= 1;
        next.disabled = photos.scrollLeft + photos.clientWidth >= photos.scrollWidth - 1;
    }
    for (const button of [previous, next]) {
        button.addEventListener('click', () => {
            const step = photos.firstElementChild.getBoundingClientRect().width + parseFloat(getComputedStyle(photos).columnGap);
            photos.scrollBy({left: Number(button.dataset.step) * step, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
        });
    }
    photos.addEventListener('scroll', syncPhotos, {passive:true});
    window.addEventListener('resize', syncPhotos);
    syncPhotos();
}

const passwordForm = document.querySelector('[data-password-form]');
if (passwordForm) {
    for (const button of passwordForm.querySelectorAll('.reveal')) {
        const field = document.getElementById(button.getAttribute('aria-controls'));
        button.hidden = false;
        button.addEventListener('click', () => {
            const reveal = field.type === 'password';
            field.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Ocultar' : 'Mostrar';
            button.setAttribute('aria-label', button.textContent + ': ' + field.labels[0].textContent);
        });
    }
    const { nueva, confirmacion } = passwordForm.elements;
    const feedback = document.getElementById('clave-feedback');
    passwordForm.addEventListener('input', () => {
        const lengthOK = [...nueva.value].length >= 8;
        const bytesOK = new TextEncoder().encode(nueva.value).length <= 72;
        const mismatch = confirmacion.value !== '' && confirmacion.value !== nueva.value;
        nueva.setCustomValidity(nueva.value && !lengthOK ? 'Usa al menos 8 caracteres.' : !bytesOK ? 'La contraseña supera los 72 bytes.' : '');
        confirmacion.setCustomValidity(mismatch ? 'Las contraseñas deben coincidir.' : '');
        const ready = lengthOK && bytesOK && confirmacion.value !== '' && !mismatch;
        feedback.textContent = ready ? 'Las contraseñas coinciden.' : nueva.validationMessage && nueva.value ? nueva.validationMessage : mismatch ? 'Las contraseñas todavía no coinciden.' : 'Usa al menos 8 caracteres.';
        feedback.dataset.valid = ready ? 'yes' : mismatch || !bytesOK ? 'no' : '';
    });
}

for (const album of document.querySelectorAll('[data-album]')) {
    const images = album.querySelectorAll('img');
    let current = 0;
    for (const button of album.querySelectorAll('[data-turn]')) {
        button.addEventListener('click', () => {
            images[current].hidden = true;
            current = (current + Number(button.dataset.turn) + images.length) % images.length;
            images[current].hidden = false;
            album.querySelector('span').textContent = (current + 1) + ' de ' + images.length;
        });
    }
}

// The strip also supports native touch scrolling when JavaScript is unavailable.
const viewer = document.querySelector('.portrait-dialog');
if (viewer) {
    const fullPhoto = viewer.querySelector('[data-full-photo]');
    const title = viewer.querySelector('#viewer-title');
    const navigation = viewer.querySelector('.viewer-nav');
    let activeAlbum;
    let activeIndex = 0;

    function displayPhoto(index) {
        const { links } = activeAlbum;
        activeIndex = (index + links.length) % links.length;
        const image = links[activeIndex].querySelector('img');
        fullPhoto.src = links[activeIndex].href;
        fullPhoto.alt = image.alt;
        title.textContent = activeAlbum.name;
        navigation.hidden = links.length < 2;
        navigation.querySelector('span').textContent = (activeIndex + 1) + ' / ' + links.length;
    }

    for (const album of document.querySelectorAll('[data-portrait-album]')) {
        const strip = album.querySelector('.album-slides');
        const links = Array.from(strip.querySelectorAll('.album-slide'));
        const controls = album.querySelector('.album-tools');
        function slideTo(index) {
            const target = links[(index + links.length) % links.length];
            strip.scrollTo({left: target.offsetLeft - links[0].offsetLeft, behavior: 'instant'});
        }
        function currentSlide() {
            return Math.max(0, Math.min(links.length - 1, Math.round(strip.scrollLeft / Math.max(1, strip.clientWidth))));
        }
        if (controls) {
            controls.hidden = false;
            const indicator = controls.querySelector('span');
            strip.addEventListener('scroll', () => {
                indicator.textContent = (currentSlide() + 1) + ' / ' + links.length;
            }, {passive:true});
            for (const arrow of controls.querySelectorAll('[data-slide]')) {
                arrow.addEventListener('click', () => slideTo(currentSlide() + Number(arrow.dataset.slide)));
            }
        }
        if (typeof viewer.showModal !== 'function') continue;
        for (const [index, link] of links.entries()) {
            link.addEventListener('click', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                activeAlbum = {links, slideTo, name: album.closest('article').querySelector('h3').textContent};
                displayPhoto(index);
                viewer.showModal();
            });
        }
    }
    viewer.querySelector('[data-close]').addEventListener('click', () => viewer.close());
    viewer.addEventListener('close', () => activeAlbum.slideTo(activeIndex));
    for (const arrow of navigation.querySelectorAll('[data-move]')) {
        arrow.addEventListener('click', () => displayPhoto(activeIndex + Number(arrow.dataset.move)));
    }
    viewer.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            displayPhoto(activeIndex + (event.key === 'ArrowLeft' ? -1 : 1));
        }
    });
}

const clipForm = document.querySelector('[data-clip-editor]');
if (clipForm) {
    const input = clipForm.querySelector('[data-original]');
    const preview = clipForm.querySelector('[data-preview]');
    const slider = clipForm.querySelector('[data-start]');
    const play = clipForm.querySelector('[data-play]');
    const publish = clipForm.querySelector('[data-publish]');
    const status = clipForm.querySelector('[data-clip-status]');
    const maxSeconds = 15;
    const maxBytes = 4 * 1024 * 1024;
    let objectURL = '';
    let revision = 0;
    let previewEnd = null;
    const number = new Intl.NumberFormat('es-PE', {maximumFractionDigits:1});

    function mediaEvent(media, event, change) {
        return new Promise((resolve, reject) => {
            const clean = () => {
                clearTimeout(timer);
                media.removeEventListener(event, done);
                media.removeEventListener('error', fail);
            };
            const done = () => { clean(); resolve(); };
            const fail = () => { clean(); reject(new Error('El dispositivo no pudo leer este video. Prueba con un MP4.')); };
            const timer = setTimeout(fail, 12000);
            media.addEventListener(event, done, {once:true});
            media.addEventListener('error', fail, {once:true});
            try { change(); } catch { fail(); }
        });
    }
    async function seek(media, time) {
        if (Math.abs(media.currentTime - time) < .02 && media.readyState >= 2) return;
        await mediaEvent(media, 'seeked', () => { media.currentTime = time; });
    }
    function showTime() {
        const start = Number(slider.value);
        clipForm.querySelector('output').textContent = number.format(start) + ' s – ' + number.format(Math.min(start + maxSeconds, preview.duration)) + ' s';
    }
    async function suggestStart(media, version) {
        const canvas = document.createElement('canvas');
        canvas.width = 48;
        canvas.height = 32;
        const ctx = canvas.getContext('2d', {willReadFrequently:true});
        let best = 0, bestScore = -1;
        const last = Math.max(0, media.duration - maxSeconds);
        for (let i = 0; i < 6; i++) {
            if (version !== revision) return 0;
            const start = last * i / 5;
            await seek(media, start + maxSeconds / 2);
            ctx.drawImage(media, 0, 0, 48, 32);
            const pixels = ctx.getImageData(0, 0, 48, 32).data;
            let detail = 0;
            for (let k = 4; k < pixels.length; k += 4) {
                const light = pixels[k+1];
                if (light > 24 && light < 232) detail += Math.abs(light - pixels[k-3]);
            }
            if (detail > bestScore) { bestScore = detail; best = start; }
        }
        return best;
    }
    input.addEventListener('change', async () => {
        const version = ++revision;
        preview.pause();
        previewEnd = null;
        if (objectURL) URL.revokeObjectURL(objectURL);
        const file = input.files[0];
        publish.disabled = true;
        play.hidden = true;
        slider.closest('label').hidden = true;
        preview.hidden = true;
        preview.removeAttribute('src');
        preview.load();
        status.textContent = '';
        if (!file) return;
        objectURL = URL.createObjectURL(file);
        try {
            status.textContent = 'Leyendo video…';
            await mediaEvent(preview,'loadeddata', () => { preview.preload = 'auto'; preview.src = objectURL; });
            if (version !== revision) return;
            if (!Number.isFinite(preview.duration) || preview.duration <= 0) throw new Error('El video no indica una duración válida. Exporta el original como MP4.');
            preview.hidden = false;
            slider.max = Math.max(0,preview.duration - maxSeconds);
            slider.value = 0;
            if (preview.duration > maxSeconds) {
                status.textContent = 'Buscando un fragmento con buena luz y detalle…';
                const start = await suggestStart(preview,version);
                if (version !== revision) return;
                slider.value = start;
                await seek(preview,Number(slider.value));
                slider.closest('label').hidden = false;
            }
            if (version !== revision) return;
            showTime();
            status.textContent = preview.duration > maxSeconds ? 'Fragmento sugerido. Mueve el control para elegir otro momento.' : 'Video listo para previsualizar.';
            play.hidden = false;
            publish.disabled = false;
        } catch (error) { if (version === revision) status.textContent = error.message; }
    });
    slider.addEventListener('input', () => {
        previewEnd = null;
        preview.pause();
        preview.currentTime = Number(slider.value);
        showTime();
    });
    play.addEventListener('click', async () => {
        try {
            const start = Number(slider.value);
            await seek(preview,start);
            previewEnd = Math.min(start + maxSeconds, preview.duration);
            await preview.play();
        } catch (error) { status.textContent = error.message; }
    });
    preview.addEventListener('timeupdate', () => {
        if (previewEnd !== null && preview.currentTime >= previewEnd) { preview.pause(); previewEnd = null; }
    });

    async function encode(start, length) {
        const Audio = window.AudioContext || window.webkitAudioContext;
        const format = window.MediaRecorder && ['video/mp4','video/webm;codecs=vp8,opus','video/webm'].find(t => MediaRecorder.isTypeSupported(t));
        const canvas = document.createElement('canvas');
        if (!format || !canvas.captureStream || !Audio) throw new Error('Este navegador solo admite clips ya recortados. Elige un MP4 de hasta 15 s y 4 MB.');
        const audio = new Audio();
        const media = document.createElement('video');
        media.playsInline = true;
        media.preload = 'auto';
        let stream, timer, watchdog, recorder;
        try {
            await audio.resume();
            await mediaEvent(media,'loadeddata', () => { media.src = objectURL; });
            await seek(media,start);
            const ratio = Math.min(1,720 / Math.max(media.videoWidth, media.videoHeight));
            canvas.width = Math.max(2,2 * Math.round(media.videoWidth * ratio / 2));
            canvas.height = Math.max(2,2 * Math.round(media.videoHeight * ratio / 2));
            const ctx = canvas.getContext('2d');
            const paint = () => ctx.drawImage(media,0,0,canvas.width,canvas.height);
            paint();
            stream = canvas.captureStream(24);
            const output = audio.createMediaStreamDestination();
            audio.createMediaElementSource(media).connect(output);
            for (const track of output.stream.getAudioTracks()) stream.addTrack(track);
            recorder = new MediaRecorder(stream,{mimeType:format,videoBitsPerSecond:950000,audioBitsPerSecond:64000});
            const chunks = [];
            const blob = await new Promise((resolve,reject) => {
                recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
                recorder.onerror = () => reject(new Error('No se pudo codificar este video.'));
                recorder.onstop = () => resolve(new Blob(chunks,{type:format.split(';')[0]}));
                const stop = () => { if (recorder.state === 'recording') recorder.stop(); };
                media.onended = stop;
                watchdog = setTimeout(() => reject(new Error('El recorte se interrumpió. Mantén la pestaña visible e inténtalo otra vez.')), (length+12)*1000);
                recorder.start();
                media.play().catch(reject);
                timer = setInterval(() => {
                    paint();
                    const elapsed = media.currentTime - start;
                    status.textContent = 'Preparando clip… ' + Math.min(100,Math.round(elapsed*100/length)) + ' %';
                    if (elapsed >= length - .04) stop();
                },40);
            });
            if (!blob.size || blob.size > maxBytes) throw new Error('El clip supera 4 MB. Elige un archivo de menor resolución.');
            return new File([blob], format.startsWith('video/mp4')?'clip.mp4':'clip.webm',{type:blob.type});
        } finally {
            clearInterval(timer);
            clearTimeout(watchdog);
            if (recorder && recorder.state !== 'inactive') recorder.stop();
            media.pause();
            media.removeAttribute('src');
            media.load();
            if (stream) stream.getTracks().forEach(track=>track.stop());
            await audio.close();
        }
    }
    clipForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (publish.disabled) return;
        const source = input.files[0];
        const start = Number(slider.value);
        preview.pause();
        publish.disabled = input.disabled = slider.disabled = play.disabled = true;
        try {
            status.textContent = 'Preparando el video…';
            const ready = source.size <= maxBytes && preview.duration <= maxSeconds && ['video/mp4','video/webm'].includes(source.type);
            const clip = ready ? source : await encode(start,Math.min(maxSeconds,preview.duration - start));
            const files = new DataTransfer();
            files.items.add(clip);
            const upload = document.createElement('input');
            upload.type = 'file'; upload.name = 'clip'; upload.hidden = true; upload.files = files.files;
            clipForm.append(upload);
            status.textContent = 'Subiendo clip…';
            clipForm.submit();
        } catch (error) {
            status.textContent = error.message || 'No se pudo preparar el video.';
            publish.disabled = input.disabled = slider.disabled = play.disabled = false;
        }
    });
}
