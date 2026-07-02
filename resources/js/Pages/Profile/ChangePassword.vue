<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Eye, EyeOff, KeyRound, ShieldCheck, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppShell from '../../components/layouts/AppShell.vue';

defineProps({
  status: {
    type: String,
    default: null,
  },
});

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);

const passwordRules = computed(() => [
  {
    label: 'Mínimo 8 caracteres',
    valid: form.password.length >= 8,
  },
  {
    label: 'Una mayúscula',
    valid: /[A-ZÁÉÍÓÚÑ]/.test(form.password),
  },
  {
    label: 'Una minúscula',
    valid: /[a-záéíóúñ]/.test(form.password),
  },
  {
    label: 'Un número',
    valid: /\d/.test(form.password),
  },
]);

const submit = () => {
  form.patch('/profile/password', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('current_password', 'password', 'password_confirmation');
    },
  });
};

const clearForm = () => {
  form.reset('current_password', 'password', 'password_confirmation');
  form.clearErrors();
};
</script>

<template>
  <Head title="Cambiar contraseña" />

  <AppShell :page-title="'Cambiar contraseña'">
    <div class="mx-auto w-full max-w-6xl">
      <header class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="mt-3 flex items-center gap-4">
              <span
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-100 text-blue-600 shadow-sm dark:bg-blue-950/60 dark:text-blue-300"
              >
                <KeyRound class="h-6 w-6" />
              </span>

              <div>
                <h1
                  class="text-3xl font-black tracking-tight text-slate-950 dark:text-slate-50 sm:text-4xl"
                >
                  Cambiar contraseña
                </h1>

                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  Actualiza tu contraseña para mantener protegida tu cuenta.
                </p>
              </div>
            </div>
          </div>

          <Link
            href="/profile"
            class="inline-flex h-11 items-center justify-center rounded-full border border-slate-300 bg-white px-8 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            Volver
          </Link>
        </div>
      </header>

      <section
        class="mx-auto mt-8 w-full max-w-3xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20"
      >
        <div
          class="border-b border-slate-200 bg-slate-50 px-6 py-5 dark:border-slate-800 dark:bg-slate-900/60 sm:px-8"
        >
          <div class="flex items-start gap-3">
            <span
              class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-950"
            >
              <ShieldCheck class="h-5 w-5" />
            </span>

            <div>
              <h2
                class="text-sm font-bold uppercase tracking-[0.22em] text-slate-600 dark:text-slate-300"
              >
                Mantén segura tu cuenta
              </h2>

              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Te recomendamos guardar tu contraseña en un lugar seguro para facilitar tu acceso.
              </p>
            </div>
          </div>
        </div>

        <div class="p-6 sm:p-8">
          <p
            v-if="status"
            class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300"
          >
            {{ status }}
          </p>

          <p
            v-if="form.hasErrors"
            class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
          >
            Verifica los campos marcados para poder actualizar tu contraseña.
          </p>

          <form class="space-y-6" @submit.prevent="submit">
            <div class="space-y-2">
              <label class="ui-label"> Contraseña actual </label>

              <div class="relative">
                <input
                  v-model="form.current_password"
                  :type="showCurrent ? 'text' : 'password'"
                  class="ui-input input input-bordered h-12 w-full rounded-xl pr-12"
                  :class="{ 'input-error': form.errors.current_password }"
                  autocomplete="current-password"
                />

                <button
                  type="button"
                  class="absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                  :aria-label="
                    showCurrent ? 'Ocultar contraseña actual' : 'Mostrar contraseña actual'
                  "
                  @click="showCurrent = !showCurrent"
                >
                  <EyeOff v-if="showCurrent" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>

              <p
                v-if="form.errors.current_password"
                class="text-xs font-semibold text-red-600 dark:text-red-400"
              >
                {{ form.errors.current_password }}
              </p>
            </div>

            <div class="space-y-2">
              <label class="ui-label"> Nueva contraseña </label>

              <div class="relative">
                <input
                  v-model="form.password"
                  :type="showNew ? 'text' : 'password'"
                  class="ui-input input input-bordered h-12 w-full rounded-xl pr-12"
                  :class="{ 'input-error': form.errors.password }"
                  autocomplete="new-password"
                />

                <button
                  type="button"
                  class="absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                  :aria-label="showNew ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'"
                  @click="showNew = !showNew"
                >
                  <EyeOff v-if="showNew" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>

              <div
                class="mt-3 grid gap-2 rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/40 sm:grid-cols-2"
              >
                <div
                  v-for="rule in passwordRules"
                  :key="rule.label"
                  class="flex items-center gap-2 text-sm font-medium"
                  :class="
                    rule.valid
                      ? 'text-emerald-600 dark:text-emerald-300'
                      : 'text-slate-500 dark:text-slate-400'
                  "
                >
                  <span
                    class="flex h-5 w-5 items-center justify-center rounded-full"
                    :class="
                      rule.valid
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'bg-slate-200 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                    "
                  >
                    <Check v-if="rule.valid" class="h-3.5 w-3.5" />
                    <X v-else class="h-3.5 w-3.5" />
                  </span>

                  {{ rule.label }}
                </div>
              </div>

              <p
                v-if="form.errors.password"
                class="text-xs font-semibold text-red-600 dark:text-red-400"
              >
                {{ form.errors.password }}
              </p>
            </div>

            <div class="space-y-2">
              <label class="ui-label"> Confirmar nueva contraseña </label>

              <div class="relative">
                <input
                  v-model="form.password_confirmation"
                  :type="showConfirm ? 'text' : 'password'"
                  class="ui-input input input-bordered h-12 w-full rounded-xl pr-12"
                  :class="{ 'input-error': form.errors.password_confirmation }"
                  autocomplete="new-password"
                />

                <button
                  type="button"
                  class="absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                  :aria-label="showConfirm ? 'Ocultar confirmación' : 'Mostrar confirmación'"
                  @click="showConfirm = !showConfirm"
                >
                  <EyeOff v-if="showConfirm" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>

              <p
                v-if="form.password_confirmation && form.password !== form.password_confirmation"
                class="text-xs font-semibold text-amber-600 dark:text-amber-400"
              >
                La confirmación todavía no coincide con la nueva contraseña.
              </p>

              <p
                v-if="form.errors.password_confirmation"
                class="text-xs font-semibold text-red-600 dark:text-red-400"
              >
                {{ form.errors.password_confirmation }}
              </p>
            </div>
            <div class="pt-2">
              <button
                type="submit"
                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                :disabled="form.processing"
              >
                <Check class="h-4 w-4" />
                {{ form.processing ? 'Actualizando...' : 'Actualizar contraseña' }}
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </AppShell>
</template>
