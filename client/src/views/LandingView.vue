<script setup>
// Public welcome / app intro for logged-out visitors (ported from PHP index.php).
// Restyled into the Vue design language: dark / flat / compact, Font Awesome icons
// (no emoji), no 3D tactile shadows. Logged-in users never see this — the router
// guard forwards them to /dashboard.
import { RouterLink } from 'vue-router';
import LocaleSwitcher from '../components/LocaleSwitcher.vue';

const year = new Date().getFullYear();
</script>

<template>
  <div class="landing">
    <header class="lp-header">
      <div class="lp-header-inner">
        <RouterLink to="/" class="brand" aria-label="BitBalance">
          <span class="mark">B</span>
          <span class="wordmark">BitBalance</span>
        </RouterLink>
        <div class="lp-header-actions">
          <LocaleSwitcher />
          <RouterLink to="/login" class="signin-link">{{ $t('landing.nav.sign_in') }}</RouterLink>
        </div>
      </div>
    </header>

    <main class="lp-main">
      <!-- Hero -->
      <section class="card hero">
        <span class="badge"><i class="fa-solid fa-bolt" /> {{ $t('landing.hero.badge') }}</span>
        <h1 class="hero-title">
          {{ $t('landing.hero.title') }}
          <span class="accent-line">{{ $t('landing.hero.title_accent') }}</span>
        </h1>
        <p class="hero-sub">{{ $t('landing.hero.subtitle') }}</p>
        <div class="hero-actions">
          <RouterLink to="/signup" class="btn-primary">
            <i class="fa-solid fa-rocket" /> {{ $t('landing.hero.cta_primary') }}
          </RouterLink>
          <RouterLink to="/login" class="btn-secondary">{{ $t('landing.hero.cta_secondary') }}</RouterLink>
        </div>
        <div class="hero-proof">
          <span class="proof"><i class="fa-solid fa-camera" /> {{ $t('landing.hero.proof.ai') }}</span>
          <span class="proof"><i class="fa-solid fa-fire" /> {{ $t('landing.hero.proof.streaks') }}</span>
          <span class="proof"><i class="fa-solid fa-user-group" /> {{ $t('landing.hero.proof.leaderboards') }}</span>
        </div>
      </section>

      <!-- Feature showcase -->
      <section class="showcase">
        <div class="section-head">
          <strong class="section-title">
            {{ $t('landing.showcase.title') }} <span class="accent-line">{{ $t('landing.showcase.title_accent') }}</span>
          </strong>
          <p class="section-sub">{{ $t('landing.showcase.subtitle') }}</p>
        </div>

        <div class="showcase-grid">
          <!-- AI logging -->
          <article class="card show-card">
            <div class="demo demo-ai">
              <span class="demo-photo"><i class="fa-solid fa-camera" /></span>
              <i class="fa-solid fa-arrow-right demo-arrow" />
              <span class="demo-result">
                <span class="demo-food"><i class="fa-solid fa-bowl-food" /> {{ $t('landing.showcase.ai.demo_food') }}</span>
                <span class="demo-cal">{{ $t('landing.showcase.ai.demo_cal') }}</span>
              </span>
            </div>
            <span class="tag tag-ai">{{ $t('landing.showcase.ai.tag') }}</span>
            <strong class="show-title">{{ $t('landing.showcase.ai.title') }}</strong>
            <p class="show-body">{{ $t('landing.showcase.ai.body') }}</p>
          </article>

          <!-- Gamification -->
          <article class="card show-card">
            <div class="demo demo-game">
              <span class="demo-level">{{ $t('landing.showcase.game.level') }}</span>
              <div class="demo-xp">
                <span class="xp-bar"><span class="xp-fill" /></span>
                <span class="xp-num">{{ $t('landing.showcase.game.xp') }}</span>
              </div>
              <span class="demo-streak"><i class="fa-solid fa-fire" /> {{ $t('landing.showcase.game.streak') }}</span>
            </div>
            <span class="tag tag-game">{{ $t('landing.showcase.game.tag') }}</span>
            <strong class="show-title">{{ $t('landing.showcase.game.title') }}</strong>
            <p class="show-body">{{ $t('landing.showcase.game.body') }}</p>
          </article>

          <!-- Social -->
          <article class="card show-card">
            <div class="demo demo-social">
              <div class="rank rank-1"><i class="fa-solid fa-trophy" /><span class="ava">L</span><span class="rank-xp">2,340</span></div>
              <div class="rank rank-2"><i class="fa-solid fa-medal" /><span class="ava">T</span><span class="rank-xp">1,980</span></div>
              <div class="rank rank-3"><i class="fa-solid fa-medal" /><span class="ava">M</span><span class="rank-xp">1,210</span></div>
            </div>
            <span class="tag tag-social">{{ $t('landing.showcase.social.tag') }}</span>
            <strong class="show-title">{{ $t('landing.showcase.social.title') }}</strong>
            <p class="show-body">{{ $t('landing.showcase.social.body') }}</p>
          </article>
        </div>
      </section>

      <!-- Final CTA -->
      <section class="card final">
        <strong class="final-title">{{ $t('landing.final.heading') }}</strong>
        <p class="final-body">{{ $t('landing.final.body') }}</p>
        <RouterLink to="/signup" class="btn-primary btn-lg">
          <i class="fa-solid fa-rocket" /> {{ $t('landing.final.button') }}
        </RouterLink>
      </section>

      <footer class="lp-footer">{{ $t('landing.footer.copyright', { year }) }}</footer>
    </main>
  </div>
