(function () {
    // ==================== THEME TOGGLE ====================
    // Light mode is always the default. Dark mode only activates when explicitly chosen.
    const themeToggle = document.getElementById('theme-toggle');

    function getCurrentTheme() {
        // Default to light if no theme is set
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);

        // Save to localStorage for guests
        localStorage.setItem('theme', theme);

        // If user is logged in, also save to server
        if (window.isLoggedIn) {
            saveThemePreference(theme);
        }
    }

    function toggleTheme() {
        const currentTheme = getCurrentTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    }

    async function saveThemePreference(theme) {
        try {
            const formData = new FormData();
            formData.append('action', 'update_preference');
            formData.append('key', 'theme');
            formData.append('value', theme);

            await fetch('/api/user-preferences', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Failed to save theme preference:', error);
        }
    }

    if (themeToggle) {
        // Hide theme toggle in PWA mode (follows system settings)
        if (window.isPWA) {
            themeToggle.style.display = 'none';
        } else {
            themeToggle.addEventListener('click', toggleTheme);
        }
    }

    // ==================== NAV TOGGLE ====================
    const navToggle = document.getElementById('nav-toggle');
    const navLinks = document.querySelectorAll('.primary-nav a');
    navLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (navToggle && navToggle.checked) {
                navToggle.checked = false;
            }
        });
    });

    // Close button for mobile nav is now a <label> that toggles the checkbox directly

    // ==================== NEWSLETTER AJAX ====================
    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const emailInput = document.getElementById('newsletter-email');
            const submitBtn = document.getElementById('newsletter-submit');
            const messageEl = document.getElementById('newsletter-message');
            const email = emailInput.value.trim();

            if (!email) return;

            // Show loading state
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Subscribing...';
            submitBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('email', email);

                const response = await fetch('/api/newsletter-signup.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();

                // Show message
                messageEl.textContent = result.message;
                messageEl.className = 'newsletter-message ' + (result.success ? 'success' : 'error');
                messageEl.style.display = 'block';

                if (result.success) {
                    emailInput.value = '';
                    // Hide form after successful signup
                    newsletterForm.style.display = 'none';
                }

            } catch (error) {
                messageEl.textContent = 'Something went wrong. Please try again.';
                messageEl.className = 'newsletter-message error';
                messageEl.style.display = 'block';
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    const formNotices = document.querySelectorAll('.notice');
    formNotices.forEach((notice) => {
            notice.setAttribute('role', 'status');
    });

    // Tab switching functionality (for Connect page)
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTab = btn.dataset.tab;

            // Remove active classes
            tabButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            // Add active class to clicked button and corresponding content
            btn.classList.add('active');
            const targetContent = document.getElementById(targetTab);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    // Enhanced Event filtering functionality (for Events page)
    const eventFiltersContainer = document.querySelector('.event-filters');
    const eventsGrid = document.querySelector('.events-grid');
    const searchInput = document.getElementById('event-search');
    const monthFilter = document.getElementById('month-filter');
    const loadMoreBtn = document.getElementById('load-more-events');
    const visibleCountSpan = document.getElementById('visible-count');
    const totalCountSpan = document.getElementById('total-count');

    let currentCategoryFilter = 'all';
    let currentSearchTerm = '';
    let currentMonthFilter = 'all';
    let visibleLimit = 12;

    // Apply all filters
    function applyFilters() {
        const eventCards = document.querySelectorAll('.event-card-detailed');
        let visibleCount = 0;
        let totalMatches = 0;

        eventCards.forEach(card => {
            const category = card.dataset.category;
            const month = card.dataset.month;
            const title = card.dataset.title || '';
            const description = card.dataset.description || '';
            const searchText = title + ' ' + description;

            // Check category filter
            const categoryMatch = currentCategoryFilter === 'all' || category === currentCategoryFilter;

            // Check search filter
            const searchMatch = currentSearchTerm === '' || searchText.includes(currentSearchTerm.toLowerCase());

            // Check month filter
            const monthMatch = currentMonthFilter === 'all' || month === currentMonthFilter;

            // Show if all filters match
            if (categoryMatch && searchMatch && monthMatch) {
                totalMatches++;

                // Show only up to visibleLimit
                if (visibleCount < visibleLimit) {
                    card.style.display = '';
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            } else {
                card.style.display = 'none';
                card.classList.add('hidden');
            }
        });

        // Update counter
        if (visibleCountSpan) visibleCountSpan.textContent = visibleCount;
        if (totalCountSpan) totalCountSpan.textContent = totalMatches;

        // Show/hide load more button
        if (loadMoreBtn) {
            loadMoreBtn.style.display = visibleCount < totalMatches ? 'block' : 'none';
        }
    }

    // Category filter buttons - use event delegation for robustness
    if (eventFiltersContainer) {
        eventFiltersContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.filter-btn');
            if (!btn) return;

            e.preventDefault();
            currentCategoryFilter = btn.dataset.filter;
            visibleLimit = 12; // Reset limit when changing filters

            // Update active class on all filter buttons
            eventFiltersContainer.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            applyFilters();
        });
    }

    // Search input
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchTerm = e.target.value;
            visibleLimit = 12; // Reset limit when searching
            applyFilters();
        });
    }

    // Month filter
    if (monthFilter) {
        monthFilter.addEventListener('change', (e) => {
            currentMonthFilter = e.target.value;
            visibleLimit = 12; // Reset limit when changing month
            applyFilters();
        });
    }

    // Load more button
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            visibleLimit += 12; // Load 12 more events
            applyFilters();
        });
    }

    // Handle hash-based filter links from subnav (e.g., /events#weekly)
    function handleHashFilter() {
        const container = document.querySelector('.event-filters');
        if (!container) return;

        const hash = window.location.hash.replace('#', '');
        if (!hash) return;

        // Find the filter button that matches the hash
        const filterBtn = container.querySelector(`[data-filter="${hash}"]`);
        if (filterBtn) {
            currentCategoryFilter = hash;
            visibleLimit = 12;

            // Update active class
            container.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            filterBtn.classList.add('active');

            applyFilters();

            // Scroll to the events section
            setTimeout(() => {
                const eventsSection = document.querySelector('.events-calendar');
                if (eventsSection) {
                    eventsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 100);
        }
    }

    // Check hash on page load - run immediately and also after a short delay
    // to ensure DOM is fully ready
    if (eventFiltersContainer && window.location.hash) {
        handleHashFilter();
        // Also try after a small delay in case of timing issues
        setTimeout(handleHashFilter, 50);
    }

    // Handle hash changes (for subnav clicks when already on events page)
    window.addEventListener('hashchange', handleHashFilter);

    // Use event delegation for subnav clicks - more reliable than attaching to each link
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href*="/events#"]');
        if (!link) return;

        // Only handle if we're already on the events page
        const isEventsPage = window.location.pathname === '/events' || window.location.pathname === '/events/';
        if (isEventsPage) {
            e.preventDefault();
            const hash = link.getAttribute('href').split('#')[1];
            if (hash) {
                history.pushState(null, '', '#' + hash);
                handleHashFilter();
            }
        }
    });

    // Newsletter form enhancement (basic validation fallback - main AJAX handler is above)

    // User menu dropdown
    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        const trigger = userMenu.querySelector('.user-menu-trigger');

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('open');
            trigger.setAttribute('aria-expanded', userMenu.classList.contains('open'));
        });
    }

    // Navigation dropdowns
    const navDropdowns = document.querySelectorAll('.nav-dropdown');
    navDropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.nav-dropdown-trigger');

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close other dropdowns
            navDropdowns.forEach(d => {
                if (d !== dropdown) d.classList.remove('open');
            });
            dropdown.classList.toggle('open');
            trigger.setAttribute('aria-expanded', dropdown.classList.contains('open'));
        });
    });

    // Close all dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        // Close user menu
        if (userMenu && !userMenu.contains(e.target)) {
            userMenu.classList.remove('open');
            userMenu.querySelector('.user-menu-trigger')?.setAttribute('aria-expanded', 'false');
        }
        // Close nav dropdowns
        navDropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
                dropdown.querySelector('.nav-dropdown-trigger')?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (userMenu) {
                userMenu.classList.remove('open');
                userMenu.querySelector('.user-menu-trigger')?.setAttribute('aria-expanded', 'false');
            }
            navDropdowns.forEach(dropdown => {
                dropdown.classList.remove('open');
                dropdown.querySelector('.nav-dropdown-trigger')?.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // ==================== YOUTUBE FACADE (Performance) ====================
    // Lazy-load YouTube iframes only when user clicks play
    document.querySelectorAll('.youtube-facade').forEach(facade => {
        facade.addEventListener('click', function() {
            const videoId = this.dataset.videoId;
            if (!videoId) return;

            const iframe = document.createElement('iframe');
            iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
            iframe.title = this.querySelector('img')?.alt || 'YouTube video';
            iframe.frameBorder = '0';
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            iframe.allowFullscreen = true;

            this.replaceWith(iframe);
        });
    });

    // ==================== QUESTION SEARCH AUTOCOMPLETE ====================
    // Autocomplete for bible study question search (works on both questions and topics pages)
    const questionSearchInput = document.getElementById('question-search-input') || document.getElementById('topics-search-input');
    const autocompleteDropdown = document.getElementById('autocomplete-dropdown') || document.getElementById('topics-autocomplete-dropdown');

    if (questionSearchInput && autocompleteDropdown) {
        let debounceTimer;
        let selectedIndex = -1;

        // Debounced search
        questionSearchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();

            if (query.length < 2) {
                autocompleteDropdown.innerHTML = '';
                autocompleteDropdown.classList.remove('active');
                return;
            }

            debounceTimer = setTimeout(async () => {
                try {
                    const response = await fetch(`/api/question-search?q=${encodeURIComponent(query)}&limit=8`);
                    const data = await response.json();

                    if (data.results && data.results.length > 0) {
                        renderAutocomplete(data.results);
                    } else {
                        autocompleteDropdown.innerHTML = '<div class="autocomplete-no-results">No matching questions found</div>';
                        autocompleteDropdown.classList.add('active');
                    }
                } catch (error) {
                    console.error('Autocomplete error:', error);
                }
            }, 200);
        });

        function renderAutocomplete(results) {
            selectedIndex = -1;
            autocompleteDropdown.innerHTML = results.map((r, i) => {
                if (r.type === 'topic') {
                    // Render topic
                    return `
                        <a href="${r.url}" class="autocomplete-item autocomplete-topic-item" data-index="${i}">
                            <span class="autocomplete-icon">${r.icon || '📁'}</span>
                            <div class="autocomplete-content">
                                <span class="autocomplete-label">Topic</span>
                                <span class="autocomplete-title">${escapeHtml(r.name)}</span>
                                <span class="autocomplete-meta">
                                    ${r.parent ? `<span class="autocomplete-parent">${escapeHtml(r.parent)}</span>` : ''}
                                    ${r.study_count > 0 ? `<span class="autocomplete-count">${r.study_count} ${r.study_count === 1 ? 'study' : 'studies'}</span>` : ''}
                                </span>
                            </div>
                        </a>
                    `;
                } else {
                    // Render question
                    return `
                        <a href="${r.url}" class="autocomplete-item autocomplete-question-item" data-index="${i}">
                            <span class="autocomplete-icon">${r.icon || '❓'}</span>
                            <div class="autocomplete-content">
                                <span class="autocomplete-question">${escapeHtml(r.question)}</span>
                                <span class="autocomplete-meta">
                                    <span class="autocomplete-topic-badge">${escapeHtml(r.topic)}</span>
                                    ${r.study_count > 0 ? `<span class="autocomplete-count">${r.study_count} ${r.study_count === 1 ? 'study' : 'studies'}</span>` : ''}
                                </span>
                            </div>
                        </a>
                    `;
                }
            }).join('');
            autocompleteDropdown.classList.add('active');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Keyboard navigation
        questionSearchInput.addEventListener('keydown', (e) => {
            const items = autocompleteDropdown.querySelectorAll('.autocomplete-item');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                items[selectedIndex].click();
            } else if (e.key === 'Escape') {
                autocompleteDropdown.classList.remove('active');
                selectedIndex = -1;
            }
        });

        function updateSelection(items) {
            items.forEach((item, i) => {
                item.classList.toggle('selected', i === selectedIndex);
            });
        }

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!questionSearchInput.contains(e.target) && !autocompleteDropdown.contains(e.target)) {
                autocompleteDropdown.classList.remove('active');
            }
        });

        // Show dropdown on focus if there's content
        questionSearchInput.addEventListener('focus', () => {
            if (autocompleteDropdown.innerHTML && questionSearchInput.value.length >= 2) {
                autocompleteDropdown.classList.add('active');
            }
        });
    }

})();
