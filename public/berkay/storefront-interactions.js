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
        let hoverCloseTimer;

        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            trigger.addEventListener('click', event => {
                if (event.detail > 0) {
                    event.preventDefault();
                    menu.open = true;
                }
            });

            menu.addEventListener('pointerenter', event => {
                if (event.pointerType !== 'mouse') return;

                window.clearTimeout(hoverCloseTimer);
                menu.open = true;
            });

            menu.addEventListener('pointerleave', event => {
                if (event.pointerType !== 'mouse' || menu.contains(event.relatedTarget)) return;

                hoverCloseTimer = window.setTimeout(() => {
                    if (!menu.contains(document.activeElement)) menu.open = false;
                }, 120);
            });
        }

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

function enableMouseDrag(track) {
    let gesture = null;
    let suppressClick = false;

    track.addEventListener('pointerdown', event => {
        suppressClick = false;
        if (event.pointerType !== 'mouse' || event.button !== 0 || !track.classList.contains('is-scrollable')) return;

        gesture = { id: event.pointerId, x: event.clientX, scroll: track.scrollLeft };
    });

    track.addEventListener('pointermove', event => {
        if (gesture?.id !== event.pointerId) return;

        const distance = event.clientX - gesture.x;
        if (!suppressClick) {
            if (Math.abs(distance) < 6) return;

            suppressClick = true;
            track.setPointerCapture(event.pointerId);
            track.classList.add('is-dragging');
        }

        event.preventDefault();
        track.scrollLeft = gesture.scroll - distance;
    });

    const endGesture = event => {
        if (gesture?.id !== event.pointerId) return;

        gesture = null;
        track.classList.remove('is-dragging');
    };

    track.addEventListener('pointerup', endGesture);
    track.addEventListener('pointercancel', endGesture);
    track.addEventListener('lostpointercapture', endGesture);
    track.addEventListener('pointerleave', event => {
        if (!track.hasPointerCapture(event.pointerId)) endGesture(event);
    });
    track.addEventListener('dragstart', event => {
        if (gesture) event.preventDefault();
    });
    track.addEventListener('click', event => {
        if (!suppressClick || event.detail === 0) return;

        suppressClick = false;
        event.preventDefault();
        event.stopPropagation();
    }, true);

    const updateDraggable = () => track.classList.toggle('is-scrollable', track.scrollWidth - track.clientWidth >= 2);
    new ResizeObserver(updateDraggable).observe(track);
    updateDraggable();
}

window.addEventListener('DOMContentLoaded', async () => {
    document.querySelectorAll('.bk-category-strip.bk-drag-scroll').forEach(enableMouseDrag);

    const productView = document.querySelector('[data-bk-product-view]');

    if (productView && window.axios) {
        window.axios.post(productView.dataset.bkProductView).catch(() => {});
    }

    const collections = document.querySelector('[data-bk-collections]');
    if (!collections) return;

    try {
        const response = await fetch(collections.dataset.bkCollections);
        if (!response.ok) return;
        collections.innerHTML = await response.text();
    } catch {
        return;
    }

    collections.querySelectorAll('.bk-product-track').forEach(enableMouseDrag);
});
