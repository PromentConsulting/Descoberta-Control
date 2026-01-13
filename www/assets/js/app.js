document.addEventListener("DOMContentLoaded", () => {
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });
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

        let selectionRange = null;

        syncRichWrapper(wrapper);

        const syncToTextarea = () => {
            if (textarea && editor) {
                textarea.value = editor.innerHTML.trim();
            }
        };

        const saveSelection = () => {
            const selection = window.getSelection();
            if (selection && selection.rangeCount > 0) {
                selectionRange = selection.getRangeAt(0);
            }
        };

        const restoreSelection = () => {
            if (!selectionRange) return;
            const selection = window.getSelection();
            if (!selection) return;
            selection.removeAllRanges();
            selection.addRange(selectionRange);
        };

        editor?.addEventListener('input', syncToTextarea);
        editor?.addEventListener('keyup', saveSelection);
        editor?.addEventListener('mouseup', saveSelection);
        editor?.addEventListener('mouseleave', saveSelection);
        editor?.addEventListener('blur', saveSelection);

        const insertGrid = (rows, cols) => {
            if (!rows || !cols) return;
            editor?.focus();
            restoreSelection();
            const buildRow = (cells) => `<tr>${cells}</tr>`;
            const cell = '<td>&nbsp;</td>';
            const tableBody = Array.from({ length: rows })
                .map(() => buildRow(cell.repeat(cols)))
                .join('');
            const html = `<table class="rich-grid"><tbody>${tableBody}</tbody></table><p></p>`;
            document.execCommand('insertHTML', false, html);
            syncToTextarea();
        };

        const initGridPicker = (btn) => {
            let panel = null;
            let label = null;
            let grid = null;

            const closePanel = () => panel?.classList.remove('open');

            const updateActive = (rows, cols) => {
                if (!grid || !label) return;
                label.textContent = `${rows} x ${cols}`;
                grid.querySelectorAll('.grid-picker-cell').forEach(cell => {
                    const r = Number(cell.dataset.rows || 0);
                    const c = Number(cell.dataset.cols || 0);
                    cell.classList.toggle('active', r <= rows && c <= cols);
                });
            };

            const buildPanel = () => {
                const picker = document.createElement('div');
                picker.className = 'grid-picker';

                const gridContainer = document.createElement('div');
                gridContainer.className = 'grid-picker-grid';

                for (let r = 1; r <= 5; r++) {
                    for (let c = 1; c <= 5; c++) {
                        const cell = document.createElement('div');
                        cell.className = 'grid-picker-cell';
                        cell.dataset.rows = String(r);
                        cell.dataset.cols = String(c);
                        cell.addEventListener('mouseenter', () => updateActive(r, c));
                        cell.addEventListener('click', () => {
                            insertGrid(r, c);
                            closePanel();
                        });
                        gridContainer.appendChild(cell);
                    }
                }

                const pickerLabel = document.createElement('div');
                pickerLabel.className = 'grid-picker-label';
                pickerLabel.textContent = '0 x 0';

                picker.appendChild(gridContainer);
                picker.appendChild(pickerLabel);

                wrapper.appendChild(picker);
                panel = picker;
                label = pickerLabel;
                grid = gridContainer;
            };

            const togglePanel = () => {
                if (!panel) buildPanel();
                panel.classList.toggle('open');
                if (panel.classList.contains('open')) {
                    updateActive(0, 0);
                }
            };

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                togglePanel();
            });

            document.addEventListener('click', (e) => {
                if (!panel || !panel.classList.contains('open')) return;
                if (panel.contains(e.target) || e.target === btn) return;
                closePanel();
            });
        };

        toolbarButtons.forEach(btn => {
            if (btn.dataset.gridPicker !== undefined) {
                initGridPicker(btn);
                return;
            }

            btn.addEventListener('click', () => {
                const command = btn.dataset.command;
                const value = btn.dataset.value || '';
                editor?.focus();
                restoreSelection();
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

    const slugFromProduct = (product) => {
        const permalink = product?.permalink || product?.link || '';
        if (permalink) return permalink;
        return product?.slug || '';
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
                const slugInput = form.querySelector('[name="slug"]');
                if (slugInput) slugInput.value = slugFromProduct(product);
                setRichContent(form.querySelector('[name="description"]').closest('[data-rich-editor]'), product.description || '');

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
                const slugInput = form.querySelector('[name="slug"]');
                if (slugInput) slugInput.value = slugFromProduct(product);

                setRichContent(form.querySelector('[name="description"]').closest('[data-rich-editor]'), product.description || '');

                const ciclesVal = metaValue(product, window.CENTRE_META_KEYS.cicles) || [];
                const categoriaVal = metaValue(product, window.CENTRE_META_KEYS.categoria) || [];

                setSelectValues(form.querySelector('select[name="cicles[]"]'), Array.isArray(ciclesVal) ? ciclesVal : []);
                setSelectValues(form.querySelector('select[name="categoria[]"]'), Array.isArray(categoriaVal) ? categoriaVal : []);
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
        const galleryGrid = modal.querySelector('[data-gallery-grid]');
        const removedGalleryInput = modal.querySelector('[name="removed_gallery_images"]');
        const preuLinkWrapper = modal.querySelector('[data-preu-link]');
        const preuLinkAnchor = preuLinkWrapper?.querySelector('[data-preu-link-url]');
        const preuLinkEmpty = preuLinkWrapper?.querySelector('[data-preu-link-empty]');
        let removedGallery = [];

        const setSelectValue = (select, value) => {
            if (!select) return;
            select.value = value || '';
        };

        const metaFromProduct = (product, key) => {
            if (product?.meta && Object.prototype.hasOwnProperty.call(product.meta, key)) {
                return product.meta[key];
            }
            return metaValue(product, window.CASE_META_KEYS[key]);
        };

        const setNormativaText = (value) => {
            if (!normativaHint) return;
            normativaHint.textContent = value ? `Fitxer actual: ${value}` : 'No hi ha cap fitxer pujat.';
        };

        const renderGallery = (images) => {
            if (!galleryGrid || !removedGalleryInput) return;
            galleryGrid.innerHTML = '';
            if (!images.length) {
                const empty = document.createElement('p');
                empty.className = 'gallery-empty';
                empty.textContent = 'No hi ha imatges a la galeria.';
                galleryGrid.appendChild(empty);
                return;
            }

            images.forEach(image => {
                const item = document.createElement('div');
                item.className = 'gallery-item';

                const img = document.createElement('img');
                img.src = image.src || '';
                img.alt = image.alt || 'Imatge de galeria';
                item.appendChild(img);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'gallery-remove';
                remove.innerHTML = '&times;';
                remove.addEventListener('click', () => {
                    removedGallery.push({
                        id: image.id || null,
                        src: image.src || ''
                    });
                    removedGalleryInput.value = JSON.stringify(removedGallery);
                    item.remove();
                    if (!galleryGrid.querySelector('.gallery-item')) {
                        renderGallery([]);
                    }
                });
                item.appendChild(remove);

                galleryGrid.appendChild(item);
            });
        };

        const setPreuLink = (url) => {
            if (!preuLinkWrapper) return;
            const trimmed = String(url || '').trim();
            if (trimmed) {
                if (preuLinkAnchor) {
                    preuLinkAnchor.textContent = trimmed;
                    preuLinkAnchor.href = trimmed;
                    preuLinkAnchor.style.display = 'inline';
                }
                if (preuLinkEmpty) preuLinkEmpty.style.display = 'none';
            } else {
                if (preuLinkAnchor) {
                    preuLinkAnchor.textContent = '';
                    preuLinkAnchor.removeAttribute('href');
                    preuLinkAnchor.style.display = 'none';
                }
                if (preuLinkEmpty) preuLinkEmpty.style.display = 'inline';
            }
        };

        document.querySelectorAll('[data-edit-case]').forEach(btn => {
            btn.addEventListener('click', () => {
                const product = JSON.parse(btn.dataset.editCase || '{}');

                form.querySelector('[name="product_id"]').value = product.id || '';
                form.querySelector('[name="title"]').value = product.name || '';
                const slugInput = form.querySelector('[name="slug"]');
                if (slugInput) slugInput.value = slugFromProduct(product);

                setRichContent(form.querySelector('[name="description"]').closest('[data-rich-editor]'), product.description || '');
                setRichContent(form.querySelector('[name="short_description"]').closest('[data-rich-editor]'), product.short_description || '');
                setRichContent(form.querySelector('[name="preus"]').closest('[data-rich-editor]'), metaFromProduct(product, 'preus') || '');
                setPreuLink(product.preu_link || '');

                form.querySelector('[name="places"]').value = metaFromProduct(product, 'places') || '';
                form.querySelector('[name="regims_admessos"]').value = metaFromProduct(product, 'regims_admessos') || '';
                form.querySelector('[name="exclusivitat"]').value = metaFromProduct(product, 'exclusivitat') || '';
                form.querySelector('[name="habitacions"]').value = metaFromProduct(product, 'habitacions') || '';
                form.querySelector('[name="provincia"]').value = metaFromProduct(product, 'provincia') || '';
                form.querySelector('[name="comarca"]').value = metaFromProduct(product, 'comarca') || '';
                form.querySelector('[name="calefaccio"]').value = metaFromProduct(product, 'calefaccio') || '';
                form.querySelector('[name="sales_activitats"]').value = metaFromProduct(product, 'sales_activitats') || '';
                form.querySelector('[name="exteriors"]').value = metaFromProduct(product, 'exteriors') || '';
                form.querySelector('[name="places_adaptades"]').value = metaFromProduct(product, 'places_adaptades') || '';
                form.querySelector('[name="google_maps"]').value = metaFromProduct(product, 'google_maps') || '';

                setSelectValue(form.querySelector('[name="piscina"]'), normalizeYesNo(metaFromProduct(product, 'piscina')));
                setSelectValue(form.querySelector('[name="wifi"]'), normalizeYesNo(metaFromProduct(product, 'wifi')));

                const normativaVal = metaFromProduct(product, 'normativa') || '';
                form.querySelector('[name="existing_normativa"]').value = normativaVal;
                setNormativaText(normativaVal);

                const firstImage = (product.images || [])[0] || {};
                form.querySelector('[name="existing_image_id"]').value = firstImage.id || '';
                form.querySelector('[name="existing_image_src"]').value = firstImage.src || '';
                form.querySelector('[name="featured_url"]').value = firstImage.src || '';

                removedGallery = [];
                if (removedGalleryInput) {
                    removedGalleryInput.value = JSON.stringify(removedGallery);
                }
                const galleryImages = (product.images || []).slice(1);
                renderGallery(galleryImages);

                openModal(modal);
            });
        });
    };

    initRangeFilters();
    initActivitatEditor();
    initCentreEditor();
    initCaseEditor();
});
