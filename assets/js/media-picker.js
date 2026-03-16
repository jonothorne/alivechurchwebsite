/**
 * Unified Media Picker
 *
 * A reusable media picker modal that works across admin and CMS inline editor.
 * Features: Pagination, tag filtering, search, responsive grid, size selection with recommendations.
 *
 * Usage:
 *   MediaPicker.open(function(url) {
 *       console.log('Selected:', url);
 *   });
 */

window.MediaPicker = (function() {
    'use strict';

    // State
    const state = {
        allItems: [],
        availableTags: [],
        activeTag: '',
        searchQuery: '',
        page: 1,
        perPage: 50,
        total: 0,
        hasMore: true,
        isLoading: false,
        callback: null,
        modal: null,
        apiEndpoint: '/admin/api/media',
        showSizePicker: true, // Option to enable/disable size picker
        currentView: 'grid' // 'grid' or 'sizes'
    };

    // Size descriptions and recommendations
    const sizeInfo = {
        'thumbnail': { label: 'Thumbnail', desc: 'Icons & small previews', icon: '🔍' },
        'small': { label: 'Small', desc: 'Sidebar images', icon: '📱' },
        'medium': { label: 'Medium', desc: 'Content images', icon: '💻', recommended: true },
        'large': { label: 'Large', desc: 'Featured images', icon: '🖥️' },
        'xlarge': { label: 'Extra Large', desc: 'Hero banners', icon: '🖼️' }
    };

    // CSS styles
    const styles = `
        .mp-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .mp-overlay.active {
            opacity: 1;
        }
        .mp-modal {
            background: var(--color-bg-elevated, #fff);
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.95);
            transition: transform 0.2s;
        }
        .mp-overlay.active .mp-modal {
            transform: scale(1);
        }
        .mp-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--color-border, #e5e7eb);
        }
        .mp-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-text, #1f2937);
        }
        .mp-close {
            background: none;
            border: none;
            font-size: 1.75rem;
            cursor: pointer;
            color: var(--color-text-muted, #6b7280);
            line-height: 1;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.15s;
        }
        .mp-close:hover {
            background: var(--color-bg-subtle, #f3f4f6);
            color: var(--color-text, #1f2937);
        }
        .mp-filters {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid var(--color-border, #e5e7eb);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .mp-search {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--color-bg-subtle, #f9fafb);
            color: var(--color-text, #1f2937);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .mp-search:focus {
            outline: none;
            border-color: var(--color-purple, #8b5cf6);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        .mp-tags {
            display: flex;
            gap: 0.375rem;
            flex-wrap: wrap;
        }
        .mp-tag {
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid var(--color-border, #e5e7eb);
            background: var(--color-bg-subtle, #f9fafb);
            color: var(--color-text, #374151);
            cursor: pointer;
            transition: all 0.15s;
        }
        .mp-tag:hover {
            border-color: var(--tag-color, var(--color-purple, #8b5cf6));
        }
        .mp-tag.active {
            background: var(--tag-color, var(--color-purple, #8b5cf6));
            color: white;
            border-color: var(--tag-color, var(--color-purple, #8b5cf6));
        }
        .mp-body {
            padding: 1rem 1.25rem;
            overflow-y: auto;
            flex: 1;
        }
        .mp-loading, .mp-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--color-text-muted, #6b7280);
        }
        .mp-loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--color-border, #e5e7eb);
            border-top-color: var(--color-purple, #8b5cf6);
            border-radius: 50%;
            animation: mp-spin 0.8s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        @keyframes mp-spin {
            to { transform: rotate(360deg); }
        }
        .mp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
        }
        .mp-item {
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.15s, transform 0.15s;
            background: var(--color-bg-subtle, #f3f4f6);
            position: relative;
        }
        .mp-item:hover {
            border-color: var(--color-purple, #8b5cf6);
            transform: scale(1.03);
        }
        .mp-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .mp-item-tags {
            position: absolute;
            bottom: 4px;
            left: 4px;
            right: 4px;
            display: flex;
            gap: 2px;
            flex-wrap: wrap;
        }
        .mp-item-tag {
            font-size: 0.6rem;
            padding: 1px 4px;
            background: rgba(0,0,0,0.6);
            color: white;
            border-radius: 3px;
        }
        .mp-item-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            font-size: 0.6rem;
            padding: 2px 6px;
            background: var(--color-purple, #8b5cf6);
            color: white;
            border-radius: 4px;
            font-weight: 500;
        }
        .mp-pagination {
            margin-top: 1rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }
        .mp-load-more {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid var(--color-border, #e5e7eb);
            background: var(--color-bg-subtle, #f9fafb);
            color: var(--color-text, #374151);
            cursor: pointer;
            transition: all 0.15s;
        }
        .mp-load-more:hover {
            border-color: var(--color-purple, #8b5cf6);
            background: var(--color-bg-elevated, #fff);
        }
        .mp-count {
            font-size: 0.8rem;
            color: var(--color-text-muted, #6b7280);
        }

        /* Size Picker Styles */
        .mp-size-picker {
            padding: 0.5rem 0;
        }
        .mp-size-back {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: none;
            border: none;
            color: var(--color-purple, #8b5cf6);
            cursor: pointer;
            font-size: 0.875rem;
            padding: 0.5rem;
            margin: -0.5rem;
            margin-bottom: 1rem;
            border-radius: 6px;
            transition: background 0.15s;
        }
        .mp-size-back:hover {
            background: var(--color-bg-subtle, #f3f4f6);
        }
        .mp-size-preview {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .mp-size-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            object-fit: contain;
        }
        .mp-size-filename {
            text-align: center;
            font-size: 0.875rem;
            color: var(--color-text-muted, #6b7280);
            margin-bottom: 1.5rem;
        }
        .mp-size-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 0.75rem;
        }
        .mp-size-card {
            background: var(--color-bg-subtle, #f9fafb);
            border: 2px solid var(--color-border, #e5e7eb);
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
            position: relative;
        }
        .mp-size-card:hover {
            border-color: var(--color-purple, #8b5cf6);
            transform: translateY(-2px);
        }
        .mp-size-card.recommended {
            border-color: var(--color-purple, #8b5cf6);
            background: rgba(139, 92, 246, 0.05);
        }
        .mp-size-badge {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--color-purple, #8b5cf6);
            color: white;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .mp-size-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .mp-size-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--color-text, #1f2937);
            margin-bottom: 0.25rem;
        }
        .mp-size-desc {
            font-size: 0.7rem;
            color: var(--color-text-muted, #6b7280);
            margin-bottom: 0.5rem;
        }
        .mp-size-dims {
            font-size: 0.75rem;
            color: var(--color-text-muted, #6b7280);
            font-family: monospace;
        }
        .mp-size-filesize {
            font-size: 0.7rem;
            color: var(--color-text-muted, #9ca3af);
            margin-top: 0.25rem;
        }

        @media (max-width: 640px) {
            .mp-modal {
                width: 100%;
                max-width: none;
                max-height: 100vh;
                border-radius: 0;
            }
            .mp-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
            .mp-size-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    `;

    // Format file size
    function formatSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Create modal HTML
    function createModal() {
        // Add styles if not already added
        if (!document.getElementById('media-picker-styles')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'media-picker-styles';
            styleEl.textContent = styles;
            document.head.appendChild(styleEl);
        }

        // Create modal element
        const modal = document.createElement('div');
        modal.className = 'mp-overlay';
        modal.innerHTML = `
            <div class="mp-modal">
                <div class="mp-header">
                    <h3>Select Image</h3>
                    <button class="mp-close" aria-label="Close">&times;</button>
                </div>
                <div class="mp-filters">
                    <input type="text" class="mp-search" placeholder="Search images...">
                    <div class="mp-tags"></div>
                </div>
                <div class="mp-body">
                    <div class="mp-loading">Loading</div>
                    <div class="mp-grid" style="display: none;"></div>
                    <div class="mp-empty" style="display: none;">No images found.</div>
                    <div class="mp-pagination" style="display: none;">
                        <button class="mp-load-more">Load More</button>
                        <span class="mp-count"></span>
                    </div>
                </div>
            </div>
        `;

        // Event listeners
        modal.querySelector('.mp-close').addEventListener('click', close);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) close();
        });

        // Search with debounce
        let searchTimeout;
        modal.querySelector('.mp-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                state.searchQuery = modal.querySelector('.mp-search').value.trim();
                state.page = 1;
                state.allItems = [];
                state.currentView = 'grid';
                loadMedia();
            }, 300);
        });

        // Load more
        modal.querySelector('.mp-load-more').addEventListener('click', function() {
            if (state.hasMore && !state.isLoading) {
                state.page++;
                loadMedia();
            }
        });

        document.body.appendChild(modal);
        return modal;
    }

    // Load media from API
    async function loadMedia() {
        if (state.isLoading) return;

        const modal = state.modal;
        const grid = modal.querySelector('.mp-grid');
        const loading = modal.querySelector('.mp-loading');
        const empty = modal.querySelector('.mp-empty');
        const pagination = modal.querySelector('.mp-pagination');
        const filters = modal.querySelector('.mp-filters');

        // Show filters in grid view
        filters.style.display = state.currentView === 'grid' ? 'flex' : 'none';

        if (state.page === 1) {
            loading.style.display = 'block';
            grid.style.display = 'none';
            grid.innerHTML = '';
            empty.style.display = 'none';
            pagination.style.display = 'none';
        }

        state.isLoading = true;

        try {
            const params = new URLSearchParams({
                type: 'image',
                limit: state.perPage,
                offset: (state.page - 1) * state.perPage
            });

            if (state.activeTag) {
                params.append('tag', state.activeTag);
            }
            if (state.searchQuery) {
                params.append('search', state.searchQuery);
            }

            const response = await fetch(state.apiEndpoint + '?' + params.toString());
            const result = await response.json();

            loading.style.display = 'none';
            state.isLoading = false;

            // Populate tags on first load
            if (result.tags && state.page === 1) {
                state.availableTags = result.tags;
                const tagsContainer = modal.querySelector('.mp-tags');
                tagsContainer.innerHTML = result.tags.map(tag =>
                    `<button type="button" class="mp-tag" data-tag="${tag.slug}" style="--tag-color: ${tag.color}">${tag.name}</button>`
                ).join('');

                // Tag click handlers
                tagsContainer.querySelectorAll('.mp-tag').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const tag = this.dataset.tag;

                        // Toggle active state
                        if (state.activeTag === tag) {
                            state.activeTag = '';
                            this.classList.remove('active');
                        } else {
                            tagsContainer.querySelectorAll('.mp-tag').forEach(b => b.classList.remove('active'));
                            state.activeTag = tag;
                            this.classList.add('active');
                        }

                        state.page = 1;
                        state.allItems = [];
                        loadMedia();
                    });
                });
            }

            // Handle both admin API (result.data) and CMS API (result.media) response formats
            let items = result.data || result.media || [];

            // Normalize item fields for CMS API (uses file_url instead of url)
            items = items.map(item => ({
                id: item.id,
                url: item.url || item.file_url,
                thumbnail: item.thumbnail || item.file_url,
                name: item.name || item.original_filename,
                alt: item.alt || item.alt_text || '',
                type: item.type || item.file_type,
                size: item.size || item.file_size,
                width: item.width,
                height: item.height,
                tags: item.tags || [],
                variants: (item.variants || []).map(v => ({
                    variant_name: v.variant_name,
                    variant_path: v.variant_path ? ('/' + v.variant_path.replace(/^.*?uploads\//, 'uploads/')) : '',
                    width: v.width,
                    height: v.height,
                    file_size: v.file_size
                }))
            }));

            if (items.length > 0) {
                if (state.page === 1) {
                    state.allItems = items;
                } else {
                    state.allItems = [...state.allItems, ...items];
                }

                state.hasMore = items.length === state.perPage;
                state.total = result.total || (result.pagination ? result.pagination.total : null) || state.allItems.length;

                renderGrid();
                updatePagination();
            } else if (state.page === 1) {
                grid.style.display = 'none';
                empty.style.display = 'block';
                pagination.style.display = 'none';
            } else {
                state.hasMore = false;
                updatePagination();
            }
        } catch (error) {
            console.error('Media picker error:', error);
            loading.style.display = 'none';
            state.isLoading = false;
            if (state.page === 1) {
                empty.textContent = 'Error loading media library';
                empty.style.display = 'block';
            }
        }
    }

    // Render grid
    function renderGrid() {
        const modal = state.modal;
        const grid = modal.querySelector('.mp-grid');
        const empty = modal.querySelector('.mp-empty');

        grid.innerHTML = '';

        if (state.allItems.length === 0) {
            grid.style.display = 'none';
            empty.style.display = 'block';
            return;
        }

        grid.style.display = 'grid';
        empty.style.display = 'none';

        state.allItems.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'mp-item';

            let tagsHtml = '';
            if (item.tags && item.tags.length > 0) {
                tagsHtml = '<div class="mp-item-tags">' +
                    item.tags.slice(0, 2).map(t => `<span class="mp-item-tag">${t}</span>`).join('') +
                    '</div>';
            }

            // Show "Sizes" badge if image has variants
            const hasVariants = item.variants && item.variants.length > 0;
            const badgeHtml = hasVariants ? '<span class="mp-item-badge">Sizes</span>' : '';

            div.innerHTML = `
                <img src="${item.thumbnail || item.url}" alt="${item.name || ''}" loading="lazy">
                ${badgeHtml}
                ${tagsHtml}
            `;

            div.addEventListener('click', function() {
                if (state.showSizePicker && hasVariants) {
                    showSizePicker(item);
                } else {
                    selectUrl(item.url);
                }
            });

            grid.appendChild(div);
        });
    }

    // Show size picker for an image
    function showSizePicker(item) {
        const modal = state.modal;
        const grid = modal.querySelector('.mp-grid');
        const pagination = modal.querySelector('.mp-pagination');
        const filters = modal.querySelector('.mp-filters');

        state.currentView = 'sizes';
        filters.style.display = 'none';
        pagination.style.display = 'none';

        // Filter out webp variants (served automatically via .htaccess)
        const jpgVariants = item.variants.filter(v => !v.variant_name.includes('_webp'));

        // Sort by width descending
        jpgVariants.sort((a, b) => (b.width || 0) - (a.width || 0));

        // Find preview image (medium or first available)
        const previewVariant = jpgVariants.find(v => v.variant_name === 'medium') || jpgVariants[0];
        const previewUrl = previewVariant ? previewVariant.variant_path : item.url;

        // Build size picker HTML
        let html = `
            <div class="mp-size-picker">
                <button class="mp-size-back" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to library
                </button>

                <div class="mp-size-preview">
                    <img src="${previewUrl}" alt="${item.name || ''}">
                </div>

                <div class="mp-size-filename">${item.name || 'Unknown'}</div>

                <div class="mp-size-grid">
        `;

        // Add variant options
        for (const variant of jpgVariants) {
            const info = sizeInfo[variant.variant_name] || {
                label: variant.variant_name.charAt(0).toUpperCase() + variant.variant_name.slice(1),
                desc: '',
                icon: '📷'
            };

            html += `
                <div class="mp-size-card ${info.recommended ? 'recommended' : ''}" data-url="${variant.variant_path}">
                    ${info.recommended ? '<span class="mp-size-badge">Recommended</span>' : ''}
                    <div class="mp-size-icon">${info.icon}</div>
                    <div class="mp-size-label">${info.label}</div>
                    ${info.desc ? `<div class="mp-size-desc">${info.desc}</div>` : ''}
                    <div class="mp-size-dims">${variant.width || '?'} × ${variant.height || '?'}</div>
                    <div class="mp-size-filesize">${formatSize(variant.file_size)}</div>
                </div>
            `;
        }

        // Add original as option
        html += `
                <div class="mp-size-card" data-url="${item.url}">
                    <div class="mp-size-icon">📁</div>
                    <div class="mp-size-label">Original</div>
                    <div class="mp-size-desc">Full resolution</div>
                    <div class="mp-size-dims">${item.width || '?'} × ${item.height || '?'}</div>
                    <div class="mp-size-filesize">${formatSize(item.size)}</div>
                </div>
            </div>
        </div>`;

        grid.innerHTML = html;
        grid.style.display = 'block';

        // Back button handler
        grid.querySelector('.mp-size-back').addEventListener('click', function() {
            state.currentView = 'grid';
            renderGrid();
            updatePagination();
            modal.querySelector('.mp-filters').style.display = 'flex';
        });

        // Size card click handlers
        grid.querySelectorAll('.mp-size-card').forEach(card => {
            card.addEventListener('click', function() {
                const url = this.dataset.url;
                if (url) {
                    selectUrl(url);
                }
            });
        });
    }

    // Update pagination
    function updatePagination() {
        const modal = state.modal;
        const pagination = modal.querySelector('.mp-pagination');
        const loadMore = modal.querySelector('.mp-load-more');
        const countEl = modal.querySelector('.mp-count');

        if (state.allItems.length > 0 && state.currentView === 'grid') {
            pagination.style.display = 'flex';
            loadMore.style.display = state.hasMore ? 'inline-block' : 'none';

            const showing = state.allItems.length;
            countEl.textContent = state.hasMore
                ? `Showing ${showing} of ${state.total} images`
                : `Showing all ${showing} images`;
        } else {
            pagination.style.display = 'none';
        }
    }

    // Select URL and call callback
    function selectUrl(url) {
        close();
        if (state.callback) {
            state.callback(url);
        }
    }

    // Open modal
    function open(callback, options) {
        options = options || {};

        state.callback = callback || null;
        state.apiEndpoint = options.apiEndpoint || '/admin/api/media';
        state.showSizePicker = options.showSizePicker !== false; // Default true
        state.activeTag = '';
        state.searchQuery = '';
        state.page = 1;
        state.allItems = [];
        state.hasMore = true;
        state.currentView = 'grid';

        // Create or get modal
        if (!state.modal) {
            state.modal = createModal();
        }

        // Reset UI
        state.modal.querySelector('.mp-search').value = '';
        state.modal.querySelectorAll('.mp-tag').forEach(btn => btn.classList.remove('active'));
        state.modal.querySelector('.mp-filters').style.display = 'flex';

        // Show modal
        state.modal.style.display = 'flex';
        requestAnimationFrame(function() {
            state.modal.classList.add('active');
        });

        // Load media
        loadMedia();

        // Handle escape key
        document.addEventListener('keydown', handleEscape);
    }

    // Close modal
    function close() {
        if (state.modal) {
            state.modal.classList.remove('active');
            setTimeout(function() {
                state.modal.style.display = 'none';
            }, 200);
        }
        document.removeEventListener('keydown', handleEscape);
    }

    function handleEscape(e) {
        if (e.key === 'Escape') {
            close();
        }
    }

    // Public API
    return {
        open: open,
        close: close
    };
})();
