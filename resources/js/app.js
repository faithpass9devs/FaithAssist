import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import {
  applyPalette,
  applyTheme,
  getInitialCustomColor,
  getInitialPalette,
  getInitialTheme,
} from './composables/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const bootTheme = getInitialTheme();
applyTheme(bootTheme);
applyPalette(getInitialPalette(), bootTheme, getInitialCustomColor());

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) =>
    resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const initialTheme = getInitialTheme(props.initialPage.props.auth?.user?.ui_theme);
    const initialPalette = getInitialPalette(props.initialPage.props.auth?.user?.ui_palette);
    const initialCustomColor = getInitialCustomColor(props.initialPage.props.auth?.user?.ui_custom_color);

    applyTheme(initialTheme);
    applyPalette(initialPalette, initialTheme, initialCustomColor);

    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
  progress: {
    color: '#06b6d4',
  },
});
