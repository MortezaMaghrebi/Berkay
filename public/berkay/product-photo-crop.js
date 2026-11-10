const input = document.querySelector('[data-bk-product-photos]');
const dialog = document.querySelector('#bk-crop-dialog');

if (input && dialog && typeof DataTransfer === 'function') {
    const open = document.querySelector('#bk-crop-open');
    const choice = dialog.querySelector('select');
    const canvas = dialog.querySelector('canvas');
    const controls = [...dialog.querySelectorAll('input[type=range]')];
    const [zoom, horizontal, vertical] = controls;
    const apply = dialog.querySelector('[data-crop-apply]');
    const error = dialog.querySelector('[role=alert]');
    const ctx = canvas.getContext('2d');
    let image = null;
    let previewUrl = null;

    const draw = () => {
        if (!image) return;

        const side = Math.min(image.naturalWidth, image.naturalHeight) / Number(zoom.value);
        const x = (image.naturalWidth - side) * Number(horizontal.value) / 100;
        const y = (image.naturalHeight - side) * Number(vertical.value) / 100;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(image, x, y, side, side, 0, 0, canvas.width, canvas.height);
    };

    const load = async () => {
        const file = input.files[Number(choice.value)];
        if (!file) return;

        apply.disabled = true;
        image = null;
        error.hidden = true;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const url = previewUrl = URL.createObjectURL(file);
        const preview = new Image();
        preview.src = url;

        try {
            await preview.decode();
            if (previewUrl !== url || !dialog.open) return;
            image = preview;
            controls.forEach(control => { control.value = control.defaultValue; });
            draw();
            apply.disabled = false;
        } catch {
            if (previewUrl === url) error.hidden = false;
        }
    };

    input.addEventListener('change', () => { open.hidden = input.files.length === 0; });
    open.addEventListener('click', () => {
        choice.replaceChildren(...Array.from(input.files, (file, index) => new Option(file.name, index)));
        dialog.showModal();
        load();
    });
    choice.addEventListener('change', load);
    controls.forEach(control => control.addEventListener('input', draw));
    dialog.querySelector('[data-crop-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        image = null;
    });
    apply.addEventListener('click', () => {
        const index = Number(choice.value);
        const files = Array.from(input.files);
        apply.disabled = true;

        canvas.toBlob(blob => {
            if (!dialog.open) return;
            if (!blob) {
                error.hidden = false;
                apply.disabled = false;
                return;
            }
            const edited = new DataTransfer();
            files.forEach((file, i) => edited.items.add(i === index
                ? new File([blob], file.name.replace(/\.[^.]*$/, '') + '.jpg', {type: 'image/jpeg'})
                : file));
            input.files = edited.files;
            input.dispatchEvent(new Event('change', {bubbles: true}));
            dialog.close();
        }, 'image/jpeg', .9);
    });
}
