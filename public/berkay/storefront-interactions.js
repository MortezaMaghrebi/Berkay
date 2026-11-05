(() => {
    const root = document.documentElement;
    root.dataset.bkInput = 'keyboard';
    document.addEventListener('pointerdown', () => { root.dataset.bkInput = 'pointer'; }, { passive: true, capture: true });
    document.addEventListener('keydown', () => { root.dataset.bkInput = 'keyboard'; }, { capture: true });
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    window.addEventListener('load', () => requestAnimationFrame(() => {
    document.querySelectorAll('.bk-category-menu').forEach(menu => {
        const trigger = menu.querySelector('summary');
        const panel = menu.querySelector('.bk-mega-menu');
        menu.addEventListener('toggle', () => {
            panel.getAnimations().forEach(animation => animation.cancel());
            if (menu.open && root.dataset.bkInput === 'pointer' && !reducedMotion.matches) {
                panel.animate([{opacity:0,transform:'translateY(-6px) scale(.985)'},{opacity:1,transform:'translateY(0) scale(1)'}], {duration:180,easing:'cubic-bezier(.23,1,.32,1)'});
            }
        });
        document.addEventListener('pointerdown', event => {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        }, {passive:true});
        menu.addEventListener('keydown', event => {
            if (event.key === 'Escape' && menu.open) {
                event.preventDefault(); event.stopPropagation(); menu.open = false; trigger.focus();
            }
        });
    });
    }));
})();
