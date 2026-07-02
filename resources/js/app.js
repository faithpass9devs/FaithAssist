import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { applyTheme, getInitialTheme } from './composables/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

applyTheme(getInitialTheme());

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) =>
    resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    applyTheme(getInitialTheme(props.initialPage.props.auth?.user?.ui_theme));

    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
  progress: {
    color: '#06b6d4',
  },
});
