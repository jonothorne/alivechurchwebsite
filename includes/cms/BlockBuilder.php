<?php
/**
 * BlockBuilder - Block-based page builder system
 *
 * Manages drag-and-drop content blocks for flexible page layouts.
 */

class BlockBuilder {
    private $pdo;
    private $isEditMode = false;

    /**
     * Block type definitions with fields and templates
     */
    public static $blockTypes = [
        'hero' => [
            'name' => 'Hero Section',
            'icon' => 'hero',
            'category' => 'headers',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'required' => true],
                'subheading' => ['type' => 'text', 'label' => 'Subheading'],
                'backgroundImage' => ['type' => 'image', 'label' => 'Background Image'],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#4B2679'],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text'],
                'buttonUrl' => ['type' => 'text', 'label' => 'Button URL'],
                'alignment' => ['type' => 'select', 'label' => 'Alignment', 'options' => ['left', 'center', 'right'], 'default' => 'center'],
                'overlay' => ['type' => 'checkbox', 'label' => 'Dark Overlay', 'default' => true]
            ],
            'template' => 'hero.php'
        ],
        'text' => [
            'name' => 'Text Section',
            'icon' => 'text',
            'category' => 'content',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'content' => ['type' => 'richtext', 'label' => 'Content', 'required' => true],
                'width' => ['type' => 'select', 'label' => 'Width', 'options' => ['narrow', 'medium', 'wide'], 'default' => 'medium'],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color']
            ],
            'template' => 'text.php'
        ],
        'two-column' => [
            'name' => 'Two Column',
            'icon' => 'columns',
            'category' => 'layout',
            'fields' => [
                'leftContent' => ['type' => 'richtext', 'label' => 'Left Content', 'required' => true],
                'rightContent' => ['type' => 'richtext', 'label' => 'Right Content'],
                'rightImage' => ['type' => 'image', 'label' => 'Right Image'],
                'imagePosition' => ['type' => 'select', 'label' => 'Image Position', 'options' => ['left', 'right'], 'default' => 'right'],
                'ratio' => ['type' => 'select', 'label' => 'Column Ratio', 'options' => ['50-50', '60-40', '40-60', '70-30', '30-70'], 'default' => '50-50']
            ],
            'template' => 'two-column.php'
        ],
        'card-grid' => [
            'name' => 'Card Grid',
            'icon' => 'grid',
            'category' => 'content',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Section Heading'],
                'subheading' => ['type' => 'text', 'label' => 'Section Subheading'],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => ['2', '3', '4'], 'default' => '3'],
                'cards' => ['type' => 'repeater', 'label' => 'Cards', 'fields' => [
                    'icon' => ['type' => 'text', 'label' => 'Icon/Emoji'],
                    'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                    'description' => ['type' => 'textarea', 'label' => 'Description'],
                    'linkText' => ['type' => 'text', 'label' => 'Link Text'],
                    'linkUrl' => ['type' => 'text', 'label' => 'Link URL']
                ]]
            ],
            'template' => 'card-grid.php'
        ],
        'cta' => [
            'name' => 'Call to Action',
            'icon' => 'megaphone',
            'category' => 'content',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'required' => true],
                'content' => ['type' => 'textarea', 'label' => 'Content'],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'required' => true],
                'buttonUrl' => ['type' => 'text', 'label' => 'Button URL', 'required' => true],
                'buttonStyle' => ['type' => 'select', 'label' => 'Button Style', 'options' => ['primary', 'secondary', 'outline'], 'default' => 'primary'],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#4B2679'],
                'alignment' => ['type' => 'select', 'label' => 'Alignment', 'options' => ['left', 'center', 'right'], 'default' => 'center']
            ],
            'template' => 'cta.php'
        ],
        'image' => [
            'name' => 'Image',
            'icon' => 'image',
            'category' => 'media',
            'fields' => [
                'image' => ['type' => 'image', 'label' => 'Image', 'required' => true],
                'alt' => ['type' => 'text', 'label' => 'Alt Text'],
                'caption' => ['type' => 'text', 'label' => 'Caption'],
                'width' => ['type' => 'select', 'label' => 'Width', 'options' => ['small', 'medium', 'large', 'full'], 'default' => 'large'],
                'alignment' => ['type' => 'select', 'label' => 'Alignment', 'options' => ['left', 'center', 'right'], 'default' => 'center']
            ],
            'template' => 'image.php'
        ],
        'gallery' => [
            'name' => 'Gallery',
            'icon' => 'gallery',
            'category' => 'media',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => ['2', '3', '4', '5'], 'default' => '4'],
                'images' => ['type' => 'repeater', 'label' => 'Images', 'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Image', 'required' => true],
                    'alt' => ['type' => 'text', 'label' => 'Alt Text'],
                    'caption' => ['type' => 'text', 'label' => 'Caption']
                ]]
            ],
            'template' => 'gallery.php'
        ],
        'spacer' => [
            'name' => 'Spacer',
            'icon' => 'spacer',
            'category' => 'layout',
            'fields' => [
                'height' => ['type' => 'select', 'label' => 'Height', 'options' => ['small', 'medium', 'large', 'xlarge'], 'default' => 'medium']
            ],
            'template' => 'spacer.php'
        ],
        'divider' => [
            'name' => 'Divider',
            'icon' => 'divider',
            'category' => 'layout',
            'fields' => [
                'style' => ['type' => 'select', 'label' => 'Style', 'options' => ['line', 'dots', 'gradient'], 'default' => 'line'],
                'width' => ['type' => 'select', 'label' => 'Width', 'options' => ['narrow', 'medium', 'full'], 'default' => 'medium']
            ],
            'template' => 'divider.php'
        ]
    ];

    public function __construct() {
        require_once __DIR__ . '/../db-config.php';
        $this->pdo = getDbConnection();
        $this->checkEditMode();
    }

    /**
     * Check if user is in edit mode
     */
    private function checkEditMode() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
                      isset($_SESSION['admin_user_id']);
        $this->isEditMode = $isLoggedIn &&
                           (!isset($_GET['preview']) || $_GET['preview'] !== 'true');
    }

    /**
     * Check if currently in edit mode
     */
    public function isEditMode() {
        return $this->isEditMode;
    }

    /**
     * Get all blocks for a page (ordered)
     */
    public function getPageBlocks($pageSlug) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM page_blocks
                 WHERE page_slug = ? AND visible = TRUE
                 ORDER BY display_order ASC"
            );
            $stmt->execute([$pageSlug]);
            $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON data for each block
            foreach ($blocks as &$block) {
                $block['data'] = json_decode($block['block_data'], true) ?? [];
            }

            return $blocks;
        } catch (Exception $e) {
            error_log('BlockBuilder getPageBlocks error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a page uses block builder
     */
    public function pageHasBlocks($pageSlug) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM page_blocks WHERE page_slug = ? AND visible = TRUE"
            );
            $stmt->execute([$pageSlug]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('BlockBuilder pageHasBlocks error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save/update a single block
     */
    public function saveBlock($pageSlug, $blockUuid, $blockType, $blockData, $displayOrder = 0, $userId = null) {
        try {
            // Validate block type
            if (!isset(self::$blockTypes[$blockType])) {
                throw new Exception("Invalid block type: {$blockType}");
            }

            $jsonData = json_encode($blockData, JSON_UNESCAPED_UNICODE);

            // Check if block exists
            $stmt = $this->pdo->prepare(
                "SELECT id FROM page_blocks WHERE block_uuid = ?"
            );
            $stmt->execute([$blockUuid]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing block
                $stmt = $this->pdo->prepare(
                    "UPDATE page_blocks
                     SET block_data = ?, display_order = ?, updated_by = ?
                     WHERE block_uuid = ?"
                );
                $stmt->execute([$jsonData, $displayOrder, $userId, $blockUuid]);
            } else {
                // Create new block
                $stmt = $this->pdo->prepare(
                    "INSERT INTO page_blocks (page_slug, block_uuid, block_type, block_data, display_order, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$pageSlug, $blockUuid, $blockType, $jsonData, $displayOrder, $userId]);
            }

            return true;
        } catch (Exception $e) {
            error_log('BlockBuilder saveBlock error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save multiple blocks at once (bulk save)
     */
    public function saveBlocks($pageSlug, $blocks, $userId = null) {
        try {
            $this->pdo->beginTransaction();

            // Get existing block UUIDs for this page
            $stmt = $this->pdo->prepare(
                "SELECT block_uuid FROM page_blocks WHERE page_slug = ?"
            );
            $stmt->execute([$pageSlug]);
            $existingUuids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $newUuids = [];

            foreach ($blocks as $index => $block) {
                $blockUuid = $block['uuid'] ?? $this->generateUuid();
                $blockType = $block['type'];
                $blockData = $block['data'] ?? [];
                $displayOrder = $block['order'] ?? $index;

                $newUuids[] = $blockUuid;

                $this->saveBlock($pageSlug, $blockUuid, $blockType, $blockData, $displayOrder, $userId);
            }

            // Remove blocks that are no longer in the list
            $removedUuids = array_diff($existingUuids, $newUuids);
            if (!empty($removedUuids)) {
                $placeholders = implode(',', array_fill(0, count($removedUuids), '?'));
                $stmt = $this->pdo->prepare(
                    "DELETE FROM page_blocks WHERE block_uuid IN ({$placeholders})"
                );
                $stmt->execute($removedUuids);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('BlockBuilder saveBlocks error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a block
     */
    public function deleteBlock($blockUuid, $userId = null) {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM page_blocks WHERE block_uuid = ?"
            );
            $stmt->execute([$blockUuid]);
            return true;
        } catch (Exception $e) {
            error_log('BlockBuilder deleteBlock error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reorder blocks
     */
    public function reorderBlocks($pageSlug, $blockOrder, $userId = null) {
        try {
            $this->pdo->beginTransaction();

            foreach ($blockOrder as $order => $blockUuid) {
                $stmt = $this->pdo->prepare(
                    "UPDATE page_blocks SET display_order = ?, updated_by = ? WHERE block_uuid = ? AND page_slug = ?"
                );
                $stmt->execute([$order, $userId, $blockUuid, $pageSlug]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('BlockBuilder reorderBlocks error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Render a single block
     */
    public function renderBlock($block) {
        $blockType = $block['block_type'];
        $blockData = $block['data'];
        $blockUuid = $block['block_uuid'];

        if (!isset(self::$blockTypes[$blockType])) {
            return "<!-- Unknown block type: {$blockType} -->";
        }

        $templateFile = __DIR__ . '/../../templates/blocks/' . self::$blockTypes[$blockType]['template'];

        if (!file_exists($templateFile)) {
            return "<!-- Block template not found: {$blockType} -->";
        }

        // Make variables available to template
        $data = $blockData;
        $uuid = $blockUuid;
        $type = $blockType;
        $isEditMode = $this->isEditMode;

        ob_start();
        include $templateFile;
        return ob_get_clean();
    }

    /**
     * Render all blocks for a page
     */
    public function renderPage($pageSlug) {
        // Make page slug available to block templates
        $GLOBALS['slug'] = $pageSlug;

        $blocks = $this->getPageBlocks($pageSlug);

        $output = '';

        if ($this->isEditMode) {
            $output .= '<div class="block-builder-canvas" data-page="' . htmlspecialchars($pageSlug) . '">';

            // Show empty state if no blocks
            if (empty($blocks)) {
                $output .= '<div class="block-builder-empty">';
                $output .= '<div class="block-builder-empty-content">';
                $output .= '<h2>Start Building Your Page</h2>';
                $output .= '<p>Add your first block to get started</p>';
                $output .= '<button type="button" class="block-add-btn block-add-first">+ Add Your First Block</button>';
                $output .= '</div>';
                $output .= '</div>';
            }
        }

        if (empty($blocks) && !$this->isEditMode) {
            return '';
        }

        foreach ($blocks as $block) {
            if ($this->isEditMode) {
                $output .= '<div class="block-wrapper" data-block-uuid="' . htmlspecialchars($block['block_uuid']) . '" data-block-type="' . htmlspecialchars($block['block_type']) . '">';
                $output .= '<div class="block-controls">';
                $output .= '<span class="block-drag-handle" title="Drag to reorder">&#9776;</span>';
                $output .= '<span class="block-type-label">' . htmlspecialchars(self::$blockTypes[$block['block_type']]['name']) . '</span>';
                $output .= '<button type="button" class="block-duplicate-btn" title="Duplicate block">Duplicate</button>';
                $output .= '<button type="button" class="block-delete-btn" title="Delete block">Delete</button>';
                $output .= '</div>';
            }

            $output .= $this->renderBlock($block);

            if ($this->isEditMode) {
                $output .= '</div>';
            }
        }

        if ($this->isEditMode) {
            $output .= '<div class="block-add-zone"><button type="button" class="block-add-btn">+ Add Block</button></div>';
            $output .= '</div>';
        }

        return $output;
    }

    /**
     * Get block types for frontend
     */
    public static function getBlockTypesForJS() {
        $types = [];
        foreach (self::$blockTypes as $key => $config) {
            $types[$key] = [
                'name' => $config['name'],
                'icon' => $config['icon'],
                'category' => $config['category'],
                'fields' => $config['fields']
            ];
        }
        return $types;
    }

    /**
     * Generate a UUID v4
     */
    private function generateUuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get block type definition
     */
    public static function getBlockType($type) {
        return self::$blockTypes[$type] ?? null;
    }

    /**
     * Get all block categories
     */
    public static function getCategories() {
        return [
            'headers' => 'Headers',
            'content' => 'Content',
            'layout' => 'Layout',
            'media' => 'Media'
        ];
    }
}
