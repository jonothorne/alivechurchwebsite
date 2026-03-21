<?php
/**
 * Reusable Media Picker Component
 *
 * Include this file in any admin page that needs image selection.
 *
 * Usage:
 * 1. Include this file: require_once __DIR__ . '/includes/media-picker.php';
 * 2. Call openMediaPicker(callback) where callback receives the selected URL
 * 3. Or use the helper: createImagePickerField($fieldName, $currentValue, $label)
 */
?>

<!-- Media Picker Modal -->
<div id="media-picker-modal" class="media-picker-overlay">
    <div class="media-picker-modal">
        <div class="media-picker-header">
            <h3>Select Image</h3>
            <button type="button" id="media-picker-close-btn" class="media-picker-close">&times;</button>
        </div>
        <div class="media-picker-filters">
            <input type="text" id="media-search" placeholder="Search images...">
            <div id="media-tags" class="media-tag-filters"></div>
        </div>
        <div class="media-picker-body">
            <div id="media-picker-loading" class="media-picker-loading">Loading...</div>
            <div id="media-picker-grid" class="media-picker-grid"></div>
            <div id="media-picker-empty" class="media-picker-empty">
                No images found. <a href="/admin/media">Upload one</a>.
            </div>
            <div id="media-picker-pagination" class="media-picker-pagination">
                <button type="button" id="media-load-more" class="btn btn-outline">
                    Load More
                </button>
                <span id="media-count" class="media-count"></span>
            </div>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.image-picker-field {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}
.image-preview-container {
    width: 120px;
    height: 80px;
    border-radius: var(--radius-md);
    overflow: hidden;
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border);
    flex-shrink: 0;
}
.image-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.image-preview-hidden {
    display: none;
}
.media-picker-clear-hidden {
    display: none;
}
.image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: var(--color-text-muted);
}
.image-picker-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.media-picker-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}
.media-picker-modal {
    background: var(--color-bg-elevated);
    border-radius: var(--radius-xl);
    width: 90%;
    max-width: 700px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}
.media-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--color-border);
}
.media-picker-header h3 {
    margin: 0;
    font-size: 1rem;
}
.media-picker-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-muted);
    line-height: 1;
}
.media-picker-close:hover {
    color: var(--color-text);
}
.media-picker-filters {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.media-picker-filters input {
    padding: 0.5rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    background: var(--color-bg-subtle);
    color: var(--color-text);
}
.media-picker-filters input:focus {
    outline: none;
    border-color: var(--color-purple);
}
.media-tag-filters {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}
.media-tag-btn {
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    border: 1px solid var(--color-border);
    background: var(--color-bg-subtle);
    color: var(--color-text);
    cursor: pointer;
    transition: all 0.15s;
}
.media-tag-btn:hover {
    border-color: var(--tag-color, var(--color-purple));
}
.media-tag-btn.active {
    background: var(--tag-color, var(--color-purple));
    color: white;
    border-color: var(--tag-color, var(--color-purple));
}
.media-picker-body {
    padding: 1rem;
    overflow-y: auto;
    flex: 1;
}
.media-picker-loading {
    text-align: center;
    padding: 2rem;
}
.media-picker-empty {
    display: none;
    text-align: center;
    padding: 2rem;
    color: var(--color-text-muted);
}
.media-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 0.75rem;
}
.media-picker-item {
    aspect-ratio: 1;
    border-radius: var(--radius-md);
    overflow: hidden;
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.15s, transform 0.15s;
    background: var(--color-bg-subtle);
}
.media-picker-item:hover {
    border-color: var(--color-purple);
    transform: scale(1.02);
}
.media-picker-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.media-picker-pagination {
    margin-top: 1rem;
    text-align: center;
    display: none;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}
.media-count {
    font-size: 0.8rem;
    color: var(--color-text-muted);
}
</style>

