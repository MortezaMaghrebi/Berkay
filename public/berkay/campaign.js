function note(audio, pitch, volume = .06, delay = 0, length = .045) {
    if (audio?.state !== 'running') return;
    const now = audio.currentTime + delay;
    const oscillator = audio.createOscillator();
    const gain = audio.createGain();
    oscillator.type = 'triangle';
    oscillator.frequency.value = pitch;
    gain.gain.setValueAtTime(volume, now);
    gain.gain.exponentialRampToValueAtTime(.001, now + length);
    oscillator.connect(gain);
    gain.connect(audio.destination);
    oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
    oscillator.start(now);
    oscillator.stop(now + length);
}

function celebrate() {
    const layer = document.createElement('div');
    layer.className = 'bk-wheel-confetti';
    layer.setAttribute('aria-hidden', 'true');
    const colors = ['#dba54e', '#7b4131', '#f4d59a', '#496d5c', '#ac5563'];
    for (let i = 0; i < 64; i++) {
        const piece = document.createElement('i');
        piece.style.left = Math.random() * 100 + '%';
        piece.style.background = colors[i % colors.length];
        layer.append(piece);
    }
    document.body.append(layer);
    for (const piece of layer.children) {
        piece.animate([
            {transform: 'translateY(0) rotate(0deg)'},
            {transform: 'translate(' + (Math.random() * 180 - 90) + 'px, 105vh) rotate(' + (540 + Math.random() * 720) + 'deg)'},
        ], {duration: 1000 + Math.random() * 750, delay: Math.random() * 260, fill: 'both'});
    }
    return new Promise(resolve => window.setTimeout(() => { layer.remove(); resolve(); }, 2050));
}

function initializeWheel() {
    const form = document.querySelector('[data-campaign-spin]');
    const disc = document.querySelector('.bk-wheel-disc');
    if (!form || !disc || typeof disc.animate !== 'function') return;
    const button = form.querySelector('button[type=submit]');
    const status = form.querySelector('[data-wheel-status]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const Audio = window.AudioContext || window.webkitAudioContext;
    const audioButton = form.querySelector('[data-wheel-audio]');
    let muted = false;
    audioButton.hidden = !Audio;
    audioButton.addEventListener('click', () => {
        muted = !muted;
        audioButton.setAttribute('aria-pressed', String(muted));
        audioButton.setAttribute('aria-label', muted ? 'روشن کردن صدای گردونه' : 'بی‌صدا کردن گردونه');
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled) return;
        button.disabled = true;
        status.textContent = 'در حال ثبت نتیجهٔ گردونه…';
        let audio;
        if (Audio && !muted) {
            try {
                audio = new Audio();
                audio.resume().catch(() => {});
            } catch { /* Audio is optional. */ }
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('Spin failed');

            const result = await response.json();
            const count = Number(form.dataset.segments);
            if (!motion.matches && Number.isInteger(result.segment) && result.segment >= 0 && result.segment < count) {
                const stop = 3600 - 360 * (result.segment + .5) / count;
                const animation = disc.animate(
                    [{transform: 'rotate(0deg)'}, {transform: 'rotate(' + stop + 'deg)'}],
                    {duration: 6500, easing: 'cubic-bezier(.18,.65,.22,1)', fill: 'forwards'},
                );
                let frame, previous = -1;
                let lastTick = 0;
                const tick = time => {
                    const step = Math.floor((animation.effect.getComputedTiming().progress ?? 0) * stop * count / 360);
                    if (step !== previous && time - lastTick >= 45) {
                        if (!muted) note(audio, step % 2 ? 620 : 780);
                        previous = step;
                        lastTick = time;
                    }
                    frame = requestAnimationFrame(tick);
                };
                if (audio) frame = requestAnimationFrame(tick);
                try { await animation.finished; }
                finally { if (frame !== undefined) cancelAnimationFrame(frame); }
            }

            if (result.won) status.textContent = 'تبریک! جایزهٔ شما: ' + result.label;

            let celebration = result.won && !motion.matches ? celebrate() : null;
            if (!muted && audio?.state === 'running' && Number.isInteger(result.segment)) {
                note(audio, 523, .08, 0, .16);
                note(audio, result.won ? 784 : 392, .08, .11, .21);
                celebration ??= new Promise(resolve => window.setTimeout(resolve, 370));
            }
            await celebration;

            const target = new URL(result.url, window.location.href);
            if (target.origin !== window.location.origin) throw new Error('Invalid destination');
            if (target.pathname === window.location.pathname && target.search === window.location.search) {
                window.location.hash = target.hash;
                window.location.reload();
            } else {
                window.location.assign(target.href);
            }
        } catch {
            status.textContent = 'دریافت نتیجه ممکن نشد. دوباره بزنید تا نتیجهٔ ثبت‌شده نمایش داده شود.';
            button.disabled = false;
        } finally {
            audio?.close().catch(() => {});
        }
    });
}

// Bind to the form rendered by Vue, not the nodes it replaces.
const afterShopMount = () => window.setTimeout(initializeWheel, 0);
if (document.readyState === 'complete') afterShopMount();
else {
    let scheduled = false;
    const schedule = () => {
        if (scheduled) return;
        scheduled = true;
        afterShopMount();
    };
    document.addEventListener('DOMContentLoaded', schedule, { once: true });
    window.addEventListener('load', schedule, { once: true });
}
