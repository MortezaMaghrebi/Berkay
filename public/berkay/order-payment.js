document.addEventListener('click', event => {
    const button = event.target.closest('[data-open-payment]');
    const dialog = button && document.getElementById(button.dataset.openPayment);
    if (dialog && !dialog.open) dialog.showModal();
});

const notice = document.querySelector('[data-payment-notice]');
if (notice) {
    fetch(notice.dataset.paymentNotice, { headers: { Accept: 'application/json' }, cache: 'no-store' })
        .then(response => response.ok ? response.json() : null)
        .then(payload => {
            const order = payload?.order;
            if (!order) return;
            const dismissKey = 'bk-order-dismissed:' + order.id;
            try {
                if (sessionStorage.getItem(dismissKey)) return;
            } catch {}
            const link = notice.querySelector('[data-notice-order]');
            link.href = order.url;
            link.textContent = order.label;
            notice.querySelector('[data-notice-dismiss]').addEventListener('click', () => {
                notice.hidden = true;
                try { sessionStorage.setItem(dismissKey, '1'); } catch {}
            });
            notice.hidden = false;
        })
        .catch(() => {});
}
