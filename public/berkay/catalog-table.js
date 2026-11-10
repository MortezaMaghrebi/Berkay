window.addEventListener('load', () => {
    const panel = document.querySelector('.bk-catalog-table');

    if (panel) {
        const all = panel.querySelector('[data-save-all]');
        const feedback = panel.querySelector('.bk-catalog-feedback');
        const variantTemplate = panel.querySelector('[data-variant-template]');
        const edits = new Map();
        let pending = false;
        let nextDraft = 0;

        function updateButtons() {
            all.disabled = pending || edits.size === 0;
            panel.querySelectorAll('[data-save-row]').forEach(button => {
                button.disabled = pending || !edits.has(button.closest('[data-row]'));
            });
        }

        function mark(row, fields) {
            edits.set(row, new Set([...(edits.get(row) || []), ...fields]));
            row.classList.add('is-edited');
            updateButtons();
        }

        function notice(message, error = false) {
            feedback.textContent = message;
            feedback.classList.toggle('bk-error', error);
        }

        async function request(url, method, data) {
            const response = await fetch(url, {
                method,
                headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':panel.dataset.csrf},
                body: JSON.stringify(data),
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || panel.dataset.error);

            return result;
        }

        async function save(rows) {
            if (pending || !rows.length) return;
            const payload = [];

            for (const row of rows) {
                const group = row.closest('[data-product]');
                const item = {key: row.dataset.id || row.dataset.key, id: Number(row.dataset.id) || null, parent: Number(group.dataset.product)};
                if (!item.id) item.color = Number(row.dataset.option);

                for (const name of edits.get(row)) {
                    const input = row.querySelector('[name="' + name + '"]');
                    if (!input || input.disabled || (name === 'quantity' && row.querySelector('[name=unlimited_stock]')?.checked && item.id)) continue;
                    if (!input.reportValidity()) return;
                    item[name] = input.type === 'checkbox' ? input.checked : input.type === 'number' ? input.valueAsNumber : input.value;
                }
                payload.push(item);
            }

            pending = true;
            rows.forEach(row => row.querySelectorAll('input:not(:disabled),select:not(:disabled)').forEach(input => {
                input.dataset.busy = '1';
                input.disabled = true;
            }));
            updateButtons();

            try {
                const result = await request(panel.dataset.update, 'PATCH', {rows:payload});
                for (const saved of result.rows) {
                    const row = rows.find(candidate => (candidate.dataset.id || candidate.dataset.key) === saved.key);
                    const group = row.closest('[data-product]');
                    row.dataset.id = saved.id;
                    group.dataset.type = saved.type;
                    edits.delete(row);
                    row.classList.remove('is-edited');

                    const submitted = payload.find(item => item.key === saved.key);
                    const preview = row.querySelector('[data-preview-url]');
                    if (preview && submitted?.status !== undefined) {
                        if (submitted.status) {
                            preview.href = preview.dataset.previewUrl;
                            preview.removeAttribute('aria-disabled');
                        } else {
                            preview.removeAttribute('href');
                            preview.setAttribute('aria-disabled', 'true');
                        }
                    }

                    if (saved.type === 'configurable') {
                        ['price','quantity'].forEach(name => {
                            const field = group.querySelector('[data-parent-row] [name="' + name + '"]');
                            if (!field) return;
                            field.disabled = true;
                            delete field.dataset.busy;
                        });

                        if (row.hasAttribute('data-parent-row') && submitted?.discount !== undefined) {
                            group.querySelectorAll('[data-variant]').forEach(child => {
                                if (!edits.get(child)?.has('discount')) child.querySelector('[name=discount]').value = submitted.discount;
                            });
                        }
                    }
                }
                for (const saved of result.rows) {
                    const group = panel.querySelector('[data-product="' + saved.parent + '"]');
                    group.querySelectorAll('[data-row][data-id]').forEach(row => {
                        const input = row.querySelector('[name=unlimited_stock]');
                        if (input && !edits.get(row)?.has('unlimited_stock') && Object.hasOwn(saved.stock, row.dataset.id)) {
                            input.checked = saved.stock[row.dataset.id];
                        }
                    });
                }
                notice(result.message);
            } catch (error) {
                notice(error.message, true);
            } finally {
                rows.forEach(row => row.querySelectorAll('[data-busy]').forEach(input => {
                    input.disabled = false;
                    delete input.dataset.busy;
                }));
                pending = false;
                updateButtons();
            }
        }

        panel.addEventListener('input', event => {
            const row = event.target.closest('[data-row]');
            if (row && event.target.name) mark(row, [event.target.name]);
        });

        panel.addEventListener('click', async event => {
            const button = event.target.closest('button');
            if (!button || pending) return;

            if (button.matches('[data-save-all]')) {
                await save([...panel.querySelectorAll('[data-row]')].filter(row => edits.has(row)));
                return;
            }

            const row = button.closest('[data-row]');
            if (button.matches('[data-save-row]')) {
                await save([row]);
                return;
            }

            const group = button.closest('[data-product]');
            if (!group) return;

            if (button.matches('[data-expand]')) {
                const expanded = button.getAttribute('aria-expanded') !== 'true';
                button.setAttribute('aria-expanded', String(expanded));
                group.querySelectorAll('[data-variant],[data-palette]').forEach(child => { child.hidden = !expanded; });
            }

            if (button.matches('[data-color]')) {
                const existing = [...group.querySelectorAll('[data-variant]')].find(child => child.dataset.option === button.dataset.color);
                if (existing) {
                    existing.querySelector('[name=name]').focus();
                    return;
                }
                const parent = group.querySelector('[data-parent-row]');
                if (group.dataset.type === 'simple' && !group.querySelector('[data-variant]') && !confirm(panel.dataset.confirmConvert)) return;
                const child = variantTemplate.querySelector('[data-row]').cloneNode(true);
                child.dataset.key = 'new-' + ++nextDraft;
                child.dataset.option = button.dataset.color;
                child.hidden = false;
                child.querySelector('[data-swatch]').style.setProperty('--swatch', button.style.getPropertyValue('--swatch'));
                child.querySelector('[name=name]').value = (parent.querySelector('[name=name]').value + ' – ' + button.dataset.label).slice(0, 200);
                child.querySelector('[name=price]').value = parent.querySelector('[name=price]').value || '0';
                if (group.dataset.type === 'simple' && !group.querySelector('[data-variant]')) {
                    child.querySelector('[name=quantity]').value = parent.querySelector('[name=quantity]').value || '0';
                }
                button.closest('tr').before(child);
                child.querySelector('[name=unlimited_stock]').checked = parent.querySelector('[name=unlimited_stock]').checked;
                mark(child, ['name','price','quantity','status','unlimited_stock']);
                child.querySelector('[name=price]').focus();
            }

            if (button.matches('[data-delete-row]') && confirm(panel.dataset.confirmDelete)) {
                try {
                    pending = true;
                    updateButtons();
                    if (row.dataset.id) {
                        const base = new URL(panel.dataset.remove);
                        base.pathname = base.pathname.replace(/\/0\/0$/, '/' + group.dataset.product + '/' + row.dataset.id);
                        const result = await request(base, 'DELETE');
                        notice(result.message);
                    }
                    const target = row.hasAttribute('data-parent-row') ? group : row;
                    if (target === group) group.querySelectorAll('[data-row]').forEach(child => edits.delete(child));
                    edits.delete(row);
                    target.remove();
                } catch (error) {
                    notice(error.message, true);
                } finally {
                    pending = false;
                    updateButtons();
                }
            }
        });

        panel.querySelector('[data-adjust-form]').addEventListener('submit', async event => {
            event.preventDefault();
            if (pending) return;
            if (edits.size) {
                notice(panel.dataset.unsaved, true);
                return;
            }
            if (!confirm(panel.dataset.confirmAdjust)) return;

            pending = true;
            updateButtons();
            const data = Object.fromEntries(new FormData(event.target));
            try {
                const result = await request(panel.dataset.adjust, 'POST', data);
                sessionStorage.setItem('berkay-catalog-message', result.message);
                location.reload();
            } catch (error) {
                notice(error.message, true);
                pending = false;
                updateButtons();
            }
        });

        window.addEventListener('beforeunload', event => {
            if (!edits.size) return;
            event.preventDefault();
            event.returnValue = '';
        });

        const previous = sessionStorage.getItem('berkay-catalog-message');
        if (previous) {
            notice(previous);
            sessionStorage.removeItem('berkay-catalog-message');
        }
    }
}, {once: true});
