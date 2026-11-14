const digits = value => value.replace(/[۰-۹٠-٩]/g, digit => String(digit.charCodeAt(0) & 15)).replace(/\D/g, '');

for (const group of document.querySelectorAll('[data-otp]')) {
    const code = group.querySelector('[name="code"]');
    const boxes = group.querySelector('fieldset');
    const fields = [...boxes.querySelectorAll('input')];
    function focus(index) {
        fields[index].focus();
        fields[index].select();
    }
    function fill(value, index) {
        const text = digits(value);
        if (text.length === fields.length) index = 0;
        const part = text.slice(0, fields.length - index);
        if (!part) {
            fields[index].value = '';
            return;
        }
        [...part].forEach((digit, offset) => { fields[index + offset].value = digit; });
        focus(Math.min(index + part.length, fields.length - 1));
    }
    fields.forEach((field, index) => {
        field.disabled = false;
        field.required = true;
        field.addEventListener('input', () => fill(field.value, index));
        field.addEventListener('focus', () => field.select());
        field.addEventListener('paste', event => {
            event.preventDefault();
            fill(event.clipboardData.getData('text'), index);
        });
        field.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !field.value && index > 0) {
                event.preventDefault();
                fields[index - 1].value = '';
                focus(index - 1);
            }
            const next = index + (event.key === 'ArrowLeft' ? -1 : event.key === 'ArrowRight' ? 1 : 0);
            if (next !== index && fields[next]) {
                event.preventDefault();
                focus(next);
            }
        });
    });
    group.closest('form').addEventListener('submit', () => {
        code.value = fields.map(field => field.value).join('');
    });
    code.type = 'hidden';
    code.required = false;
    code.removeAttribute('autocomplete');
    group.querySelector('[data-otp-fallback]').hidden = true;
    boxes.hidden = false;
}
