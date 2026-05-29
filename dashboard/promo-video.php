<?php
// dashboard/promo-video.php
require_once __DIR__ . '/../include/init.php';

$activePage = 'promo-video';
$activeHeader = 'dashboard';
$isRecordMode = isset($_GET['record']);
$bodyClass = 'page-promo-video' . ($isRecordMode ? ' mode-record' : '');
$displayUser = $isLoggedIn ? $user['user_name'] : 'Guest';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promo Video Simulator | BitBalance</title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/promo-video.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <?php if (!$isRecordMode): ?>
        <?php include PROJECT_ROOT . 'views/header.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>
    <?php endif; ?>

    <main class="dashboard-content">
        <section class="promo-simulator-page">
            <div class="promo-layout">
                
                <!-- Left Side: 9:16 Vertical Video Frame Viewport -->
                <div class="promo-phone-viewport">
                    <div class="video-canvas" id="videoCanvas">
                        <!-- Animated Gradient Background -->
                        <div class="video-background"></div>
                        <div class="video-glow-spot glow-1"></div>
                        <div class="video-glow-spot glow-2"></div>
                        <div class="video-glow-spot glow-3"></div>

                        <!-- ==========================================
                             SCENE 1: THE HOOK (0s - 4s)
                             ========================================== -->
                        <div class="video-scene active" id="scene1">
                            <div class="scene-hook-logo">B</div>
                            <div>
                                <h1 class="video-big-text" id="txtS1Title">Tired of boring trackers?</h1>
                                <p class="video-sub-text" id="txtS1Desc">Meet the gamified future of nutrition.</p>
                            </div>
                            <div class="video-brand-footer">
                                <div class="video-brand-footer-mark">B</div>
                                <span>BitBalance</span>
                            </div>
                        </div>

                        <!-- ==========================================
                             SCENE 2: THE SOLUTION (4s - 8s)
                             ========================================== -->
                        <div class="video-scene" id="scene2">
                            <div>
                                <h1 class="video-big-text" id="txtS2Title" style="font-size: 76px; margin-top: 50px;">Track Meals.<br>Earn XP.<br>Level Up.</h1>
                                <p class="video-sub-text" id="txtS2Desc">Every log fuels your streak flame.</p>
                            </div>
                            
                            <!-- Phone inside phone mockup showing XP increase -->
                            <div class="scene-phone-mock">
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <div class="video-brand-footer-mark" style="width:46px; height:46px; font-size:20px; border-radius:12px;">B</div>
                                    <span style="font-size:22px; font-weight:900;">Progress Vault</span>
                                </div>
                                <div class="mock-xp-card">
                                    <div class="mock-xp-row">
                                        <span>Level 12 reached</span>
                                        <span style="color: var(--color-primary);">+10 XP</span>
                                    </div>
                                    <div class="mock-xp-bar"><div class="mock-xp-fill"></div></div>
                                </div>
                                <div style="height: 10px; background: rgba(255,255,255,0.05); border-radius: 999px;"></div>
                            </div>

                            <div class="video-brand-footer">
                                <div class="video-brand-footer-mark">B</div>
                                <span>BitBalance</span>
                            </div>
                        </div>

                        <!-- ==========================================
                             SCENE 3: WRAPPED SHOWCASE (8s - 12s)
                             ========================================== -->
                        <div class="video-scene" id="scene3">
                            <div>
                                <h1 class="video-big-text" id="txtS3Title" style="font-size: 78px; margin-top: 50px;">Your Weekly Wrapped</h1>
                                <p class="video-sub-text" id="txtS3Desc">AI-generated viral stories.</p>
                            </div>

                            <!-- Aura story visual bento card card -->
                            <div class="scene-aura-card bg-aura-carb">
                                <span class="kicker" id="txtS3AuraKicker">Your Food Aura</span>
                                <div>
                                    <h2 id="txtS3AuraTitle">Banh Mi Baron</h2>
                                    <p id="txtS3AuraDesc">Sandwiching a whole week of discipline!</p>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:22px; font-weight:900;">
                                    <span>BitBalance Story</span>
                                    <strong>bitbalance</strong>
                                </div>
                            </div>

                            <div class="video-brand-footer">
                                <div class="video-brand-footer-mark">B</div>
                                <span>BitBalance</span>
                            </div>
                        </div>

                        <!-- ==========================================
                             SCENE 4: OUTRO CTA (12s - 16s)
                             ========================================== -->
                        <div class="video-scene" id="scene4">
                            <!-- Confetti particles burst -->
                            <span class="outro-confetti vc-1"></span>
                            <span class="outro-confetti vc-2"></span>
                            <span class="outro-confetti vc-3"></span>
                            <span class="outro-confetti vc-4"></span>

                            <!-- Floating orbiting badges -->
                            <div class="outro-badge-container">
                                <div class="outro-badge b1" title="Banh Mi Baron">🥖</div>
                                <div class="outro-badge b2" title="Pho Real" style="transform: scale(1.15) translateY(-20px); z-index: 5;">🍜</div>
                                <div class="outro-badge b3" title="Rice Goddess">🍚</div>
                            </div>

                            <div>
                                <h1 class="video-big-text" id="txtS4Title" style="margin: 0 0 10px;">Level up your life.</h1>
                                <p class="video-sub-text" id="txtS4Desc" style="margin: 0;">Start your delicious journey today.</p>
                            </div>

                            <button class="video-btn-cta" id="txtS4CtaBtn">Join Now</button>

                            <div class="video-brand-footer" style="margin-top: 40px;">
                                <div class="video-brand-footer-mark">B</div>
                                <span>BitBalance</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Right Side: Simulator Control Panel -->
                <div class="promo-panel-card">
                    <div class="promo-panel-header">
                        <h2><i class="fa-solid fa-video"></i> Video Promo Simulator</h2>
                        <p>Play, toggle languages, or jump to specific scenes to **record your screen** at high resolution!</p>
                    </div>

                    <!-- Visual video seek timeline bar -->
                    <div class="simulator-time-slider">
                        <div class="simulator-time-progress" id="simProgress"></div>
                    </div>

                    <!-- Simulator Controls -->
                    <div class="simulator-controls-row">
                        <button class="story-btn-primary" id="btnSimPlay">
                            <i class="fa-solid fa-pause" id="iconPlay"></i> <span id="txtPlayBtn">Pause</span>
                        </button>
                        <button class="story-btn-secondary" id="btnSimReplay">
                            <i class="fa-solid fa-rotate-left"></i> Replay
                        </button>

                        <!-- Language Toggle -->
                        <div class="story-lang-toggle" style="margin-left: auto;">
                            <button class="story-lang-btn active" id="btnLangEn">EN</button>
                            <button class="story-lang-btn" id="btnLangVi">VN</button>
                        </div>
                    </div>

                    <!-- Export Video Button -->
                    <div class="simulator-export-row">
                        <button class="story-btn-export" id="btnExportVideo">
                            <i class="fa-solid fa-download"></i> Export Video (.webm)
                        </button>
                        <p class="export-hint">Records the animation as a 1080×1920 video file. Chrome will ask you to share this tab — just click <strong>Share</strong>.</p>
                    </div>

                    <!-- Scene Seekers -->
                    <div class="simulator-scene-indicators">
                        <div class="scene-indicator-row active" data-scene="1" data-start="0">
                            <span>Scene 1: The Hook (Hook)</span>
                            <span class="scene-time-badge">0s - 4s</span>
                        </div>
                        <div class="scene-indicator-row" data-scene="2" data-start="4000">
                            <span>Scene 2: The Solution (Core)</span>
                            <span class="scene-time-badge">4s - 8s</span>
                        </div>
                        <div class="scene-indicator-row" data-scene="3" data-start="8000">
                            <span>Scene 3: Food Aura (Wrapped)</span>
                            <span class="scene-time-badge">8s - 12s</span>
                        </div>
                        <div class="scene-indicator-row" data-scene="4" data-start="12000">
                            <span>Scene 4: Call to Action (Outro)</span>
                            <span class="scene-time-badge">12s - 16s</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>

    <?php if (!$isRecordMode): ?>
        <?php include PROJECT_ROOT . 'views/footer.php'; ?>
    <?php endif; ?>

    <!-- Standalone simulator logic script -->
    <script>
        const PromoSimulator = {
            isPlaying: true,
            elapsedTime: 0,
            duration: 16000, // 16 seconds total video
            intervalTime: 100,
            timer: null,
            currentLang: 'en',
            isRecording: false,
            currentScene: 1,

            // Localization map for advertisement copy
            copy: {
                en: {
                    s1_title: "Tired of boring trackers?",
                    s1_desc: "Meet the gamified future of nutrition.",
                    s2_title: "Track Meals.<br>Earn XP.<br>Level Up.",
                    s2_desc: "Every log fuels your streak flame.",
                    s3_title: "Your Weekly Wrapped",
                    s3_desc: "AI-generated viral stories.",
                    s3_aura_kicker: "Your Food Aura",
                    s3_aura_title: "Banh Mi Baron",
                    s3_aura_desc: "Sandwiching a whole week of discipline!",
                    s4_title: "Level up your life.",
                    s4_desc: "Start your delicious journey today.",
                    s4_cta: "Join Now",
                    pause: "Pause",
                    play: "Play"
                },
                vi: {
                    s1_title: "Chán ngấy đếm calo khô khan?",
                    s1_desc: "Chào mừng bạn đến với kỷ nguyên gamified!",
                    s2_title: "Log Bữa Ăn.<br>Nhận Điểm XP.<br>Tăng Cấp Độ.",
                    s2_desc: "Mỗi miếng cắn thắp sáng ngọn lửa streak.",
                    s3_title: "Tổng Kết Tuần Của Bạn",
                    s3_desc: "Story sinh động sẵn sàng lên sóng.",
                    s3_aura_kicker: "Hào Quang Ẩm Thực",
                    s3_aura_title: "Béo Ngậy Trà Sữa",
                    s3_aura_desc: "Hào quang của sự ngọt ngào & kiên trì!",
                    s4_title: "Nâng cấp phong cách sống.",
                    s4_desc: "Bắt đầu hành trình dinh dưỡng vui vẻ ngay.",
                    s4_cta: "Tham Gia Ngay",
                    pause: "Tạm dừng",
                    play: "Tiếp tục"
                }
            },

            init() {
                this.bindEvents();
                this.applyLocalization();
                this.start();
            },

            bindEvents() {
                const btnPlay = document.getElementById('btnSimPlay');
                const btnReplay = document.getElementById('btnSimReplay');
                const btnLangEn = document.getElementById('btnLangEn');
                const btnLangVi = document.getElementById('btnLangVi');
                const btnExport = document.getElementById('btnExportVideo');
                const indicators = document.querySelectorAll('.scene-indicator-row');

                btnPlay.addEventListener('click', () => this.togglePlay());
                btnReplay.addEventListener('click', () => this.replay());

                btnLangEn.addEventListener('click', () => this.switchLang('en'));
                btnLangVi.addEventListener('click', () => this.switchLang('vi'));

                if (btnExport) {
                    btnExport.addEventListener('click', () => this.exportVideo());
                }

                indicators.forEach(row => {
                    row.addEventListener('click', () => {
                        const scene = parseInt(row.getAttribute('data-scene'));
                        const startMs = parseInt(row.getAttribute('data-start'));
                        this.jumpToScene(scene, startMs);
                    });
                });
            },

            start() {
                this.isPlaying = true;
                this.updatePlayBtnState();
                
                clearInterval(this.timer);
                this.timer = setInterval(() => {
                    if (!this.isPlaying) return;

                    this.elapsedTime += this.intervalTime;
                    if (this.elapsedTime >= this.duration) {
                        this.elapsedTime = 0; // Loop video
                    }

                    this.updateTimeProgress();
                    this.checkSceneTransitions();
                }, this.intervalTime);
            },

            togglePlay() {
                this.isPlaying = !this.isPlaying;
                this.updatePlayBtnState();
            },

            updatePlayBtnState() {
                const icon = document.getElementById('iconPlay');
                const txt = document.getElementById('txtPlayBtn');
                const labels = this.copy[this.currentLang];

                if (this.isPlaying) {
                    icon.className = 'fa-solid fa-pause';
                    txt.innerText = labels.pause;
                } else {
                    icon.className = 'fa-solid fa-play';
                    txt.innerText = labels.play;
                }
            },

            replay() {
                this.elapsedTime = 0;
                this.start();
            },

            switchLang(lang) {
                if (this.currentLang === lang) return;
                this.currentLang = lang;

                document.getElementById('btnLangEn').classList.toggle('active', lang === 'en');
                document.getElementById('btnLangVi').classList.toggle('active', lang === 'vi');

                this.applyLocalization();
                this.updatePlayBtnState();
            },

            applyLocalization() {
                const text = this.copy[this.currentLang];

                document.getElementById('txtS1Title').innerText = text.s1_title;
                document.getElementById('txtS1Desc').innerText = text.s1_desc;

                document.getElementById('txtS2Title').innerHTML = text.s2_title;
                document.getElementById('txtS2Desc').innerText = text.s2_desc;

                document.getElementById('txtS3Title').innerText = text.s3_title;
                document.getElementById('txtS3Desc').innerText = text.s3_desc;
                document.getElementById('txtS3AuraKicker').innerText = text.s3_aura_kicker;
                document.getElementById('txtS3AuraTitle').innerText = text.s3_aura_title;
                document.getElementById('txtS3AuraDesc').innerText = text.s3_aura_desc;

                // Alternate Aura background in scene 3 based on language to show custom varieties!
                const auraCard = document.querySelector('.scene-aura-card');
                if (this.currentLang === 'vi') {
                    auraCard.className = 'scene-aura-card bg-aura-protein';
                } else {
                    auraCard.className = 'scene-aura-card bg-aura-carb';
                }

                document.getElementById('txtS4Title').innerText = text.s4_title;
                document.getElementById('txtS4Desc').innerText = text.s4_desc;
                document.getElementById('txtS4CtaBtn').innerText = text.s4_cta;
            },

            updateTimeProgress() {
                const pct = (this.elapsedTime / this.duration) * 100;
                document.getElementById('simProgress').style.width = pct + '%';
            },

            checkSceneTransitions() {
                let activeScene = 1;
                
                if (this.elapsedTime >= 12000) {
                    activeScene = 4;
                } else if (this.elapsedTime >= 8000) {
                    activeScene = 3;
                } else if (this.elapsedTime >= 4000) {
                    activeScene = 2;
                }

                if (this.currentScene !== activeScene) {
                    this.setActiveScene(activeScene);
                }
            },

            setActiveScene(sceneIndex) {
                this.currentScene = sceneIndex;

                // Toggle scenes active classes
                for (let i = 1; i <= 4; i++) {
                    const sceneEl = document.getElementById(`scene${i}`);
                    sceneEl.classList.toggle('active', i === sceneIndex);
                }

                // Toggle indicator lists
                const rows = document.querySelectorAll('.scene-indicator-row');
                rows.forEach(row => {
                    const s = parseInt(row.getAttribute('data-scene'));
                    row.classList.toggle('active', s === sceneIndex);
                });
            },

            jumpToScene(sceneIndex, startMs) {
                this.isPlaying = false; // Pause on click
                this.elapsedTime = startMs;
                this.updateTimeProgress();
                this.setActiveScene(sceneIndex);
                this.updatePlayBtnState();
            },

            /**
             * Exports the video animation as a .webm file.
             * Uses getDisplayMedia to capture the tab, then crops to exactly the
             * video canvas area using an offscreen <canvas>, producing a clean
             * 1080×1920 output file.
             *
             * Flow: scale canvas to fit viewport → capture tab → crop each
             * frame via canvas → MediaRecorder → auto-download .webm
             */
            async exportVideo() {
                if (this.isRecording) return;

                const exportBtn = document.getElementById('btnExportVideo');
                const originalHTML = exportBtn.innerHTML;
                const viewport = document.querySelector('.promo-phone-viewport');
                const savedViewportStyle = viewport.style.cssText;

                try {
                    this.isRecording = true;

                    // 1 — Enter record mode (hides sidebar/header/footer/panel)
                    document.body.classList.add('mode-record');

                    // 2 — Scale the 1080×1920 canvas to fit ENTIRELY within browser window
                    //     so getDisplayMedia can see the full animation (not just the top).
                    var vw = window.innerWidth;
                    var vh = window.innerHeight;
                    var scale = Math.min(vw / 1080, vh / 1920) * 0.92;
                    var scaledW = 1080 * scale;
                    var scaledH = 1920 * scale;

                    viewport.style.cssText =
                        'transform: scale(' + scale + ');' +
                        'transform-origin: top left;' +
                        'position: fixed;' +
                        'left: ' + ((vw - scaledW) / 2) + 'px;' +
                        'top: ' + ((vh - scaledH) / 2) + 'px;' +
                        'width: 1080px; height: 1920px;' +
                        'border: none; box-shadow: none; border-radius: 0; padding: 0; margin: 0;';

                    // Let CSS settle
                    await new Promise(function(r) { setTimeout(r, 400); });

                    // 3 — Show instruction overlay
                    var overlay = document.createElement('div');
                    overlay.id = 'recordOverlay';
                    overlay.innerHTML =
                        '<div style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.85);color:#fff;font-family:Inter,sans-serif;text-align:center;padding:40px;">' +
                        '<div>' +
                        '<p style="font-size:28px;font-weight:800;margin-bottom:16px;">\ud83d\udcf9 Ready to record</p>' +
                        '<p style="font-size:18px;opacity:.7;">Chrome will ask you to share <strong>this tab</strong>.<br>Click <strong>"Share"</strong> and recording starts automatically.</p>' +
                        '</div></div>';
                    document.body.appendChild(overlay);

                    await new Promise(function(r) { setTimeout(r, 200); });

                    // 4 — Request tab capture
                    var stream = await navigator.mediaDevices.getDisplayMedia({
                        video: {
                            displaySurface: 'browser',
                            frameRate: { ideal: 60, max: 60 }
                        },
                        audio: false,
                        preferCurrentTab: true,
                        selfBrowserSurface: 'include'
                    });

                    // Remove instruction overlay immediately
                    overlay.remove();

                    // 5 — Feed the captured stream into a hidden <video> for frame access
                    var srcVideo = document.createElement('video');
                    srcVideo.srcObject = stream;
                    srcVideo.muted = true;
                    srcVideo.playsInline = true;
                    srcVideo.style.cssText = 'position:fixed;top:-9999px;left:-9999px;pointer-events:none;opacity:0;';
                    document.body.appendChild(srcVideo);
                    await srcVideo.play();

                    // Wait for stream to produce actual frames
                    await new Promise(function(r) { setTimeout(r, 500); });

                    // 6 — Calculate crop coordinates
                    //     The stream's native resolution may differ from CSS pixels (Retina DPR).
                    var trackSettings = stream.getVideoTracks()[0].getSettings();
                    var streamW = trackSettings.width  || vw;
                    var streamH = trackSettings.height || vh;

                    // Ratio between stream pixels and CSS viewport pixels
                    var ratioX = streamW / vw;
                    var ratioY = streamH / vh;

                    // getBoundingClientRect gives CSS coordinates (post-transform)
                    var rect = viewport.getBoundingClientRect();
                    var srcX = Math.round(rect.left * ratioX);
                    var srcY = Math.round(rect.top  * ratioY);
                    var srcW = Math.round(rect.width  * ratioX);
                    var srcH = Math.round(rect.height * ratioY);

                    // Clamp to stream bounds
                    srcX = Math.max(0, srcX);
                    srcY = Math.max(0, srcY);
                    if (srcX + srcW > streamW) srcW = streamW - srcX;
                    if (srcY + srcH > streamH) srcH = streamH - srcY;

                    // 7 — Create offscreen processing canvas at target resolution
                    var cropCanvas = document.createElement('canvas');
                    cropCanvas.width  = 1080;
                    cropCanvas.height = 1920;
                    var ctx = cropCanvas.getContext('2d');

                    // Fill with black initially
                    ctx.fillStyle = '#0f172a';
                    ctx.fillRect(0, 0, 1080, 1920);

                    // 8 — Frame loop: crop video area → draw to 1080×1920 canvas
                    var rafId;
                    function drawFrame() {
                        if (srcVideo.readyState >= srcVideo.HAVE_CURRENT_DATA) {
                            ctx.drawImage(srcVideo, srcX, srcY, srcW, srcH, 0, 0, 1080, 1920);
                        }
                        rafId = requestAnimationFrame(drawFrame);
                    }
                    drawFrame();

                    // 9 — Capture stream from the cropped canvas at 60fps
                    var outputStream = cropCanvas.captureStream(60);

                    // 10 — Setup MediaRecorder on the cropped output stream
                    var mimeType = 'video/webm;codecs=vp9';
                    if (!MediaRecorder.isTypeSupported(mimeType)) {
                        mimeType = 'video/webm;codecs=vp8';
                    }
                    if (!MediaRecorder.isTypeSupported(mimeType)) {
                        mimeType = 'video/webm';
                    }

                    var recorder = new MediaRecorder(outputStream, {
                        mimeType: mimeType,
                        videoBitsPerSecond: 10000000 // 10 Mbps
                    });

                    var chunks = [];
                    recorder.ondataavailable = function(e) {
                        if (e.data && e.data.size > 0) chunks.push(e.data);
                    };

                    var self = this;

                    recorder.onstop = function() {
                        // Clean up frame loop + capture
                        cancelAnimationFrame(rafId);
                        stream.getTracks().forEach(function(t) { t.stop(); });
                        srcVideo.remove();

                        // Build downloadable blob
                        var blob = new Blob(chunks, { type: mimeType });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'bitbalance-promo-' + self.currentLang + '-' + Date.now() + '.webm';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        // Restore everything
                        viewport.style.cssText = savedViewportStyle;
                        document.body.classList.remove('mode-record');
                        exportBtn.innerHTML = originalHTML;
                        exportBtn.disabled = false;
                        self.isRecording = false;
                    };

                    // Handle user stopping share via Chrome's built-in stop button
                    stream.getVideoTracks()[0].onended = function() {
                        if (recorder.state !== 'inactive') {
                            recorder.stop();
                        }
                    };

                    // 11 — Reset animation to Scene 1 and start playback
                    this.elapsedTime = 0;
                    this.setActiveScene(1);
                    this.isPlaying = true;
                    this.updatePlayBtnState();

                    // Small delay to render first frame before recording starts
                    await new Promise(function(r) { setTimeout(r, 300); });

                    // 12 — Start recording!
                    recorder.start(100);

                    exportBtn.innerHTML = '<i class="fa-solid fa-circle fa-beat" style="color:#ef4444;"></i> Recording...';
                    exportBtn.disabled = true;

                    // 13 — Auto-stop after the full animation duration + small buffer
                    setTimeout(function() {
                        if (recorder.state !== 'inactive') {
                            recorder.stop();
                        }
                    }, self.duration + 800);

                } catch (err) {
                    console.error('Export cancelled or failed:', err);

                    // Clean up on error
                    var leftover = document.getElementById('recordOverlay');
                    if (leftover) leftover.remove();

                    viewport.style.cssText = savedViewportStyle;
                    document.body.classList.remove('mode-record');
                    exportBtn.innerHTML = originalHTML;
                    exportBtn.disabled = false;
                    this.isRecording = false;
                }
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            PromoSimulator.init();
        });
    </script>
</body>
</html>
