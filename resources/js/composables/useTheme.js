import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const THEMES = ['light', 'dark'];

export function normalizeTheme(value, fallback = 'light') {
  return THEMES.includes(value) ? value : fallback;
}

export function getInitialTheme(preferredTheme = null) {
  const fallback =
    typeof document !== 'undefined'
      ? normalizeTheme(document.documentElement.dataset.theme, 'light')
      : 'light';

  return normalizeTheme(preferredTheme, fallback);
}

export function applyTheme(theme) {
  if (typeof document === 'undefined') {
    return;
  }

  document.documentElement.dataset.theme = theme;
  document.documentElement.classList.toggle('dark', theme === 'dark');
  document.documentElement.style.colorScheme = theme;
}

export function useTheme() {
  const page = usePage();
  const theme = ref(getInitialTheme(page.props.auth?.user?.ui_theme));
  const savingTheme = ref(false);

  const isDark = computed(() => theme.value === 'dark');

  const syncTheme = (value) => {
    applyTheme(value);

    if (page.props.auth?.user) {
      page.props.auth.user.ui_theme = value;
    }
  };

  const persistTheme = async (value, previousValue) => {
    if (!page.props.auth?.user) {
      return;
    }

    savingTheme.value = true;

    try {
      const response = await fetch('/profile/theme', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ theme: value }),
      });

      if (!response.ok) {
        theme.value = previousValue;
        throw new Error('No se pudo guardar la preferencia de tema.');
      }

      const json = await response.json();
      theme.value = normalizeTheme(json.theme, value);
    } finally {
      savingTheme.value = false;
    }
  };

  const toggleTheme = async () => {
    if (savingTheme.value) {
      return;
    }

    const previousValue = theme.value;
    theme.value = theme.value === 'dark' ? 'light' : 'dark';

    try {
      await persistTheme(theme.value, previousValue);
    } catch (error) {
      console.error(error);
    }
  };

  onMounted(() => {
    syncTheme(theme.value);
  });

  watch(theme, (value) => {
    syncTheme(value);
  });

  return {
    theme,
    isDark,
    savingTheme,
    toggleTheme,
  };
}
