document.addEventListener("DOMContentLoaded", () => {
    const modals = document.querySelectorAll('.modal-overlay');
    const openModal = (modal) => {
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };
    document.querySelectorAll('[data-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.open);
            openModal(target);
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
    const syncRichWrapper = (wrapper, content = '') => {
        const textarea = wrapper.querySelector('textarea');
        const editor = wrapper.querySelector('.rich-editor');
        if (!textarea || !editor) return;
        const value = content || textarea.value || '';
        editor.innerHTML = value;
        textarea.value = value.trim();
    };

    richEditors.forEach(wrapper => {
        const textarea = wrapper.querySelector('textarea');
        const editor = wrapper.querySelector('.rich-editor');
        const toolbarButtons = wrapper.querySelectorAll('.rich-toolbar button');

        syncRichWrapper(wrapper);

        const syncToTextarea = () => {
            if (textarea && editor) {
                textarea.value = editor.innerHTML.trim();
            }
        };

        editor?.addEventListener('input', syncToTextarea);

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

    const sortableTables = document.querySelectorAll('.styled-table');
    sortableTables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sort-key]');
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        headers.forEach(header => {
            header.addEventListener('click', () => {
                const key = header.dataset.sortKey;
                if (!key) return;

                const nextDir = header.dataset.sortDir === 'asc' ? 'desc' : 'asc';
                headers.forEach(h => h.removeAttribute('data-sort-dir'));
                header.dataset.sortDir = nextDir;

                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => {
                    const aCell = a.querySelector(`td[data-col="${key}"]`);
                    const bCell = b.querySelector(`td[data-col="${key}"]`);

                    const aVal = (aCell?.dataset.sortValue ?? aCell?.textContent ?? '').trim();
                    const bVal = (bCell?.dataset.sortValue ?? bCell?.textContent ?? '').trim();

                    const aNum = Number(aVal);
                    const bNum = Number(bVal);
                    const numeric = aVal !== '' && bVal !== '' && !Number.isNaN(aNum) && !Number.isNaN(bNum);

                    const comparison = numeric
                        ? aNum - bNum
                        : aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });

                    return nextDir === 'asc' ? comparison : -comparison;
                });

                rows.forEach(row => tbody.appendChild(row));
            });
        });
    });

    const setSelectValues = (select, values) => {
        if (!select) return;
        const normalized = new Set((values || []).map(v => String(v)));
        Array.from(select.options).forEach(opt => {
            opt.selected = normalized.has(opt.value);
        });
    };

    const setRichContent = (wrapper, value) => {
        if (!wrapper) return;
        const textarea = wrapper.querySelector('textarea');
        const editor = wrapper.querySelector('.rich-editor');
        const content = value || '';
        if (editor) editor.innerHTML = content;
        if (textarea) textarea.value = content;
    };

    const initRangeFilters = () => {
        document.querySelectorAll('[data-range-filter]').forEach(wrapper => {
            const minInput = wrapper.querySelector('[data-range-min]');
            const maxInput = wrapper.querySelector('[data-range-max]');
            const display = wrapper.querySelector('[data-range-display]');
            if (!minInput || !maxInput || !display) return;

            const updateDisplay = () => {
                const minVal = Number(minInput.value) || 0;
                const maxVal = Number(maxInput.value) || 0;
                const realMin = Math.min(minVal, maxVal);
                const realMax = Math.max(minVal, maxVal);
                minInput.value = realMin;
                maxInput.value = realMax;
                display.textContent = `${realMin} - ${realMax}`;
            };

            minInput.addEventListener('input', updateDisplay);
            maxInput.addEventListener('input', updateDisplay);
            updateDisplay();
        });
    };

    const metaValue = (product, key) => {
        const meta = (product?.meta_data || []).find(m => m.key === key);
        return meta ? meta.value : '';
    };

    const normalizeYesNo = (value) => {
        const val = String(value || '').toLowerCase();
        if (val === 'si' || val === 'sí') return 'Si';
        if (val === 'no') return 'No';
        return '';
    };

    const initActivitatEditor = () => {
        if (!window.ACTIVITAT_META_KEYS) return;
        const modal = document.getElementById('modalEditActivitat');
        const form = modal?.querySelector('form');
        if (!modal || !form) return;

        const buttons = document.querySelectorAll('[data-edit-activitat]');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const product = JSON.parse(btn.dataset.editActivitat || '{}');
                form.querySelector('[name="product_id"]').value = product.id || '';
                form.querySelector('[name="status"]').value = product.status || 'draft';
                form.querySelector('[name="title"]').value = product.name || '';
                form.querySelector('[name="description"]').value = product.description || '';

                const ciclesVal = metaValue(product, window.ACTIVITAT_META_KEYS.cicles) || [];
                const categoriaVal = metaValue(product, window.ACTIVITAT_META_KEYS.categoria) || [];

                setSelectValues(form.querySelector('select[name="cicles[]"]'), Array.isArray(ciclesVal) ? ciclesVal : []);
                setSelectValues(form.querySelector('select[name="categoria[]"]'), Array.isArray(categoriaVal) ? categoriaVal : []);

                setRichContent(form.querySelector('[name="continguts"]').closest('[data-rich-editor]'), metaValue(product, window.ACTIVITAT_META_KEYS.continguts) || '');
                setRichContent(form.querySelector('[name="programa"]').closest('[data-rich-editor]'), metaValue(product, window.ACTIVITAT_META_KEYS.programa) || '');
                setRichContent(form.querySelector('[name="preus"]').closest('[data-rich-editor]'), metaValue(product, window.ACTIVITAT_META_KEYS.preus) || '');
                setRichContent(form.querySelector('[name="inclou"]').closest('[data-rich-editor]'), metaValue(product, window.ACTIVITAT_META_KEYS.inclou) || '');

                const firstImage = (product.images || [])[0] || {};
                form.querySelector('[name="existing_image_id"]').value = firstImage.id || '';
                form.querySelector('[name="existing_image_src"]').value = firstImage.src || '';
                form.querySelector('[name="featured_url"]').value = firstImage.src || '';

                openModal(modal);
            });
        });
    };

    const initCentreEditor = () => {
        if (!window.CENTRE_META_KEYS) return;
        const modal = document.getElementById('modalEditCentre');
        const form = modal?.querySelector('form');
        if (!modal || !form) return;

        const buttons = document.querySelectorAll('[data-edit-centre]');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const product = JSON.parse(btn.dataset.editCentre || '{}');
                form.querySelector('[name="product_id"]').value = product.id || '';
                form.querySelector('[name="status"]').value = product.status || 'draft';
                form.querySelector('[name="title"]').value = product.name || '';

                setRichContent(form.querySelector('[name="description"]').closest('[data-rich-editor]'), product.description || '');
                setRichContent(form.querySelector('[name="competencies"]').closest('[data-rich-editor]'), metaValue(product, window.CENTRE_META_KEYS.competencies) || '');
                setRichContent(form.querySelector('[name="metodologia"]').closest('[data-rich-editor]'), metaValue(product, window.CENTRE_META_KEYS.metodologia) || '');

                for (let i = 1; i <= 5; i++) {
                    const titleField = form.querySelector(`[name="titol_programa_${i}"]`);
                    const descWrapper = form.querySelector(`[name="descripcio_programa_${i}"]`)?.closest('[data-rich-editor]');
                    if (titleField) {
                        titleField.value = metaValue(product, window.CENTRE_META_KEYS[`titol_programa_${i}`]) || '';
                    }
                    if (descWrapper) {
                        setRichContent(descWrapper, metaValue(product, window.CENTRE_META_KEYS[`descripcio_programa_${i}`]) || '');
                    }
                }

                setRichContent(form.querySelector('[name="preus"]').closest('[data-rich-editor]'), metaValue(product, window.CENTRE_META_KEYS.preus) || '');
                setRichContent(form.querySelector('[name="inclou"]').closest('[data-rich-editor]'), metaValue(product, window.CENTRE_META_KEYS.inclou) || '');
                setRichContent(form.querySelector('[name="altres_activitats"]').closest('[data-rich-editor]'), metaValue(product, window.CENTRE_META_KEYS.altres_activitats) || '');
                setRichContent(form.querySelector('[name="cases_on_es_pot_fer"]').closest('[data-rich-editor]'), metaValue(product, window.CENTRE_META_KEYS.cases_on_es_pot_fer) || '');
                setRichContent(form.querySelector('[name="altres_propostes"]').closest('[data-rich-editor]'), metaValue(product, window.CENTRE_META_KEYS.altres_propostes) || '');

                const firstImage = (product.images || [])[0] || {};
                form.querySelector('[name="existing_image_id"]').value = firstImage.id || '';
                form.querySelector('[name="existing_image_src"]').value = firstImage.src || '';
                form.querySelector('[name="featured_url"]').value = firstImage.src || '';

                openModal(modal);
            });
        });
    };

    const initCaseEditor = () => {
        if (!window.CASE_META_KEYS) return;
        const modal = document.getElementById('modalEditCase');
        const form = modal?.querySelector('form');
        if (!modal || !form) return;

        const normativaHint = modal.querySelector('[data-current-normativa]');

        const setSelectValue = (select, value) => {
            if (!select) return;
            select.value = value || '';
        };

        const setNormativaText = (value) => {
            if (!normativaHint) return;
            normativaHint.textContent = value ? `Fitxer actual: ${value}` : 'No hi ha cap fitxer pujat.';
        };

        document.querySelectorAll('[data-edit-case]').forEach(btn => {
            btn.addEventListener('click', () => {
                const product = JSON.parse(btn.dataset.editCase || '{}');

                form.querySelector('[name="product_id"]').value = product.id || '';
                form.querySelector('[name="title"]').value = product.name || '';

                setRichContent(form.querySelector('[name="description"]').closest('[data-rich-editor]'), product.description || '');
                setRichContent(form.querySelector('[name="short_description"]').closest('[data-rich-editor]'), product.short_description || '');

                form.querySelector('[name="places"]').value = metaValue(product, window.CASE_META_KEYS.places) || '';
                form.querySelector('[name="regims_admessos"]').value = metaValue(product, window.CASE_META_KEYS.regims_admessos) || '';
                form.querySelector('[name="exclusivitat"]').value = metaValue(product, window.CASE_META_KEYS.exclusivitat) || '';
                form.querySelector('[name="habitacions"]').value = metaValue(product, window.CASE_META_KEYS.habitacions) || '';
                form.querySelector('[name="provincia"]').value = metaValue(product, window.CASE_META_KEYS.provincia) || '';
                form.querySelector('[name="comarca"]').value = metaValue(product, window.CASE_META_KEYS.comarca) || '';
                form.querySelector('[name="calefaccio"]').value = metaValue(product, window.CASE_META_KEYS.calefaccio) || '';
                form.querySelector('[name="sales_activitats"]').value = metaValue(product, window.CASE_META_KEYS.sales_activitats) || '';
                form.querySelector('[name="exteriors"]').value = metaValue(product, window.CASE_META_KEYS.exteriors) || '';
                form.querySelector('[name="places_adaptades"]').value = metaValue(product, window.CASE_META_KEYS.places_adaptades) || '';
                form.querySelector('[name="google_maps"]').value = metaValue(product, window.CASE_META_KEYS.google_maps) || '';

                setSelectValue(form.querySelector('[name="piscina"]'), normalizeYesNo(metaValue(product, window.CASE_META_KEYS.piscina)));
                setSelectValue(form.querySelector('[name="wifi"]'), normalizeYesNo(metaValue(product, window.CASE_META_KEYS.wifi)));

                const normativaVal = metaValue(product, window.CASE_META_KEYS.normativa) || '';
                form.querySelector('[name="existing_normativa"]').value = normativaVal;
                setNormativaText(normativaVal);

                openModal(modal);
            });
        });
    };

    initRangeFilters();
    initActivitatEditor();
    initCentreEditor();
    initCaseEditor();
});
