<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
  CircleHelp,
  Eye,
  EyeOff,
  Lock,
  Mail,
  MoonStar,
  Palette,
  RotateCcw,
  ShieldCheck,
  Sparkles,
  SunMedium,
} from 'lucide-vue-next';
import { useTheme } from '../../composables/useTheme';

const showPassword = ref(false);
const showPalettePicker = ref(false);
const showCustomColorBar = ref(false);
const customHue = ref(0);
const {
  theme,
  isDark,
  palette,
  paletteOptions,
  customColor,
  savingTheme,
  resetPalette,
  setCustomColor,
  setPalette,
  toggleTheme,
} = useTheme();

defineProps({
  status: {
    type: String,
    default: null,
  },
});

const form = useForm({
  email: '',
  password: '',
  remember: false,
  theme: theme.value,
  palette: palette.value,
  custom_color: customColor.value,
});

watch(
  theme,
  (value) => {
    form.theme = value;
  },
  { immediate: true },
);

watch(
  palette,
  (value) => {
    form.palette = value;
  },
  { immediate: true },
);

watch(
  customColor,
  (value) => {
    form.custom_color = value;
  },
  { immediate: true },
);

const forgotPasswordHref = computed(() => {
  const email = form.email.trim();

  if (!email) {
    return '/forgot-password/email';
  }

  return `/forgot-password/email?email=${encodeURIComponent(email)}`;
});

