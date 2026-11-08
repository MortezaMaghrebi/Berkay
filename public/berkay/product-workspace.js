(() => {
    const init = () => {
        const root = document.querySelector('.bk-product-workspace');
        if (!root) return;
        const status = root.querySelector('.bk-product-editor-status');
        const nav = root.querySelector('.bk-product-section-nav');
        let dirty = false;
        status.textContent = root.dataset.savedLabel;
        const markDirty = event => {
            if (event.target.closest('.bk-variant-dialog')) return;
            dirty = true;status.textContent=root.dataset.dirtyLabel;status.classList.add('is-dirty');
        };
        root.addEventListener('berkay:variant-saved', () => {
            dirty = true;
            status.textContent = root.dataset.dirtyLabel;
            status.classList.add('is-dirty');
        });
        root.addEventListener('input',markDirty);root.addEventListener('change',markDirty);
        const sections = root.querySelectorAll('.bk-product-edit-section,.bk-product-extra,.bk-workspace-target');
        sections.forEach((section,index)=>{
            if (!section.id) section.id = 'bk-edit-section-' + index;
            const summary = section.querySelector(':scope > summary');
            if (section.classList.contains('bk-product-extra') || (summary && !section.open) || (!summary && !section.dataset.title)) return;
            const button = document.createElement('button');button.type='button';
            button.textContent=summary ? summary.firstChild.textContent.trim() : section.dataset.title;
            button.addEventListener('click',()=>{section.open=true;section.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});});
            nav.append(button);
        });
        window.addEventListener('beforeunload', event => {
            if (!dirty) return;

            event.preventDefault();
            event.returnValue = '';
        });

        const form = root.querySelector('form');
        form?.addEventListener('submit',()=>{
            const title=root.querySelector('[name="name"]')?.value || '';
            const description=root.querySelector('[name="short_description"]')?.value || '';
            const temp=new DOMParser().parseFromString(description,'text/html').body;
            const metaTitle=root.querySelector('[name="meta_title"]');const metaDescription=root.querySelector('[name="meta_description"]');
            if(metaTitle && !metaTitle.value.trim()) metaTitle.value=title.slice(0,70);
            if(metaDescription && !metaDescription.value.trim()) metaDescription.value=temp.textContent.trim().slice(0,160);
            [metaTitle,metaDescription].filter(Boolean).forEach(field=>field.dispatchEvent(new Event('input',{bubbles:true})));
            dirty=false;
        });
    };
    if(document.readyState==='complete') init();else window.addEventListener('load',init,{once:true});
})();
