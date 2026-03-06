<?php
/**
 * GrapesJS Helper Functions
 * Legacy section conversion and sanitization
 */

/**
 * Convert legacy page sections to GrapesJS HTML
 */
function convert_legacy_sections_to_html($page_id) {
    require_once __DIR__ . '/../../includes/db-config.php';
    $pdo = getDbConnection();

    // Get page data for default hero
    $stmt = $pdo->prepare("SELECT title, meta_description FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch();

    // Get all sections for this page
    $stmt = $pdo->prepare("
        SELECT *
        FROM page_sections
        WHERE page_id = ?
        ORDER BY section_order ASC
    ");
    $stmt->execute([$page_id]);
    $sections = $stmt->fetchAll();

    $html = '';

    // Check if page has a hero section
    $hasHero = false;
    foreach ($sections as $section) {
        if ($section['section_type'] === 'hero') {
            $hasHero = true;
            break;
        }
    }

    // If no hero section and page has title, add default hero at the beginning
    if (!$hasHero && $page && !empty($page['title'])) {
        $title = htmlspecialchars($page['title']);
        $description = htmlspecialchars($page['meta_description'] ?? '');

        $html .= <<<HTML
<section class="page-hero">
    <div class="container">
        <h1>$title</h1>

HTML;

        if (!empty($description)) {
            $html .= <<<HTML
        <p class="lead">$description</p>

HTML;
        }

        $html .= <<<HTML
    </div>
</section>

HTML;
    }

    // Add all sections
    foreach ($sections as $section) {
        $html .= render_section_as_html($section);
    }

    return $html;
}

/**
 * Render a single section as HTML for GrapesJS
 */
function render_section_as_html($section) {
    $type = $section['section_type'];
    $additional = json_decode($section['additional_data'] ?? '{}', true);

    switch ($type) {
        case 'hero':
            return render_hero_section($section);

        case 'text':
            return render_text_section($section);

        case 'image-text':
            return render_image_text_section($section);

        case 'two-column':
            return render_two_column_section($section);

        case 'cards':
            return render_cards_section($section);

        case 'cta':
            return render_cta_section($section);

        case 'custom-html':
            return render_custom_html_section($section, $additional);

        default:
            return '';
    }
}

/**
 * Render hero section
 */
function render_hero_section($section) {
    $title = htmlspecialchars($section['heading'] ?? 'Welcome');
    $subtitle = htmlspecialchars($section['subheading'] ?? '');
    $bg_image = htmlspecialchars($section['background_image'] ?? '');
    $cta_text = htmlspecialchars($section['button_text'] ?? 'Learn More');
    $cta_link = htmlspecialchars($section['button_url'] ?? '#');

    $bgStyle = $bg_image ? "background-image: url('$bg_image'); background-size: cover; background-position: center;" : "background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%);";

    return <<<HTML
<section class="hero" style="$bgStyle padding: 100px 20px; text-align: center; color: white; position: relative;">
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(45, 27, 78, 0.5);"></div>
    <div class="container" style="max-width: 1200px; margin: 0 auto; position: relative; z-index: 1;">
        <h1 style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1rem;">$title</h1>
        <p class="eyebrow" style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9;">$subtitle</p>
        <a href="$cta_link" class="btn btn-primary" style="padding: 12px 32px; background: white; color: #2D1B4E; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;">$cta_text</a>
    </div>
</section>

HTML;
}

/**
 * Render text section
 */
function render_text_section($section) {
    $content = $section['content'] ?? '';

    return <<<HTML
<section class="text-section" style="padding: 60px 20px; background: white;">
    <div class="container" style="max-width: 900px; margin: 0 auto;">
        $content
    </div>
</section>

HTML;
}

/**
 * Render image-text section
 */
function render_image_text_section($section) {
    $image = htmlspecialchars($section['image_url'] ?? '');
    $content = $section['content'] ?? '';
    $additional = json_decode($section['additional_data'] ?? '{}', true);
    $imagePosition = $additional['image_position'] ?? 'left';

    $flexDirection = $imagePosition === 'right' ? 'row-reverse' : 'row';

    return <<<HTML
<section class="split" style="padding: 60px 20px; background: #F9FAFB;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; display: flex; gap: 3rem; align-items: center; flex-direction: $flexDirection; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 300px;">
            <img src="$image" alt="" style="width: 100%; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
        </div>
        <div style="flex: 1; min-width: 300px;">
            $content
        </div>
    </div>
</section>

HTML;
}

/**
 * Render two-column section
 */
function render_two_column_section($section) {
    $content = $section['content'] ?? '';
    // For two-column, content might be in additional_data or we use a simple split
    $additional = json_decode($section['additional_data'] ?? '{}', true);
    $column1 = $additional['column1_content'] ?? $content;
    $column2 = $additional['column2_content'] ?? '';

    return <<<HTML
<section class="columns" style="padding: 60px 20px; background: white;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
        <div>
            $column1
        </div>
        <div>
            $column2
        </div>
    </div>
</section>

HTML;
}

/**
 * Render cards section
 */
function render_cards_section($section) {
    $content = $section['content'] ?? '';
    $heading = htmlspecialchars($section['heading'] ?? '');

    // Simple card rendering - just wrap content in a card grid
    return <<<HTML
<section class="card-grid" style="padding: 60px 20px; background: #F9FAFB;">
    <div class="container" style="max-width: 1200px; margin: 0 auto;">
        <h2 style="font-size: 2.5rem; font-weight: 700; color: #2D1B4E; margin-bottom: 3rem; text-align: center;">$heading</h2>
        <div>
            $content
        </div>
    </div>
</section>

HTML;
}

/**
 * Render CTA section
 */
function render_cta_section($section) {
    $heading = htmlspecialchars($section['heading'] ?? '');
    $subheading = htmlspecialchars($section['subheading'] ?? '');
    $button_text = htmlspecialchars($section['button_text'] ?? 'Get Started');
    $button_link = htmlspecialchars($section['button_url'] ?? '#');

    return <<<HTML
<section class="cta" style="padding: 80px 20px; background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); color: white; text-align: center;">
    <div class="container" style="max-width: 800px; margin: 0 auto;">
        <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem;">$heading</h2>
        <p style="font-size: 1.125rem; margin-bottom: 2rem; opacity: 0.9;">$subheading</p>
        <a href="$button_link" class="btn btn-primary" style="padding: 12px 32px; background: white; color: #2D1B4E; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;">$button_text</a>
    </div>
</section>

HTML;
}

/**
 * Render custom HTML section
 */
function render_custom_html_section($section, $additional) {
    $content = $section['content'] ?? '';
    $cssClass = $additional['css_class'] ?? 'content-section';
    $containerClass = $additional['container_class'] ?? 'container';

    if ($containerClass) {
        return <<<HTML
<section class="$cssClass" style="padding: 60px 20px;">
    <div class="$containerClass" style="max-width: 1200px; margin: 0 auto;">
        $content
    </div>
</section>

HTML;
    } else {
        return <<<HTML
<section class="$cssClass" style="padding: 60px 20px;">
    $content
</section>

HTML;
    }
}

/**
 * Sanitize GrapesJS HTML
 * Basic implementation - for production, use HTMLPurifier
 */
function sanitize_grapes_html($html) {
    // For now, we trust admin users
    // In production, implement HTMLPurifier here
    return $html;
}

/**
 * Sanitize GrapesJS CSS
 */
function sanitize_grapes_css($css) {
    // Basic CSS sanitization
    // Remove potentially dangerous CSS
    $dangerous = ['javascript:', 'expression(', 'behaviour:', 'vbscript:'];

    foreach ($dangerous as $pattern) {
        $css = str_ireplace($pattern, '', $css);
    }

    return $css;
}
