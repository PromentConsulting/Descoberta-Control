document.addEventListener("DOMContentLoaded", () => {
    const modals = document.querySelectorAll('.modal-overlay');
    document.querySelectorAll('[data-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.open);
            if (target) target.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            modals.forEach(m => m.classList.remove('open'));
            document.body.style.overflow = '';
        });
    });

    modals.forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    });

    const richEditors = document.querySelectorAll('[data-rich-editor]');
    richEditors.forEach(wrapper => {
        const textarea = wrapper.querySelector('textarea');
        const editor = wrapper.querySelector('.rich-editor');
        const toolbarButtons = wrapper.querySelectorAll('.rich-toolbar button');

        editor.innerHTML = textarea.value;

        const syncToTextarea = () => {
            textarea.value = editor.innerHTML.trim();
        };

        syncToTextarea();

        editor.addEventListener('input', syncToTextarea);

        toolbarButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const command = btn.dataset.command;
                const value = btn.dataset.value || '';
                if (command === 'createLink') {
                    const url = prompt('Introdueix l\'enllaç');
                    if (url) {
                        document.execCommand(command, false, url);
                    }
                } else {
                    document.execCommand(command, false, value);
                }
                syncToTextarea();
            });
        });
    });
});
