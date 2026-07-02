<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '../../components/layouts/AppShell.vue';

const page = usePage();

const profile = computed(() => page.props.auth?.user?.profile ?? null);
const user = computed(() => page.props.auth?.user ?? null);

const initials = computed(() => {
  if (user.value?.initials) {
    return user.value.initials;
  }

  const firstName = profile.value?.name?.trim() ?? '';
  const lastName = profile.value?.paterno?.trim() ?? '';

  if (firstName && lastName) {
    return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
  }

  const emailName = (user.value?.email ?? '').split('@')[0]?.trim() ?? '';

  return (emailName.charAt(0) || 'U').toUpperCase();
});
</script>

<template>
  <AppShell :page-title="'Perfil'">
    <section
      class="mx-auto w-full max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-lg transition-colors duration-300 dark:border-slate-800 dark:bg-slate-900 sm:p-8"
    >
      <div class="flex flex-col items-center gap-4 text-center">
        <div
          class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-2 border-blue-200 bg-blue-600 text-2xl font-black uppercase text-white shadow-md shadow-blue-900/20 dark:border-blue-800 dark:bg-blue-500"
        >
          {{ initials }}
        </div>

        <div>
          <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Perfil</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
            Informacion base para encabezado y sesion.
          </p>
        </div>
      </div>

      <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div
          class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950"
        >
          <dt
            class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
          >
            Nombre
          </dt>
          <dd class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
            {{ profile?.name || '-' }}
          </dd>
        </div>
        <div
          class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950"
        >
          <dt
            class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
          >
            Apellido paterno
          </dt>
          <dd class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
            {{ profile?.paterno || '-' }}
          </dd>
        </div>
        <div
          class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950"
        >
          <dt
            class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
          >
            Apellido materno
          </dt>
          <dd class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
            {{ profile?.materno || '-' }}
          </dd>
        </div>
        <div
          class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950"
        >
          <dt
            class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
          >
            Correo
          </dt>
          <dd class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
            {{ user?.email || '-' }}
          </dd>
        </div>
      </dl>

      <div class="mt-6">
        <Link
          href="/profile/password"
          class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-blue-500 bg-white px-4 text-sm font-semibold text-blue-600 transition hover:bg-blue-50 hover:text-blue-700 dark:border-blue-400 dark:bg-slate-900 dark:text-blue-300 dark:hover:bg-slate-800"
        >
          <KeyRound class="h-4 w-4" />
          Cambiar contraseña
        </Link>
      </div>
    </section>
  </AppShell>
</template>
