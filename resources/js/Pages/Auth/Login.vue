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
  ShieldCheck,
  Sparkles,
  SunMedium,
} from 'lucide-vue-next';
import { useTheme } from '../../composables/useTheme';

const showPassword = ref(false);
const { theme, isDark, savingTheme, toggleTheme } = useTheme();

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
});

watch(
  theme,
  (value) => {
    form.theme = value;
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

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};
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
              class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-4 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 dark:bg-slate-100 dark:text-slate-900"
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
              class="ui-btn ui-btn-primary h-12 w-full bg-linear-to-r from-slate-700 to-blue-900 hover:from-slate-800 hover:to-blue-950"
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
  </main>
</template>
