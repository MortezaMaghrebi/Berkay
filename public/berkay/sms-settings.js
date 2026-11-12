document.addEventListener('click', async event => {
    const button = event.target.closest('.bk-sms-panel [data-sms-copy]');
    if (!button) return;

    const example = button.closest('.bk-sms-example');
    const status = example.querySelector('[role=status]');

    try {
        await navigator.clipboard.writeText(button.dataset.smsCopy);
        status.textContent = button.dataset.copied;
    } catch {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(example.querySelector('p'));
        selection?.removeAllRanges();
        selection?.addRange(range);
        status.textContent = button.dataset.copyFailed;
    }

    status.hidden = false;
});
