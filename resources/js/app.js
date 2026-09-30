// Keep the native, keyboard-accessible account disclosure easy to dismiss.
document.addEventListener('click', (event) => {
    document.querySelectorAll('.account-switcher[open]').forEach((switcher) => {
        if (!switcher.contains(event.target)) switcher.open = false;
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.account-switcher[open]').forEach((switcher) => {
        const hadFocus = switcher.contains(document.activeElement);
        switcher.open = false;
        if (hadFocus) switcher.querySelector('summary').focus();
    });
});

function initializeChargeEditors() {
    document.querySelectorAll('[data-charge-editor]').forEach((editor) => {
        const rows = editor.querySelector('[data-charge-rows]');
        let index = Math.max(-1, ...Array.from(rows.querySelectorAll('input[name]'), input => Number(input.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
        editor.addEventListener('click', (event) => {
            const add = event.target.closest('[data-add-charge]');
            const remove = event.target.closest('[data-remove-charge]');
            if (add && rows.children.length < 100) {
                const template = editor.querySelector('[data-charge-template]').innerHTML.replaceAll('__INDEX__', String(index++));
                rows.insertAdjacentHTML('beforeend', template);
                rows.lastElementChild.querySelector('input').focus();
            } else if (remove) {
                remove.closest('[data-charge-row]').remove();
                editor.querySelector('[data-add-charge]').focus();
            } else return;
            editor.querySelector('[data-add-charge]').disabled = rows.children.length >= 100;
            editor.querySelector('[data-charge-announcement]').textContent = `${rows.children.length} charge lines. Their total is checked when you save.`;
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChargeEditors, { once: true });
} else {
    initializeChargeEditors();
}