<script <?= csp_nonce(); ?>>
(function() {
    // Media picker state
    let mediaPickerState = {
        allItems: [],
        filteredItems: [],
        displayedItems: [],
        availableTags: [],
        activeTag: '',
        searchQuery: '',
        page: 1,
        perPage: 50,
        totalLoaded: 0,
        hasMore: true,
        isLoading: false,
        callback: null
    };

    // Make functions globally available
    window.openMediaPicker = function(callback) {
        mediaPickerState.callback = callback || null;
        document.getElementById('media-picker-modal').style.display = 'flex';
        document.getElementById('media-search').value = '';
        mediaPickerState.activeTag = '';
        mediaPickerState.searchQuery = '';
        mediaPickerState.page = 1;
        mediaPickerState.allItems = [];
        mediaPickerState.hasMore = true;

        // Reset tag buttons
        document.querySelectorAll('.media-tag-btn').forEach(btn => btn.classList.remove('active'));

        loadMediaLibrary();
    };

    window.closeMediaPicker = function() {
        document.getElementById('media-picker-modal').style.display = 'none';
    };

    window.loadMediaLibrary = async function() {
        if (mediaPickerState.isLoading) return;

        const grid = document.getElementById('media-picker-grid');
        const loading = document.getElementById('media-picker-loading');
        const empty = document.getElementById('media-picker-empty');
        const tagsContainer = document.getElementById('media-tags');
        const pagination = document.getElementById('media-picker-pagination');

        if (mediaPickerState.page === 1) {
            loading.style.display = 'block';
            grid.innerHTML = '';
            empty.style.display = 'none';
            pagination.style.display = 'none';
        }

        mediaPickerState.isLoading = true;

        try {
            // Build query params
            const params = new URLSearchParams({
                type: 'image',
                limit: mediaPickerState.perPage,
                offset: (mediaPickerState.page - 1) * mediaPickerState.perPage
            });

            if (mediaPickerState.activeTag) {
                params.append('tag', mediaPickerState.activeTag);
            }
            if (mediaPickerState.searchQuery) {
                params.append('search', mediaPickerState.searchQuery);
            }

            const response = await fetch('/admin/api/media?' + params.toString());
            const result = await response.json();

            loading.style.display = 'none';
            mediaPickerState.isLoading = false;

            // Populate tags on first load
            if (result.tags && mediaPickerState.page === 1) {
                mediaPickerState.availableTags = result.tags;
                tagsContainer.innerHTML = result.tags.map(tag =>
                    `<button type="button" class="media-tag-btn" data-tag="${tag.slug}" style="--tag-color: ${tag.color}">${tag.name}</button>`
                ).join('');
            }

            if (result.data && result.data.length > 0) {
                if (mediaPickerState.page === 1) {
                    mediaPickerState.allItems = result.data;
                } else {
                    mediaPickerState.allItems = [...mediaPickerState.allItems, ...result.data];
                }

                mediaPickerState.hasMore = result.data.length === mediaPickerState.perPage;
                mediaPickerState.totalLoaded = result.total || mediaPickerState.allItems.length;

                renderMediaGrid();
                updatePagination();
            } else if (mediaPickerState.page === 1) {
                empty.style.display = 'block';
                pagination.style.display = 'none';
            } else {
                mediaPickerState.hasMore = false;
                updatePagination();
            }
        } catch (error) {
            console.error('Media library error:', error);
            loading.style.display = 'none';
            mediaPickerState.isLoading = false;
            if (mediaPickerState.page === 1) {
                empty.textContent = 'Error loading media library';
                empty.style.display = 'block';
            }
        }
    };

    window.loadMoreMedia = function() {
        if (mediaPickerState.hasMore && !mediaPickerState.isLoading) {
            mediaPickerState.page++;
            loadMediaLibrary();
        }
    };

    window.renderMediaGrid = function() {
        const grid = document.getElementById('media-picker-grid');
        const empty = document.getElementById('media-picker-empty');

        grid.innerHTML = '';

        if (mediaPickerState.allItems.length === 0) {
            empty.style.display = 'block';
            return;
        }

        empty.style.display = 'none';
        mediaPickerState.allItems.forEach(item => {
            const div = document.createElement('div');
            div.className = 'media-picker-item';
            div.innerHTML = `<img src="${item.thumbnail || item.url}" alt="${item.name || ''}" loading="lazy">`;
            div.onclick = () => selectMediaItem(item.url);
            grid.appendChild(div);
        });
    };

    window.updatePagination = function() {
        const pagination = document.getElementById('media-picker-pagination');
        const loadMore = document.getElementById('media-load-more');
        const countEl = document.getElementById('media-count');

        if (mediaPickerState.allItems.length > 0) {
            pagination.style.display = 'flex';
            loadMore.style.display = mediaPickerState.hasMore ? 'inline-flex' : 'none';

            const total = mediaPickerState.totalLoaded;
            const showing = mediaPickerState.allItems.length;
            countEl.textContent = mediaPickerState.hasMore
                ? `Showing ${showing} images`
                : `Showing all ${showing} images`;
        } else {
            pagination.style.display = 'none';
        }
    };

    window.filterMedia = function() {
        mediaPickerState.searchQuery = document.getElementById('media-search').value.toLowerCase();
        mediaPickerState.page = 1;
        mediaPickerState.allItems = [];
        loadMediaLibrary();
    };

    // Debounce search
    let searchTimeout;
    document.getElementById('media-search')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterMedia, 300);
    });

    window.filterByTag = function(tag) {
        const buttons = document.querySelectorAll('.media-tag-btn');
        buttons.forEach(btn => btn.classList.remove('active'));

        if (mediaPickerState.activeTag === tag) {
            mediaPickerState.activeTag = '';
        } else {
            mediaPickerState.activeTag = tag;
            document.querySelector(`[data-tag="${tag}"]`)?.classList.add('active');
        }

        mediaPickerState.page = 1;
        mediaPickerState.allItems = [];
        loadMediaLibrary();
    };

    window.selectMediaItem = function(url) {
        // Try to use medium size variant if available
        let finalUrl = url;
        const mediumUrl = url.replace(/(\.[^.]+)$/, '-medium$1');
        const smallUrl = url.replace(/(\.[^.]+)$/, '-small$1');

        // Check if medium variant exists by trying to load it
        const img = new Image();
        img.onload = function() {
            finalUrl = mediumUrl;
            completeSelection(finalUrl);
        };
        img.onerror = function() {
            // Try small variant
            const imgSmall = new Image();
            imgSmall.onload = function() {
                finalUrl = smallUrl;
                completeSelection(finalUrl);
            };
            imgSmall.onerror = function() {
                // Use original
                completeSelection(url);
            };
            imgSmall.src = smallUrl;
        };
        img.src = mediumUrl;

        closeMediaPicker();
    };

    function completeSelection(url) {
        if (mediaPickerState.callback) {
            mediaPickerState.callback(url);
        }
    }

    // Close modal on overlay click
    document.getElementById('media-picker-modal')?.addEventListener('click', function(e) {
        if (e.target === this) closeMediaPicker();
    });

    // Close button
    document.getElementById('media-picker-close-btn')?.addEventListener('click', closeMediaPicker);

    // Load more button
    document.getElementById('media-load-more')?.addEventListener('click', loadMoreMedia);

    // Tag filter buttons (event delegation since tags are dynamically added)
    document.getElementById('media-tags')?.addEventListener('click', function(e) {
        const tagBtn = e.target.closest('.media-tag-btn');
        if (tagBtn) {
            const tag = tagBtn.dataset.tag;
            filterByTag(tag);
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('media-picker-modal')?.style.display === 'flex') {
            closeMediaPicker();
        }
    });
})();

