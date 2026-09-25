(() => {
    document.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => {
            field.form?.requestSubmit();
        });
    });
})();
