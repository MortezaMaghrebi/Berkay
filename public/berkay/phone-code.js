const digits = value => value.replace(/[۰-۹٠-٩]/g, digit => String(digit.charCodeAt(0) & 15)).replace(/\D/g, '');

function enhanceCodeEntry(group) {
    const code = group.querySelector('[name="code"]');
    const boxes = group.querySelector('fieldset');
    const fields = Array.from(boxes.querySelectorAll('input'));

    function syncCode() {
        code.value = fields.map(field => field.value).join('');
    }

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
            syncCode();
            return;
        }

        Array.from(part).forEach((digit, offset) => {
            fields[index + offset].value = digit;
        });
        syncCode();
        if (index + part.length < fields.length) focus(index + part.length);
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
                syncCode();
                focus(index - 1);
            }

            const next = event.key === 'ArrowLeft' ? index - 1 : event.key === 'ArrowRight' ? index + 1 : index;
            if (next !== index && fields[next]) {
                event.preventDefault();
                focus(next);
            }
        });
    });

    group.closest('form').addEventListener('submit', syncCode);
    const prefilled = code.value;
    code.type = 'hidden';
    code.required = false;
    code.removeAttribute('autocomplete');
    group.querySelector('[data-otp-fallback]').hidden = true;
    boxes.hidden = false;

    Array.from(digits(prefilled).slice(0, fields.length)).forEach((digit, index) => {
        fields[index].value = digit;
    });
    syncCode();
}

function initialize() {
    document.querySelectorAll('[data-otp]').forEach(enhanceCodeEntry);
}

// The storefront mounts Vue during DOMContentLoaded and replaces the original form nodes.
function afterStorefrontMount() {
    window.setTimeout(initialize, 0);
}

let initialized = false;
function scheduleInitialization() {
    if (initialized) return;
    initialized = true;
    afterStorefrontMount();
}

if (document.readyState === 'complete') {
    scheduleInitialization();
} else {
    document.addEventListener('DOMContentLoaded', scheduleInitialization, { once: true });
    window.addEventListener('load', scheduleInitialization, { once: true });
}
