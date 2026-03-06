/**
 * Alive Church CMS - Inline Editor
 *
 * Provides WYSIWYG editing directly on the page for logged-in admins.
 */

(function() {
    'use strict';

    // Editor state
    const state = {
        isEditing: false,
        currentElement: null,
        hasUnsavedChanges: false,
        originalContent: new Map()
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        // Check if we're in edit mode (admin logged in)
        if (!document.body.classList.contains('cms-edit-mode')) {
            return;
        }

        createEditorToolbar();
        createFloatingToolbar();
        setupEditableElements();
        setupKeyboardShortcuts();
        setupBeforeUnload();
    }

    /**
     * Create the main editor toolbar (top bar)
     */
    function createEditorToolbar() {
        const toolbar = document.createElement('div');
        toolbar.id = 'cms-toolbar';
        toolbar.innerHTML = `
            <div class="cms-toolbar-inner">
                <div class="cms-toolbar-left">
                    <span class="cms-toolbar-logo">CMS</span>
                    <span class="cms-toolbar-status" id="cms-status">Ready to edit</span>
                </div>
                <div class="cms-toolbar-center">
                    <span class="cms-toolbar-page">Editing: <strong>${getPageSlug()}</strong></span>
                </div>
                <div class="cms-toolbar-right">
                    <button class="cms-btn cms-btn-ghost" id="cms-preview-btn" title="Preview (Ctrl+P)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Preview
                    </button>
                    <button class="cms-btn cms-btn-ghost" id="cms-media-btn" title="Media Library">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        Media
                    </button>
                    <button class="cms-btn cms-btn-ghost" id="cms-settings-btn" title="Page Settings">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        Settings
                    </button>
                    <a href="/admin" class="cms-btn cms-btn-ghost" title="Dashboard">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="/admin/logout" class="cms-btn cms-btn-ghost">Exit Edit Mode</a>
                </div>
            </div>
        `;

        document.body.insertBefore(toolbar, document.body.firstChild);
        document.body.style.paddingTop = '50px';

        // Event listeners
        document.getElementById('cms-preview-btn').addEventListener('click', togglePreview);
        document.getElementById('cms-media-btn').addEventListener('click', openMediaLibrary);
        document.getElementById('cms-settings-btn').addEventListener('click', openPageSettings);
    }

    /**
     * Create floating formatting toolbar (appears when editing)
     */
    function createFloatingToolbar() {
        const toolbar = document.createElement('div');
        toolbar.id = 'cms-floating-toolbar';
        toolbar.className = 'cms-floating-toolbar';
        toolbar.innerHTML = `
            <button data-cmd="bold" title="Bold (Ctrl+B)"><strong>B</strong></button>
            <button data-cmd="italic" title="Italic (Ctrl+I)"><em>I</em></button>
            <button data-cmd="underline" title="Underline (Ctrl+U)"><u>U</u></button>
            <span class="cms-toolbar-divider"></span>
            <button data-cmd="formatBlock" data-value="h2" title="Heading 2">H2</button>
            <button data-cmd="formatBlock" data-value="h3" title="Heading 3">H3</button>
            <button data-cmd="formatBlock" data-value="p" title="Paragraph">P</button>
            <span class="cms-toolbar-divider"></span>
            <button data-cmd="insertUnorderedList" title="Bullet List">•</button>
            <button data-cmd="insertOrderedList" title="Numbered List">1.</button>
            <span class="cms-toolbar-divider"></span>
            <button data-cmd="createLink" title="Insert Link">🔗</button>
            <button data-cmd="insertImage" title="Insert Image">🖼</button>
            <span class="cms-toolbar-divider"></span>
            <button data-cmd="removeFormat" title="Remove Formatting">✕</button>
            <span class="cms-toolbar-divider"></span>
            <button class="cms-save-btn" data-cmd="save" title="Save (Ctrl+S)">Save</button>
            <button class="cms-cancel-btn" data-cmd="cancel" title="Cancel (Esc)">Cancel</button>
        `;

        document.body.appendChild(toolbar);

        // Add event listeners to buttons
        toolbar.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Prevent losing focus
                handleToolbarCommand(btn.dataset.cmd, btn.dataset.value);
            });
        });
    }

    /**
     * Setup all editable elements
     */
    function setupEditableElements() {
        const editables = document.querySelectorAll('[data-cms-editable], [data-cms-global]');

        editables.forEach(el => {
            // Store original content
            state.originalContent.set(el, el.innerHTML);

            // Click to edit
            el.addEventListener('click', (e) => {
                if (!state.isEditing || state.currentElement !== el) {
                    e.preventDefault();
                    e.stopPropagation();
                    startEditing(el);
                }
            });
        });
    }

    /**
     * Start editing an element
     */
    function startEditing(el) {
        // Save any previous edits
        if (state.currentElement && state.currentElement !== el) {
            saveCurrentEdit();
        }

        state.isEditing = true;
        state.currentElement = el;

        const type = el.dataset.cmsType || 'html';

        // Make element editable
        if (type === 'text') {
            el.contentEditable = 'plaintext-only';
        } else if (type === 'image') {
            openImagePicker(el);
            return;
        } else if (type === 'link') {
            openLinkEditor(el);
            return;
        } else {
            el.contentEditable = 'true';
        }

        el.classList.add('cms-editing');
        el.focus();

        // Position floating toolbar
        positionFloatingToolbar(el);

        // Update status
        updateStatus('Editing: ' + (el.dataset.cmsEditable || el.dataset.cmsGlobal));

        // Track changes
        el.addEventListener('input', handleContentChange);
        el.addEventListener('blur', handleBlur);
        el.addEventListener('keydown', handleKeydown);
    }

    /**
     * Stop editing current element
     */
    function stopEditing(save = true) {
        if (!state.currentElement) return;

        const el = state.currentElement;

        if (save && state.hasUnsavedChanges) {
            // Save changes - this will update originalContent after successful save
            saveCurrentEdit();
        } else if (!save && state.hasUnsavedChanges) {
            // Cancel - restore original content only if there were unsaved changes
            el.innerHTML = state.originalContent.get(el);
        }
        // If save=false and no unsaved changes, just leave content as-is (already matches original)

        el.contentEditable = 'false';
        el.classList.remove('cms-editing');

        // Hide floating toolbar
        document.getElementById('cms-floating-toolbar').style.display = 'none';

        // Remove event listeners
        el.removeEventListener('input', handleContentChange);
        el.removeEventListener('blur', handleBlur);
        el.removeEventListener('keydown', handleKeydown);

        state.isEditing = false;
        state.currentElement = null;
        state.hasUnsavedChanges = false;

        updateStatus('Ready to edit');
    }

    /**
     * Save current edit to server
     */
    async function saveCurrentEdit() {
        if (!state.currentElement) return;

        const el = state.currentElement;
        const isGlobal = el.hasAttribute('data-cms-global');
        const key = isGlobal ? el.dataset.cmsGlobal : el.dataset.cmsEditable;
        const pageSlug = el.dataset.cmsPage || getPageSlug();
        const type = el.dataset.cmsType || 'html';

        // Get content based on type
        let content;
        if (type === 'image') {
            content = el.src;
        } else if (type === 'link') {
            content = el.href;
        } else if (type === 'text') {
            content = el.textContent;
        } else {
            content = el.innerHTML;
        }

        updateStatus('Saving...');

        try {
            const response = await fetch('/api/cms/save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    key: key,
                    page: isGlobal ? '_global' : pageSlug,
                    content: content,
                    type: type,
                    isGlobal: isGlobal
                })
            });

            const result = await response.json();

            if (result.success) {
                // Update original content
                state.originalContent.set(el, el.innerHTML);
                state.hasUnsavedChanges = false;
                updateStatus('Saved!');

                // Flash green border
                el.classList.add('cms-saved');
                setTimeout(() => el.classList.remove('cms-saved'), 1000);
            } else {
                updateStatus('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Save error:', error);
            updateStatus('Error saving content');
        }
    }

    /**
     * Position the floating toolbar above the current element
     */
    function positionFloatingToolbar(el) {
        const toolbar = document.getElementById('cms-floating-toolbar');
        const rect = el.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        let top = rect.top + scrollTop - toolbar.offsetHeight - 10;
        let left = rect.left + (rect.width / 2) - (toolbar.offsetWidth / 2);

        // Keep toolbar in viewport
        if (top < 60) { // Below CMS toolbar
            top = rect.bottom + scrollTop + 10;
        }
        if (left < 10) left = 10;
        if (left + toolbar.offsetWidth > window.innerWidth - 10) {
            left = window.innerWidth - toolbar.offsetWidth - 10;
        }

        toolbar.style.top = top + 'px';
        toolbar.style.left = left + 'px';
        toolbar.style.display = 'flex';
    }

    /**
     * Handle toolbar commands
     */
    function handleToolbarCommand(cmd, value) {
        if (cmd === 'save') {
            saveCurrentEdit().then(() => {
                // After saving, stop editing but don't restore (content is already correct)
                state.hasUnsavedChanges = false;
                stopEditing(true);
            });
            return;
        }

        if (cmd === 'cancel') {
            // Cancel - restore original content
            stopEditing(false);
            return;
        }

        if (cmd === 'createLink') {
            const url = prompt('Enter URL:', 'https://');
            if (url) {
                document.execCommand('createLink', false, url);
            }
            return;
        }

        if (cmd === 'insertImage') {
            openMediaLibrary((src) => {
                document.execCommand('insertImage', false, src);
            });
            return;
        }

        if (cmd === 'formatBlock') {
            document.execCommand(cmd, false, '<' + value + '>');
            return;
        }

        document.execCommand(cmd, false, value);
    }

    /**
     * Handle content changes
     */
    function handleContentChange() {
        state.hasUnsavedChanges = true;
        updateStatus('Unsaved changes');
    }

    /**
     * Handle blur (clicking outside)
     */
    function handleBlur(e) {
        // Check if clicking on toolbar
        const toolbar = document.getElementById('cms-floating-toolbar');
        if (toolbar.contains(e.relatedTarget)) {
            return;
        }

        // Small delay to allow toolbar clicks
        setTimeout(() => {
            if (state.currentElement && !state.currentElement.contains(document.activeElement)) {
                // Save changes if any, then stop editing (pass true to indicate we want to save)
                stopEditing(true);
            }
        }, 200);
    }

    /**
     * Handle keyboard shortcuts
     */
    function handleKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            stopEditing(false);
        }

        if (e.ctrlKey || e.metaKey) {
            if (e.key === 's') {
                e.preventDefault();
                saveCurrentEdit();
            }
        }
    }

    /**
     * Setup global keyboard shortcuts
     */
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'p') {
                    e.preventDefault();
                    togglePreview();
                }
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
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }

    /**
     * Toggle preview mode
     */
    function togglePreview() {
        if (state.hasUnsavedChanges) {
            saveCurrentEdit();
        }
        stopEditing(false);

        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('preview', 'true');
        window.open(currentUrl.toString(), '_blank');
    }

    /**
     * Open media library modal
     */
    function openMediaLibrary(callback) {
        const modal = createModal('Media Library', `
            <div class="cms-media-library">
                <div class="cms-media-upload">
                    <input type="file" id="cms-media-upload-input" accept="image/*" multiple>
                    <label for="cms-media-upload-input">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <span>Drop images here or click to upload</span>
                    </label>
                </div>
                <div class="cms-media-grid" id="cms-media-grid">
                    <p>Loading media...</p>
                </div>
            </div>
        `);

        // Load existing media
        loadMedia(callback);

        // Handle upload
        const uploadInput = document.getElementById('cms-media-upload-input');
        uploadInput.addEventListener('change', (e) => handleMediaUpload(e, callback));
    }

    /**
     * Load media from server
     */
    async function loadMedia(selectCallback) {
        const grid = document.getElementById('cms-media-grid');

        try {
            const response = await fetch('/api/cms/media.php');
            const result = await response.json();

            if (result.success && result.media.length > 0) {
                grid.innerHTML = result.media.map(item => `
                    <div class="cms-media-item" data-url="${item.file_url}">
                        <img src="${item.file_url}" alt="${item.alt_text || ''}">
                        <span class="cms-media-name">${item.original_filename}</span>
                    </div>
                `).join('');

                // Add click handlers
                grid.querySelectorAll('.cms-media-item').forEach(item => {
                    item.addEventListener('click', () => {
                        if (selectCallback) {
                            selectCallback(item.dataset.url);
                        }
                        closeModal();
                    });
                });
            } else {
                grid.innerHTML = '<p>No media found. Upload some images!</p>';
            }
        } catch (error) {
            grid.innerHTML = '<p>Error loading media</p>';
        }
    }

    /**
     * Handle media upload
     */
    async function handleMediaUpload(e, callback) {
        const files = e.target.files;
        if (!files.length) return;

        const formData = new FormData();
        for (let file of files) {
            formData.append('files[]', file);
        }

        updateStatus('Uploading...');

        try {
            const response = await fetch('/api/cms/upload.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                updateStatus('Upload complete');
                loadMedia(callback);
            } else {
                updateStatus('Upload failed: ' + result.error);
            }
        } catch (error) {
            updateStatus('Upload error');
        }
    }

    /**
     * Open image picker for image elements
     */
    function openImagePicker(el) {
        openMediaLibrary((src) => {
            el.src = src;
            state.hasUnsavedChanges = true;
            saveCurrentEdit();
        });
    }

    /**
     * Open link editor for link elements
     */
    function openLinkEditor(el) {
        const currentHref = el.getAttribute('href') || '';
        const newHref = prompt('Enter URL:', currentHref);

        if (newHref !== null) {
            el.setAttribute('href', newHref);
            state.hasUnsavedChanges = true;
            saveCurrentEdit();
        }

        state.currentElement = null;
        state.isEditing = false;
    }

    /**
     * Open page settings modal
     */
    function openPageSettings() {
        createModal('Page Settings', `
            <form id="cms-page-settings-form" class="cms-form">
                <div class="cms-form-group">
                    <label>Page Title</label>
                    <input type="text" name="title" id="cms-page-title" placeholder="Page Title">
                </div>
                <div class="cms-form-group">
                    <label>Meta Description</label>
                    <textarea name="meta_description" id="cms-page-meta" rows="3" placeholder="SEO description..."></textarea>
                </div>
                <div class="cms-form-group">
                    <label>Template</label>
                    <select name="template" id="cms-page-template">
                        <option value="default">Default</option>
                        <option value="full-width">Full Width</option>
                        <option value="sidebar">With Sidebar</option>
                        <option value="landing">Landing Page</option>
                    </select>
                </div>
                <div class="cms-form-actions">
                    <button type="submit" class="cms-btn cms-btn-primary">Save Settings</button>
                </div>
            </form>
        `);

        // Load current settings
        loadPageSettings();

        // Handle form submission
        document.getElementById('cms-page-settings-form').addEventListener('submit', savePageSettings);
    }

    /**
     * Load page settings
     */
    async function loadPageSettings() {
        try {
            const response = await fetch('/api/cms/page.php?slug=' + getPageSlug());
            const result = await response.json();

            if (result.success && result.page) {
                document.getElementById('cms-page-title').value = result.page.title || '';
                document.getElementById('cms-page-meta').value = result.page.meta_description || '';
                document.getElementById('cms-page-template').value = result.page.template || 'default';
            }
        } catch (error) {
            console.error('Error loading page settings:', error);
        }
    }

    /**
     * Save page settings
     */
    async function savePageSettings(e) {
        e.preventDefault();

        const form = e.target;
        const data = {
            slug: getPageSlug(),
            title: form.title.value,
            meta_description: form.meta_description.value,
            template: form.template.value
        };

        try {
            const response = await fetch('/api/cms/page.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                updateStatus('Settings saved');
                closeModal();
            } else {
                updateStatus('Error: ' + result.error);
            }
        } catch (error) {
            updateStatus('Error saving settings');
        }
    }

    /**
     * Create modal
     */
    function createModal(title, content) {
        // Remove existing modal
        closeModal();

        const modal = document.createElement('div');
        modal.id = 'cms-modal';
        modal.className = 'cms-modal';
        modal.innerHTML = `
            <div class="cms-modal-backdrop"></div>
            <div class="cms-modal-content">
                <div class="cms-modal-header">
                    <h3>${title}</h3>
                    <button class="cms-modal-close">&times;</button>
                </div>
                <div class="cms-modal-body">
                    ${content}
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close handlers
        modal.querySelector('.cms-modal-backdrop').addEventListener('click', closeModal);
        modal.querySelector('.cms-modal-close').addEventListener('click', closeModal);

        return modal;
    }

    /**
     * Close modal
     */
    function closeModal() {
        const modal = document.getElementById('cms-modal');
        if (modal) {
            modal.remove();
        }
    }

    /**
     * Update status message
     */
    function updateStatus(message) {
        const status = document.getElementById('cms-status');
        if (status) {
            status.textContent = message;
        }
    }

    /**
     * Get current page slug
     */
    function getPageSlug() {
        const path = window.location.pathname.replace(/^\/|\/$/g, '');
        return path || 'home';
    }

})();
