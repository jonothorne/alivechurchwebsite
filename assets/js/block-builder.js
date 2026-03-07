/**
 * Block Builder - Drag and drop page builder
 */

(function() {
    'use strict';

    // State
    const state = {
        pageSlug: '',
        blocks: [],
        blockTypes: {},
        categories: {},
        selectedBlock: null,
        hasUnsavedChanges: false,
        draggedElement: null
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', init);

    async function init() {
        const canvas = document.querySelector('.block-builder-canvas');
        if (!canvas) return;

        state.pageSlug = canvas.dataset.page;

        // Load block data first (wait for it)
        await loadBlocks();

        // Setup UI after data is loaded
        createBlockPalette();
        setupDragAndDrop();
        setupBlockControls();
        setupKeyboardShortcuts();
        setupBeforeUnload();
    }

    /**
     * Load blocks from API
     */
    async function loadBlocks() {
        try {
            const response = await fetch(`/api/cms/blocks.php?page=${encodeURIComponent(state.pageSlug)}`);
            const data = await response.json();

            if (data.success) {
                state.blocks = data.blocks;
                state.blockTypes = data.blockTypes;
                state.categories = data.categories;
            }
        } catch (error) {
            console.error('Failed to load blocks:', error);
        }
    }

    /**
     * Create the block palette sidebar
     */
    function createBlockPalette() {
        const palette = document.createElement('div');
        palette.className = 'block-palette';
        palette.innerHTML = `
            <div class="block-palette-header">
                <h3>Add Block</h3>
                <button type="button" class="block-palette-close">&times;</button>
            </div>
            <div class="block-palette-search">
                <input type="text" placeholder="Search blocks..." class="block-search-input">
            </div>
            <div class="block-palette-categories"></div>
        `;

        document.body.appendChild(palette);

        // Populate categories
        const categoriesContainer = palette.querySelector('.block-palette-categories');

        // Get unique categories from blockTypes
        const categoryBlocks = {};
        for (const [type, config] of Object.entries(state.blockTypes)) {
            const cat = config.category || 'content';
            if (!categoryBlocks[cat]) categoryBlocks[cat] = [];
            categoryBlocks[cat].push({ type, ...config });
        }

        for (const [catKey, catName] of Object.entries(state.categories)) {
            if (!categoryBlocks[catKey]) continue;

            const section = document.createElement('div');
            section.className = 'block-category';
            section.innerHTML = `<h4>${catName}</h4>`;

            const grid = document.createElement('div');
            grid.className = 'block-type-grid';

            for (const block of categoryBlocks[catKey]) {
                const item = document.createElement('div');
                item.className = 'block-type-item';
                item.dataset.blockType = block.type;
                item.draggable = true;
                item.innerHTML = `
                    <span class="block-type-icon">${getBlockIcon(block.icon)}</span>
                    <span class="block-type-name">${block.name}</span>
                `;
                item.addEventListener('click', () => addBlock(block.type));
                item.addEventListener('dragstart', handlePaletteDragStart);
                grid.appendChild(item);
            }

            section.appendChild(grid);
            categoriesContainer.appendChild(section);
        }

        // Close button
        palette.querySelector('.block-palette-close').addEventListener('click', () => {
            palette.classList.remove('open');
        });

        // Search functionality
        const searchInput = palette.querySelector('.block-search-input');
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            palette.querySelectorAll('.block-type-item').forEach(item => {
                const name = item.querySelector('.block-type-name').textContent.toLowerCase();
                item.style.display = name.includes(query) ? '' : 'none';
            });
        });

        // Add block button handler (including the "first block" button)
        document.querySelectorAll('.block-add-btn, .block-add-first').forEach(btn => {
            btn.addEventListener('click', () => {
                palette.classList.add('open');
            });
        });
    }

    /**
     * Get icon for block type
     */
    function getBlockIcon(iconName) {
        const icons = {
            'hero': '🎯',
            'text': '📝',
            'columns': '▥',
            'grid': '⊞',
            'megaphone': '📢',
            'image': '🖼',
            'gallery': '🖼️',
            'spacer': '↕',
            'divider': '─'
        };
        return icons[iconName] || '📦';
    }

    /**
     * Add a new block
     */
    async function addBlock(blockType, position = null) {
        const uuid = generateUUID();
        const blockConfig = state.blockTypes[blockType];

        // Create default data from field definitions
        const defaultData = {};
        for (const [fieldKey, fieldConfig] of Object.entries(blockConfig.fields)) {
            if (fieldConfig.default !== undefined) {
                defaultData[fieldKey] = fieldConfig.default;
            } else if (fieldConfig.type === 'text' || fieldConfig.type === 'textarea') {
                defaultData[fieldKey] = '';
            } else if (fieldConfig.type === 'richtext') {
                defaultData[fieldKey] = '<p></p>';
            } else if (fieldConfig.type === 'repeater') {
                defaultData[fieldKey] = [];
            }
        }

        const newBlock = {
            uuid: uuid,
            type: blockType,
            data: defaultData,
            order: position !== null ? position : state.blocks.length
        };

        state.blocks.push(newBlock);
        state.hasUnsavedChanges = true;

        // Close palette
        document.querySelector('.block-palette').classList.remove('open');

        // Save and reload page
        await saveBlocks();
        location.reload();
    }

    /**
     * Setup drag and drop
     */
    function setupDragAndDrop() {
        const canvas = document.querySelector('.block-builder-canvas');
        if (!canvas) return;

        // Track if drag started from handle
        let dragStartedFromHandle = false;

        // Make block wrappers draggable
        canvas.querySelectorAll('.block-wrapper').forEach(wrapper => {
            const handle = wrapper.querySelector('.block-drag-handle');

            // Always make wrapper draggable, but only allow drag from handle
            wrapper.draggable = true;

            if (handle) {
                handle.addEventListener('mousedown', (e) => {
                    dragStartedFromHandle = true;
                    // Add visual feedback
                    wrapper.classList.add('drag-ready');
                });

                handle.addEventListener('mouseup', () => {
                    dragStartedFromHandle = false;
                    wrapper.classList.remove('drag-ready');
                });
            }

            wrapper.addEventListener('dragstart', (e) => {
                // Only allow drag if it started from the handle
                if (!dragStartedFromHandle) {
                    e.preventDefault();
                    return;
                }
                handleDragStart(e);
            });
            wrapper.addEventListener('dragend', handleDragEnd);
            wrapper.addEventListener('dragover', handleDragOver);
            wrapper.addEventListener('dragleave', handleDragLeave);
            wrapper.addEventListener('drop', handleDrop);
        });

        // Canvas drop zone
        canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        canvas.addEventListener('drop', handleCanvasDrop);

        // Reset handle state on document mouseup (in case mouseup happens outside)
        document.addEventListener('mouseup', () => {
            dragStartedFromHandle = false;
            canvas.querySelectorAll('.drag-ready').forEach(el => {
                el.classList.remove('drag-ready');
            });
        });
    }

    function handleDragStart(e) {
        state.draggedElement = e.target.closest('.block-wrapper');
        if (!state.draggedElement) return;

        state.draggedElement.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', state.draggedElement.dataset.blockUuid);

        // Create a drag image (ghost)
        const rect = state.draggedElement.getBoundingClientRect();
        e.dataTransfer.setDragImage(state.draggedElement, rect.width / 2, 20);
    }

    function handleDragEnd(e) {
        if (state.draggedElement) {
            state.draggedElement.classList.remove('dragging');
            state.draggedElement.classList.remove('drag-ready');
            state.draggedElement = null;
        }
        // Clean up all drag-over classes
        document.querySelectorAll('.block-wrapper').forEach(el => {
            el.classList.remove('drag-over');
            el.classList.remove('drag-ready');
        });
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const wrapper = e.target.closest('.block-wrapper');
        if (wrapper && wrapper !== state.draggedElement) {
            // Remove drag-over from all others first
            document.querySelectorAll('.block-wrapper.drag-over').forEach(el => {
                if (el !== wrapper) el.classList.remove('drag-over');
            });
            wrapper.classList.add('drag-over');
        }
    }

    function handleDragLeave(e) {
        const wrapper = e.target.closest('.block-wrapper');
        if (wrapper) {
            // Only remove if we're actually leaving this wrapper
            const relatedTarget = e.relatedTarget;
            if (!relatedTarget || !wrapper.contains(relatedTarget)) {
                wrapper.classList.remove('drag-over');
            }
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();

        const targetWrapper = e.target.closest('.block-wrapper');
        if (!targetWrapper || !state.draggedElement || targetWrapper === state.draggedElement) {
            return;
        }

        targetWrapper.classList.remove('drag-over');

        // Get block UUIDs
        const draggedUuid = state.draggedElement.dataset.blockUuid;
        const targetUuid = targetWrapper.dataset.blockUuid;

        console.log('[BlockBuilder] Dropping', draggedUuid, 'onto', targetUuid);

        // Reorder in state
        const draggedIndex = state.blocks.findIndex(b => b.block_uuid === draggedUuid || b.uuid === draggedUuid);
        const targetIndex = state.blocks.findIndex(b => b.block_uuid === targetUuid || b.uuid === targetUuid);

        console.log('[BlockBuilder] Indices:', draggedIndex, targetIndex);

        if (draggedIndex !== -1 && targetIndex !== -1 && draggedIndex !== targetIndex) {
            // Reorder the blocks array
            const [removed] = state.blocks.splice(draggedIndex, 1);
            state.blocks.splice(targetIndex, 0, removed);

            // Update order values
            state.blocks.forEach((block, index) => {
                block.order = index;
                block.display_order = index;
            });

            // Reorder DOM elements immediately for visual feedback
            const canvas = document.querySelector('.block-builder-canvas');
            const wrappers = Array.from(canvas.querySelectorAll('.block-wrapper'));
            const addZone = canvas.querySelector('.block-add-zone');

            // Sort wrappers based on new state order
            state.blocks.forEach(block => {
                const uuid = block.block_uuid || block.uuid;
                const wrapper = wrappers.find(w => w.dataset.blockUuid === uuid);
                if (wrapper && addZone) {
                    canvas.insertBefore(wrapper, addZone);
                }
            });

            state.hasUnsavedChanges = true;
            saveBlockOrder();
        }
    }

    function handleCanvasDrop(e) {
        // Handle drops from palette
        const blockType = e.dataTransfer.getData('block-type');
        if (blockType) {
            addBlock(blockType);
        }
    }

    function handlePaletteDragStart(e) {
        const blockType = e.target.closest('.block-type-item').dataset.blockType;
        e.dataTransfer.setData('block-type', blockType);
        e.dataTransfer.effectAllowed = 'copy';
    }

    /**
     * Setup block control buttons
     */
    function setupBlockControls() {
        // Delete buttons
        document.querySelectorAll('.block-delete-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if (!confirm('Delete this block?')) return;

                const wrapper = e.target.closest('.block-wrapper');
                const uuid = wrapper.dataset.blockUuid;

                try {
                    const response = await fetch(`/api/cms/blocks.php?uuid=${encodeURIComponent(uuid)}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();

                    if (data.success) {
                        wrapper.remove();
                        state.blocks = state.blocks.filter(b => (b.block_uuid || b.uuid) !== uuid);
                        showNotification('Block deleted', 'success');
                    }
                } catch (error) {
                    showNotification('Failed to delete block', 'error');
                }
            });
        });

        // Duplicate buttons
        document.querySelectorAll('.block-duplicate-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const wrapper = e.target.closest('.block-wrapper');
                const uuid = wrapper.dataset.blockUuid;
                const blockType = wrapper.dataset.blockType;

                // Find the block data
                const block = state.blocks.find(b => (b.block_uuid || b.uuid) === uuid);
                if (block) {
                    const newUuid = generateUUID();
                    const newBlock = {
                        uuid: newUuid,
                        type: blockType,
                        data: JSON.parse(JSON.stringify(block.data || block.block_data)),
                        order: state.blocks.length
                    };
                    state.blocks.push(newBlock);
                    state.hasUnsavedChanges = true;
                    await saveBlocks();
                    location.reload();
                }
            });
        });
    }

    /**
     * Open block settings modal
     */
    function openBlockSettings(uuid, blockType) {
        const block = state.blocks.find(b => (b.block_uuid || b.uuid) === uuid);
        const blockConfig = state.blockTypes[blockType];

        if (!block || !blockConfig) return;

        const blockData = block.data || (typeof block.block_data === 'string' ? JSON.parse(block.block_data) : block.block_data) || {};

        // Create modal
        const modal = document.createElement('div');
        modal.className = 'block-settings-modal';
        modal.innerHTML = `
            <div class="block-settings-content">
                <div class="block-settings-header">
                    <h3>Edit ${blockConfig.name}</h3>
                    <button type="button" class="block-settings-close">&times;</button>
                </div>
                <form class="block-settings-form">
                    <div class="block-settings-fields"></div>
                    <div class="block-settings-actions">
                        <button type="button" class="btn btn-secondary block-settings-cancel">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        `;

        const fieldsContainer = modal.querySelector('.block-settings-fields');

        // Generate form fields
        for (const [fieldKey, fieldConfig] of Object.entries(blockConfig.fields)) {
            const fieldValue = blockData[fieldKey] ?? fieldConfig.default ?? '';
            const fieldHtml = generateFormField(fieldKey, fieldConfig, fieldValue);
            fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        }

        document.body.appendChild(modal);

        // Setup image pickers
        modal.querySelectorAll('.field-image-picker').forEach(picker => {
            picker.addEventListener('click', () => {
                openMediaLibrary((url) => {
                    const input = picker.parentElement.querySelector('input[type="hidden"]');
                    const preview = picker.querySelector('.image-preview');
                    input.value = url;
                    preview.innerHTML = `<img src="${url}" alt="">`;
                });
            });
        });

        // Close handlers
        modal.querySelector('.block-settings-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.block-settings-cancel').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        // Save handler
        modal.querySelector('.block-settings-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const newData = {};

            for (const [key, value] of formData.entries()) {
                // Handle nested keys like cards[0].title
                if (key.includes('[')) {
                    // Parse array notation - simplified for now
                    newData[key] = value;
                } else {
                    newData[key] = value;
                }
            }

            // Handle checkboxes
            modal.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                newData[cb.name] = cb.checked;
            });

            // Update block data
            block.data = { ...blockData, ...newData };
            state.hasUnsavedChanges = true;

            await saveBlocks();
            modal.remove();
            location.reload();
        });
    }

    /**
     * Generate form field HTML
     */
    function generateFormField(key, config, value) {
        const label = config.label || key;
        const required = config.required ? 'required' : '';

        switch (config.type) {
            case 'text':
                return `
                    <div class="form-field">
                        <label for="field-${key}">${label}</label>
                        <input type="text" id="field-${key}" name="${key}" value="${escapeHtml(value)}" ${required}>
                    </div>
                `;

            case 'textarea':
                return `
                    <div class="form-field">
                        <label for="field-${key}">${label}</label>
                        <textarea id="field-${key}" name="${key}" rows="4" ${required}>${escapeHtml(value)}</textarea>
                    </div>
                `;

            case 'richtext':
                return `
                    <div class="form-field">
                        <label for="field-${key}">${label}</label>
                        <div class="richtext-toolbar">
                            <button type="button" data-cmd="bold"><b>B</b></button>
                            <button type="button" data-cmd="italic"><i>I</i></button>
                            <button type="button" data-cmd="formatBlock" data-value="h2">H2</button>
                            <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
                        </div>
                        <div class="richtext-editor" contenteditable="true" data-field="${key}">${value}</div>
                        <input type="hidden" name="${key}" value="${escapeHtml(value)}">
                    </div>
                `;

            case 'image':
                return `
                    <div class="form-field">
                        <label>${label}</label>
                        <div class="field-image-picker">
                            <div class="image-preview">${value ? `<img src="${escapeHtml(value)}" alt="">` : 'Click to select image'}</div>
                        </div>
                        <input type="hidden" name="${key}" value="${escapeHtml(value)}">
                    </div>
                `;

            case 'color':
                return `
                    <div class="form-field">
                        <label for="field-${key}">${label}</label>
                        <input type="color" id="field-${key}" name="${key}" value="${value || config.default || '#4B2679'}">
                    </div>
                `;

            case 'select':
                const options = (config.options || []).map(opt =>
                    `<option value="${opt}" ${opt === value ? 'selected' : ''}>${opt}</option>`
                ).join('');
                return `
                    <div class="form-field">
                        <label for="field-${key}">${label}</label>
                        <select id="field-${key}" name="${key}">${options}</select>
                    </div>
                `;

            case 'checkbox':
                return `
                    <div class="form-field form-field-checkbox">
                        <label>
                            <input type="checkbox" name="${key}" ${value ? 'checked' : ''}>
                            ${label}
                        </label>
                    </div>
                `;

            case 'repeater':
                // Simplified repeater - just show JSON for now
                const items = Array.isArray(value) ? value : [];
                return `
                    <div class="form-field form-field-repeater">
                        <label>${label}</label>
                        <div class="repeater-items" data-field="${key}">
                            ${items.map((item, i) => `
                                <div class="repeater-item" data-index="${i}">
                                    <div class="repeater-item-header">
                                        <span>Item ${i + 1}</span>
                                        <button type="button" class="repeater-remove">&times;</button>
                                    </div>
                                    <div class="repeater-item-fields">
                                        ${Object.entries(config.fields).map(([subKey, subConfig]) => `
                                            <div class="form-field form-field-sm">
                                                <label>${subConfig.label}</label>
                                                <input type="text" name="${key}[${i}][${subKey}]" value="${escapeHtml(item[subKey] || '')}">
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        <button type="button" class="btn btn-sm repeater-add" data-field="${key}">+ Add Item</button>
                    </div>
                `;

            default:
                return `
                    <div class="form-field">
                        <label for="field-${key}">${label}</label>
                        <input type="text" id="field-${key}" name="${key}" value="${escapeHtml(value)}">
                    </div>
                `;
        }
    }

    /**
     * Save all blocks
     */
    async function saveBlocks() {
        const blocksToSave = state.blocks.map((block, index) => ({
            uuid: block.block_uuid || block.uuid,
            type: block.block_type || block.type,
            data: block.data || (typeof block.block_data === 'string' ? JSON.parse(block.block_data) : block.block_data) || {},
            order: index
        }));

        try {
            const response = await fetch('/api/cms/blocks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page: state.pageSlug,
                    blocks: blocksToSave
                })
            });

            const data = await response.json();

            if (data.success) {
                state.hasUnsavedChanges = false;
                showNotification('Changes saved', 'success');
            } else {
                showNotification('Failed to save: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Save error:', error);
            showNotification('Failed to save changes', 'error');
        }
    }

    /**
     * Save block order only
     */
    async function saveBlockOrder() {
        const order = state.blocks.map(b => b.block_uuid || b.uuid);

        console.log('[BlockBuilder] Saving order:', order);

        try {
            const response = await fetch('/api/cms/blocks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page: state.pageSlug,
                    reorder: order
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Order saved', 'success');
                state.hasUnsavedChanges = false;
                // Don't reload - DOM is already updated
            } else {
                showNotification('Failed to save order', 'error');
                // Reload to restore correct order
                location.reload();
            }
        } catch (error) {
            console.error('Reorder error:', error);
            showNotification('Failed to save order', 'error');
        }
    }

    /**
     * Open media library
     */
    function openMediaLibrary(callback) {
        // Use existing CMS media library if available
        if (typeof window.openMediaLibrary === 'function') {
            window.openMediaLibrary(callback);
            return;
        }

        // Fallback: simple file input
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('files[]', file);

            try {
                const response = await fetch('/api/cms/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success && data.files.length > 0) {
                    callback(data.files[0].url);
                }
            } catch (error) {
                console.error('Upload error:', error);
            }
        };
        input.click();
    }

    /**
     * Setup keyboard shortcuts
     */
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (state.hasUnsavedChanges) {
                    saveBlocks();
                }
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.block-settings-modal, .block-palette.open').forEach(el => {
                    el.classList.remove('open');
                    if (el.classList.contains('block-settings-modal')) {
                        el.remove();
                    }
                });
            }
        });
    }

    /**
     * Setup beforeunload warning
     */
    function setupBeforeUnload() {
        window.addEventListener('beforeunload', (e) => {
            if (state.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `block-notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Generate UUID v4
     */
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

})();
