function initializeCampaignEditor() {
    const editor = document.getElementById('campaign-settings');
    if (!editor) return;

    document.getElementById('campaign-results')?.addEventListener('submit', event => {
        if (event.target.matches('[data-campaign-reset]')
            && !window.confirm('فرصت چرخش این مشتری تمدید شود؟ جایزهٔ تحویل‌نشده یا کد استفاده‌نشدهٔ این نتیجه لغو می‌شود.')) {
            event.preventDefault();
        }
    });

    const list = editor.querySelector('[data-prize-list]');
    const template = document.getElementById('campaign-prize-template');
    const add = editor.querySelector('[data-prize-add]');
    const title = editor.querySelector('#campaign-title');
    const description = editor.querySelector('#campaign-description');
    const wheel = editor.querySelector('input[type=checkbox][name=wheel]');
    const preview = editor.querySelector('[data-event-preview]');
    const saveButton = editor.querySelector('[data-campaign-save]');
    const image = preview.querySelector('img');
    const whole = new Intl.NumberFormat('fa-IR');
    const percent = new Intl.NumberFormat('fa-IR', {style: 'percent', maximumFractionDigits: 2});

    function updatePreview() {
        const selected = editor.querySelector('[name=event]:checked');
        const card = selected?.closest('label');
        preview.hidden = !selected?.value;
        if (preview.hidden) return;
        const source = card.querySelector('img').getAttribute('src');
        if (image.getAttribute('src') !== source) image.src = source;
        preview.querySelector('[data-preview-title]').textContent = title.value.trim() || card.querySelector('span').textContent;
        preview.querySelector('[data-preview-description]').textContent = description.value.trim();
        preview.querySelector('[data-preview-action]').textContent = wheel.checked ? 'شرکت در گردونه' : 'دیدن محصولات';
    }

    function updatePrizes() {
        const rows = [...list.querySelectorAll('[data-prize-row]')];
        const weights = rows.map(row => row.querySelector('[data-prize-field=label]').value.trim()
            ? Math.max(0, Number(row.querySelector('[data-prize-field=weight]').value)) : 0);
        const total = weights.reduce((sum, weight) => sum + weight, 0);
        rows.forEach((row, index) => {
            for (const field of row.querySelectorAll('[data-prize-field]')) {
                field.name = 'prizes[' + index + '][' + field.dataset.prizeField + ']';
            }
            row.querySelector('[data-prize-number]').textContent = 'گزینهٔ ' + whole.format(index + 1);
            row.querySelector('[data-prize-chance]').textContent = total && weights[index]
                ? 'شانس ' + percent.format(weights[index] / total) : '';
            const kind = row.querySelector('select').value;
            const amount = row.querySelector('[data-prize-value]');
            amount.hidden = !['percent', 'credit'].includes(kind);
            amount.querySelector('span').textContent = kind === 'percent' ? 'درصد تخفیف' : 'مبلغ به تومان';
            const input = amount.querySelector('input');
            input.disabled = amount.hidden;
            input.min = 1;
            input.max = kind === 'percent' ? 100 : 9000000;
            input.required = !amount.hidden;
        });
        editor.querySelector('[data-prize-count]').textContent = whole.format(rows.length) + ' از ۸ گزینه';
        editor.querySelector('[data-prize-empty]').hidden = rows.length > 0;
        if (add) add.disabled = rows.length >= 8;
    }

    if (add && template) {
        add.hidden = false;
        add.addEventListener('click', () => {
            list.append(template.content.cloneNode(true));
            updatePrizes();
            list.lastElementChild.querySelector('input').focus();
        });
        list.addEventListener('click', event => {
            const remove = event.target.closest('[data-prize-remove]');
            if (!remove) return;
            const row = remove.closest('[data-prize-row]');
            const neighbor = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            updatePrizes();
            (neighbor?.querySelector('input') || add).focus();
        });
    }

    editor.addEventListener('input', () => {
        updatePreview();
        updatePrizes();
        const saveState = editor.querySelector('[data-save-state]');
        if (saveState) saveState.textContent = 'تغییرات ذخیره نشده';
    });
    editor.addEventListener('submit', () => {
        if (!saveButton) return;

        saveButton.disabled = true;
        saveButton.textContent = 'در حال ذخیره…';

        const saveState = editor.querySelector('[data-save-state]');
        if (saveState) saveState.textContent = 'تنظیمات در حال ذخیره است…';
    });
    updatePreview();
    updatePrizes();
}

// The admin Vue app replaces its markup on window load.
const afterAdminMount = () => window.setTimeout(initializeCampaignEditor, 0);
if (document.readyState === 'complete') afterAdminMount();
else window.addEventListener('load', afterAdminMount, { once: true });
