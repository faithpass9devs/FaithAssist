<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { MoonStar, Palette, RotateCcw, SunMedium, X } from 'lucide-vue-next';
import { useTheme } from '../../composables/useTheme';

defineProps({
  pageTitle: {
    type: String,
    required: true,
  },
});

const page = usePage();
const form = useForm({});
const menuOpen = ref(false);
const menuRef = ref(null);
const paletteRef = ref(null);
const showPalettePicker = ref(false);
const showCustomColorBar = ref(false);
const customHue = ref(0);
const {
  isDark,
  savingTheme,
  toggleTheme,
  palette,
  paletteOptions,
  customColor,
  resetPalette,
  setCustomColor,
  setPalette,
} = useTheme();

const appName = import.meta.env.VITE_APP_NAME || 'FAITHPASS';

const authUser = computed(() => page.props.auth?.user ?? null);

const displayName = computed(() => {
  const profile = authUser.value?.profile;

  if (profile?.name && profile?.paterno) {
    return `${profile.name} ${profile.paterno}`;
  }

  return authUser.value?.display_name ?? 'Usuario';
});
const initials = computed(() => authUser.value?.initials ?? 'U');
const photoUrl = computed(() => authUser.value?.photo_url ?? null);

const toggleMenu = () => {
  menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
  menuOpen.value = false;
};

const logout = () => {
  form.post('/logout', {
    onFinish: () => {
      closeMenu();
    },
  });
};

const onWindowClick = (event) => {
  if (!menuRef.value?.contains(event.target)) {
    closeMenu();
  }
};

const onKeydown = (event) => {
  if (event.key !== 'Escape') {
    return;
  }

  closeMenu();
  closePalettePicker();
};

const togglePalettePicker = () => {
  showPalettePicker.value = !showPalettePicker.value;

  if (!showPalettePicker.value) {
    showCustomColorBar.value = false;
  }
};

const closePalettePicker = () => {
  showPalettePicker.value = false;
  showCustomColorBar.value = false;
};

const toggleCustomColorBar = () => {
  showCustomColorBar.value = !showCustomColorBar.value;
};

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const hueToHex = (hue) => {
  const h = ((hue % 360) + 360) % 360;
  const c = 1;
  const x = c * (1 - Math.abs(((h / 60) % 2) - 1));

  let r = 0;
  let g = 0;
  let b = 0;

  if (h < 60) {
    r = c;
    g = x;
  } else if (h < 120) {
    r = x;
    g = c;
  } else if (h < 180) {
    g = c;
    b = x;
  } else if (h < 240) {
    g = x;
    b = c;
  } else if (h < 300) {
    r = x;
    b = c;
  } else {
    r = c;
    b = x;
  }

  const toHex = (channel) => {
    const value = Math.round(channel * 255).toString(16);
    return value.length === 1 ? `0${value}` : value;
  };

  return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
};

const hexToHue = (hex) => {
  const normalized = hex.replace('#', '');

  if (normalized.length !== 6) {
    return 0;
  }

  const r = parseInt(normalized.slice(0, 2), 16) / 255;
  const g = parseInt(normalized.slice(2, 4), 16) / 255;
  const b = parseInt(normalized.slice(4, 6), 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const delta = max - min;

  if (delta === 0) {
    return 0;
  }

  let hue = 0;

  if (max === r) {
    hue = ((g - b) / delta) % 6;
  } else if (max === g) {
    hue = (b - r) / delta + 2;
  } else {
    hue = (r - g) / delta + 4;
  }

  return Math.round((hue * 60 + 360) % 360);
};

const applyHue = (event) => {
  const hue = clamp(Number(event.target.value) || 0, 0, 360);
  customHue.value = hue;
  setCustomColor(hueToHex(hue));
};

const handleResetPalette = () => {
  resetPalette();
};

const sortedPaletteOptions = computed(() => {
  const groupOrder = {
    azure: 1,
    cobalt: 1,
    cyan: 1,
    navy: 1,
    sky: 1,
    steel: 1,
    slate: 1,
    indigo: 2,
    lavender: 2,
    violet: 2,
    purple: 2,
    orchid: 2,
    plum: 2,
    rose: 3,
    mauve: 3,
    ruby: 3,
    wine: 3,
    mint: 4,
    teal: 4,
    emerald: 4,
    forest: 4,
    green: 4,
    olive: 4,
    amber: 5,
    sand: 5,
    orange: 5,
    coral: 5,
    terracotta: 5,
    chocolate: 5,
  };

  return paletteOptions.value
    .filter((option) => option.id !== 'custom' && option.id !== 'neutral')
    .sort((a, b) => {
      const aGroup = groupOrder[a.id] ?? 99;
      const bGroup = groupOrder[b.id] ?? 99;

      if (aGroup !== bGroup) {
        return aGroup - bGroup;
      }

      return a.label.localeCompare(b.label, 'es');
    });
});

watch(
  customColor,
  (value) => {
    customHue.value = hexToHue(value);
  },
  { immediate: true },
);

// El bottom sheet ocupa toda la pantalla en movil: se congela el scroll de fondo.
watch(showPalettePicker, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
  window.addEventListener('click', onWindowClick);
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('click', onWindowClick);
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});
</script>

