(() => {
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('.bk-attribute-edit-row')) return;
        event.preventDefault();
        if (form.dataset.busy) return;
        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector('.bk-option-status');
        const isNew = !form.querySelector('[name="option_id"]');
        form.dataset.busy = 'true'; button.disabled = true;
        status.textContent = form.dataset.saving;
        try {
            const response = await fetch(form.action, {method:'POST', credentials:'same-origin', headers:{Accept:'application/json'}, body:new FormData(form)});
            const result = await response.json();
            if (!response.ok) {
                status.textContent = Object.values(result.errors || {}).flat().join(' ') || result.message || form.dataset.failed;
                return;
            }
            const template = document.createElement('template');
            template.innerHTML = result.html.trim();
            const row = template.content.firstElementChild;
            row.querySelector('.bk-option-status').textContent = result.message;
            if (isNew) {
                form.after(row); form.reset(); status.textContent = result.message;
                form.querySelector('[name="name"]').focus({preventScroll:true});
            } else {
                form.replaceWith(row);
                row.querySelector('button').focus({preventScroll:true});
            }
        } catch { status.textContent = form.dataset.failed; }
        finally {delete form.dataset.busy;button.disabled = false;}
    });
})();
