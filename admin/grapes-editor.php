<?php
/**
 * GrapesJS Visual Page Editor
 * Minimal implementation - full-screen editor
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db-config.php';

// Require authentication
require_auth();

// Get page ID
$page_id = $_GET['page_id'] ?? null;

if (!$page_id || !is_numeric($page_id)) {
    die('Invalid page ID');
}

// Get page data
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT id, slug, title FROM pages WHERE id = ?");
$stmt->execute([$page_id]);
$page = $stmt->fetch();

if (!$page) {
    die('Page not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editing: <?= htmlspecialchars($page['title']); ?> - Visual Editor</title>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= htmlspecialchars(get_csrf_token()); ?>">

    <!-- GrapesJS Core CSS -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.7/dist/css/grapes.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Top toolbar */
        .top-toolbar {
            background: #2D1B4E;
            color: white;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 50px;
            flex-shrink: 0;
        }

        .top-toolbar h1 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .top-toolbar a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            transition: background 0.2s;
        }

        .top-toolbar a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* GrapesJS container - CRITICAL: must have explicit height */
        #gjs {
            height: calc(100vh - 50px);
            width: 100%;
            position: relative;
        }

        /* Ensure GrapesJS editor container takes full height */
        .gjs-editor {
            height: 100%;
        }

        /* Ensure panels and views take proper height */
        .gjs-pn-panels {
            height: 100%;
        }

        /* Panel containers need explicit height */
        .gjs-pn-panel {
            min-height: 50px;
        }

        .gjs-pn-views-container {
            height: 100%;
        }

        .gjs-pn-views {
            height: 100%;
        }

        /* Canvas must have height */
        .gjs-cv-canvas {
            height: 100%;
        }

        /* Frame wrapper */
        .gjs-frame-wrapper {
            height: 100%;
        }

        /* Hide GrapesJS logo */
        .gjs-logo-cont {
            display: none !important;
        }


        /* Custom Save/Preview buttons styling */
        .gjs-pn-btn.btn-save {
            background: #10B981 !important;
            color: white !important;
            border: none !important;
            font-weight: 600 !important;
            padding: 8px 16px !important;
            height: auto !important;
            line-height: normal !important;
        }

        .gjs-pn-btn.btn-save:hover {
            background: #059669 !important;
        }

        .gjs-pn-btn.btn-preview-page {
            background: #3B82F6 !important;
            color: white !important;
            border: none !important;
            font-weight: 600 !important;
            padding: 8px 16px !important;
            height: auto !important;
            line-height: normal !important;
        }

        .gjs-pn-btn.btn-preview-page:hover {
            background: #2563EB !important;
        }

        /* Ensure all panel buttons are visible */
        .gjs-pn-btn {
            display: inline-block !important;
        }

        /* Right sidebar panel fixes */
        .gjs-pn-views-container {
            background: white !important;
            width: 300px !important;
            min-width: 300px !important;
            height: 100% !important;
            overflow-y: auto !important;
            position: relative !important;
        }

        /* Panel views wrapper */
        .gjs-pn-views {
            background: white !important;
            width: 100% !important;
            height: 100% !important;
            overflow-y: auto !important;
        }

        /* Ensure blocks, layers, styles, traits containers are visible */
        .gjs-blocks-c,
        .gjs-layers,
        .gjs-sm-sectors,
        .gjs-trt-traits {
            display: block !important;
            padding: 10px !important;
            background: white !important;
            width: 100% !important;
            min-height: 200px !important;
            overflow-y: auto !important;
        }

        /* Panel view containers */
        .gjs-pn-view {
            display: block !important;
            width: 100% !important;
            height: auto !important;
            min-height: 100% !important;
            overflow-y: auto !important;
        }

        /* Make sure panel content is visible */
        .gjs-blocks-c .gjs-block-categories,
        .gjs-layers .gjs-layers-c,
        .gjs-sm-sectors .gjs-sm-sector,
        .gjs-trt-traits .gjs-trt-trait {
            display: block !important;
        }

        /* Block items styling */
        .gjs-block {
            display: block !important;
            margin: 10px 0 !important;
            padding: 10px !important;
            background: #f9fafb !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 4px !important;
            cursor: pointer !important;
        }

        .gjs-block:hover {
            background: #f3f4f6 !important;
            border-color: #2D1B4E !important;
        }

        /* Layer items styling */
        .gjs-layer {
            display: block !important;
            padding: 8px !important;
            margin: 2px 0 !important;
            background: white !important;
            border-bottom: 1px solid #f0f0f0 !important;
        }

        /* Style manager sectors */
        .gjs-sm-sector {
            display: block !important;
            margin: 10px 0 !important;
            background: white !important;
        }

        .gjs-sm-sector-title {
            display: block !important;
            padding: 10px !important;
            background: #f9fafb !important;
            border-bottom: 1px solid #e5e7eb !important;
            cursor: pointer !important;
        }

        /* Trait items */
        .gjs-trt-trait {
            display: block !important;
            margin: 10px 0 !important;
        }

        /* Notification styling */
        .notification {
            position: fixed;
            top: 70px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            background: #10B981;
            color: white;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            z-index: 999999;
            opacity: 0;
            transform: translateX(100px);
            transition: all 0.3s;
        }

        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .notification.error {
            background: #EF4444;
        }
    </style>
