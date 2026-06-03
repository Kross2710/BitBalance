import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router.js';
import { i18n } from './i18n/index.js';
import './lib/theme.js'; // resolves + applies data-theme before mount (no flash)
import './styles.css';

const app = createApp(App);
app.use(createPinia());
app.use(i18n); // registers $t + sets <html lang>; locale already resolved at import
app.use(router);
app.mount('#app');