</template>

<style scoped>
.landing {
  min-height: 100vh;
  /* Subtle accent glow at the top so the screen doesn't read as flat dark-on-dark
     (mirrors LoginView). */
  background: radial-gradient(120% 45% at 50% 0%, var(--focus-ring), transparent 62%);
}

/* ---- header ---- */
.lp-header { border-bottom: 1px solid var(--border); }
.lp-header-inner {
  max-width: 820px;
  margin: 0 auto;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.brand { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
.brand .mark {
  display: inline-grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: var(--accent);
  color: var(--on-accent);
  font-weight: 800;
  font-size: 17px;
}
.brand .wordmark { font-weight: 700; font-size: 16px; }
.lp-header-actions { display: flex; align-items: center; gap: 12px; }
.signin-link { color: var(--accent); font-weight: 600; text-decoration: none; font-size: 14px; white-space: nowrap; }
.signin-link:hover { filter: brightness(1.1); }

/* ---- layout ---- */
.lp-main { max-width: 820px; margin: 0 auto; padding: 20px 16px 32px; }

/* ---- shared buttons (anchors, so the global <button> skin doesn't apply) ---- */
.btn-primary,
.btn-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 12px 22px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  transition: filter 0.15s ease;
}
.btn-primary { background: var(--accent); color: var(--on-accent); }
.btn-primary:hover { filter: brightness(1.06); }
.btn-secondary { background: var(--surface-2); color: var(--text); }
.btn-secondary:hover { filter: brightness(1.08); }
.btn-lg { min-height: 50px; padding: 14px 28px; font-size: 15px; }

/* ---- hero ---- */
.hero { text-align: center; padding: 32px 22px; }
.badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--surface-2);
  color: var(--muted);
  border-radius: 999px;
  padding: 6px 13px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.badge i { color: var(--accent); }
