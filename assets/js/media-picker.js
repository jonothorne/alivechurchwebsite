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

    // CSS styles are now loaded from /assets/css/media-picker.css
    // This avoids CSP issues with dynamically injected styles

    // Format file size
    function formatSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Create modal HTML
    function createModal() {
        // CSS is loaded from /assets/css/media-picker.css (included via PHP)
        // This avoids CSP violations from dynamically injected styles

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
            // Send both page and offset to support both admin API and CMS API
            const params = new URLSearchParams({
                type: 'image',
                limit: state.perPage,
                offset: (state.page - 1) * state.perPage,
                page: state.page
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
