<script setup>
// Placeholder for the "My Trainer" segment of the Coach Hub. The real panel
// (assigned trainer card, two-way chat, advice history, goal-proposal accept)
// lands in a later slice once the /api/pt/* routes exist; for now this just
// establishes the hub's second surface so the segmented control is wired.
</script>

<template>
  <div class="trainer">
    <div class="placeholder">
      <div class="avatar"><i class="fa-solid fa-user-tie" /></div>
      <h2>My Trainer</h2>
      <p class="muted">
        Your personal trainer's feedback, chat, and goal proposals will live here.
        Coming soon.
      </p>
    </div>
  </div>
</template>

<style scoped>
.trainer {
  height: 100%;
  max-width: 1000px;
  margin: 0 auto;
  padding: 8px 16px;
  display: flex;
}
.placeholder {
  margin: auto;
  text-align: center;
  max-width: 340px;
}
.avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--card);
  border: 1px solid var(--border);
  color: var(--accent);
  display: grid;
  place-items: center;
  font-size: 22px;
  margin: 0 auto 12px;
}
.placeholder h2 { margin: 0 0 6px; }
.muted { color: var(--muted); font-size: 13px; line-height: 1.5; }

@media (max-width: 767px) {
  .trainer { padding: 8px 12px; }
}
</style>
