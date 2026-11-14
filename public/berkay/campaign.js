const spin = document.querySelector('[data-campaign-spin]');
if (spin) {
    spin.addEventListener('submit', event => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        event.preventDefault();
        spin.querySelector('button').disabled = true;
        document.querySelector('.bk-campaign-wheel')?.classList.add('bk-campaign-spinning');
        window.setTimeout(() => HTMLFormElement.prototype.submit.call(spin), 650);
    });
}
