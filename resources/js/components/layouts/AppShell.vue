<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Bell, CircleAlert, MapPin, MoonStar, Palette, RotateCcw, ShieldAlert, SunMedium, X } from 'lucide-vue-next';
import { useTheme } from '../../composables/useTheme';
import { getDeviceModel } from '../../utils/deviceModel';

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
const moderationNotification = ref(page.props.auth?.pending_moderation_notification ?? null);
const moderationAcknowledged = ref(false);
let moderationPoll = null;
let locationPoll = null;
let locationPermission = null;
let deviceModelPromise = null;

const locationStatus = ref('checking');
const locationRetrying = ref(false);

const locationBlocked = computed(
  () => !!authUser.value && ['denied', 'unsupported', 'insecure', 'unavailable'].includes(locationStatus.value),
);

const locationTitle = computed(() => ({
  denied: 'Permiso de ubicación bloqueado',
  unsupported: 'Ubicación no disponible en este navegador',
  insecure: 'Conexión no segura',
})[locationStatus.value] ?? 'No pudimos obtener tu ubicación');

const locationDescription = computed(() => ({
  denied:
    'Para usar el sistema debes permitir el acceso a tu ubicación. Ábrelo en el candado de la barra de direcciones, cambia el permiso de Ubicación a "Permitir" y vuelve a intentarlo.',
  unsupported:
    'Este navegador no permite compartir la ubicación. Ingresa desde un navegador actualizado para continuar usando el sistema.',
  insecure:
    'La ubicación solo puede obtenerse mediante una conexión segura (HTTPS). Ingresa por la dirección segura del sistema para continuar.',
})[locationStatus.value] ?? 'No fue posible leer tu ubicación en este momento. Verifica que la ubicación del dispositivo esté encendida y vuelve a intentarlo.');

const postLocation = (coords, deviceModel) =>
  fetch('/mi-sesion/ubicacion', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({
      latitude: Number(coords.latitude.toFixed(7)),
      longitude: Number(coords.longitude.toFixed(7)),
      accuracy: Number.isFinite(coords.accuracy) ? Math.round(coords.accuracy) : null,
      device_model: deviceModel,
    }),
  }).catch(() => {
    // Location reporting is best effort and must not interrupt the current page.
  });