.hero-title { margin: 16px 0 0; font-size: 32px; line-height: 1.18; font-weight: 800; }
.hero-title .accent-line { display: block; color: var(--accent); }
.hero-sub { margin: 14px auto 0; max-width: 560px; color: var(--muted); font-size: 15px; line-height: 1.6; }
.hero-actions { margin-top: 22px; display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
.hero-proof { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.proof {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--surface-2);
  border-radius: 999px;
  padding: 7px 13px;
  font-size: 13px;
  color: var(--text);
}
.proof i { color: var(--accent); }

/* ---- section heading ---- */
.section-head { text-align: center; margin: 30px 0 16px; }
.section-title { display: block; font-size: 21px; font-weight: 800; }
.section-title .accent-line { color: var(--accent); }
.section-sub { margin: 8px auto 0; max-width: 520px; color: var(--muted); font-size: 14px; line-height: 1.55; }

/* ---- showcase ---- */
.showcase-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.show-card { display: flex; flex-direction: column; gap: 10px; transition: border-color 0.15s ease; }
.show-card:hover { border-color: var(--field-border); }
.show-title { font-size: 16px; font-weight: 700; }
.show-body { margin: 0; color: var(--muted); font-size: 13.5px; line-height: 1.55; }
.tag {
  align-self: flex-start;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 4px 10px;
  border-radius: 999px;
}
.tag-ai { background: var(--macro-f-bg); color: var(--macro-f); }
.tag-game { background: var(--focus-ring); color: var(--accent); }
.tag-social { background: var(--macro-p-bg); color: var(--macro-p); }

/* ---- demo widgets (flat, token-driven, FA icons instead of emoji) ---- */
.demo {
  background: var(--inset);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px;
  min-height: 94px;
  display: flex;
  align-items: center;
}
/* AI: photo -> result */
.demo-ai { gap: 12px; }
.demo-photo {
  display: inline-grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 10px;
  background: var(--macro-f-bg);
  color: var(--macro-f);
  font-size: 18px;
  flex-shrink: 0;
}
.demo-arrow { color: var(--muted); font-size: 13px; }
.demo-result { display: flex; flex-direction: column; gap: 3px; }
.demo-food { font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 7px; }
.demo-food i { color: var(--accent); }
.demo-cal { color: var(--muted); font-size: 13px; }
/* Game: level + XP bar + streak */
.demo-game { flex-direction: column; align-items: stretch; justify-content: center; gap: 10px; }
.demo-level {
  align-self: flex-start;
  background: var(--accent);
  color: var(--on-accent);
  font-weight: 800;
  font-size: 13px;
  padding: 3px 11px;
  border-radius: 999px;
}
.demo-xp { display: flex; align-items: center; gap: 10px; }
.xp-bar { flex: 1; height: 10px; background: var(--surface-2); border-radius: 6px; overflow: hidden; }
.xp-fill { display: block; height: 100%; width: 72%; background: var(--accent); border-radius: 6px; }
.xp-num { font-size: 12px; font-weight: 700; color: var(--muted); }
.demo-streak { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; color: var(--streak); }
/* Social: 3-row leaderboard */
.demo-social { flex-direction: column; align-items: stretch; justify-content: center; gap: 7px; }
.rank { display: flex; align-items: center; gap: 10px; }
.rank > i { width: 16px; text-align: center; font-size: 14px; }
.rank-1 > i { color: #ffd700; }
.rank-2 > i { color: #c0c0c0; }
.rank-3 > i { color: #cd7f32; }
.ava {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--surface-2);
  color: var(--text);
  font-size: 12px;
  font-weight: 700;
}
.rank-xp { margin-left: auto; font-size: 13px; font-weight: 700; color: var(--muted); }

/* ---- final CTA ---- */
.final { text-align: center; padding: 32px 22px; margin-top: 16px; }
.final-title { display: block; font-size: 21px; font-weight: 800; }
.final-body { margin: 10px auto 18px; max-width: 480px; color: var(--muted); font-size: 14px; line-height: 1.55; }

/* ---- footer ---- */
.lp-footer {
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid var(--border);
  text-align: center;
  color: var(--muted);
  font-size: 12.5px;
}

/* ---- entrance: a single short rise, disabled under reduced-motion ---- */
@media (prefers-reduced-motion: no-preference) {
  .hero,
  .showcase,
  .final { animation: lp-rise 0.4s cubic-bezier(0.16, 1, 0.3, 1) backwards; }
  .showcase { animation-delay: 0.06s; }
  .final { animation-delay: 0.12s; }
}
@keyframes lp-rise {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: none; }
}

/* ---- responsive ---- */
@media (max-width: 640px) {
  .showcase-grid { grid-template-columns: 1fr; }
  .hero { padding: 26px 18px; }
  .hero-title { font-size: 27px; }
}
@media (max-width: 480px) {
  .hero-actions { flex-direction: column; }
  .hero-actions .btn-primary,
  .hero-actions .btn-secondary { width: 100%; }
}
</style>