const sortedPaletteOptions = computed(() => {
  const groupOrder = {
    azure: 1,
    cobalt: 1,
    cyan: 1,
    steel: 1,
    slate: 1,
    indigo: 2,
    violet: 2,
    purple: 2,
    orchid: 2,
    rose: 3,
    mauve: 3,
    ruby: 3,
    mint: 4,
    teal: 4,
    forest: 4,
    green: 4,
    olive: 4,
    amber: 5,
    orange: 5,
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

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};

const togglePalettePicker = () => {
  showPalettePicker.value = !showPalettePicker.value;

  if (!showPalettePicker.value) {
    showCustomColorBar.value = false;
  }
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

watch(
  customColor,
  (value) => {
    customHue.value = hexToHue(value);
  },
  { immediate: true },
);
</script>

<template>
  <Head title="Iniciar sesion" />

  <main class="ui-auth-page">

    <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
      <button
        type="button"
        class="ui-icon-btn h-11 w-11 rounded-full bg-white/90 backdrop-blur dark:bg-slate-900/90"
        :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        :disabled="savingTheme"
        @click="toggleTheme"
      >
        <SunMedium v-if="isDark" class="h-5 w-5" />
        <MoonStar v-else class="h-5 w-5" />
      </button>
    </div>

    <div
      class="relative mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-6xl items-center justify-center"
    >
      <section class="ui-auth-card">
        <div class="card-body p-6 sm:p-8">
          <div class="flex flex-col items-center text-center">
            <div
              class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-linear-to-br from-slate-700 to-blue-900 text-white shadow-lg shadow-slate-400/30 dark:from-slate-200 dark:to-sky-400 dark:text-slate-950 dark:shadow-slate-950/30"
            >
              <ShieldCheck class="h-7 w-7" />
            </div>
            <p
              class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
              <Sparkles class="h-3.5 w-3.5" />
              FaithAssist QR
            </p>
          </div>

          <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div
              v-if="status"
              class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
            >
              {{ status }}
            </div>

            <div class="space-y-2">
              <label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >Correo institucional</label
              >
              <div class="relative">
                <Mail
                  class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500"
                />
                <input
                  id="email"
                  name="email"
                  type="email"
                  v-model="form.email"
                  class="input ui-input w-full pl-10"
                  :class="{ 'input-error': form.errors.email }"
                  placeholder="nombre@organizacion.org"
                  autocomplete="email"
                />
              </div>
              <p v-if="form.errors.email" class="text-sm font-medium text-red-600">
                {{ form.errors.email }}
              </p>
            </div>

            <div class="space-y-2">
              <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >Contrasena</label
              >
              <div class="relative">
                <Lock
                  class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500"
                />
                <input
                  id="password"
                  name="password"
                  :type="showPassword ? 'text' : 'password'"
                  v-model="form.password"
                  class="input ui-input w-full pl-10 pr-12"
                  :class="{ 'input-error': form.errors.password }"
                  placeholder="Escribe tu contrasena"
                  autocomplete="current-password"
                />
                <button
                  type="button"
                  class="absolute right-1.5 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                  :aria-label="showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>
              <p v-if="form.errors.password" class="text-sm font-medium text-red-600">
                {{ form.errors.password }}
              </p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
              <label class="label cursor-pointer justify-start gap-2 p-0">
                <input
                  type="checkbox"
                  name="remember"
                  value="1"
                  v-model="form.remember"
                  class="checkbox checkbox-sm border-slate-300 dark:border-slate-600"
                />
                <span class="label-text text-slate-600 dark:text-slate-300"
                  >Recordar mi sesion</span
                >
              </label>
              <Link
                :href="forgotPasswordHref"
                class="text-sm font-medium text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-blue-900 dark:text-slate-300 dark:decoration-slate-500 dark:hover:text-slate-100"
              >
                Olvide mi contrasena
              </Link>
            </div>

            <button
              type="submit"
              class="ui-btn ui-btn-primary ui-btn-enter h-12 w-full bg-linear-to-r from-slate-700 to-blue-900 text-white hover:from-slate-800 hover:to-blue-950 dark:text-white"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Ingresando...' : 'Entrar al sistema' }}
            </button>
          </form>

          <div
            class="divider my-6 text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500"
          >
            soporte
          </div>

          <p
            class="flex items-center justify-center gap-2 text-center text-sm text-slate-600 dark:text-slate-300"
          >
            <CircleHelp class="h-4 w-4 text-slate-500 dark:text-slate-400" />
            Si no puedes ingresar, contacta a mesa de ayuda.
          </p>

        </div>
      </section>
    </div>

    <div class="fixed bottom-6 right-6 z-20 flex flex-col items-end sm:bottom-8 sm:right-8">
      <div
        v-if="showPalettePicker"
        class="mb-3 w-[20rem] max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-2xl backdrop-blur dark:border-slate-700 dark:bg-slate-900/95"
      >
        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">
          PERZONALIZAR AMBIENTE
        </p>

        <div
          class="max-h-64 overflow-y-auto pr-0.5"
          style="scrollbar-width: thin"
        >
          <div class="grid grid-cols-4 gap-2.5">
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

          <button
            type="button"
            class="group col-span-2 rounded-xl border border-slate-200 bg-white p-2 text-center transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600"
            @click="handleResetPalette"
          >
            <span class="mb-1.5 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/30 bg-slate-100 text-slate-600 shadow-inner dark:bg-slate-700 dark:text-slate-200">
              <RotateCcw class="h-4 w-4" />
            </span>
            <span class="block truncate text-[10px] font-semibold text-slate-600 dark:text-slate-300">
              Reset
            </span>
          </button>

          <button
            type="button"
            class="group col-span-2 rounded-xl border p-2 text-center transition"
            :class="
              showCustomColorBar
                ? 'border-slate-500 bg-white dark:border-slate-400 dark:bg-slate-800'
                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600'
            "
            @click="toggleCustomColorBar"
          >
            <span
              class="mx-auto mb-1.5 block h-10 w-10 rounded-full border border-white/30 shadow-inner"
              :style="{ backgroundColor: customColor }"
            ></span>
            <span class="block truncate text-[10px] font-semibold text-slate-600 dark:text-slate-300">
              Color
            </span>
          </button>
          </div>
        </div>

        <div v-if="showCustomColorBar" class="mt-3 border-t border-slate-200 px-1 pt-3 dark:border-slate-700">
            <input
              type="range"
              min="0"
              max="360"
              :value="customHue"
              class="ui-hue-slider"
              aria-label="Ajustar color"
              @input="applyHue"
            />

          <p class="mt-1.5 text-[10px] font-medium uppercase tracking-wide text-slate-400/90 dark:text-slate-500">
            Color actual: {{ customColor }}
          </p>
        </div>
      </div>

      <button
        type="button"
        class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-slate-300 bg-white/95 text-slate-700 shadow-lg transition hover:-translate-y-0.5 hover:border-slate-400 hover:bg-white dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-200 dark:hover:border-slate-600"
        aria-label="Personalizar colores"
        @click="togglePalettePicker"
      >
        <Palette class="h-5 w-5" />
      </button>
    </div>
  </main>
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
</style>