const requestLocation = () => {
  if (!authUser.value) return;

  if (window.isSecureContext === false) {
    locationStatus.value = 'insecure';
    return;
  }

  if (!navigator.geolocation) {
    locationStatus.value = 'unsupported';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    async ({ coords }) => {
      locationStatus.value = 'granted';
      locationRetrying.value = false;
      deviceModelPromise ??= getDeviceModel();
      postLocation(coords, await deviceModelPromise);
    },
    (error) => {
      locationStatus.value = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable';
      locationRetrying.value = false;
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  );
};

const retryLocation = () => {
  locationRetrying.value = true;
  requestLocation();
};

const isRestrictedModeration = computed(() => ['moderada', 'grave'].includes(moderationNotification.value?.level));
const moderationTitle = computed(() => ({
  leve: 'Primera advertencia',
  moderada: 'Segunda advertencia',
  grave: 'Advertencia final',
})[moderationNotification.value?.level] ?? 'Aviso importante');
const moderationDescription = computed(() => {
  if (moderationNotification.value?.level === 'moderada') {
    return 'Tu cuenta quedará suspendida durante 24 horas. Durante este periodo no podrás utilizar el sistema.';
  }

  if (moderationNotification.value?.level === 'grave') {
    return 'Tu cuenta ha sido bloqueada y no podrás utilizar el sistema hasta nuevo aviso.';
  }

  return 'Se ha registrado una advertencia en tu cuenta. Lee el mensaje y confirma que estás enterado para continuar.';
});

const fetchModerationNotification = async () => {
  try {
    const response = await fetch('/notificaciones/pendiente', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (response.ok && !moderationAcknowledged.value) {
      const data = await response.json();
      moderationNotification.value = data.notification;
    }
  } catch {
    // Notification polling is best effort and must not interrupt the current page.
  }
};

const acknowledgeModeration = async () => {
  if (!moderationNotification.value) return;

  if (isRestrictedModeration.value) {
    if (moderationNotification.value.id) {
      await fetch(`/notificaciones/${moderationNotification.value.id}/confirmar`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
    }
    moderationAcknowledged.value = true;
    logout();
    return;
  }

  const response = await fetch(`/notificaciones/${moderationNotification.value.id}/confirmar`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  if (response.ok) {
    moderationNotification.value = null;
  }
};

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

watch(locationBlocked, (blocked) => {
  document.body.style.overflow = blocked ? 'hidden' : '';
});

onMounted(() => {
  window.addEventListener('click', onWindowClick);
  window.addEventListener('keydown', onKeydown);
  moderationPoll = window.setInterval(fetchModerationNotification, 10000);
  requestLocation();
  locationPoll = window.setInterval(requestLocation, 60000);

  navigator.permissions?.query?.({ name: 'geolocation' })
    .then((status) => {
      locationPermission = status;
      status.onchange = requestLocation;
    })
    .catch(() => {
      // The Permissions API is optional; the periodic retry keeps the gate in sync.
    });
});

onBeforeUnmount(() => {
  window.removeEventListener('click', onWindowClick);
  window.removeEventListener('keydown', onKeydown);
  window.clearInterval(moderationPoll);
  window.clearInterval(locationPoll);

  if (locationPermission) {
    locationPermission.onchange = null;
  }

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

  <Teleport to="body">
    <div
      v-if="locationBlocked && !moderationNotification"
      class="fixed inset-0 z-[95] flex min-h-screen items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="location-gate-title"
    >
      <section class="flex w-full max-w-xl flex-col overflow-y-auto rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex-1 px-5 py-8 text-center sm:px-10 sm:py-10">
          <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">
            <MapPin class="h-11 w-11" />
          </div>

          <p class="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Permiso requerido</p>
          <h2 id="location-gate-title" class="mt-2 text-2xl font-black text-slate-900 dark:text-slate-100 sm:text-3xl">
            {{ locationTitle }}
          </h2>
          <p class="mx-auto mt-4 max-w-lg text-base leading-7 text-slate-600 dark:text-slate-300">
            Se requiere permitir el uso de tu ubicación para poder usar el sistema.
          </p>

          <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-left dark:border-slate-700 dark:bg-slate-800/70">
            <p class="text-sm leading-6 text-slate-700 dark:text-slate-200">{{ locationDescription }}</p>
          </div>
        </div>

        <div class="flex flex-col gap-2 border-t border-slate-200 p-5 dark:border-slate-700 sm:flex-row sm:px-10">
          <button
            type="button"
            class="btn flex-1 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:opacity-70"
            :disabled="locationRetrying"
            @click="retryLocation"
          >
            {{ locationRetrying ? 'Comprobando ubicación...' : 'Permitir ubicación' }}
          </button>
          <button type="button" class="btn btn-ghost flex-1 rounded-xl" @click="logout">Cerrar sesión</button>
        </div>
      </section>
    </div>
  </Teleport>

  <Teleport to="body">
    <div
      v-if="moderationNotification"
      class="fixed inset-0 z-[100] flex min-h-screen items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
      :class="isRestrictedModeration ? 'p-0 sm:p-6' : ''"
      role="alertdialog"
      aria-modal="true"
      :aria-labelledby="`moderation-title-${moderationNotification.id}`"
    >
      <section
        class="flex w-full flex-col overflow-y-auto border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
        :class="isRestrictedModeration ? 'min-h-screen rounded-none sm:min-h-0 sm:max-h-[90vh] sm:max-w-2xl sm:rounded-3xl' : 'max-h-[90vh] max-w-xl rounded-3xl'"
      >
        <div class="flex-1 px-5 py-8 text-center sm:px-10 sm:py-10">
          <div
            class="mx-auto flex h-20 w-20 items-center justify-center rounded-full"
            :class="isRestrictedModeration ? 'bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'bg-amber-100 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300'"
          >
            <ShieldAlert v-if="isRestrictedModeration" class="h-11 w-11" />
            <CircleAlert v-else class="h-11 w-11" />
          </div>

          <p class="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Notificación de cuenta</p>
          <h2 :id="`moderation-title-${moderationNotification.id}`" class="mt-2 text-2xl font-black text-slate-900 dark:text-slate-100 sm:text-3xl">
            {{ moderationTitle }}
          </h2>
          <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600 dark:text-slate-300">{{ moderationDescription }}</p>

          <div class="mt-7 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-left dark:border-slate-700 dark:bg-slate-800/70">
            <div class="flex items-start gap-3">
              <Bell class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
              <p class="whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ moderationNotification.message }}</p>
            </div>
          </div>

          <p v-if="!isRestrictedModeration" class="mt-5 text-sm font-medium text-slate-500 dark:text-slate-400">Confirma que has leído esta notificación para continuar.</p>
        </div>

        <div class="border-t border-slate-200 p-5 dark:border-slate-700 sm:px-10">
          <button
            type="button"
            class="btn w-full rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
            @click="acknowledgeModeration"
          >
            {{ isRestrictedModeration ? 'Entendido, cerrar sesión' : 'Entendido, continuar' }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
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
