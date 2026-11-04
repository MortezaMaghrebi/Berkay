window.addEventListener('load', () => requestAnimationFrame(() => {
    const helper = document.getElementById('bk-ai-helper');
    if (!helper) return;
    const byId = id => document.getElementById(id);
    const message = text => { byId('ai-message').textContent = text; };
    byId('ai-prompt-button').addEventListener('click', () => {
        const topic = byId('ai-topic').value.trim();
        if (!topic) { message(helper.dataset.topicRequired); byId('ai-topic').focus(); return; }
        byId('ai-prompt').value = helper.dataset.template.replace('{topic}', topic);
        message(helper.dataset.ready);
    });
    byId('ai-copy-button').addEventListener('click', async () => {
        const prompt = byId('ai-prompt');
        if (!prompt.value) { message(helper.dataset.topicRequired); return; }
        try { await navigator.clipboard.writeText(prompt.value); message(helper.dataset.copied); }
        catch { prompt.focus(); prompt.select(); message(helper.dataset.manualCopy); }
    });
    byId('ai-import-button').addEventListener('click', async () => {
        const button = byId('ai-import-button');
        button.disabled = true;
        message(helper.dataset.busy);
        try {
            const response = await fetch(helper.dataset.importUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('.bk-article-form input[name="_token"]').value },
                body: JSON.stringify({ payload: byId('ai-result').value }),
            });
            const result = await response.json();
            if (!response.ok) {
                message(result.errors ? Object.values(result.errors).flat().join(' ') : helper.dataset.invalid);
                return;
            }
            for (const key of ['title', 'slug', 'category', 'excerpt', 'body', 'meta_title', 'meta_description']) {
                byId(key).value = result.data[key] || '';
            }
            const editor = window.tinymce?.get('body');
            if (editor) { editor.setContent(result.data.body); editor.save(); }
            byId('status').value = 'draft';
            byId('published_at').value = '';
            message(helper.dataset.imported);
        } catch { message(helper.dataset.invalid); }
        finally { button.disabled = false; }
    });
}));