/**
 * Helper function to create an image picker field
 * Call this from PHP: <?= createImagePickerField('image_url', $currentValue, 'Image'); ?>
 */
</script>

<?php
/**
 * Creates an image picker field with preview
 *
 * @param string $fieldName The form field name
 * @param string $currentValue The current image URL value
 * @param string $label The field label
 * @param string $helpText Optional help text
 * @return string HTML for the image picker field
 */
function createImagePickerField($fieldName, $currentValue = '', $label = 'Image', $helpText = '') {
    $hasImage = !empty($currentValue);
    $fieldId = str_replace(['[', ']'], ['_', ''], $fieldName);

    $html = '<div class="form-group">';
    $html .= '<label>' . htmlspecialchars($label) . '</label>';
    $html .= '<div class="image-picker-field">';
    $html .= '    <input type="hidden" name="' . htmlspecialchars($fieldName) . '" id="' . $fieldId . '" value="' . htmlspecialchars($currentValue) . '">';
    $html .= '    <div class="image-preview-container">';

    if ($hasImage) {
        $html .= '        <img src="' . htmlspecialchars($currentValue) . '" id="' . $fieldId . '_preview" class="image-preview">';
    } else {
        $html .= '        <img src="" id="' . $fieldId . '_preview" class="image-preview image-preview-hidden">';
        $html .= '        <div id="' . $fieldId . '_placeholder" class="image-placeholder">No image</div>';
    }

    $html .= '    </div>';
    $html .= '    <div class="image-picker-actions">';
    $html .= '        <button type="button" class="btn btn-sm btn-outline media-picker-select-btn" data-field="' . $fieldId . '">Select Image</button>';
    $html .= '        <button type="button" class="btn btn-sm btn-outline media-picker-clear-btn' . ($hasImage ? '' : ' media-picker-clear-hidden') . '" data-field="' . $fieldId . '" id="' . $fieldId . '_clear">Clear</button>';
    $html .= '    </div>';
    $html .= '</div>';

    if ($helpText) {
        $html .= '<div class="form-help">' . htmlspecialchars($helpText) . '</div>';
    }

    $html .= '</div>';

    return $html;
}
?>

