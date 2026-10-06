<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { LogOut, ShieldAlert } from 'lucide-vue-next';
import AppShell from '../../components/layouts/AppShell.vue';

const page = usePage();
const form = useForm({});
const accountStatus = computed(() => page.props.auth?.user?.account_status ?? 'blocked');
const isSuspended = computed(() => accountStatus.value === 'suspended');
const logout = () => form.post('/logout');
</script>

<template>
  <AppShell page-title="Cuenta restringida">
    <div class="mx-auto flex min-h-[70vh] max-w-2xl items-center justify-center">
      <section class="w-full rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-xl dark:border-slate-700 dark:bg-slate-900 sm:p-10">
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-300">
          <ShieldAlert class="h-11 w-11" />
        </div>
        <p class="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Acceso restringido</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900 dark:text-slate-100 sm:text-3xl">
          {{ isSuspended ? 'Cuenta suspendida por 24 horas' : 'Cuenta bloqueada por tiempo indefinido' }}
        </h1>
        <p class="mx-auto mt-4 max-w-lg text-base leading-7 text-slate-600 dark:text-slate-300">
          {{ isSuspended ? 'Podrás volver a utilizar el sistema cuando finalice la suspensión.' : 'Por favor, ponte en contacto con quien te proporcionó el usuario para solicitar el levantamiento del bloqueo.' }}
        </p>
        <button type="button" class="btn mt-8 w-full rounded-xl border-0 bg-sky-700 text-white hover:bg-sky-800 sm:w-auto" :disabled="form.processing" @click="logout">
          <LogOut class="h-4 w-4" />
          Cerrar sesión
        </button>
      </section>
    </div>
  </AppShell>
</template>
