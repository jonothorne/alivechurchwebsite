/**
 * GrapesJS Page Builder Initialization
 * Alive Church CMS - Visual Page Editor
 */

let editor = null;

// Get CSRF token from meta tag or hidden input
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;

    const input = document.querySelector('input[name="csrf_token"]');
    if (input) return input.value;

    return '';
}

// Get page ID from URL
function getPageId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('page_id');
}

// Load page content with smart handling for migrated vs native pages
async function loadPageContent(editor, pageId) {
    try {
        const response = await fetch(`/admin/api/grapes-save.php?page_id=${pageId}`, {
            headers: {
                'X-CSRF-Token': getCsrfToken()
            }
        });

        if (!response.ok) {
            throw new Error('Failed to load page data');
        }

        const result = await response.json();
        console.log('Loaded page data:', result);

        if (result && result.success && result.data) {
            const data = result.data;

            // Check if this is a migrated page (has HTML but no components)
            if (data.html && !data.components) {
                console.log('Loading migrated page - parsing HTML into components');
                // Set HTML and let GrapesJS parse it into components
                editor.setComponents(data.html);
                if (data.css) {
                    editor.setStyle(data.css);
                }
            }
            // Check if this is a native GrapesJS page (has components)
            else if (data.components) {
                console.log('Loading native GrapesJS page - using components');
                // Load components and styles directly
                editor.setComponents(data.components);
                if (data.styles) {
                    editor.setStyle(data.styles);
                }
            }
            // Empty page
            else {
                console.log('Empty page - no content to load');
            }

            showNotification('Page loaded', 'success');
        }
    } catch (error) {
        console.error('Error loading page content:', error);
        showNotification('Error loading page: ' + error.message, 'error');
    }
}

// Initialize GrapesJS Editor
function initGrapesEditor() {
    const pageId = getPageId();

    if (!pageId) {
        console.error('No page_id provided');
        alert('Error: No page ID provided');
        return;
    }

    console.log('Initializing GrapesJS for page:', pageId);

    try {
        editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            width: '100%',
            fromElement: false,

            // Storage Manager - AJAX to PHP backend
            storageManager: {
                type: 'remote',
                autosave: true,
                autoload: false, // Changed to false - we'll load manually
                stepsBeforeSave: 3,
                options: {
                    remote: {
                        urlStore: `/admin/api/grapes-save.php?page_id=${pageId}`,
                        urlLoad: `/admin/api/grapes-save.php?page_id=${pageId}`,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': getCsrfToken()
                        },
                        onStore: (data) => {
                            console.log('Storing data...', data);
                            return {
                                page_id: pageId,
                                html: data.html,
                                css: data.css,
                                components: JSON.stringify(data.components),
                                styles: JSON.stringify(data.styles),
                                csrf_token: getCsrfToken()
                            };
                        },
                        onLoad: (result) => {
                            console.log('Loading data...', result);
                            if (result && result.success && result.data) {
                                return result.data;
                            }
                            return {};
                        },
                        onError: (error) => {
                            console.error('Storage error:', error);
                        }
                    }
                }
            },

        // Asset Manager - Media library integration
        assetManager: {
            upload: '/admin/api/grapes-media.php',
            uploadName: 'file',
            headers: {
                'X-CSRF-Token': getCsrfToken()
            },
            autoAdd: 1,
            uploadFile: async function(e) {
                const files = e.dataTransfer ? e.dataTransfer.files : e.target.files;
                const formData = new FormData();

                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }
                formData.append('csrf_token', getCsrfToken());
                formData.append('page_id', pageId);

                try {
                    const response = await fetch('/admin/api/grapes-media.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-Token': getCsrfToken()
                        }
                    });

                    const result = await response.json();
                    return result.data || [];
                } catch (error) {
                    console.error('Upload error:', error);
                    return [];
                }
            }
        },

        // Plugins - keep it simple, avoid preset-webpage issue
        plugins: [],
        pluginsOpts: {},

        // Canvas configuration
        canvas: {
            styles: [
                '/assets/css/style.css'
            ],
            scripts: []
        },

        // Device Manager - Responsive preview
        deviceManager: {
            devices: [
                {
                    name: 'Desktop',
                    width: ''
                },
                {
                    name: 'Tablet',
                    width: '768px',
                    widthMedia: '768px'
                },
                {
                    name: 'Mobile',
                    width: '320px',
                    widthMedia: '480px'
                }
            ]
        },

        // Panels configuration - Use GrapesJS defaults
        panels: {
            defaults: []
        },

        // Layer Manager
        layerManager: {},

        // Style Manager
        styleManager: {
            sectors: [
                {
                    name: 'Dimensions',
                    open: false,
                    buildProps: ['width', 'min-height', 'padding', 'margin']
                },
                {
                    name: 'Typography',
                    open: false,
                    buildProps: ['font-family', 'font-size', 'font-weight', 'letter-spacing', 'color', 'line-height', 'text-align']
                },
                {
                    name: 'Decorations',
                    open: false,
                    buildProps: ['background-color', 'border-radius', 'border', 'box-shadow', 'background']
                },
                {
                    name: 'Extra',
                    open: false,
                    buildProps: ['transition', 'perspective', 'transform']
                }
            ]
        },

        // Traits Manager
        traitManager: {},

        // Blocks Manager
        blockManager: {}
        });

        console.log('GrapesJS instance created');

        // Add custom commands
        setupCommands();

        // Initialize custom blocks
        if (typeof initCustomBlocks === 'function') {
            initCustomBlocks(editor);
        }

        // Manual load with smart handling of migrated vs native content
        loadPageContent(editor, pageId);

        // Auto-save notification
        editor.on('storage:end:store', () => {
            showNotification('Changes saved', 'success');
        });

        editor.on('storage:error:store', (err) => {
            console.error('Storage error:', err);
            showNotification('Error saving changes: ' + err, 'error');
        });

        console.log('GrapesJS initialized successfully');
        return editor;

    } catch (error) {
        console.error('Failed to initialize GrapesJS:', error);
        alert('Error initializing page editor: ' + error.message + '\n\nCheck the browser console for details.');
        return null;
    }
}