<script <?= csp_nonce(); ?>>
// Helper functions for image picker fields
window.openMediaPickerFor = function(fieldId) {
    openMediaPicker(function(url) {
        setImageFieldValue(fieldId, url);
    });
};

window.setImageFieldValue = function(fieldId, url) {
    document.getElementById(fieldId).value = url;
    const preview = document.getElementById(fieldId + '_preview');
    const placeholder = document.getElementById(fieldId + '_placeholder');
    const clearBtn = document.getElementById(fieldId + '_clear');

    if (preview) {
        preview.src = url;
        preview.classList.remove('image-preview-hidden');
    }
    if (placeholder) {
        placeholder.style.display = 'none';
    }
    if (clearBtn) {
        clearBtn.classList.remove('media-picker-clear-hidden');
    }
};

window.clearImageField = function(fieldId) {
    document.getElementById(fieldId).value = '';
    const preview = document.getElementById(fieldId + '_preview');
    const placeholder = document.getElementById(fieldId + '_placeholder');
    const clearBtn = document.getElementById(fieldId + '_clear');

    if (preview) {
        preview.classList.add('image-preview-hidden');
        preview.src = '';
    }
    if (placeholder) {
        placeholder.style.display = 'flex';
    }
    if (clearBtn) {
        clearBtn.classList.add('media-picker-clear-hidden');
    }
};

// Event delegation for select/clear buttons
document.addEventListener('click', function(e) {
    const selectBtn = e.target.closest('.media-picker-select-btn');
    if (selectBtn) {
        const fieldId = selectBtn.dataset.field;
        openMediaPickerFor(fieldId);
        return;
    }

    const clearBtn = e.target.closest('.media-picker-clear-btn');
    if (clearBtn) {
        const fieldId = clearBtn.dataset.field;
        clearImageField(fieldId);
        return;
    }
});
</script>