</head>
<body>
    <!-- Top Toolbar -->
    <div class="top-toolbar">
        <h1>✏️ Editing: <?= htmlspecialchars($page['title']); ?></h1>
        <a href="/admin/pages.php">← Back to Pages</a>
    </div>

    <!-- GrapesJS Editor -->
    <div id="gjs"></div>

    <!-- GrapesJS Core -->
    <script src="https://unpkg.com/grapesjs@0.21.7/dist/grapes.min.js"></script>

    <!-- Plugins -->
    <script src="https://unpkg.com/grapesjs-preset-webpage@1.0.3"></script>

    <!-- Custom Blocks -->
    <script src="/admin/assets/js/grapes-blocks.js"></script>

    <!-- Editor Initialization -->
    <script>
        const pageId = <?= $page_id; ?>;

        // Get CSRF token
        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        }

        // Show notification
        function showNotification(message, isError = false) {
            const notification = document.createElement('div');
            notification.className = 'notification' + (isError ? ' error' : '');
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Load page content
        async function loadPageContent(editor, pageId) {
            try {
                const response = await fetch(`/admin/api/grapes-save.php?page_id=${pageId}`, {
                    headers: { 'X-CSRF-Token': getCsrfToken() }
                });

                const result = await response.json();

                if (result && result.success && result.data) {
                    const data = result.data;

                    // Check if migrated page (has HTML but no components)
                    if (data.html && !data.components) {
                        editor.setComponents(data.html);
                        if (data.css) editor.setStyle(data.css);
                    }
                    // Native GrapesJS page
                    else if (data.components) {
                        editor.setComponents(data.components);
                        if (data.styles) editor.setStyle(data.styles);
                    }
                }
            } catch (error) {
                console.error('Error loading page:', error);
                showNotification('Error loading page: ' + error.message, true);
            }
        }

        // Initialize GrapesJS
        const editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            width: '100%',
            fromElement: false,

            // Storage - use custom type to have full control
            storageManager: {
                type: 'remote',
                autosave: false,
                autoload: false,
                options: {
                    remote: {
                        urlStore: `/admin/api/grapes-save.php?page_id=${pageId}`,
                        urlLoad: `/admin/api/grapes-save.php?page_id=${pageId}`,
                        contentTypeJson: true,
                        headers: {
                            'X-CSRF-Token': getCsrfToken()
                        },
                        onStore: (data, editor) => {
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
                            if (result && result.success && result.data) {
                                return result.data;
                            }
                            return {};
                        }
                    }
                }
            },

            // Plugins - preset-webpage includes all default panels and blocks
            plugins: ['grapesjs-preset-webpage'],
            pluginsOpts: {
                'grapesjs-preset-webpage': {
                    modalImportTitle: 'Import Template',
                    modalImportLabel: '<div style="margin-bottom: 10px; font-size: 13px;">Paste here your HTML/CSS and click Import</div>',
                    modalImportContent: function(editor) {
                        return editor.getHtml() + '<style>' + editor.getCss() + '</style>';
                    },
                    blocksBasicOpts: {
                        flexGrid: true
                    },
                    // Explicitly enable all view panels
                    showStylesOnChange: true,
                    useCustomTheme: false,
                    blocks: ['link-block', 'quote', 'text-basic'],
                    navbarOpts: false,
                    countdownOpts: false,
                    formsOpts: true
                }
            },

            // Canvas
            canvas: {
                styles: ['/assets/css/style.css']
            },


            // Device Manager
            deviceManager: {
                devices: [
                    { name: 'Desktop', width: '' },
                    { name: 'Tablet', width: '768px' },
                    { name: 'Mobile', width: '320px' }
                ]
            }
        });

        // Add save command FIRST
        editor.Commands.add('save-page', {
            run: (editor, sender) => {
                const btn = sender;
                const originalLabel = btn?.get('label');

                if (btn) btn.set('label', '⏳ Saving...');

                // Use store() which returns a promise
                editor.store()
                    .then((result) => {
                        console.log('Save successful:', result);
                        showNotification('✓ Page saved successfully!');
                        if (btn) btn.set('label', originalLabel);
                    })
                    .catch((error) => {
                        console.error('Save error:', error);
                        showNotification('Error saving page: ' + (error.message || error), true);
                        if (btn) btn.set('label', originalLabel);
                    });
            }
        });

        // Add Save button to the options panel
        const panelManager = editor.Panels;
        const optionsPanel = panelManager.getPanel('options');

        if (optionsPanel) {
            optionsPanel.get('buttons').add([
                {
                    id: 'save-page',
                    className: 'btn-save fa fa-floppy-o',
                    command: 'save-page',
                    attributes: { title: 'Save Page' },
                    label: '💾 Save'
                },
                {
                    id: 'preview-live',
                    className: 'btn-preview-page fa fa-eye',
                    attributes: { title: 'Preview Live Page' },
                    label: '👁️ Preview',
                    command(editor) {
                        window.open(`/<?= htmlspecialchars($page['slug']); ?>`, '_blank');
                    }
                }
            ]);
        }

        // Initialize custom blocks
        if (typeof initCustomBlocks === 'function') {
            initCustomBlocks(editor);
        }

        // Editor load event - Initialize panels
        editor.on('load', () => {
            console.log('Editor loaded successfully');

            // Log all available panels for debugging
            const panelManager = editor.Panels;
            console.log('Available panels:', panelManager.getPanels().map(p => p.id));

            // Explicitly open right sidebar views
            const viewsPanel = panelManager.getPanel('views');
            if (viewsPanel) {
                console.log('Views panel found:', viewsPanel);
                const buttons = viewsPanel.get('buttons');
                console.log('Views panel buttons:', buttons.map(b => b.id));

                // Activate the blocks view by default
                const blocksBtn = buttons.find(b => b.id === 'open-blocks');
                if (blocksBtn) {
                    console.log('Opening blocks panel');
                    blocksBtn.set('active', true);
                    editor.runCommand('open-blocks');
                }
            }

            // Ensure block manager is populated
            const blockManager = editor.BlockManager;
            const blocks = blockManager.getAll();
            console.log('Available blocks:', blocks.length, blocks.map(b => b.get('label')));
        });

        // Load page content
        loadPageContent(editor, pageId);

        // Auto-save notifications
        editor.on('storage:end:store', () => {
            showNotification('✓ Changes saved');
        });

        editor.on('storage:error:store', (err) => {
            console.error('Storage error:', err);
            showNotification('Error saving changes', true);
        });

        console.log('GrapesJS Editor initialized');
    </script>
</body>
</html>
