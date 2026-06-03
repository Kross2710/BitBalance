<script setup>
// Conversation list shared by the AI Coach desktop sidebar and the mobile
// bottom sheet, so both surfaces stay identical. Purely presentational — the
// parent owns the data and performs the actual select/new/delete actions.
defineProps({
  conversations: { type: Array, default: () => [] },
  activeId: { type: [Number, String], default: 0 },
});
defineEmits(['select', 'new', 'delete']);
</script>

<template>
  <div class="convo-list">
    <button class="newchat" @click="$emit('new')"><i class="fa-solid fa-plus" /> {{ $t('coach.chat.new') }}</button>
    <ul>
      <li
        v-for="c in conversations"
        :key="c.id"
        :class="{ active: c.id === activeId }"
        @click="$emit('select', c.id)"
      >
        <span class="title">{{ c.title }}</span>
        <button class="del" :title="$t('coach.chat.delete')" @click.stop="$emit('delete', c.id)">
          <i class="fa-solid fa-trash" />
        </button>
      </li>
      <li v-if="!conversations.length" class="empty muted">{{ $t('coach.chat.empty') }}</li>
    </ul>
  </div>
</template>

<style scoped>
.convo-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 0;
  flex: 1 1 auto;
}
.newchat {
  flex: none;
  background: var(--card);
  color: var(--text);
  border: 1px solid var(--border);
  text-align: left;
}
ul {
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
li {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 10px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
}
li:hover { background: var(--inset); }
li.active { background: var(--inset); color: var(--accent); }
li.empty { cursor: default; color: var(--muted); font-size: 13px; }
li.empty:hover { background: transparent; }
.title {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.del {
  flex: none;
  min-height: 0;
  background: transparent;
  color: var(--muted);
  padding: 4px 6px;
  opacity: 0;
}
li:hover .del { opacity: 1; }
.del:hover { color: #f87171; }

/* Touch: no hover to reveal, so keep the delete affordance always visible. */
@media (hover: none) {
  .del { opacity: 0.7; }
}
</style>
