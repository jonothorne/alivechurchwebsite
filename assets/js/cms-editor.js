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
     * Create the main editor toolbar (top bar) or attach to PHP-rendered toolbar
     */
    function createEditorToolbar() {
        // Check if PHP already rendered the toolbar
        let toolbar = document.getElementById('cms-toolbar');

        if (toolbar && toolbar.classList.contains('cms-toolbar-php')) {
            // PHP toolbar exists, just attach event listeners
            const previewBtn = document.getElementById('cms-preview-btn');
            const mediaBtn = document.getElementById('cms-media-btn');

            if (previewBtn) previewBtn.addEventListener('click', togglePreview);
            if (mediaBtn) mediaBtn.addEventListener('click', openMediaLibrary);
            return;
        }

        // Fallback: Create toolbar via JS (for pages that don't use header.php)
        toolbar = document.createElement('div');
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
                <div class="cms-toolbar-actions">
                    <button class="cms-btn" id="cms-preview-btn" title="Preview (Ctrl+P)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <span class="cms-btn-text">Preview</span>
                    </button>
                    <button class="cms-btn" id="cms-media-btn" title="Media Library">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <span class="cms-btn-text">Media</span>
                    </button>
                    <a href="/admin" class="cms-btn" title="Dashboard">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        <span class="cms-btn-text">Admin</span>
                    </a>
                </div>
            </div>
        `;

        document.body.insertBefore(toolbar, document.body.firstChild);
        document.body.style.paddingTop = '50px';

        // Event listeners
        document.getElementById('cms-preview-btn').addEventListener('click', togglePreview);
        document.getElementById('cms-media-btn').addEventListener('click', openMediaLibrary);
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
            <button data-cmd="formatBlock" data-value="blockquote" title="Quote">❝</button>
            <button data-cmd="introText" title="Intro Text">Intro</button>
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
                // Update button states after command
                setTimeout(updateToolbarState, 10);
            });
        });

        // Listen for selection changes to update button states
        document.addEventListener('selectionchange', () => {
            if (state.isEditing) {
                updateToolbarState();
            }
        });
    }

    /**
     * Update toolbar button active states based on current selection
     */
    function updateToolbarState() {
        const toolbar = document.getElementById('cms-floating-toolbar');
        if (!toolbar) return;

        // Check inline formatting
        const isBold = document.queryCommandState('bold');
        const isItalic = document.queryCommandState('italic');
        const isUnderline = document.queryCommandState('underline');

        // Check if cursor is inside a link
        const currentLink = findCurrentLink();
        const isInLink = !!currentLink;

        // Check block formatting
        const block = findCurrentBlock();
        const blockTag = block ? block.tagName : '';
        const hasIntro = block ? block.classList.contains('intro-text') : false;

        // Update button states
        toolbar.querySelectorAll('button').forEach(btn => {
            const cmd = btn.dataset.cmd;
            const value = btn.dataset.value;
            let isActive = false;

            if (cmd === 'bold') isActive = isBold;
            else if (cmd === 'italic') isActive = isItalic;
            else if (cmd === 'underline') isActive = isUnderline;
            else if (cmd === 'createLink') isActive = isInLink;
            else if (cmd === 'formatBlock' && value === 'h2') isActive = blockTag === 'H2';
            else if (cmd === 'formatBlock' && value === 'h3') isActive = blockTag === 'H3';
            else if (cmd === 'formatBlock' && value === 'p') isActive = blockTag === 'P' && !hasIntro;
            else if (cmd === 'formatBlock' && value === 'blockquote') isActive = blockTag === 'BLOCKQUOTE';
            else if (cmd === 'introText') isActive = hasIntro;

            btn.classList.toggle('active', isActive);
        });
    }

    /**
     * Find if cursor is inside a link element
     */
    function findCurrentLink() {
        const selection = window.getSelection();
        if (!selection.rangeCount) return null;

        let node = selection.getRangeAt(0).commonAncestorContainer;

        // If text node, get parent element
        if (node.nodeType === 3) {
            node = node.parentElement;
        }

        // Walk up to find an anchor element
        while (node && node !== document.body) {
            if (node.hasAttribute && node.hasAttribute('contenteditable')) {
                return null; // Hit the container, no link found
            }
            if (node.tagName === 'A') {
                return node;
            }
            node = node.parentElement;
        }
        return null;
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
        // Save any previous edits - IMPORTANT: capture the element before async save
        if (state.currentElement && state.currentElement !== el) {
            const elementToSave = state.currentElement;
            saveElement(elementToSave); // Use separate function to avoid async race condition
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

        // Add click handlers for images inside editable content
        setupImageClickHandlers(el);
    }

    /**
     * Setup click handlers for images inside editable content
     */
    function setupImageClickHandlers(container) {
        const images = container.querySelectorAll('img');
        images.forEach(img => {
            // Add visual indicator class
            img.classList.add('cms-editable-image');

            // Remove any existing handler to avoid duplicates
            img.removeEventListener('click', handleImageClick);

            // Add click handler
            img.addEventListener('click', handleImageClick);
        });
    }

    /**
     * Handle click on image inside editable content
     */
    function handleImageClick(e) {
        e.preventDefault();
        e.stopPropagation();

        const img = e.target;

        // Open media library and replace image on selection
        openMediaLibrary((src) => {
            img.src = src;
            state.hasUnsavedChanges = true;
            updateStatus('Image updated - remember to save');
        });
    }

    /**
     * Stop editing current element
     */
    function stopEditing(save = true) {
        if (!state.currentElement) return;

        const el = state.currentElement;

        if (save && state.hasUnsavedChanges) {
            // Save changes - pass element directly to avoid race condition
            saveElement(el);
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
     * Save a specific element to server (prevents race conditions when switching elements)
     */
    async function saveElement(el) {
        if (!el) return;

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

        console.log('Saving:', key, 'on page:', pageSlug);

        updateStatus('Saving...');

        try {
            const response = await fetch('/api/cms/save', {
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
            console.log('Save response for', key, ':', result);

            if (result.success) {
                // Update original content
                state.originalContent.set(el, el.innerHTML);
                updateStatus('Saved!');
                console.log('Content saved successfully for', key);

                // Flash green border
                el.classList.add('cms-saved');
                setTimeout(() => el.classList.remove('cms-saved'), 1000);
            } else {
                console.error('Save failed for', key, ':', result.error);
                updateStatus('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Save error for', key, ':', error);
            updateStatus('Error saving content');
        }
    }

    /**
     * Save current edit to server
     */
    async function saveCurrentEdit() {
        if (!state.currentElement) return;
        await saveElement(state.currentElement);
        state.hasUnsavedChanges = false;
    }

    /**
     * Position the floating toolbar - fixed at bottom center of viewport
     */
    function positionFloatingToolbar(el) {
        const toolbar = document.getElementById('cms-floating-toolbar');

        // Use fixed positioning so toolbar follows scroll
        toolbar.style.position = 'fixed';
        toolbar.style.bottom = '20px';
        toolbar.style.top = 'auto';
        toolbar.style.left = '50%';
        toolbar.style.transform = 'translateX(-50%)';
        toolbar.style.display = 'flex';
    }

    /**
     * Find the current block element containing the cursor
     */
    function findCurrentBlock() {
        const selection = window.getSelection();
        if (!selection.rangeCount) return null;

        let node = selection.getRangeAt(0).commonAncestorContainer;

        // If text node, get parent element
        if (node.nodeType === 3) {
            node = node.parentElement;
        }

        // Walk up to find a block element, but stop at contentEditable
        while (node && node !== document.body) {
            if (node.hasAttribute && node.hasAttribute('contenteditable')) {
                return null; // Hit the container, no block found
            }
            if (node.tagName && ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE'].includes(node.tagName)) {
                // Make sure this isn't the contentEditable container
                if (!node.hasAttribute('contenteditable')) {
                    return node;
                }
            }
            node = node.parentElement;
        }
        return null;
    }

    /**
     * Convert current block to paragraph
     */
    function convertToParagraph() {
        const block = findCurrentBlock();
        if (block && block.tagName !== 'P') {
            const p = document.createElement('p');
            p.innerHTML = block.innerHTML;
            // Preserve intro-text class if present
            if (block.classList.contains('intro-text')) {
                p.classList.add('intro-text');
            }
            block.parentNode.replaceChild(p, block);
            // Move cursor into new element
            const range = document.createRange();
            range.selectNodeContents(p);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            state.hasUnsavedChanges = true;
        }
    }

    /**
     * Convert current block to blockquote
     */
    function convertToBlockquote() {
        const block = findCurrentBlock();
        if (block && block.tagName !== 'BLOCKQUOTE') {
            const bq = document.createElement('blockquote');
            bq.innerHTML = block.innerHTML;
            block.parentNode.replaceChild(bq, block);
            // Move cursor into new element
            const range = document.createRange();
            range.selectNodeContents(bq);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            state.hasUnsavedChanges = true;
        } else if (!block) {
            // No block found, use execCommand as fallback
            document.execCommand('formatBlock', false, '<blockquote>');
            state.hasUnsavedChanges = true;
        }
    }

    /**
     * Handle toolbar commands
     */
    function handleToolbarCommand(cmd, value) {
        if (cmd === 'save') {
            // Capture the element before async operations
            const elToSave = state.currentElement;
            if (elToSave) {
                saveElement(elToSave).then(() => {
                    // After saving, stop editing but don't save again
                    state.hasUnsavedChanges = false;
                    stopEditing(false); // false = don't save again
                });
            }
            return;
        }

        if (cmd === 'cancel') {
            // Cancel - restore original content
            stopEditing(false);
            return;
        }

        if (cmd === 'createLink') {
            // Check if cursor is already in a link - if so, edit it
            const existingLink = findCurrentLink();
            if (existingLink) {
                showLinkModal(existingLink, true); // true = editing existing
                return;
            }

            // Save the current selection before anything steals focus
            const selection = window.getSelection();
            if (!selection.rangeCount || selection.isCollapsed) {
                alert('Please select some text first to create a link.');
                return;
            }

            const range = selection.getRangeAt(0);
            const selectedText = range.toString();

            if (!selectedText.trim()) {
                alert('Please select some text first to create a link.');
                return;
            }

            // Check if selection is within our editable element
            if (!state.currentElement || !state.currentElement.contains(range.commonAncestorContainer)) {
                alert('Please select text within the editable area.');
                return;
            }

            // Extract the selected content as a document fragment
            const fragment = range.extractContents();

            // Create link element and append the fragment
            const link = document.createElement('a');
            link.appendChild(fragment);

            // Insert the link where the selection was
            range.insertNode(link);

            console.log('Link created:', link);
            console.log('Current element HTML:', state.currentElement.innerHTML);

            // Now show modal to get URL (link is already in place)
            showLinkModal(link, false); // false = new link

            state.hasUnsavedChanges = true;
            return;
        }

        if (cmd === 'insertImage') {
            openMediaLibrary((src) => {
                document.execCommand('insertImage', false, src);
            });
            return;
        }

        if (cmd === 'introText') {
            const block = findCurrentBlock();
            if (block) {
                block.classList.toggle('intro-text');
                state.hasUnsavedChanges = true;
            } else {
                // Try to wrap in paragraph first
                document.execCommand('formatBlock', false, '<p>');
                setTimeout(() => {
                    const newBlock = findCurrentBlock();
                    if (newBlock) {
                        newBlock.classList.add('intro-text');
                        state.hasUnsavedChanges = true;
                    }
                }, 10);
            }
            return;
        }

        if (cmd === 'formatBlock') {
            if (value === 'p') {
                convertToParagraph();
            } else if (value === 'blockquote') {
                convertToBlockquote();
            } else {
                document.execCommand(cmd, false, '<' + value + '>');
            }
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

        // Check if a modal is open - don't blur while modal is active
        const modal = document.getElementById('cms-modal');
        if (modal) {
            return;
        }

        // Small delay to allow toolbar clicks
        setTimeout(() => {
            // Check again for modal (might have opened during delay)
            const modalCheck = document.getElementById('cms-modal');
            if (modalCheck) {
                return;
            }

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
            const response = await fetch('/api/cms/media');
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
            const response = await fetch('/api/cms/upload', {
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
                        <option value="blank">Blank</option>
                        <option value="two-column">Two Column</option>
                        <option value="split-hero">Split Hero</option>
                        <option value="team">Team/Staff</option>
                        <option value="contact">Contact Page</option>
                        <option value="gallery">Gallery</option>
                        <option value="text-heavy">Text Heavy</option>
                        <option value="cards">Card Grid</option>
                        <option value="centered">Centered</option>
                        <option value="video-hero">Video Hero</option>
                        <option value="announcement">Announcement</option>
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
            const response = await fetch('/api/cms/page?slug=' + getPageSlug());
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
            const response = await fetch('/api/cms/page', {
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
     * Show link URL input modal
     */
    function showLinkModal(linkElement, isEditing = false) {
        const currentUrl = isEditing ? linkElement.getAttribute('href') || '' : '';
        const title = isEditing ? 'Edit Link' : 'Insert Link';
        const submitText = isEditing ? 'Update Link' : 'Insert Link';

        const modal = createModal(title, `
            <form id="cms-link-form" class="cms-form">
                <div class="cms-form-group">
                    <label>URL</label>
                    <input type="text" name="url" id="cms-link-url" placeholder="e.g., /connect or https://example.com" value="${currentUrl}" autofocus>
                </div>
                <div class="cms-form-actions">
                    ${isEditing ? '<button type="button" class="cms-btn cms-btn-danger" id="cms-link-remove">Remove Link</button>' : ''}
                    <button type="button" class="cms-btn cms-btn-ghost" id="cms-link-cancel">Cancel</button>
                    <button type="submit" class="cms-btn cms-btn-primary">${submitText}</button>
                </div>
            </form>
        `);

        const urlInput = document.getElementById('cms-link-url');
        const form = document.getElementById('cms-link-form');
        const cancelBtn = document.getElementById('cms-link-cancel');
        const removeBtn = document.getElementById('cms-link-remove');

        // Focus and select the input
        setTimeout(() => {
            urlInput.focus();
            urlInput.select();
        }, 100);

        // Handle form submit
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const url = urlInput.value.trim();
            if (url) {
                linkElement.href = url;
                state.hasUnsavedChanges = true;
                closeModal();
                updateStatus(isEditing ? 'Link updated - click Save' : 'Link added - click Save');

                // Re-focus the editable element so save button works
                if (state.currentElement) {
                    state.currentElement.focus();
                }
            } else {
                // No URL entered, remove the link wrapper but keep text
                const textContent = linkElement.textContent;
                linkElement.replaceWith(document.createTextNode(textContent));
                state.hasUnsavedChanges = true;
                closeModal();
                updateStatus('Link removed - click Save');
            }
        });

        // Handle remove link (only for editing)
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                const textContent = linkElement.textContent;
                linkElement.replaceWith(document.createTextNode(textContent));
                state.hasUnsavedChanges = true;
                closeModal();
                updateStatus('Link removed - click Save');

                if (state.currentElement) {
                    state.currentElement.focus();
                }
            });
        }

        // Handle cancel
        cancelBtn.addEventListener('click', () => {
            if (!isEditing) {
                // New link - remove the link wrapper but keep text
                const textContent = linkElement.textContent;
                linkElement.replaceWith(document.createTextNode(textContent));
            }
            // For editing, just close - don't change anything
            closeModal();

            if (state.currentElement) {
                state.currentElement.focus();
            }
        });
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
