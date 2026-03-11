<?php
/**
 * Bible Study Navigator Popup
 * Modal popup for quick navigation between books and chapters
 * The toggle button is placed inline in the sidebar (bible-study-chapter.php)
 */
?>
<!-- Backdrop overlay -->
<div class="study-nav-backdrop" id="study-nav-backdrop"></div>

<!-- Study Navigator Popup -->
<div class="study-nav-popup" id="study-nav-popup">
    <div class="study-nav-header">
        <h3>Browse Studies</h3>
        <button class="study-nav-close" id="study-nav-close">&times;</button>
    </div>

    <div class="study-nav-body">
        <!-- Book Selection -->
        <div class="study-nav-books" id="study-nav-books">
            <div class="study-nav-testament">
                <h4>Old Testament</h4>
                <div class="study-nav-book-list" id="old-testament-books"></div>
            </div>
            <div class="study-nav-testament">
                <h4>New Testament</h4>
                <div class="study-nav-book-list" id="new-testament-books"></div>
            </div>
        </div>

        <!-- Chapter Selection (shown after book is selected) -->
        <div class="study-nav-chapters" id="study-nav-chapters" style="display: none;">
            <button class="study-nav-back" id="study-nav-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                All Books
            </button>
            <h4 id="selected-book-name">Genesis</h4>
            <div class="study-nav-chapter-grid" id="chapter-grid"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const toggle = document.getElementById('study-nav-toggle');
    const popup = document.getElementById('study-nav-popup');
    const backdrop = document.getElementById('study-nav-backdrop');
    const closeBtn = document.getElementById('study-nav-close');
    const backBtn = document.getElementById('study-nav-back');
    const booksView = document.getElementById('study-nav-books');
    const chaptersView = document.getElementById('study-nav-chapters');
    const oldTestamentList = document.getElementById('old-testament-books');
    const newTestamentList = document.getElementById('new-testament-books');
    const chapterGrid = document.getElementById('chapter-grid');
    const selectedBookName = document.getElementById('selected-book-name');

    let booksData = null;

    function openPopup() {
        popup.classList.add('visible');
        backdrop.classList.add('visible');
        document.body.style.overflow = 'hidden';
        if (!booksData) {
            loadBooks();
        }
    }

    function closePopup() {
        popup.classList.remove('visible');
        backdrop.classList.remove('visible');
        document.body.style.overflow = '';
    }

    // Toggle popup
    if (toggle) {
        toggle.addEventListener('click', openPopup);
    }

    // Close popup
    closeBtn.addEventListener('click', closePopup);
    backdrop.addEventListener('click', closePopup);

    // Close on escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && popup.classList.contains('visible')) {
            closePopup();
        }
    });

    // Back to books view
    backBtn.addEventListener('click', () => {
        chaptersView.style.display = 'none';
        booksView.style.display = 'block';
    });

    // Load books data
    async function loadBooks() {
        try {
            const response = await fetch('/api/bible-study/books.php');
            const data = await response.json();

            if (data.success) {
                booksData = data.books;
                renderBooks();
            }
        } catch (error) {
            console.error('Failed to load books:', error);
        }
    }

    // Render book lists
    function renderBooks() {
        oldTestamentList.innerHTML = '';
        newTestamentList.innerHTML = '';

        booksData.forEach(book => {
            const hasStudies = book.available.length > 0;
            const btn = document.createElement('button');
            btn.className = 'study-nav-book' + (hasStudies ? '' : ' no-studies');
            btn.textContent = book.name;
            if (hasStudies) {
                btn.innerHTML += `<span class="study-count">${book.available.length}</span>`;
                btn.addEventListener('click', () => selectBook(book));
            } else {
                btn.disabled = true;
                btn.title = 'Coming soon';
            }

            if (book.testament === 'old') {
                oldTestamentList.appendChild(btn);
            } else {
                newTestamentList.appendChild(btn);
            }
        });
    }

    // Show chapters for selected book
    function selectBook(book) {
        selectedBookName.textContent = book.name;
        chapterGrid.innerHTML = '';

        for (let i = 1; i <= book.chapters; i++) {
            const hasStudy = book.available.includes(i);
            const el = document.createElement(hasStudy ? 'a' : 'span');
            el.className = 'study-nav-chapter' + (hasStudy ? '' : ' unavailable');
            el.textContent = i;

            if (hasStudy) {
                el.href = `/bible-study/${book.slug}/${i}`;
            }

            chapterGrid.appendChild(el);
        }

        booksView.style.display = 'none';
        chaptersView.style.display = 'block';
    }
})();
</script>
