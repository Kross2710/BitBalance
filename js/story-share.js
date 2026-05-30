/**
 * js/story-share.js
 * BitBalance Instagram-Style Interactive Story Share feature.
 * Gamified 3D Tactile design system with Spotify-inspired multi-slide narrative and Gemini AI captions.
 */

const BitBalanceStory = {
    // State
    currentSlide: 0,
    totalSlides: 5,
    slideDuration: 5000, // 5 seconds per slide
    slideTimer: null,
    progressInterval: null,
    elapsedTime: 0,
    isPaused: false,
    currentLang: 'en',
    storyData: null,
    html2canvasLoaded: false,

    // Static translations for UI labels inside the story
    translations: {
        en: {
            brand: "BitBalance",
            wrapped: "Weekly Wrapped",
            kicker_aura: "Your Food Aura",
            kicker_badge: "Achievement Unlocked",
            kicker_streak: "Discipline Burning",
            kicker_leaderboard: "Leaderboard Menace",
            kicker_spotify: "Diet & Beats",
            kicker_summary: "Your Wrapped Summary",
            footer_text: "Track meals. Earn XP. Level up.",
            logged_title: "Foods Logged",
            streak_title: "Logging Streak",
            leaderboard_title: "Leaderboard Rank",
            favorite_title: "Favorite Food",
            downloading: "Generating PNG...",
            download_btn: "Download Story",
            share_btn: "Share Story",
            sharing_err: "Sharing not supported, downloading instead!"
        },
        vi: {
            brand: "BitBalance",
            wrapped: "Tổng Kết Tuần",
            kicker_aura: "Hào Quang Dinh Dưỡng",
            kicker_badge: "Mở Khóa Huy Hiệu",
            kicker_streak: "Kỷ Luật Rực Lửa",
            kicker_leaderboard: "Đỉnh Cao Xếp Hạng",
            kicker_spotify: "Khớp Nhạc & Thực Đơn",
            kicker_summary: "Tóm Tắt Tuần Qua",
            footer_text: "Ghi chép món ăn. Nhận XP. Lên cấp.",
            logged_title: "Món Đã Ăn",
            streak_title: "Chuỗi Streak",
            leaderboard_title: "Hạng Bạn Bè",
            favorite_title: "Món Hảo Vị",
            downloading: "Đang vẽ ảnh PNG...",
            download_btn: "Tải Story Về",
            share_btn: "Chia Sẻ Story",
            sharing_err: "Trình duyệt không hỗ trợ chia sẻ trực tiếp, đang tải về máy!"
        }
    },

    // Initialize DOM and event listeners
    init() {
        this.currentLang = this.resolveGlobalLang();
        this.injectModalMarkup();
        this.bindEvents();
        this.loadHtml2Canvas();
        this.maybeAutoOpen();
    },

    // Deep-link support: pages without their own trigger (e.g. Diet & Beats)
    // link here with ?story=open to open the Weekly Wrapped immediately.
    maybeAutoOpen() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('story') === 'open' || window.location.hash === '#weekly-wrapped') {
            this.open();
            // Strip the trigger from the URL so a refresh/back doesn't re-open it.
            window.history.replaceState(null, '', window.location.pathname);
        }
    },

    // Resolve the site-wide language from <html lang> (set by the global i18n locale)
    resolveGlobalLang() {
        const lang = (document.documentElement.lang || 'en').toLowerCase();
        return lang.indexOf('vi') === 0 ? 'vi' : 'en';
    },

    // Dynamically load html2canvas library
    loadHtml2Canvas() {
        if (typeof html2canvas !== 'undefined') {
            this.html2canvasLoaded = true;
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
        script.async = true;
        script.onload = () => {
            this.html2canvasLoaded = true;
        };
        document.head.appendChild(script);
    },

    // Injects the hidden Export DOM and the Overlay Modal DOM
    injectModalMarkup() {
        if (document.getElementById('storyModal')) return;

        // Modal Markup
        const modalHtml = `
        <div id="storyModal" class="story-modal">
            <div class="story-modal-container">
                <button id="storyBtnClose" class="story-btn-close" aria-label="Close modal">&times;</button>
                
                <!-- Main viewport scaled down for responsive preview in-app -->
                <div class="story-preview-viewport">
                    <div id="storyExportRoot">
                        <!-- Top Progress Bar Indicators (Populated dynamically) -->
                        <div class="story-progress-indicators"></div>

                        <!-- Confetti decorations -->
                        <span class="story-confetti conf-c1"></span>
                        <span class="story-confetti conf-c2"></span>
                        <span class="story-confetti conf-c3"></span>
                        <span class="story-confetti conf-c4"></span>

                        <!-- Click Tap Areas to transition slides -->
                        <div class="story-tap-areas">
                            <div class="story-tap-left" id="storyTapLeft" title="Previous Slide"></div>
                            <div class="story-tap-right" id="storyTapRight" title="Next Slide"></div>
                        </div>

                        <!-- Header brand lockup -->
                        <header class="story-header">
                            <div class="story-brand">
                                <div class="story-brand-icon">B</div>
                                <span id="lblBrandName">BitBalance</span>
                            </div>
                            <div class="story-pill" id="lblWrappedTitle">Weekly Wrapped</div>
                        </header>

                        <!-- ==========================================
                             SLIDE 1: AURA
                             ========================================== -->
                        <div class="story-slide active" id="slideAura">
                            <div class="story-hero-card">
                                <span class="story-kicker" id="lblKickerAura">Your Food Aura</span>
                                <h1 class="story-title" id="lblAuraTitle">Vibrant Vibe</h1>
                                <p class="story-desc" id="lblAuraDesc">Thinking about what you logged...</p>
                            </div>
                            <div class="story-footer">
                                <span id="lblFooter1">Track meals. Earn XP. Level up.</span>
                                <strong>bitbalance</strong>
                            </div>
                        </div>

                        <!-- ==========================================
                             SLIDE 2: TOP FOOD
                             ========================================== -->
                        <div class="story-slide" id="slideTopFood">
                            <div class="story-hero-card">
                                <span class="story-kicker" id="lblKickerBadge">Achievement Unlocked</span>
                                <h1 class="story-title" id="lblBadgeTitle">Banh Mi Baron</h1>
                                <p class="story-desc" id="lblBadgeDesc">Diacritics are optional. Devotion is not.</p>
                                <div class="story-badge-burst">
                                    <i class="fa-solid fa-bread-slice story-badge-icon" id="iconBadge"></i>
                                </div>
                            </div>
                            <div class="story-footer">
                                <span id="lblFooter2">Track meals. Earn XP. Level up.</span>
                                <strong>bitbalance</strong>
                            </div>
                        </div>

                        <!-- ==========================================
                             SLIDE 3: STREAK COOKER
                             ========================================== -->
                        <div class="story-slide" id="slideStreak">
                            <div class="story-hero-card">
                                <span class="story-kicker" id="lblKickerStreak">Discipline Burning</span>
                                <h1 class="story-title" id="lblStreakTitle">On Fire!</h1>
                                <p class="story-desc" id="lblStreakDesc">Keeping the logs hot day after day.</p>
                            </div>
                            <div class="story-streak-center">
                                <div class="story-flame-container">
                                    <i class="fa-solid fa-fire story-flame-icon"></i>
                                </div>
                                <h2 class="story-streak-number" id="lblStreakNumber">14d</h2>
                                <span class="story-streak-label" id="lblStreakCountLabel">Logging Streak</span>
                            </div>
                            <div class="story-footer">
                                <span id="lblFooter3">Track meals. Earn XP. Level up.</span>
                                <strong>bitbalance</strong>
                            </div>
                        </div>

                        <!-- ==========================================
                             SLIDE 4: LEADERBOARD
                             ========================================== -->
                        <div class="story-slide" id="slideLeaderboard">
                            <div class="story-hero-card">
                                <span class="story-kicker" id="lblKickerLeaderboard">Leaderboard Menace</span>
                                <h1 class="story-title" id="lblLeaderboardTitle">Rank #1</h1>
                                <p class="story-desc" id="lblLeaderboardDesc">No one is catching up this week.</p>
                            </div>
                            <div class="story-podium-container">
                                <div class="podium-column second">
                                    <div class="podium-avatar">🥈</div>
                                    <span class="podium-rank">#2</span>
                                    <span class="podium-label">Friends</span>
                                </div>
                                <div class="podium-column first">
                                    <div class="podium-avatar" id="lblUserAvatar">U</div>
                                    <span class="podium-rank">#1</span>
                                    <span class="podium-label">You</span>
                                </div>
                                <div class="podium-column third">
                                    <div class="podium-avatar">🥉</div>
                                    <span class="podium-rank">#3</span>
                                    <span class="podium-label">Friends</span>
                                </div>
                            </div>
                            <div class="story-footer">
                                <span id="lblFooter4">Track meals. Earn XP. Level up.</span>
                                <strong>bitbalance</strong>
                            </div>
                        </div>

                        <!-- ==========================================
                             SLIDE 5 (NEW): DIET & BEATS (SPOTIFY)
                             ========================================== -->
                        <div class="story-slide" id="slideSpotify">
                            <div class="story-hero-card">
                                <span class="story-kicker" id="lblKickerSpotify">Diet & Beats</span>
                                <h1 class="story-title" id="lblSpotifyTitle">Sad Ramen Hours</h1>
                                <p class="story-desc" id="lblSpotifyDesc">Eating instant noodles while sobbing to Taylor Swift. A balanced diet of tears and sodium.</p>
                            </div>
                            <div class="story-spotify-visual">
                                <div class="spotify-card">
                                    <div class="spotify-card-top">
                                        <i class="fa-brands fa-spotify spotify-logo-icon"></i>
                                        <span class="spotify-badge">MAPPED VIBE</span>
                                    </div>
                                    <div class="spotify-card-body">
                                        <div class="spotify-music-info">
                                            <img id="imgSpotifyArt" class="spotify-album-art" crossorigin="anonymous" alt="" style="display:none">
                                            <i class="fa-solid fa-music music-note-icon" id="iconSpotifyNote"></i>
                                            <div>
                                                <strong id="lblSpotifyTrack">All Too Well</strong>
                                                <span id="lblSpotifyArtist">Taylor Swift</span>
                                            </div>
                                        </div>
                                        <div class="spotify-food-info">
                                            <i class="fa-solid fa-utensils food-icon"></i>
                                            <div>
                                                <strong id="lblSpotifyFood">Mì Ly</strong>
                                                <span id="lblSpotifyFoodTime">at 01:45 AM</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="story-footer">
                                <span id="lblFooterSpotify">Track meals. Earn XP. Level up.</span>
                                <strong>bitbalance</strong>
                            </div>
                        </div>

                        <!-- ==========================================
                             SLIDE 6: BENTO SUMMARY
                             ========================================== -->
                        <div class="story-slide" id="slideSummary">
                            <div class="story-bento-grid">
                                <div class="bento-card bento-card-featured">
                                    <div>
                                        <h3 class="bento-title" id="lblArchetypeTitle">Your Dietary Archetype</h3>
                                        <h2 class="bento-value" id="lblArchetypeName">Whey & Trà Sữa</h2>
                                        <div class="bento-archetype-tag" id="lblUserLevel">Lv 12</div>
                                    </div>
                                    <div class="story-xp-bar"><div class="story-xp-fill" id="pbSummaryXp"></div></div>
                                </div>
                                <div class="bento-card">
                                    <h3 class="bento-title" id="lblBentoLogged">Foods Logged</h3>
                                    <h2 class="bento-value" id="lblBentoLoggedVal">32</h2>
                                </div>
                                <div class="bento-card">
                                    <h3 class="bento-title" id="lblBentoStreak">Best Streak</h3>
                                    <h2 class="bento-value" id="lblBentoStreakVal">14d</h2>
                                </div>
                                <div class="bento-card">
                                    <h3 class="bento-title" id="lblBentoFavorite">Favorite Food</h3>
                                    <h2 class="bento-value" id="lblBentoFavoriteVal">Banh Mi</h2>
                                </div>
                                <div class="bento-card">
                                    <h3 class="bento-title" id="lblBentoLeaderboard">Weekly Rank</h3>
                                    <h2 class="bento-value" id="lblBentoLeaderboardVal">#1</h2>
                                </div>
                            </div>
                            <div class="story-footer">
                                <span id="lblFooter5">Track meals. Earn XP. Level up.</span>
                                <strong>bitbalance</strong>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Story Controls Overlay -->
                <div class="story-controls">
                    <div class="story-controls-row">
                        <!-- Actions -->
                        <button class="story-btn-primary" id="btnDownloadStory">
                            <i class="fa-solid fa-download"></i> <span id="txtDownloadBtn">Download Story</span>
                        </button>
                        <button class="story-btn-secondary" id="btnShareStory">
                            <i class="fa-solid fa-share-nodes"></i> <span id="txtShareBtn">Share Story</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    },

    // Bind event listeners to DOM
    bindEvents() {
        const btnOpen = document.getElementById('btnOpenStory');
        if (btnOpen) {
            btnOpen.addEventListener('click', () => this.open());
        }

        const btnClose = document.getElementById('storyBtnClose');
        const storyModal = document.getElementById('storyModal');
        const tapLeft = document.getElementById('storyTapLeft');
        const tapRight = document.getElementById('storyTapRight');
        const btnDownload = document.getElementById('btnDownloadStory');
        const btnShare = document.getElementById('btnShareStory');
        const viewport = document.querySelector('.story-preview-viewport');

        // Modal Close
        btnClose.addEventListener('click', () => this.close());
        storyModal.addEventListener('click', (e) => {
            if (e.target === storyModal) this.close();
        });

        // Navigation
        tapLeft.addEventListener('click', (e) => {
            e.stopPropagation();
            this.prevSlide();
        });
        tapRight.addEventListener('click', (e) => {
            e.stopPropagation();
            this.nextSlide();
        });

        // Long Press to Pause on hold (Tap and hold)
        viewport.addEventListener('mousedown', () => this.pauseCarousel());
        viewport.addEventListener('mouseup', () => this.resumeCarousel());
        viewport.addEventListener('mouseleave', () => this.resumeCarousel());
        
        viewport.addEventListener('touchstart', (e) => {
            this.pauseCarousel();
        }, { passive: true });
        viewport.addEventListener('touchend', () => this.resumeCarousel(), { passive: true });

        // Action Buttons
        btnDownload.addEventListener('click', () => this.exportPng(false));
        btnShare.addEventListener('click', () => this.exportPng(true));
    },

    // Open Modal and start carousel
    open() {
        const modal = document.getElementById('storyModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Lock background scroll

        this.currentSlide = 0;
        this.fetchAndPopulateData();
    },

    // Close Modal and clean timers
    close() {
        const modal = document.getElementById('storyModal');
        modal.classList.remove('active');
        document.body.style.overflow = ''; // Unlock scroll

        this.clearTimers();
    },

    // Fetch dynamic JSON data from server and apply languages
    fetchAndPopulateData() {
        this.clearTimers();
        
        // Show loading state
        document.getElementById('lblAuraTitle').innerText = this.currentLang === 'vi' ? 'Đang phân tích...' : 'Analyzing...';
        document.getElementById('lblAuraDesc').innerText = this.currentLang === 'vi' ? 'Hào quang của bạn đang được hội chẩn bởi AI Coach...' : 'Your nutritional aura is being computed by the AI Coach...';
        
        // Update Static translation labels immediately
        this.applyStaticTranslations();

        fetch('handlers/story_data.php')
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    this.storyData = data;
                    this.populateSlidesContent();
                    this.startCarousel();
                } else {
                    console.error('Error fetching story data:', data.error);
                    alert(data.error || 'Failed to fetch wrapped stats.');
                    this.close();
                }
            })
            .catch(err => {
                console.error('Error fetching wrapped data:', err);
                alert('Connection failed.');
                this.close();
            });
    },

    // Update static labels in the story based on language
    applyStaticTranslations() {
        const text = this.translations[this.currentLang];
        
        document.getElementById('lblBrandName').innerText = text.brand;
        document.getElementById('lblWrappedTitle').innerText = text.wrapped;
        document.getElementById('lblKickerAura').innerText = text.kicker_aura;
        document.getElementById('lblKickerBadge').innerText = text.kicker_badge;
        document.getElementById('lblKickerStreak').innerText = text.kicker_streak;
        document.getElementById('lblKickerLeaderboard').innerText = text.kicker_leaderboard;
        document.getElementById('lblKickerSpotify').innerText = text.kicker_spotify;
        
        document.getElementById('lblFooter1').innerText = text.footer_text;
        document.getElementById('lblFooter2').innerText = text.footer_text;
        document.getElementById('lblFooter3').innerText = text.footer_text;
        document.getElementById('lblFooter4').innerText = text.footer_text;
        document.getElementById('lblFooterSpotify').innerText = text.footer_text;
        document.getElementById('lblFooter5').innerText = text.footer_text;

        document.getElementById('lblStreakCountLabel').innerText = text.streak_title;
        
        document.getElementById('lblArchetypeTitle').innerText = this.currentLang === 'vi' ? 'Hình Mẫu Ẩm Thực' : 'Your Dietary Archetype';
        document.getElementById('lblBentoLogged').innerText = text.logged_title;
        document.getElementById('lblBentoStreak').innerText = text.streak_title;
        document.getElementById('lblBentoFavorite').innerText = text.favorite_title;
        document.getElementById('lblBentoLeaderboard').innerText = text.leaderboard_title;

        document.getElementById('txtDownloadBtn').innerText = text.download_btn;
        document.getElementById('txtShareBtn').innerText = text.share_btn;
    },

    // Filter out display:none slides dynamically
    getActiveSlides() {
        return Array.from(document.querySelectorAll('.story-slide')).filter(slide => slide.style.display !== 'none');
    },

    // Dynamically render top progress bars
    renderProgressBars() {
        const container = document.querySelector('.story-progress-indicators');
        if (!container) return;
        container.innerHTML = '';
        const slides = this.getActiveSlides();
        slides.forEach(() => {
            container.insertAdjacentHTML('beforeend', '<div class="story-progress-bar"><div class="story-progress-fill"></div></div>');
        });
    },

    // Populate data contents into DOM
    populateSlidesContent() {
        const data = this.storyData;
        const lang = this.currentLang;

        // Manage Dynamic Spotify Slide Insertion
        if (data.spotify) {
            this.totalSlides = 6;
            document.getElementById('slideSpotify').style.display = 'flex';
            
            // Populate Spotify Slide Elements
            document.getElementById('lblSpotifyTitle').innerText = data.spotify.archetype;
            document.getElementById('lblSpotifyDesc').innerText = data.spotify.desc;
            document.getElementById('lblSpotifyTrack').innerText = data.spotify.track;
            document.getElementById('lblSpotifyArtist').innerText = data.spotify.artist;
            document.getElementById('lblSpotifyFood').innerText = data.spotify.food;
            // We don't track a per-pairing timestamp — show a label, not a time
            // (this previously read data.spotify.time_str → "at undefined").
            document.getElementById('lblSpotifyFoodTime').innerText = (lang === 'vi' ? 'Món ăn yêu thích' : 'Favorite snack');

            // Real album art, with the music-note icon as a graceful fallback.
            const art = document.getElementById('imgSpotifyArt');
            const note = document.getElementById('iconSpotifyNote');
            if (art && note) {
                if (data.spotify.image) {
                    art.onerror = () => { art.style.display = 'none'; note.style.display = ''; };
                    art.onload = () => { art.style.display = ''; note.style.display = 'none'; };
                    art.src = data.spotify.image;
                } else {
                    art.style.display = 'none';
                    note.style.display = '';
                }
            }
        } else {
            this.totalSlides = 5;
            document.getElementById('slideSpotify').style.display = 'none';
        }

        // Render progress bar counts dynamically
        this.renderProgressBars();

        // Apply Aura Slide Backgrounds based on dynamic archetype name / favorites
        const root = document.getElementById('storyExportRoot');
        root.className = ''; // Reset
        
        // Pick dynamic background based on favorite food tags
        const fav = data.stats.favorite_food.toLowerCase();
        if (fav.includes('rice') || fav.includes('cơm') || fav.includes('bread') || fav.includes('bánh mì') || fav.includes('phở') || fav.includes('pho')) {
            root.classList.add('aura-bg-carb');
        } else if (fav.includes('chicken') || fav.includes('thịt') || fav.includes('whey') || fav.includes('egg') || fav.includes('trứng')) {
            root.classList.add('aura-bg-protein');
        } else {
            root.classList.add('aura-bg-balanced');
        }

        // SLIDE 1: AURA
        document.getElementById('lblAuraTitle').innerText = data.diet_archetype;
        document.getElementById('lblAuraDesc').innerText = data.archetype_desc;

        // SLIDE 2: BADGE / TOP FOOD
        document.getElementById('lblBadgeTitle').innerText = data.badge.name;
        document.getElementById('lblBadgeDesc').innerText = data.slide2_topfood;
        
        // Choose badge icon dynamically
        const icon = document.getElementById('iconBadge');
        icon.className = 'fa-solid story-badge-icon';
        if (data.badge.icon.startsWith('fa-')) {
            icon.classList.add(data.badge.icon);
        } else {
            icon.classList.add('fa-star');
        }

        // SLIDE 3: STREAK
        document.getElementById('lblStreakTitle').innerText = data.slide3_streak;
        document.getElementById('lblStreakDesc').innerText = lang === 'vi' ? 
            `Không bỏ log dù chỉ 1 bữa! Thói quen tuyệt vời.` : 
            `Never skipping a meal. Keep the flame burning!`;
        document.getElementById('lblStreakNumber').innerText = data.stats.streak + 'd';

        // SLIDE 4: LEADERBOARD
        document.getElementById('lblLeaderboardTitle').innerText = lang === 'vi' ? 
            `Hạng ${data.stats.leaderboard_rank} Bảng Tuần!` : 
            `Rank ${data.stats.leaderboard_rank} Weekly!`;
        document.getElementById('lblLeaderboardDesc').innerText = data.slide4_leaderboard;
        document.getElementById('lblUserAvatar').innerText = data.user.username.charAt(0).toUpperCase();

        // SLIDE 6 (INDEX 5): BENTO SUMMARY
        document.getElementById('lblArchetypeName').innerText = data.diet_archetype;
        document.getElementById('lblUserLevel').innerText = `Lv ${data.user.level}`;
        document.getElementById('pbSummaryXp').style.width = data.user.progress_pct + '%';
        document.getElementById('lblBentoLoggedVal').innerText = data.stats.total_foods;
        document.getElementById('lblBentoStreakVal').innerText = data.stats.streak + 'd';
        document.getElementById('lblBentoFavoriteVal').innerText = data.stats.favorite_food;
        document.getElementById('lblBentoLeaderboardVal').innerText = data.stats.leaderboard_rank;
    },

    // Start running the story slideshow
    startCarousel() {
        this.clearTimers();
        this.elapsedTime = 0;
        this.showSlide(this.currentSlide);

        this.progressInterval = setInterval(() => {
            if (this.isPaused) return;

            this.elapsedTime += 100;
            const progressPct = (this.elapsedTime / this.slideDuration) * 100;
            
            this.updateProgressBar(this.currentSlide, progressPct);

            if (this.elapsedTime >= this.slideDuration) {
                this.nextSlide();
            }
        }, 100);
    },

    // Pause slideshow on mouse/touch hold
    pauseCarousel() {
        this.isPaused = true;
    },

    // Resume slideshow
    resumeCarousel() {
        this.isPaused = false;
    },

    // Show a specific slide by index
    showSlide(index) {
        const activeSlides = this.getActiveSlides();
        activeSlides.forEach((slide, idx) => {
            slide.classList.toggle('active', idx === index);
        });

        // Set dark-mode background style on Slide 3 (Streak Cooker) for high-contrast fire effect!
        const root = document.getElementById('storyExportRoot');
        const activeSlide = activeSlides[index];
        if (activeSlide && activeSlide.id === 'slideStreak') {
            root.classList.add('aura-bg-dark');
        } else {
            root.classList.remove('aura-bg-dark');
        }

        // Fill previous progress bars fully, clear next ones
        const progressFills = document.querySelectorAll('.story-progress-fill');
        progressFills.forEach((fill, idx) => {
            if (idx < index) {
                fill.style.width = '100%';
            } else if (idx > index) {
                fill.style.width = '0%';
            }
        });
        
        this.elapsedTime = 0;
    },

    updateProgressBar(index, percent) {
        const progressFills = document.querySelectorAll('.story-progress-fill');
        if (progressFills[index]) {
            progressFills[index].style.width = percent + '%';
        }
    },

    // Navigate to next slide
    nextSlide() {
        if (this.currentSlide < this.totalSlides - 1) {
            this.currentSlide++;
            this.showSlide(this.currentSlide);
        } else {
            // Loop back to the first slide
            this.currentSlide = 0;
            this.showSlide(0);
        }
    },

    // Navigate to previous slide
    prevSlide() {
        if (this.currentSlide > 0) {
            this.currentSlide--;
            this.showSlide(this.currentSlide);
        } else {
            // Loop to the last slide
            this.currentSlide = this.totalSlides - 1;
            this.showSlide(this.currentSlide);
        }
    },

    clearTimers() {
        clearInterval(this.progressInterval);
        clearTimeout(this.slideTimer);
    },

    // Client-side HTML to PNG export using html2canvas
    exportPng(triggerShare = false) {
        if (!this.html2canvasLoaded) {
            alert('Loading rendering engine, please try again in a second...');
            return;
        }

        const downloadBtn = document.getElementById('btnDownloadStory');
        const shareBtn = document.getElementById('btnShareStory');
        const originalDownloadTxt = downloadBtn.innerHTML;
        const originalShareTxt = shareBtn.innerHTML;

        const loadingTxt = this.translations[this.currentLang].downloading;
        downloadBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${loadingTxt}`;
        shareBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${loadingTxt}`;
        downloadBtn.disabled = true;
        shareBtn.disabled = true;

        this.pauseCarousel();

        // Target the standard 1080x1920 export root container
        const storyElement = document.getElementById('storyExportRoot');

        // Capture canvas at high resolution
        html2canvas(storyElement, {
            width: 1080,
            height: 1920,
            scale: 1, // Capture standard exact dimensions
            backgroundColor: null,
            useCORS: true,
            logging: false,
            // Ensure proper font and symbol rendering
            onclone: (clonedDoc) => {
                const clonedEl = clonedDoc.getElementById('storyExportRoot');
                // Ensure correct visibility of the active slide in the clone
                const activeSlides = this.getActiveSlides();
                const activeSlideId = activeSlides[this.currentSlide].id;
                clonedEl.querySelectorAll('.story-slide').forEach(slide => {
                    if (slide.id === activeSlideId) {
                        slide.style.opacity = '1';
                        slide.style.visibility = 'visible';
                        slide.style.zIndex = '10';
                    } else {
                        slide.style.opacity = '0';
                        slide.style.visibility = 'hidden';
                        slide.style.zIndex = '1';
                    }
                });
                
                // Keep scaled 1:1 in capturing stage
                clonedEl.style.transform = 'none';
                clonedEl.style.width = '1080px';
                clonedEl.style.height = '1920px';
            }
        }).then(canvas => {
            canvas.toBlob(blob => {
                const fileName = `bitbalance-${this.currentLang}-story-${Date.now()}.png`;

                if (triggerShare && navigator.canShare) {
                    const file = new File([blob], fileName, { type: 'image/png' });
                    
                    if (navigator.canShare({ files: [file] })) {
                        navigator.share({
                            files: [file],
                            title: 'My BitBalance Wrapped Story',
                            text: 'Check out my weekly nutrition wrapped!'
                        }).then(() => {
                            this.resetButtons(downloadBtn, shareBtn, originalDownloadTxt, originalShareTxt);
                        }).catch(err => {
                            console.error('Sharing failed:', err);
                            this.fallbackDownload(blob, fileName);
                            this.resetButtons(downloadBtn, shareBtn, originalDownloadTxt, originalShareTxt);
                        });
                    } else {
                        alert(this.translations[this.currentLang].sharing_err);
                        this.fallbackDownload(blob, fileName);
                        this.resetButtons(downloadBtn, shareBtn, originalDownloadTxt, originalShareTxt);
                    }
                } else {
                    this.fallbackDownload(blob, fileName);
                    this.resetButtons(downloadBtn, shareBtn, originalDownloadTxt, originalShareTxt);
                }
            }, 'image/png');
        }).catch(err => {
            console.error('Rendering failed:', err);
            alert('Failed to generate image.');
            this.resetButtons(downloadBtn, shareBtn, originalDownloadTxt, originalShareTxt);
        });
    },

    fallbackDownload(blob, fileName) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        
        // Clean up memory
        setTimeout(() => {
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, 100);
    },

    resetButtons(btnDown, btnShare, txtDown, txtShare) {
        btnDown.innerHTML = txtDown;
        btnShare.innerHTML = txtShare;
        btnDown.disabled = false;
        btnShare.disabled = false;
        this.resumeCarousel();
    }
};

// Initialize after document is ready
document.addEventListener('DOMContentLoaded', () => {
    BitBalanceStory.init();
});
