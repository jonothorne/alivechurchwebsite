// Admin Panel JavaScript

(function() {
    'use strict';

    // Initialize TinyMCE on all textareas with class 'wysiwyg'
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: 'textarea.wysiwyg',
            height: 400,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | code',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px }',
            branding: false
        });
    }

    // Confirm delete actions (data-confirm-delete attribute)
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('[data-confirm-delete]');
        if (deleteBtn) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        }
    });

    // Custom confirm dialogs (data-confirm="message")
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-confirm]');
        if (btn && !confirm(btn.dataset.confirm)) {
            e.preventDefault();
        }
    });

    // Confirm on form submit (data-confirm-submit="message")
    document.addEventListener('submit', function(e) {
        const form = e.target.closest('[data-confirm-submit]');
        if (form && !confirm(form.dataset.confirmSubmit)) {
            e.preventDefault();
        }
    });

    // Navigate on select change (data-navigate-on-change)
    document.addEventListener('change', function(e) {
        if (e.target.matches('[data-navigate-on-change]')) {
            window.location.href = e.target.value;
        }
    });

    // Submit form on input change (data-submit-on-change)
    document.addEventListener('change', function(e) {
        if (e.target.matches('[data-submit-on-change]')) {
            e.target.form.submit();
        }
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Image preview for file uploads
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector(`#${input.id}-preview`);
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Sortable lists (if needed)
    const sortableLists = document.querySelectorAll('[data-sortable]');
    sortableLists.forEach(list => {
        // Add drag and drop functionality here if needed
        // You can use a library like SortableJS or implement custom drag/drop
    });

    console.log('Admin panel initialized');
})();
