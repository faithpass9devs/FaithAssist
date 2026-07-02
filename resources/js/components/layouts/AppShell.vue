<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { MoonStar, SunMedium } from 'lucide-vue-next';
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
const { isDark, savingTheme, toggleTheme } = useTheme();

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
  if (!menuRef.value) {
    return;
  }

  if (!menuRef.value.contains(event.target)) {
    closeMenu();
  }
};

onMounted(() => {
  window.addEventListener('click', onWindowClick);
});

onBeforeUnmount(() => {
  window.removeEventListener('click', onWindowClick);
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
