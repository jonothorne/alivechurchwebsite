<?php
/**
 * TemplateEngine - Manages page templates and layouts
 *
 * Provides a simple templating system with predefined layouts
 * that can be selected in the CMS.
 */

class TemplateEngine {
    private $templateDir;
    private $cms;
    private $pageData = [];

    // Available templates
    public static $templates = [
        'default' => [
            'name' => 'Default',
            'description' => 'Standard page with header and footer',
            'file' => 'default.php'
        ],
        'full-width' => [
            'name' => 'Full Width',
            'description' => 'Full-width sections without container constraints',
            'file' => 'full-width.php'
        ],
        'sidebar' => [
            'name' => 'With Sidebar',
            'description' => 'Main content with right sidebar',
            'file' => 'sidebar.php'
        ],
        'landing' => [
            'name' => 'Landing Page',
            'description' => 'Clean landing page with hero and CTA sections',
            'file' => 'landing.php'
        ],
        'blank' => [
            'name' => 'Blank',
            'description' => 'Completely blank page with just header/footer',
            'file' => 'blank.php'
        ],
        'two-column' => [
            'name' => 'Two Column',
            'description' => 'Content with image/media side by side',
            'file' => 'two-column.php'
        ],
        'split-hero' => [
            'name' => 'Split Hero',
            'description' => 'Hero with image on one side, text on other',
            'file' => 'split-hero.php'
        ],
        'team' => [
            'name' => 'Team/Staff',
            'description' => 'Grid layout for team member profiles',
            'file' => 'team.php'
        ],
        'contact' => [
            'name' => 'Contact Page',
            'description' => 'Contact info, map, and optional form',
            'file' => 'contact.php'
        ],
        'gallery' => [
            'name' => 'Gallery',
            'description' => 'Image gallery with lightbox',
            'file' => 'gallery.php'
        ],
        'text-heavy' => [
            'name' => 'Text Heavy',
            'description' => 'Optimized for long-form content like policies',
            'file' => 'text-heavy.php'
        ],
        'cards' => [
            'name' => 'Card Grid',
            'description' => 'Grid of cards for ministries, services, etc.',
            'file' => 'cards.php'
        ],
        'centered' => [
            'name' => 'Centered',
            'description' => 'Centered narrow content, great for focused pages',
            'file' => 'centered.php'
        ],
        'video-hero' => [
            'name' => 'Video Hero',
            'description' => 'Full-screen video background hero',
            'file' => 'video-hero.php'
        ],
        'announcement' => [
            'name' => 'Announcement',
            'description' => 'Bold announcement or event promotion page',
            'file' => 'announcement.php'
        ]
    ];

    // Available hero styles
    public static $heroStyles = [
        'standard' => 'Standard (Image background)',
        'gradient' => 'Gradient Background',
        'video' => 'Video Background',
        'minimal' => 'Minimal (No background)',
        'none' => 'No Hero Section'
    ];

    // Available section types for the page builder
    public static $sectionTypes = [
        'hero' => [
            'name' => 'Hero Section',
            'description' => 'Large header with background image/video',
            'fields' => ['heading', 'subheading', 'button_text', 'button_url', 'background_image']
        ],
        'text' => [
            'name' => 'Text Block',
            'description' => 'Rich text content section',
            'fields' => ['heading', 'content']
        ],
        'two-column' => [
            'name' => 'Two Columns',
            'description' => 'Side-by-side content blocks',
            'fields' => ['heading', 'left_content', 'right_content']
        ],
        'cards' => [
            'name' => 'Card Grid',
            'description' => 'Grid of cards with icons/images',
            'fields' => ['heading', 'cards']
        ],
        'cta' => [
            'name' => 'Call to Action',
            'description' => 'Prominent CTA section',
            'fields' => ['heading', 'content', 'button_text', 'button_url', 'background_color']
        ],
        'gallery' => [
            'name' => 'Image Gallery',
            'description' => 'Grid of images',
            'fields' => ['heading', 'images']
        ],
        'testimonials' => [
            'name' => 'Testimonials',
            'description' => 'Customer/member quotes',
            'fields' => ['heading', 'testimonials']
        ],
        'video' => [
            'name' => 'Video Section',
            'description' => 'Embedded video with optional text',
            'fields' => ['heading', 'content', 'video_url']
        ],
        'faq' => [
            'name' => 'FAQ Section',
            'description' => 'Accordion-style questions and answers',
            'fields' => ['heading', 'faqs']
        ],
        'contact' => [
            'name' => 'Contact Section',
            'description' => 'Contact info and optional form',
            'fields' => ['heading', 'content', 'show_form', 'show_map']
        ]
    ];

    public function __construct(ContentManager $cms = null) {
        $this->templateDir = __DIR__ . '/../../templates/';
        $this->cms = $cms ?? new ContentManager();
    }

    /**
     * Render a page using its template
     */
    public function render($pageSlug, $data = []) {
        $this->pageData = $data;

        // Get page settings from database
        $page = $this->getPageSettings($pageSlug);

        if (!$page) {
            // Create default page data
            $page = [
                'slug' => $pageSlug,
                'title' => ucwords(str_replace('-', ' ', $pageSlug)),
                'template' => 'default',
                'layout' => 'default',
                'hero_style' => 'standard'
            ];
        }

        $templateFile = self::$templates[$page['template']]['file'] ?? 'default.php';
        $templatePath = $this->templateDir . $templateFile;

        if (!file_exists($templatePath)) {
            $templatePath = $this->templateDir . 'default.php';
        }

        // Make variables available to template
        $cms = $this->cms;
        $pageData = $this->pageData;
        $heroStyle = $page['hero_style'] ?? 'standard';

        // Include template
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * Get page settings from database
     */
    private function getPageSettings($slug) {
        require_once __DIR__ . '/../db-config.php';
        $pdo = getDbConnection();

        try {
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
            $stmt->execute([$slug]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Render a predefined section component
     */
    public function section($type, $key, $defaults = []) {
        $sectionPath = $this->templateDir . 'sections/' . $type . '.php';

        if (!file_exists($sectionPath)) {
            return "<!-- Section type '{$type}' not found -->";
        }

        $cms = $this->cms;
        $sectionKey = $key;
        $sectionDefaults = $defaults;

        ob_start();
        include $sectionPath;
        return ob_get_clean();
    }

    /**
     * Get available templates
     */
    public static function getTemplates() {
        return self::$templates;
    }

    /**
     * Get available hero styles
     */
    public static function getHeroStyles() {
        return self::$heroStyles;
    }

    /**
     * Get available section types
     */
    public static function getSectionTypes() {
        return self::$sectionTypes;
    }
}
