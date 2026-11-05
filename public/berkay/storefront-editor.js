(() => {
    const init = async () => {
        const access = document.querySelector('#bk-staff-access');
        if (!access) return;
        const labels = JSON.parse(access.dataset.labels);
        const product = document.querySelector('.bk-product-intro[data-product-id]');
        const endpoint = new URL(access.dataset.sessionUrl, location.origin);
        if (product) endpoint.searchParams.set('product_id', product.dataset.productId);
        let session;
        try {
            const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: {Accept:'application/json'} });
            if (!response.ok) return;
            session = await response.json();
        } catch { return; }
        if (!session.admin) return;
        const panel = document.createElement('a');
        panel.href = session.admin_url; panel.textContent = labels['admin-panel']; panel.className = 'bk-staff-link';
        access.append(panel);
        if (!session.editable || !product) return;
        const tools = document.querySelector('#bk-product-editor-tools');
        tools.className = 'bk-product-editor-tools';
        const message = document.createElement('p'); message.setAttribute('role','status'); message.className='bk-editor-message';
        const actions = document.createElement('div');
        const button = (key, action) => { const el=document.createElement('button'); el.type='button'; el.textContent=labels[key]; el.addEventListener('click',action); actions.append(el); return el; };
        let name, description, before, editing=false;
        const finish = restore => {
            if (name && description) {
                if (restore && before) { name.textContent=before.name; description.textContent=before.description; }
                name.removeAttribute('contenteditable'); description.removeAttribute('contenteditable');
                name.classList.remove('bk-editing-field'); description.classList.remove('bk-editing-field');
            }
            editing=false; save.hidden=true; cancel.hidden=true; edit.hidden=false;
        };
        const edit = button('inline-edit', () => {
            name=document.querySelector('#bk-editable-name'); description=document.querySelector('.bk-detail-short');
            if (!name || !description) { message.textContent=labels.failed; return; }
            before={name:name.innerText,description:description.innerText}; editing=true;
            [name,description].forEach(el=>{el.setAttribute('contenteditable','plaintext-only');el.classList.add('bk-editing-field');});
            edit.hidden=true; save.hidden=false; cancel.hidden=false; message.textContent=labels.hint; name.focus();
        });
        const full = button('full-edit', () => {
            if (editing) finish(true);
            const dialog=document.createElement('dialog'); dialog.className='bk-full-product-editor'; dialog.setAttribute('aria-label',labels['full-edit']);
            const top=document.createElement('div'); top.className='bk-full-editor-top';
            const close=document.createElement('button'); close.type='button'; close.textContent=labels.close;
            const refresh=document.createElement('button'); refresh.type='button'; refresh.textContent=labels.refresh;
            const frame=document.createElement('iframe'); frame.src=session.edit_url; frame.title=labels['full-edit'];
            top.append(close,refresh);dialog.append(top,frame);document.body.append(dialog);
            const oldOverflow=document.body.style.overflow;document.body.style.overflow='hidden';
            dialog.addEventListener('close',()=>{document.body.style.overflow=oldOverflow;dialog.remove();full.focus();},{once:true});
            close.addEventListener('click',()=>dialog.close());refresh.addEventListener('click',()=>location.reload());
            dialog.showModal();close.focus();
        });
        const save = button('save', async () => {
            if (!editing) return;
            save.disabled=true; cancel.disabled=true; full.disabled=true; message.textContent=labels.loading;
            try {
                const response=await fetch(session.save_url,{method:'PATCH',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':session.csrf},body:JSON.stringify({name:name.innerText.trim(),short_description:description.innerText.trim()})});
                const result=await response.json();
                if (!response.ok) { message.textContent=Object.values(result.errors||{}).flat().join(' ')||result.message||labels.failed;return; }
                finish(false);message.textContent=result.message;
                location.reload();
            } catch { message.textContent=labels.failed; }
            finally {save.disabled=false;cancel.disabled=false;full.disabled=false;}
        });
        const cancel = button('cancel',()=>{finish(true);message.textContent='';});
        save.hidden=true;cancel.hidden=true;tools.append(actions,message);
    };
    if (document.readyState === 'complete') init(); else window.addEventListener('load',init,{once:true});
})();
