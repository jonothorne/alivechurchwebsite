/**
 * Music Stand - Main Application
 * Touch-optimized chord chart viewer with swipe navigation, annotations, and settings
 */

const MusicStand = {
    // State
    currentIndex: 0,
    songs: [],
    serviceId: null,
    mode: 'service', // 'service' or 'library'
    settingsSongIndex: null,

    // DOM elements
    swiper: null,
    tabs: null,
    dots: null,
    settingsPanel: null,
    settingsOverlay: null,

    // Touch handling
    touchStartX: 0,
    touchStartY: 0,
    touchEndX: 0,
    touchEndY: 0,
    isDragging: false,
    dragOffset: 0,

    // Drawing state
    drawMode: false,
    isDrawing: false,
    drawCanvas: null,
    drawCtx: null,
    drawPaths: [],
    currentPath: [],

    // Display settings
    showChords: true,
    twoColumns: false,

    // User/admin state
    isAdmin: false,
    userId: null,

    // Song library state
    allSongs: [],
    searchTimeout: null,
    draggedSong: null,

    /**
     * Initialize the app
     */
    init(config) {
        this.mode = config.mode || 'service';
        this.serviceId = config.serviceId || null;
        this.songs = config.songs || [];
        this.isAdmin = config.isAdmin || false;
        this.userId = config.userId || null;

        // Cache DOM elements
        this.swiper = document.getElementById('chart-swiper');
        this.tabs = document.querySelectorAll('.song-tab');
        this.dots = document.querySelectorAll('.page-dot');
        this.settingsPanel = document.getElementById('settings-panel');
        this.settingsOverlay = document.getElementById('settings-overlay');

        // Load song library for sidebar (service mode only)
        if (this.mode === 'service') {
            this.loadSongLibrary();
            this.setupDropZone();
            this.loadPersonalSongs();
        }

        if (this.songs.length === 0) return;

        // Setup event listeners
        if (this.swiper) {
            this.setupTouchEvents();
        }
        this.setupKeyboardEvents();

        // Initialize drawing canvas
        this.initDrawingCanvas();

        // Load annotations for all songs
        this.loadAllAnnotations(config.annotation);

        // Render all chord charts
        this.renderAllCharts();

        // Show swipe hint for first-time users (only in service mode)
        if (this.mode === 'service' && this.songs.length > 1) {
            this.showSwipeHint();
        }
    },

    /**
     * Setup touch swipe events
     */
    setupTouchEvents() {
        const container = document.getElementById('chart-container');
        if (!container) return;

        container.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
        container.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        container.addEventListener('touchend', (e) => this.handleTouchEnd(e), { passive: true });

        // Mouse events for desktop testing
        container.addEventListener('mousedown', (e) => this.handleMouseDown(e));
        container.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        container.addEventListener('mouseup', (e) => this.handleMouseUp(e));
        container.addEventListener('mouseleave', (e) => this.handleMouseUp(e));
    },

    /**
     * Handle touch start
     */
    handleTouchStart(e) {
        if (this.drawMode) return;
        this.touchStartX = e.touches[0].clientX;
        this.touchStartY = e.touches[0].clientY;
        this.isDragging = true;
        if (this.swiper) this.swiper.style.transition = 'none';
    },

    /**
     * Handle touch move
     */
    handleTouchMove(e) {
        if (this.drawMode || !this.isDragging) return;

        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const diffX = currentX - this.touchStartX;
        const diffY = currentY - this.touchStartY;

        if (Math.abs(diffY) > Math.abs(diffX)) return;

        e.preventDefault();
        this.dragOffset = diffX;
        const baseOffset = -this.currentIndex * 100;
        const dragPercent = (diffX / this.swiper.offsetWidth) * 100;
        this.swiper.style.transform = `translateX(calc(${baseOffset}% + ${dragPercent}%))`;
    },

    /**
     * Handle touch end
     */
    handleTouchEnd(e) {
        if (this.drawMode || !this.isDragging) return;

        this.isDragging = false;
        if (this.swiper) this.swiper.style.transition = 'transform 0.3s ease-out';

        const threshold = this.swiper ? this.swiper.offsetWidth * 0.2 : 100;

        if (Math.abs(this.dragOffset) > threshold) {
            if (this.dragOffset > 0 && this.currentIndex > 0) {
                this.goToSong(this.currentIndex - 1);
            } else if (this.dragOffset < 0 && this.currentIndex < this.songs.length - 1) {
                this.goToSong(this.currentIndex + 1);
            } else {
                this.goToSong(this.currentIndex);
            }
        } else {
            this.goToSong(this.currentIndex);
        }

        this.dragOffset = 0;
    },

    handleMouseDown(e) {
        if (this.drawMode) return;
        this.touchStartX = e.clientX;
        this.isDragging = true;
        if (this.swiper) this.swiper.style.transition = 'none';
        e.preventDefault();
    },

    handleMouseMove(e) {
        if (this.drawMode || !this.isDragging) return;
        const diffX = e.clientX - this.touchStartX;
        this.dragOffset = diffX;
        const baseOffset = -this.currentIndex * 100;
        const dragPercent = (diffX / this.swiper.offsetWidth) * 100;
        this.swiper.style.transform = `translateX(calc(${baseOffset}% + ${dragPercent}%))`;
    },

    handleMouseUp(e) {
        if (this.drawMode || !this.isDragging) return;
        this.handleTouchEnd(e);
    },

    /**
     * Setup keyboard navigation
     */
    setupKeyboardEvents() {
        document.addEventListener('keydown', (e) => {
            if (this.settingsPanel && this.settingsPanel.classList.contains('active')) return;

            switch (e.key) {
                case 'ArrowLeft':
                case 'PageUp':
                    if (this.currentIndex > 0) this.goToSong(this.currentIndex - 1);
                    e.preventDefault();
                    break;
                case 'ArrowRight':
                case 'PageDown':
                case ' ':
                    if (this.currentIndex < this.songs.length - 1) this.goToSong(this.currentIndex + 1);
                    e.preventDefault();
                    break;
                case 'Home':
                    this.goToSong(0);
                    e.preventDefault();
                    break;
                case 'End':
                    this.goToSong(this.songs.length - 1);
                    e.preventDefault();
                    break;
            }
        });
    },

    /**
     * Navigate to a specific song
     */
    goToSong(index) {
        if (index < 0 || index >= this.songs.length) return;
        this.currentIndex = index;

        if (this.swiper) {
            this.swiper.style.transition = 'transform 0.3s ease-out';
            this.swiper.style.transform = `translateX(-${index * 100}%)`;
        }

        this.tabs.forEach((tab, i) => {
            tab.classList.toggle('active', i === index);
            if (i === index) tab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        });

        this.dots.forEach((dot, i) => dot.classList.toggle('active', i === index));

        // Update drawing canvas for new song
        this.updateDrawingCanvas(index);
    },

    /**
     * Render all chord charts
     */
    renderAllCharts() {
        const displays = document.querySelectorAll('.chord-chart-display');
        displays.forEach((display, index) => {
            const chartData = display.dataset.chart;
            if (!chartData) return;

            try {
                const chartText = atob(chartData);
                if (!chartText) return;

                const originalKey = display.dataset.originalKey;
                const currentKey = display.dataset.currentKey;
                this.renderChart(display, chartText, originalKey, currentKey, index);
            } catch (e) {
                console.error('Failed to decode chart:', e);
            }
        });
    },

    /**
     * Render a single chord chart
     */
    renderChart(display, chartText, originalKey, currentKey, songIndex) {
        if (!chartText) {
            display.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📄</div>
                    <h3>No Chord Chart</h3>
                    <p>This song doesn't have a chord chart yet.</p>
                </div>
            `;
            return;
        }

        let transposedChart = chartText;
        if (typeof ChordTransposer !== 'undefined' && originalKey !== currentKey) {
            transposedChart = ChordTransposer.transpose(chartText, originalKey, currentKey);
        }

        let html = '';
        if (typeof ChordTransposer !== 'undefined') {
            html = ChordTransposer.formatForDisplay(transposedChart, { showChords: this.showChords });
        } else {
            html = `<pre>${this.escapeHtml(transposedChart)}</pre>`;
        }

        display.innerHTML = html;
        this.applySettings(songIndex);
    },

    /**
     * Initialize drawing canvas(es)
     */
    initDrawingCanvas() {
        if (this.mode === 'library') {
            // Single canvas for library mode
            const layer = document.getElementById('annotation-layer');
            const canvas = document.getElementById('drawing-canvas');
            if (!layer || !canvas) return;

            this.drawCanvas = canvas;
            this.drawCtx = canvas.getContext('2d');
            this.setupCanvasEvents(canvas);
            this.resizeCanvas();
        } else {
            // Multiple canvases for service mode (one per song)
            this.songs.forEach((song, index) => {
                const canvas = document.getElementById(`drawing-canvas-${index}`);
                if (canvas) {
                    song.canvas = canvas;
                    song.ctx = canvas.getContext('2d');
                    this.setupCanvasEvents(canvas, index);
                }
            });
            this.updateDrawingCanvas(this.currentIndex);
        }

        window.addEventListener('resize', () => this.resizeAllCanvases());
    },

    setupCanvasEvents(canvas, index = null) {
        canvas.addEventListener('touchstart', (e) => this.startDrawing(e, index), { passive: false });
        canvas.addEventListener('touchmove', (e) => this.draw(e, index), { passive: false });
        canvas.addEventListener('touchend', () => this.stopDrawing(index));

        canvas.addEventListener('mousedown', (e) => this.startDrawing(e, index));
        canvas.addEventListener('mousemove', (e) => this.draw(e, index));
        canvas.addEventListener('mouseup', () => this.stopDrawing(index));
        canvas.addEventListener('mouseleave', () => this.stopDrawing(index));
    },

    resizeAllCanvases() {
        if (this.mode === 'library') {
            this.resizeCanvas();
        } else {
            this.songs.forEach((song, index) => {
                if (song.canvas) {
                    const rect = song.canvas.parentElement.getBoundingClientRect();
                    song.canvas.width = rect.width;
                    song.canvas.height = rect.height;
                    this.redrawCanvasForSong(index);
                }
            });
        }
    },

    resizeCanvas() {
        if (!this.drawCanvas) return;
        const rect = this.drawCanvas.parentElement.getBoundingClientRect();
        this.drawCanvas.width = rect.width;
        this.drawCanvas.height = rect.height;
        this.redrawCanvas();
    },

    getActiveCanvas(songIndex = null) {
        if (this.mode === 'library') {
            return { canvas: this.drawCanvas, ctx: this.drawCtx };
        }
        const idx = songIndex !== null ? songIndex : this.currentIndex;
        const song = this.songs[idx];
        return song ? { canvas: song.canvas, ctx: song.ctx } : { canvas: null, ctx: null };
    },

    startDrawing(e, songIndex = null) {
        if (!this.drawMode) return;
        e.preventDefault();
        this.isDrawing = true;
        this.currentPath = [];

        const { canvas, ctx } = this.getActiveCanvas(songIndex);
        if (!canvas || !ctx) return;

        const pos = this.getDrawPosition(e, canvas);
        this.currentPath.push(pos);

        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        ctx.strokeStyle = '#fbbf24';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    },

    draw(e, songIndex = null) {
        if (!this.drawMode || !this.isDrawing) return;
        e.preventDefault();

        const { canvas, ctx } = this.getActiveCanvas(songIndex);
        if (!canvas || !ctx) return;

        const pos = this.getDrawPosition(e, canvas);
        this.currentPath.push(pos);

        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    },

    stopDrawing(songIndex = null) {
        if (!this.isDrawing) return;
        this.isDrawing = false;

        if (this.currentPath.length > 0) {
            const idx = songIndex !== null ? songIndex : this.currentIndex;
            const song = this.songs[idx];
            if (song) {
                song.drawPaths = song.drawPaths || [];
                song.drawPaths.push([...this.currentPath]);
            }
            // Also update local drawPaths for library mode
            if (this.mode === 'library') {
                this.drawPaths.push([...this.currentPath]);
            }
            this.currentPath = [];
            this.saveAnnotation(idx);
        }
    },

    getDrawPosition(e, canvas) {
        const targetCanvas = canvas || this.drawCanvas;
        if (!targetCanvas) return { x: 0, y: 0 };
        const rect = targetCanvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    },

    redrawCanvas() {
        if (!this.drawCtx) return;
        this.drawCtx.clearRect(0, 0, this.drawCanvas.width, this.drawCanvas.height);

        this.drawCtx.strokeStyle = '#fbbf24';
        this.drawCtx.lineWidth = 3;
        this.drawCtx.lineCap = 'round';
        this.drawCtx.lineJoin = 'round';

        this.drawPaths.forEach(path => {
            if (path.length < 2) return;
            this.drawCtx.beginPath();
            this.drawCtx.moveTo(path[0].x, path[0].y);
            path.slice(1).forEach(point => this.drawCtx.lineTo(point.x, point.y));
            this.drawCtx.stroke();
        });
    },

    redrawCanvasForSong(index) {
        const song = this.songs[index];
        if (!song || !song.canvas || !song.ctx) return;

        song.ctx.clearRect(0, 0, song.canvas.width, song.canvas.height);
        song.ctx.strokeStyle = '#fbbf24';
        song.ctx.lineWidth = 3;
        song.ctx.lineCap = 'round';
        song.ctx.lineJoin = 'round';

        const paths = song.drawPaths || [];
        paths.forEach(path => {
            if (path.length < 2) return;
            song.ctx.beginPath();
            song.ctx.moveTo(path[0].x, path[0].y);
            path.slice(1).forEach(point => song.ctx.lineTo(point.x, point.y));
            song.ctx.stroke();
        });
    },

    clearDrawing() {
        const idx = this.currentIndex;
        const song = this.songs[idx];

        if (this.mode === 'library') {
            this.drawPaths = [];
            if (this.drawCtx) {
                this.drawCtx.clearRect(0, 0, this.drawCanvas.width, this.drawCanvas.height);
            }
        } else if (song) {
            song.drawPaths = [];
            if (song.ctx && song.canvas) {
                song.ctx.clearRect(0, 0, song.canvas.width, song.canvas.height);
            }
        }
        this.saveAnnotation(idx);
    },

    updateDrawingCanvas(index) {
        const song = this.songs[index];
        if (!song) return;

        if (this.mode === 'library') {
            this.drawPaths = song.drawPaths || [];
            this.redrawCanvas();
        } else {
            // Resize and redraw the canvas for this song
            if (song.canvas) {
                const rect = song.canvas.parentElement.getBoundingClientRect();
                song.canvas.width = rect.width;
                song.canvas.height = rect.height;
                this.redrawCanvasForSong(index);
            }
        }
    },

    /**
     * Toggle draw mode
     */
    toggleDrawMode(enabled) {
        this.drawMode = enabled;
        if (this.mode === 'library') {
            const layer = document.getElementById('annotation-layer');
            if (layer) {
                layer.classList.toggle('draw-mode', enabled);
            }
        } else {
            // Toggle draw mode for all annotation layers in service mode
            document.querySelectorAll('.annotation-layer').forEach(layer => {
                layer.classList.toggle('draw-mode', enabled);
            });
        }

        // Show/hide the floating indicator
        const indicator = document.getElementById('draw-mode-indicator');
        if (indicator) {
            indicator.classList.toggle('active', enabled);
        }

        // Sync the checkbox in settings panel
        const toggle = document.getElementById('draw-mode-toggle');
        if (toggle) {
            toggle.checked = enabled;
        }
    },

    /**
     * Toggle chords visibility
     */
    toggleChords(show) {
        this.showChords = show;
        document.querySelectorAll('.chart-content').forEach(el => {
            el.classList.toggle('lyrics-only', !show);
        });
        this.saveAnnotation();
    },

    /**
     * Toggle two-column layout
     */
    toggleColumns(twoCol) {
        this.twoColumns = twoCol;
        document.querySelectorAll('.chart-content').forEach(el => {
            el.classList.toggle('two-columns', twoCol);
        });
        this.saveAnnotation();
    },

    /**
     * Show swipe hint
     */
    showSwipeHint() {
        if (localStorage.getItem('musicstand_swipe_hint_shown')) return;
        const hint = document.getElementById('swipe-hint');
        if (!hint) return;

        setTimeout(() => {
            hint.classList.add('show');
            setTimeout(() => {
                hint.classList.remove('show');
                localStorage.setItem('musicstand_swipe_hint_shown', '1');
            }, 3000);
        }, 1000);
    },

    /**
     * Open settings panel
     */
    openSettings(songIndex) {
        this.settingsSongIndex = songIndex;
        const song = this.songs[songIndex];

        this.populateKeySelector(song.originalKey, song.currentKey);

        // Load current settings
        document.getElementById('chord-size-value').textContent = song.chordSize || 14;
        document.getElementById('lyric-size-value').textContent = song.lyricSize || 16;

        const showChordsToggle = document.getElementById('show-chords-toggle');
        if (showChordsToggle) showChordsToggle.checked = this.showChords;

        const twoColumnsToggle = document.getElementById('two-columns-toggle');
        if (twoColumnsToggle) twoColumnsToggle.checked = this.twoColumns;

        const drawModeToggle = document.getElementById('draw-mode-toggle');
        if (drawModeToggle) drawModeToggle.checked = this.drawMode;

        this.settingsOverlay.classList.add('active');
        this.settingsPanel.classList.add('active');
    },

    closeSettings() {
        this.settingsOverlay.classList.remove('active');
        this.settingsPanel.classList.remove('active');
        this.settingsSongIndex = null;
    },

    populateKeySelector(originalKey, currentKey) {
        const selector = document.getElementById('key-selector');
        if (!selector) return;

        const keys = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];
        selector.innerHTML = keys.map(key => `
            <button type="button"
                    class="key-btn ${key === currentKey ? 'active' : ''}"
                    onclick="MusicStand.setKey('${key}')">
                ${key}
            </button>
        `).join('');
    },

    setKey(newKey) {
        if (this.settingsSongIndex === null) return;

        const song = this.songs[this.settingsSongIndex];
        song.currentKey = newKey;

        const slide = document.querySelector(`.chart-slide[data-index="${this.settingsSongIndex}"]`);
        if (slide) {
            const display = slide.querySelector('.chord-chart-display');
            const keyLabel = slide.querySelector('.current-key');

            if (display) {
                display.dataset.currentKey = newKey;
                const chartText = atob(display.dataset.chart);
                this.renderChart(display, chartText, song.originalKey, newKey, this.settingsSongIndex);
            }
            if (keyLabel) keyLabel.textContent = newKey;
        }

        document.querySelectorAll('.key-btn').forEach(btn => {
            btn.classList.toggle('active', btn.textContent.trim() === newKey);
        });

        this.saveAnnotation();
    },

    adjustSize(type, delta) {
        if (this.settingsSongIndex === null) return;

        const song = this.songs[this.settingsSongIndex];
        const key = type === 'chord' ? 'chordSize' : 'lyricSize';
        const min = 10, max = 32;

        song[key] = Math.max(min, Math.min(max, (song[key] || (type === 'chord' ? 14 : 16)) + delta));
        document.getElementById(`${type}-size-value`).textContent = song[key];

        this.applySettings(this.settingsSongIndex);
        this.saveAnnotation();
    },

    applySettings(songIndex) {
        const song = this.songs[songIndex];
        if (!song) return;

        const slide = document.querySelector(`.chart-slide[data-index="${songIndex}"]`);
        if (!slide) return;

        const chordSize = song.chordSize || 14;
        const lyricSize = song.lyricSize || 16;

        slide.querySelectorAll('.chord-lyric-pair .chord').forEach(el => {
            el.style.fontSize = chordSize + 'px';
        });
        slide.querySelectorAll('.chord-lyric-pair .lyric').forEach(el => {
            el.style.fontSize = lyricSize + 'px';
        });

        // Apply layout classes
        const chartContent = slide.querySelector('.chart-content');
        if (chartContent) {
            chartContent.classList.toggle('lyrics-only', !this.showChords);
            chartContent.classList.toggle('two-columns', this.twoColumns);
        }
    },

    /**
     * Load all annotations from server or config
     */
    async loadAllAnnotations(preloadedAnnotation) {
        // For library mode with preloaded annotation
        if (this.mode === 'library' && preloadedAnnotation && this.songs[0]) {
            const song = this.songs[0];
            song.chordSize = preloadedAnnotation.chord_size || 14;
            song.lyricSize = preloadedAnnotation.lyric_size || 16;
            song.drawPaths = preloadedAnnotation.drawing_data || [];
            this.drawPaths = song.drawPaths;
            if (preloadedAnnotation.transpose_key) {
                song.currentKey = preloadedAnnotation.transpose_key;
            }
            this.applySettings(0);
            this.redrawCanvas();
            return;
        }

        // For service mode, use preloaded annotations from config
        for (let i = 0; i < this.songs.length; i++) {
            const song = this.songs[i];
            const annotation = song.annotation;

            if (annotation) {
                song.chordSize = annotation.chord_size || 14;
                song.lyricSize = annotation.lyric_size || 16;
                song.drawPaths = annotation.drawing_data || [];
                if (annotation.transpose_key) {
                    song.currentKey = annotation.transpose_key;
                }
            }
            this.applySettings(i);
            this.redrawCanvasForSong(i);
        }
    },

    /**
     * Save annotation to server
     */
    async saveAnnotation(songIndex = null) {
        const idx = songIndex !== null ? songIndex : (this.settingsSongIndex ?? this.currentIndex);
        const song = this.songs[idx];
        if (!song) return;

        const drawPaths = this.mode === 'library' ? this.drawPaths : (song.drawPaths || []);

        const payload = {
            chord_size: song.chordSize || 14,
            lyric_size: song.lyricSize || 16,
            transpose_key: song.currentKey,
            drawing_data: drawPaths,
            show_chords: this.showChords,
            two_columns: this.twoColumns
        };

        if (song.itemId) {
            payload.item_id = song.itemId;
        } else {
            payload.song_id = song.songId;
        }

        try {
            await fetch('/musicstand/api/annotations', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        } catch (e) {
            console.error('Failed to save annotation:', e);
        }
    },

    /**
     * Save current settings as the default for this song
     */
    async saveAsDefault() {
        const idx = this.settingsSongIndex ?? this.currentIndex;
        const song = this.songs[idx];
        if (!song || !song.songId) return;

        const drawPaths = this.mode === 'library' ? this.drawPaths : (song.drawPaths || []);

        const payload = {
            song_id: song.songId,
            is_default: true,
            chord_size: song.chordSize || 14,
            lyric_size: song.lyricSize || 16,
            transpose_key: song.currentKey,
            drawing_data: drawPaths
        };

        try {
            const response = await fetch('/musicstand/api/annotations', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (data.success) {
                this.showToast('Saved as default for this song');
            }
        } catch (e) {
            console.error('Failed to save default:', e);
            this.showToast('Failed to save default');
        }
    },

    /**
     * Load the default settings for this song
     */
    async loadDefault() {
        const idx = this.settingsSongIndex ?? this.currentIndex;
        const song = this.songs[idx];
        if (!song || !song.songId) return;

        try {
            const response = await fetch(`/musicstand/api/annotations?song_id=${song.songId}&default=1`);
            const data = await response.json();

            if (data.success && data.annotation) {
                const ann = data.annotation;
                song.chordSize = ann.chord_size || 14;
                song.lyricSize = ann.lyric_size || 16;
                if (ann.transpose_key) {
                    song.currentKey = ann.transpose_key;
                    this.setKey(ann.transpose_key);
                }
                if (ann.drawing_data) {
                    song.drawPaths = ann.drawing_data;
                    if (this.mode === 'library') {
                        this.drawPaths = ann.drawing_data;
                        this.redrawCanvas();
                    } else {
                        this.redrawCanvasForSong(idx);
                    }
                }

                // Update UI
                document.getElementById('chord-size-value').textContent = song.chordSize;
                document.getElementById('lyric-size-value').textContent = song.lyricSize;
                this.applySettings(idx);

                // Save to current item
                this.saveAnnotation(idx);

                this.showToast('Loaded default settings');
            } else {
                this.showToast('No default saved for this song');
            }
        } catch (e) {
            console.error('Failed to load default:', e);
            this.showToast('Failed to load default');
        }
    },

    /**
     * Show a toast notification
     */
    showToast(message) {
        // Remove existing toast
        const existing = document.querySelector('.toast-notification');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    },

    // ==========================================
    // Song Library & Drag-Drop
    // ==========================================

    /**
     * Load song library for sidebar
     */
    async loadSongLibrary(search = '') {
        const list = document.getElementById('sidebar-song-list');
        if (!list) return;

        try {
            const url = `/musicstand/api/songs?limit=100${search ? `&search=${encodeURIComponent(search)}` : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                this.allSongs = data.songs;
                this.renderSongLibrary();
            }
        } catch (e) {
            console.error('Failed to load songs:', e);
            list.innerHTML = '<div class="sidebar-loading">Failed to load songs</div>';
        }
    },

    /**
     * Render song library in sidebar
     */
    renderSongLibrary() {
        const list = document.getElementById('sidebar-song-list');
        if (!list) return;

        if (this.allSongs.length === 0) {
            list.innerHTML = '<div class="sidebar-loading">No songs found</div>';
            return;
        }

        list.innerHTML = this.allSongs.map(song => `
            <div class="sidebar-song-item"
                 draggable="true"
                 data-song-id="${song.id}"
                 data-song-title="${this.escapeHtml(song.title)}"
                 data-song-artist="${this.escapeHtml(song.artist || '')}"
                 data-song-key="${this.escapeHtml(song.default_key || 'C')}"
                 data-has-chart="${song.has_chart > 0}">
                <div class="sidebar-song-icon ${song.has_chart > 0 ? '' : 'no-chart'}">
                    ${song.has_chart > 0 ? '🎸' : '🎵'}
                </div>
                <div class="sidebar-song-info">
                    <div class="sidebar-song-title">${this.escapeHtml(song.title)}</div>
                    ${song.artist ? `<div class="sidebar-song-artist">${this.escapeHtml(song.artist)}</div>` : ''}
                </div>
                ${song.default_key ? `<span class="sidebar-song-key">${this.escapeHtml(song.default_key)}</span>` : ''}
            </div>
        `).join('');

        // Setup drag events for each song
        list.querySelectorAll('.sidebar-song-item').forEach(item => {
            item.addEventListener('dragstart', (e) => this.handleSongDragStart(e));
            item.addEventListener('dragend', (e) => this.handleSongDragEnd(e));

            // Touch drag support
            item.addEventListener('touchstart', (e) => this.handleSongTouchStart(e), { passive: false });
            item.addEventListener('touchmove', (e) => this.handleSongTouchMove(e), { passive: false });
            item.addEventListener('touchend', (e) => this.handleSongTouchEnd(e));
        });
    },

    /**
     * Search songs
     */
    searchSongs(query) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.loadSongLibrary(query);
        }, 300);
    },

    /**
     * Setup drop zone for song tabs
     */
    setupDropZone() {
        const tabs = document.getElementById('song-tabs');
        if (!tabs) return;

        tabs.addEventListener('dragover', (e) => {
            e.preventDefault();
            tabs.classList.add('drop-hover');
        });

        tabs.addEventListener('dragleave', (e) => {
            tabs.classList.remove('drop-hover');
        });

        tabs.addEventListener('drop', (e) => {
            e.preventDefault();
            tabs.classList.remove('drop-hover');
            tabs.classList.remove('drop-active');

            const songId = e.dataTransfer.getData('text/plain');
            if (songId) {
                this.addSongToService(parseInt(songId));
            }
        });
    },

    /**
     * Handle song drag start
     */
    handleSongDragStart(e) {
        const item = e.target.closest('.sidebar-song-item');
        if (!item) return;

        this.draggedSong = {
            id: parseInt(item.dataset.songId),
            title: item.dataset.songTitle,
            artist: item.dataset.songArtist,
            key: item.dataset.songKey
        };

        e.dataTransfer.setData('text/plain', item.dataset.songId);
        e.dataTransfer.effectAllowed = 'copy';
        item.classList.add('dragging');

        // Show drop zone
        const tabs = document.getElementById('song-tabs');
        if (tabs) tabs.classList.add('drop-active');
    },

    /**
     * Handle song drag end
     */
    handleSongDragEnd(e) {
        const item = e.target.closest('.sidebar-song-item');
        if (item) item.classList.remove('dragging');

        const tabs = document.getElementById('song-tabs');
        if (tabs) {
            tabs.classList.remove('drop-active');
            tabs.classList.remove('drop-hover');
        }

        this.draggedSong = null;
    },

    // Touch drag support
    handleSongTouchStart(e) {
        const item = e.target.closest('.sidebar-song-item');
        if (!item) return;

        this.draggedSong = {
            id: parseInt(item.dataset.songId),
            title: item.dataset.songTitle,
            artist: item.dataset.songArtist,
            key: item.dataset.songKey,
            element: item,
            startY: e.touches[0].clientY,
            startX: e.touches[0].clientX
        };

        this.touchDragTimer = setTimeout(() => {
            item.classList.add('dragging');
            const tabs = document.getElementById('song-tabs');
            if (tabs) tabs.classList.add('drop-active');
            navigator.vibrate && navigator.vibrate(50);
        }, 200);
    },

    handleSongTouchMove(e) {
        if (!this.draggedSong || !this.draggedSong.element) return;

        const touch = e.touches[0];
        const moveX = Math.abs(touch.clientX - this.draggedSong.startX);
        const moveY = Math.abs(touch.clientY - this.draggedSong.startY);

        // If moved significantly, we're dragging
        if (moveX > 10 || moveY > 10) {
            clearTimeout(this.touchDragTimer);
            if (this.draggedSong.element.classList.contains('dragging')) {
                e.preventDefault();

                // Check if over drop zone
                const tabs = document.getElementById('song-tabs');
                if (tabs) {
                    const rect = tabs.getBoundingClientRect();
                    if (touch.clientY >= rect.top && touch.clientY <= rect.bottom &&
                        touch.clientX >= rect.left && touch.clientX <= rect.right) {
                        tabs.classList.add('drop-hover');
                    } else {
                        tabs.classList.remove('drop-hover');
                    }
                }
            }
        }
    },

    handleSongTouchEnd(e) {
        clearTimeout(this.touchDragTimer);

        if (!this.draggedSong) return;

        const element = this.draggedSong.element;
        if (element) element.classList.remove('dragging');

        const tabs = document.getElementById('song-tabs');
        if (tabs && tabs.classList.contains('drop-hover')) {
            this.addSongToService(this.draggedSong.id);
        }

        if (tabs) {
            tabs.classList.remove('drop-active');
            tabs.classList.remove('drop-hover');
        }

        this.draggedSong = null;
    },

    /**
     * Add song to service
     */
    async addSongToService(songId) {
        if (!this.serviceId) return;

        try {
            const response = await fetch('/musicstand/api/songs', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    service_id: this.serviceId,
                    song_id: songId
                })
            });
            const data = await response.json();

            if (data.success) {
                if (data.isPersonal) {
                    // Personal addition - store locally and add to view
                    this.addPersonalSong(data.item);
                    this.showToast(`Added "${data.item.title}" to your view`);
                } else {
                    // Admin addition - refresh the page to show new song
                    this.showToast(`Added "${data.item.title}" for everyone`);
                    // Add to the UI dynamically
                    this.addSongToUI(data.item, false);
                }

                // Close sidebar after adding
                toggleSidebar();
            } else {
                this.showToast(data.error || 'Failed to add song');
            }
        } catch (e) {
            console.error('Failed to add song:', e);
            this.showToast('Failed to add song');
        }
    },

    /**
     * Add song to UI
     */
    addSongToUI(item, isPersonal = false) {
        const index = this.songs.length;

        // Add to songs array
        this.songs.push({
            index: index,
            songId: item.songId,
            itemId: item.itemId || null,
            title: item.title,
            originalKey: item.originalKey || 'C',
            currentKey: item.currentKey || 'C',
            isPersonal: isPersonal
        });

        // Add tab
        const tabs = document.getElementById('song-tabs');
        if (tabs) {
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.className = `song-tab${isPersonal ? ' personal-song' : ''}`;
            tab.dataset.index = index;
            tab.onclick = () => goToSong(index);
            tab.textContent = `${index + 1}. ${item.title}`;
            tabs.appendChild(tab);
        }

        // Add dot
        const dots = document.getElementById('page-indicator');
        if (dots) {
            const dot = document.createElement('div');
            dot.className = 'page-dot';
            dot.dataset.index = index;
            dots.appendChild(dot);
        }

        // We'd need to add the chart slide too, but that requires fetching the chart data
        // For now, show a message that the page needs refresh for full functionality
        if (!isPersonal) {
            setTimeout(() => {
                this.showToast('Refresh page to see full chart');
            }, 2500);
        }
    },

    /**
     * Save personal songs to localStorage
     */
    savePersonalSongs() {
        const personalSongs = this.songs.filter(s => s.isPersonal);
        const key = `musicstand_personal_${this.serviceId}`;
        localStorage.setItem(key, JSON.stringify(personalSongs));
    },

    /**
     * Load personal songs from localStorage
     */
    loadPersonalSongs() {
        const key = `musicstand_personal_${this.serviceId}`;
        const saved = localStorage.getItem(key);
        if (!saved) return;

        try {
            const personalSongs = JSON.parse(saved);
            personalSongs.forEach(song => {
                // Check if already in list
                const exists = this.songs.some(s => s.songId === song.songId);
                if (!exists) {
                    this.addSongToUI(song, true);
                }
            });
        } catch (e) {
            console.error('Failed to load personal songs:', e);
        }
    },

    /**
     * Add personal song and save
     */
    addPersonalSong(item) {
        this.addSongToUI(item, true);
        this.savePersonalSongs();
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
};

// Global functions
function goToSong(index) { MusicStand.goToSong(index); }
function openSettings(index) { MusicStand.openSettings(index); }
function closeSettings() { MusicStand.closeSettings(); }
function adjustSize(type, delta) { MusicStand.adjustSize(type, delta); }

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}

// Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/musicstand/sw.js').catch(err => console.log('SW registration failed:', err));
}
