const form = document.querySelector('[data-integration-form]');

if (form) {
    const enabled = form.elements.namedItem('zarinpal_active');
    const mode = form.elements.namedItem('zarinpal_sandbox');
    const warning = form.querySelector('[data-payment-warning]');
    const saveStatus = document.querySelector('[data-integration-save-status]');
    const save = document.querySelector('button[type="submit"][form="payment"]');

    function updatePaymentWarning() {
        warning.hidden = enabled.value !== '1' || mode.value !== '0';
    }

    updatePaymentWarning();
    for (const field of form.elements) {
        field.addEventListener('input', () => {
            updatePaymentWarning();
            if (saveStatus) saveStatus.textContent = 'تغییرات هنوز ذخیره نشده‌اند.';
        });
    }

    form.addEventListener('submit', () => {
        if (saveStatus) saveStatus.textContent = 'در حال ذخیرهٔ تنظیمات…';
        if (save) save.disabled = true;
    });

    window.addEventListener('pageshow', () => {
        if (save) save.disabled = false;
    });
}

const copy = document.querySelector('[data-copy-endpoint]');

if (copy) {
    const endpoint = document.querySelector('#torob-endpoint');
    const status = document.querySelector('[data-copy-status]');
    copy.hidden = false;

    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(endpoint.value);
            status.textContent = 'نشانی کپی شد.';
        } catch {
            endpoint.focus();
            endpoint.select();
            status.textContent = 'نشانی انتخاب شد. آن را کپی کنید.';
        }
    });
}
