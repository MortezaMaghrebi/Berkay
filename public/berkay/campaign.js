const form = document.querySelector('[data-campaign-spin]');
const disc = document.querySelector('.bk-wheel-disc');

if (form && disc && typeof disc.animate === 'function') {
    const button = form.querySelector('button');
    const status = form.querySelector('[data-wheel-status]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled) return;
        button.disabled = true;
        status.textContent = 'در حال ثبت نتیجهٔ گردونه…';

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
                const stop = 1800 - 360 * (result.segment + .5) / count;
                await disc.animate(
                    [{transform: 'rotate(0deg)'}, {transform: 'rotate(' + stop + 'deg)'}],
                    {duration: 2100, easing: 'cubic-bezier(.18,.65,.22,1)', fill: 'forwards'},
                ).finished;
            }

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
        }
    });
}
