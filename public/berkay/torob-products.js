function initializeTorobProducts() {
    const form = document.getElementById('torob-bulk');
    if (!form) return;

    const products = [...document.querySelectorAll('[data-torob-product]')];
    const page = form.querySelector('[data-torob-page]');
    const filtered = form.querySelector('[data-torob-filtered]');
    const count = form.querySelector('[data-torob-count]');
    const actions = form.querySelectorAll('button[type=submit]');
    const number = new Intl.NumberFormat(document.documentElement.lang.replaceAll('_', '-') || undefined);

    function updateSelection() {
        const allResults = filtered?.checked;
        const checked = products.filter(product => product.checked).length;
        const total = allResults ? Number(form.dataset.resultCount) : checked;

        for (const product of products) product.disabled = Boolean(allResults);
        page.checked = Boolean(allResults) || checked === products.length;
        page.indeterminate = !allResults && checked > 0 && checked < products.length;
        page.disabled = Boolean(allResults);
        for (const action of actions) action.disabled = total === 0;
        count.textContent = form.dataset.pickedLabel.replace(':count', number.format(total));
    }

    page.closest('label').hidden = false;
    page.addEventListener('change', () => {
        for (const product of products) product.checked = page.checked;
        updateSelection();
    });

    filtered?.addEventListener('change', updateSelection);
    for (const product of products) product.addEventListener('change', updateSelection);
    updateSelection();
}

const afterAdminMount = () => window.setTimeout(initializeTorobProducts, 0);
if (document.readyState === 'complete') afterAdminMount();
else window.addEventListener('load', afterAdminMount, { once: true });