// Setup custom commands
function setupCommands() {
    // Device commands
    editor.Commands.add('set-device-desktop', {
        run: (editor) => editor.setDevice('Desktop')
    });

    editor.Commands.add('set-device-tablet', {
        run: (editor) => editor.setDevice('Tablet')
    });

    editor.Commands.add('set-device-mobile', {
        run: (editor) => editor.setDevice('Mobile')
    });

    // Export/Save command
    editor.Commands.add('export-template', {
        run: async (editor) => {
            const button = document.querySelector('.btn-export');
            if (button) {
                button.style.opacity = '0.5';
                button.style.pointerEvents = 'none';
            }

            try {
                await editor.store();
                showNotification('Page saved and published!', 'success');

                // Optionally redirect back to pages list
                setTimeout(() => {
                    if (confirm('Page published! Return to pages list?')) {
                        window.location.href = '/admin/pages.php';
                    }
                }, 1500);
            } catch (error) {
                showNotification('Error saving page: ' + error, 'error');
            } finally {
                if (button) {
                    button.style.opacity = '1';
                    button.style.pointerEvents = 'auto';
                }
            }
        }
    });

    // Clear canvas command
    editor.Commands.add('clear-canvas', {
        run: (editor) => {
            if (confirm('Are you sure you want to clear the entire canvas? This cannot be undone.')) {
                editor.setComponents('');
                editor.setStyle('');
                showNotification('Canvas cleared', 'info');
            }
        }
    });

    // Preview command
    editor.Commands.add('preview', {
        run: (editor) => {
            const html = editor.getHtml();
            const css = editor.getCss();

            const previewWindow = window.open('', 'preview', 'width=1200,height=800');
            previewWindow.document.write(`
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Preview</title>
                    <link rel="stylesheet" href="/assets/css/style.css">
                    <style>${css}</style>
                </head>
                <body>
                    ${html}
                </body>
                </html>
            `);
            previewWindow.document.close();
        }
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `grapes-notification grapes-notification--${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initGrapesEditor();
});
