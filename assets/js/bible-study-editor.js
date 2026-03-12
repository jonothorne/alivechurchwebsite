/**
 * Bible Study Inline Editor
 * Enables inline editing of Bible studies for editors/admins
 */
(function() {
    'use strict';

    // Only initialize if we have editable content
    const container = document.querySelector('.bible-study-editable');
    if (!container) return;

    const studyId = container.dataset.studyId;
    if (!studyId) return;

    // State
    let currentlyEditing = null;
    let originalContent = '';
    let hasUnsavedChanges = false;
    let toolbar = null;

    // Initialize
    init();

    function init() {
        createToolbar();
        createStatusToggle();
        setupEditableElements();
        setupKeyboardShortcuts();
        setupUnsavedWarning();
    }

    // Create floating toolbar
    function createToolbar() {
        toolbar = document.createElement('div');
        toolbar.className = 'bible-study-toolbar';
        toolbar.innerHTML = `
            <div class="toolbar-buttons">
                <button type="button" data-command="bold" title="Bold (Ctrl+B)"><strong>B</strong></button>
                <button type="button" data-command="italic" title="Italic (Ctrl+I)"><em>I</em></button>
                <span class="toolbar-separator"></span>
                <button type="button" data-command="h2" title="Heading 2">H2</button>
                <button type="button" data-command="h3" title="Heading 3">H3</button>
                <button type="button" data-command="p" title="Paragraph">P</button>
                <button type="button" data-command="blockquote" title="Quote">❝</button>
                <button type="button" data-command="intro" title="Intro Text">Intro</button>
                <span class="toolbar-separator"></span>
                <button type="button" data-command="verse" title="Insert verse marker">[v]</button>
                <button type="button" data-command="link" title="Insert link">&#128279;</button>
            </div>
            <div class="toolbar-actions">
                <button type="button" class="toolbar-save" title="Save (Ctrl+S)">Save</button>
                <button type="button" class="toolbar-cancel" title="Cancel (Esc)">Cancel</button>
            </div>
            <div class="toolbar-status"></div>
        `;
        document.body.appendChild(toolbar);

        // Toolbar button handlers
        toolbar.querySelectorAll('[data-command]').forEach(btn => {
            btn.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Prevent losing focus
                executeCommand(btn.dataset.command);
                // Update button states after command
                setTimeout(updateToolbarState, 10);
            });
        });

        toolbar.querySelector('.toolbar-save').addEventListener('click', saveCurrentEdit);
        toolbar.querySelector('.toolbar-cancel').addEventListener('click', cancelEdit);

        // Listen for selection changes to update button states
        document.addEventListener('selectionchange', () => {
            if (currentlyEditing) {
                updateToolbarState();
            }
        });
    }

    // Update toolbar button active states based on current selection
    function updateToolbarState() {
        if (!toolbar) return;

        // Check inline formatting
        const isBold = document.queryCommandState('bold');
        const isItalic = document.queryCommandState('italic');

        // Check block formatting
        const block = findCurrentBlock();
        const blockTag = block ? block.tagName : '';
        const hasIntro = block ? block.classList.contains('intro-text') : false;

        // Update button states
        toolbar.querySelectorAll('[data-command]').forEach(btn => {
            const cmd = btn.dataset.command;
            let isActive = false;

            if (cmd === 'bold') isActive = isBold;
            else if (cmd === 'italic') isActive = isItalic;
            else if (cmd === 'h2') isActive = blockTag === 'H2';
            else if (cmd === 'h3') isActive = blockTag === 'H3';
            else if (cmd === 'p') isActive = blockTag === 'P' && !hasIntro;
            else if (cmd === 'blockquote') isActive = blockTag === 'BLOCKQUOTE';
            else if (cmd === 'intro') isActive = hasIntro;

            btn.classList.toggle('active', isActive);
        });
    }

    // Create status toggle button
    function createStatusToggle() {
        const statusEl = document.querySelector('.study-status-toggle');
        if (!statusEl) return;

        statusEl.addEventListener('click', async () => {
            const currentStatus = statusEl.dataset.status;
            const newStatus = currentStatus === 'published' ? 'draft' : 'published';
            const action = newStatus === 'published' ? 'publish' : 'unpublish';

            if (!confirm(`Are you sure you want to ${action} this study?`)) {
                return;
            }

            statusEl.classList.add('saving');
            statusEl.querySelector('.status-text').textContent = 'Saving...';

            try {
                const response = await fetch('/api/bible-study/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ study_id: studyId, status: newStatus })
                });

                const result = await response.json();

                if (result.success) {
                    statusEl.dataset.status = newStatus;
                    statusEl.classList.toggle('published', newStatus === 'published');
                    statusEl.querySelector('.status-text').textContent = newStatus === 'published' ? 'Published' : 'Draft';
                    showNotification('Status updated!', 'success');
                } else {
                    throw new Error(result.error || 'Failed to update status');
                }
            } catch (error) {
                showNotification(error.message, 'error');
                statusEl.querySelector('.status-text').textContent = currentStatus === 'published' ? 'Published' : 'Draft';
            } finally {
                statusEl.classList.remove('saving');
            }
        });
    }

    // Setup editable elements
    function setupEditableElements() {
        const editables = container.querySelectorAll('[data-editable]');

        editables.forEach(el => {
            // Add edit indicator on hover
            el.classList.add('can-edit');

            // Click to edit
            el.addEventListener('click', (e) => {
                if (currentlyEditing === el) return;
                if (currentlyEditing) {
                    // Save current before switching
                    saveCurrentEdit().then(() => startEditing(el));
                } else {
                    startEditing(el);
                }
            });
        });
    }

    // Start editing an element
    function startEditing(el) {
        const type = el.dataset.editable;

        currentlyEditing = el;

        // For content field, use raw content (without processed verse markers/links)
        if (type === 'content' && el.dataset.rawContent) {
            originalContent = el.innerHTML; // Store processed version for cancel
            el.innerHTML = el.dataset.rawContent; // Load raw for editing
        } else {
            originalContent = el.innerHTML;
        }

        if (type === 'title' || type === 'summary') {
            el.contentEditable = 'plaintext-only';
        } else {
            el.contentEditable = 'true';
        }

        el.classList.add('editing');
        el.focus();

        // Position and show toolbar
        positionToolbar(el);
        toolbar.classList.add('visible');

        // Track changes
        el.addEventListener('input', onContentChange);

        // Add click handlers for images inside editable content
        setupImageClickHandlers(el);
    }

    // Setup click handlers for images inside editable content
    function setupImageClickHandlers(container) {
        const images = container.querySelectorAll('img');
        images.forEach(img => {
            img.classList.add('cms-editable-image');
            img.removeEventListener('click', handleImageClick);
            img.addEventListener('click', handleImageClick);
        });
    }

    // Handle click on image inside editable content
    function handleImageClick(e) {
        e.preventDefault();
        e.stopPropagation();
        const img = e.target;
        openMediaLibrary((src) => {
            img.src = src;
            hasUnsavedChanges = true;
            toolbar.classList.add('has-changes');
        });
    }

    // Open media library modal
    function openMediaLibrary(callback) {
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'bible-study-media-modal';
        modal.innerHTML = `
            <div class="media-modal-backdrop"></div>
            <div class="media-modal-content">
                <div class="media-modal-header">
                    <h3>Select Image</h3>
                    <button class="media-modal-close">&times;</button>
                </div>
                <div class="media-modal-body">
                    <div class="media-upload-area">
                        <input type="file" id="bible-study-media-upload" accept="image/*">
                        <label for="bible-study-media-upload">
                            <span>Drop image here or click to upload</span>
                        </label>
                    </div>
                    <div class="media-grid" id="bible-study-media-grid">
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close handlers
        modal.querySelector('.media-modal-backdrop').addEventListener('click', () => modal.remove());
        modal.querySelector('.media-modal-close').addEventListener('click', () => modal.remove());

        // Load media
        loadMediaGrid(modal, callback);

        // Handle upload
        modal.querySelector('#bible-study-media-upload').addEventListener('change', (e) => {
            handleMediaUpload(e, modal, callback);
        });
    }

    // Load media grid
    async function loadMediaGrid(modal, callback) {
        const grid = modal.querySelector('#bible-study-media-grid');
        try {
            const response = await fetch('/api/cms/media');
            const result = await response.json();
            if (result.success && result.media.length > 0) {
                grid.innerHTML = result.media.map(item => `
                    <div class="media-grid-item" data-url="${item.file_url}">
                        <img src="${item.file_url}" alt="${item.alt_text || ''}">
                    </div>
                `).join('');
                grid.querySelectorAll('.media-grid-item').forEach(item => {
                    item.addEventListener('click', () => {
                        if (callback) callback(item.dataset.url);
                        modal.remove();
                    });
                });
            } else {
                grid.innerHTML = '<p>No images found. Upload one!</p>';
            }
        } catch (error) {
            grid.innerHTML = '<p>Error loading images</p>';
        }
    }

    // Handle media upload
    async function handleMediaUpload(e, modal, callback) {
        const files = e.target.files;
        if (!files.length) return;

        const formData = new FormData();
        formData.append('files[]', files[0]);

        try {
            const response = await fetch('/api/cms/upload', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                loadMediaGrid(modal, callback);
            }
        } catch (error) {
            console.error('Upload error:', error);
        }
    }

    // Handle content changes
    function onContentChange() {
        hasUnsavedChanges = currentlyEditing.innerHTML !== originalContent;
        toolbar.classList.toggle('has-changes', hasUnsavedChanges);
    }

    // Position toolbar - fixed at bottom center of viewport
    function positionToolbar(el) {
        // Use fixed positioning so toolbar follows scroll
        toolbar.style.position = 'fixed';
        toolbar.style.bottom = '20px';
        toolbar.style.top = 'auto';
        toolbar.style.left = '50%';
        toolbar.style.transform = 'translateX(-50%)';
        toolbar.style.zIndex = '10001';
    }

    // Execute formatting command
    function executeCommand(command) {
        if (!currentlyEditing) return;

        switch (command) {
            case 'bold':
                document.execCommand('bold', false, null);
                break;
            case 'italic':
                document.execCommand('italic', false, null);
                break;
            case 'h2':
                document.execCommand('formatBlock', false, '<h2>');
                break;
            case 'h3':
                document.execCommand('formatBlock', false, '<h3>');
                break;
            case 'p':
                convertToParagraph();
                break;
            case 'blockquote':
                convertToBlockquote();
                break;
            case 'intro':
                toggleIntroText();
                break;
            case 'verse':
                insertVerseMarker();
                break;
            case 'link':
                insertLink();
                break;
        }
        onContentChange();
    }

    // Insert verse marker helper
    function insertVerseMarker() {
        const verseNum = prompt('Enter verse number or range (e.g., "5" or "5-7"):');
        if (verseNum && /^\d+(-\d+)?$/.test(verseNum.trim())) {
            document.execCommand('insertText', false, '[' + verseNum.trim() + ']');
        }
    }

    // Insert link
    function insertLink() {
        const selection = window.getSelection();
        const hasSelection = selection.toString().length > 0;
        const url = prompt('Enter URL:', 'https://');
        if (url && url !== 'https://') {
            if (hasSelection) {
                document.execCommand('createLink', false, url);
            } else {
                const text = prompt('Enter link text:');
                if (text) {
                    document.execCommand('insertHTML', false, `<a href="${url}">${text}</a>`);
                }
            }
        }
    }

    // Find the current block element
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

    // Convert current block to paragraph
    function convertToParagraph() {
        const block = findCurrentBlock();
        if (block && block.tagName !== 'P') {
            const p = document.createElement('p');
            p.innerHTML = block.innerHTML;
            // Preserve any classes except block-specific ones
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
        }
    }

    // Convert current block to blockquote
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
        } else if (!block) {
            // No block found, use execCommand as fallback
            document.execCommand('formatBlock', false, '<blockquote>');
        }
    }

    // Toggle intro text class on current block
    function toggleIntroText() {
        const block = findCurrentBlock();
        if (block) {
            block.classList.toggle('intro-text');
            console.log('Toggled intro-text on', block.tagName, 'now has class:', block.classList.contains('intro-text'));
        } else {
            console.log('No block found for intro text');
            // Try to wrap in paragraph first
            document.execCommand('formatBlock', false, '<p>');
            setTimeout(() => {
                const newBlock = findCurrentBlock();
                if (newBlock) {
                    newBlock.classList.add('intro-text');
                }
            }, 10);
        }
    }

    // Save current edit
    async function saveCurrentEdit() {
        if (!currentlyEditing) return;

        const el = currentlyEditing;
        const field = el.dataset.editable;
        const content = field === 'content' ? el.innerHTML : el.textContent.trim();

        // Show saving status
        setToolbarStatus('Saving...', 'saving');

        try {
            const response = await fetch('/api/bible-study/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    study_id: studyId,
                    [field]: content
                })
            });

            const result = await response.json();

            if (result.success) {
                setToolbarStatus('Saved!', 'success');
                hasUnsavedChanges = false;
                toolbar.classList.remove('has-changes');

                // For content updates, reload page to regenerate processed content
                if (field === 'content') {
                    showNotification('Content saved! Reloading...', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    // Update raw content data attribute
                    if (field === 'content') {
                        el.dataset.rawContent = content;
                    }
                    originalContent = el.innerHTML;
                    setTimeout(() => finishEditing(), 1000);
                }
            } else {
                throw new Error(result.error || 'Save failed');
            }
        } catch (error) {
            setToolbarStatus('Error: ' + error.message, 'error');
        }
    }

    // Cancel edit
    function cancelEdit() {
        if (!currentlyEditing) return;

        if (hasUnsavedChanges) {
            if (!confirm('Discard unsaved changes?')) {
                return;
            }
        }

        currentlyEditing.innerHTML = originalContent;
        finishEditing();
    }

    // Finish editing
    function finishEditing() {
        if (currentlyEditing) {
            currentlyEditing.contentEditable = 'false';
            currentlyEditing.classList.remove('editing');
            currentlyEditing.removeEventListener('input', onContentChange);
        }

        currentlyEditing = null;
        originalContent = '';
        hasUnsavedChanges = false;

        toolbar.classList.remove('visible', 'has-changes');
        setToolbarStatus('');
    }

    // Set toolbar status message
    function setToolbarStatus(message, type = '') {
        const statusEl = toolbar.querySelector('.toolbar-status');
        statusEl.textContent = message;
        statusEl.className = 'toolbar-status';
        if (type) statusEl.classList.add(type);
    }

    // Show notification
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `bible-study-notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => notification.classList.add('visible'), 10);
        setTimeout(() => {
            notification.classList.remove('visible');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Keyboard shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (!currentlyEditing) return;

            // Ctrl/Cmd + S = Save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveCurrentEdit();
            }

            // Escape = Cancel
            if (e.key === 'Escape') {
                cancelEdit();
            }

            // Ctrl/Cmd + B = Bold
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                executeCommand('bold');
            }

            // Ctrl/Cmd + I = Italic
            if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
                e.preventDefault();
                executeCommand('italic');
            }
        });
    }

    // Warn before leaving with unsaved changes
    function setupUnsavedWarning() {
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

})();
