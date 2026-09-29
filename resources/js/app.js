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