<template>
  <Head :title="pageTitle" />
  <div class="ui-shell">
    <header class="ui-topbar">
      <div class="ui-container flex items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <Link href="/" class="ui-brand">
          {{ appName }}
        </Link>

        <div class="flex items-center gap-2 sm:gap-3">
          <div class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 p-1 backdrop-blur-sm">
            <div class="relative">
              <button
                type="button"
                class="ui-icon-btn rounded-full"
                :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
                :disabled="savingTheme"
                @click="toggleTheme"
              >
                <SunMedium v-if="isDark" class="h-5 w-5" />
                <MoonStar v-else class="h-5 w-5" />
              </button>
            </div>

            <div ref="paletteRef">
              <button
                type="button"
                class="ui-icon-btn rounded-full"
                aria-label="Personalizar colores"
                :aria-expanded="showPalettePicker"
                @click.stop="togglePalettePicker"
              >
                <Palette class="h-5 w-5" />
              </button>

              <Teleport to="body">
                <Transition name="ui-sheet-fade">
                  <div
                    v-if="showPalettePicker"
                    class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm"
                    @click="closePalettePicker"
                  ></div>
                </Transition>

                <Transition name="ui-sheet">
                  <div
                    v-if="showPalettePicker"
                    class="fixed inset-x-0 bottom-0 z-50 sm:inset-0 sm:pointer-events-none sm:flex sm:items-center sm:justify-center sm:p-6"
                  >
                    <div
                      class="pointer-events-auto flex max-h-[85vh] w-full flex-col rounded-t-3xl border border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:max-h-[80vh] sm:w-104 sm:rounded-3xl sm:pb-0"
                      role="dialog"
                      aria-modal="true"
                      aria-label="Personalizar ambiente"
                    >
                      <div class="shrink-0 px-5 pt-3">
                        <span
                          class="mx-auto mb-3 block h-1.5 w-12 rounded-full bg-slate-300 dark:bg-slate-700 sm:hidden"
                        ></span>

                        <div class="flex items-center justify-between pb-3">
                          <p
                            class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400"
                          >
                            Personalizar ambiente
                          </p>

                          <button
                            type="button"
                            class="ui-icon-btn rounded-full"
                            aria-label="Cerrar"
                            @click="closePalettePicker"
                          >
                            <X class="h-5 w-5" />
                          </button>
                        </div>
                      </div>

                      <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-3" style="scrollbar-width: thin">
                        <div class="grid grid-cols-4 gap-2.5 sm:grid-cols-5">
                          <button
                            v-for="option in sortedPaletteOptions"
                            :key="option.id"
                            type="button"
                            class="group rounded-xl border p-2 text-center transition"
                            :class="
                              palette === option.id
                                ? 'border-slate-500 bg-white dark:border-slate-400 dark:bg-slate-800'
                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600'
                            "
                            :title="option.label"
                            @click="setPalette(option.id)"
                          >
                            <span
                              class="mx-auto mb-1.5 block h-10 w-10 rounded-full border border-white/30 shadow-inner"
                              :style="{ backgroundColor: option.swatch }"
                            ></span>
                            <span class="block truncate text-[10px] font-semibold text-slate-600 dark:text-slate-300">
                              {{ option.label }}
                            </span>
                          </button>
                        </div>
                      </div>

                      <div class="shrink-0 border-t border-slate-200 px-5 py-3 dark:border-slate-700">
                        <div class="grid grid-cols-2 gap-2.5">
                          <button
                            type="button"
                            class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white p-2.5 text-left transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600"
                            @click="handleResetPalette"
                          >
                            <span
                              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/30 bg-slate-100 text-slate-600 shadow-inner dark:bg-slate-700 dark:text-slate-200"
                            >
                              <RotateCcw class="h-4 w-4" />
                            </span>
                            <span class="truncate text-xs font-semibold text-slate-600 dark:text-slate-300">
                              Restablecer
                            </span>
                          </button>

                          <button
                            type="button"
                            class="flex items-center gap-2.5 rounded-xl border p-2.5 text-left transition"
                            :class="
                              showCustomColorBar
                                ? 'border-slate-500 bg-white dark:border-slate-400 dark:bg-slate-800'
                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600'
                            "
                            :aria-expanded="showCustomColorBar"
                            @click="toggleCustomColorBar"
                          >
                            <span
                              class="h-9 w-9 shrink-0 rounded-full border border-white/30 shadow-inner"
                              :style="{ backgroundColor: customColor }"
                            ></span>
                            <span class="truncate text-xs font-semibold text-slate-600 dark:text-slate-300">
                              Color propio
                            </span>
                          </button>
                        </div>

                        <div v-if="showCustomColorBar" class="mt-3 flex flex-col items-center px-1">
                          <input
                            type="range"
                            min="0"
                            max="360"
                            :value="customHue"
                            class="ui-hue-slider"
                            aria-label="Ajustar color"
                            @input="applyHue"
                          />

                          <p
                            class="mt-1.5 text-center text-[10px] font-medium uppercase tracking-wide text-slate-400/90 dark:text-slate-500"
                          >
                            Color actual: {{ customColor }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </Transition>
              </Teleport>
            </div>
          </div>

          <div class="relative" ref="menuRef">
            <button
              type="button"
              class="flex items-center gap-2 rounded-full border border-slate-300 bg-white px-2 py-1 text-left shadow-sm transition hover:border-slate-400 hover:shadow dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
              :aria-expanded="menuOpen"
              aria-haspopup="menu"
              @click.stop="toggleMenu"
            >
              <span
                class="hidden max-w-56 truncate px-1 text-sm font-semibold text-slate-700 dark:text-slate-200 sm:block"
              >
                {{ displayName }}
              </span>

              <span
                v-if="photoUrl"
                class="h-9 w-9 overflow-hidden rounded-full border border-slate-300 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
              >
                <img :src="photoUrl" alt="Foto de perfil" class="h-full w-full object-cover" />
              </span>

              <span
                v-else
                class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-slate-800 text-xs font-bold uppercase text-white dark:border-slate-700 dark:bg-slate-100 dark:text-slate-900"
              >
                {{ initials }}
              </span>
            </button>

            <div
              v-if="menuOpen"
              class="absolute right-0 mt-2 w-44 rounded-xl border border-slate-200 bg-white py-2 shadow-lg dark:border-slate-800 dark:bg-slate-900"
              role="menu"
            >
              <Link
                href="/profile"
                class="block px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                role="menuitem"
                @click="closeMenu"
              >
                Perfil
              </Link>

              <button
                type="button"
                class="block w-full px-4 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:hover:bg-red-950/40"
                role="menuitem"
                :disabled="form.processing"
                @click="logout"
              >
                {{ form.processing ? 'Cerrando...' : 'Logout' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="ui-main">
      <div class="ui-container">
        <slot />
      </div>
    </main>
  </div>
</template>

<style scoped>
.ui-hue-slider {
  width: 100%;
  height: 0.5rem;
  border-radius: 999px;
  appearance: none;
  border: 1px solid rgb(226 232 240 / 0.9);
  background: linear-gradient(
    90deg,
    #ff0000 0%,
    #ffff00 17%,
    #00ff00 33%,
    #00ffff 50%,
    #0000ff 67%,
    #ff00ff 83%,
    #ff0000 100%
  );
}

.ui-hue-slider::-webkit-slider-thumb {
  width: 0.9rem;
  height: 0.9rem;
  border-radius: 999px;
  border: 2px solid #ffffff;
  appearance: none;
  background-color: v-bind(customColor);
  box-shadow: 0 1px 3px rgb(15 23 42 / 0.35);
}

.ui-hue-slider::-moz-range-thumb {
  width: 0.9rem;
  height: 0.9rem;
  border-radius: 999px;
  border: 2px solid #ffffff;
  background-color: v-bind(customColor);
  box-shadow: 0 1px 3px rgb(15 23 42 / 0.35);
}

.ui-hue-slider::-moz-range-track {
  height: 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(226 232 240 / 0.9);
  background: linear-gradient(
    90deg,
    #ff0000 0%,
    #ffff00 17%,
    #00ff00 33%,
    #00ffff 50%,
    #0000ff 67%,
    #ff00ff 83%,
    #ff0000 100%
  );
}

.ui-sheet-fade-enter-active,
.ui-sheet-fade-leave-active {
  transition: opacity 0.2s ease;
}

.ui-sheet-fade-enter-from,
.ui-sheet-fade-leave-to {
  opacity: 0;
}

.ui-sheet-enter-active,
.ui-sheet-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s cubic-bezier(0.32, 0.72, 0, 1);
}

.ui-sheet-enter-from,
.ui-sheet-leave-to {
  opacity: 0;
  transform: translateY(100%);
}

@media (min-width: 640px) {
  .ui-sheet-enter-from,
  .ui-sheet-leave-to {
    transform: translateY(1rem) scale(0.97);
  }
}

@media (prefers-reduced-motion: reduce) {
  .ui-sheet-enter-active,
  .ui-sheet-leave-active,
  .ui-sheet-fade-enter-active,
  .ui-sheet-fade-leave-active {
    transition: none;
  }
}
</style>
