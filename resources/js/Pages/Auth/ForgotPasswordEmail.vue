<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
  status: { type: String, default: null },
  error: { type: String, default: null },
});

const page = usePage();

const form = useForm({
  email: computed(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('email') || '';
  }).value,
});

const submit = () => {
  form.post('/forgot-password/email', { preserveScroll: true });
};
</script>

<template>
  <Head title="Recuperar contraseña" />

  <main class="ui-auth-page px-4 py-10">
    <section class="ui-auth-card relative mx-auto p-7 sm:p-8">
      <div class="mb-6 flex items-center justify-between">
        <p class="ui-auth-kicker">FaithAssist</p>
        <Link href="/login" class="ui-auth-back-link">Volver al login</Link>
      </div>

      <div class="mb-6">
        <div class="ui-auth-step">
          <span class="ui-auth-step-index">1</span>
          Paso 1 de 4
        </div>
        <h1 class="ui-auth-title">Recuperar contraseña</h1>
        <p class="ui-auth-subtitle">Ingresa el correo electrónico asociado a tu cuenta para comenzar la verificación.</p>
      </div>

      <div class="mb-6 grid grid-cols-4 gap-2">
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-off" />
        <div class="ui-auth-progress-off" />
        <div class="ui-auth-progress-off" />
      </div>

      <p v-if="status" class="ui-auth-success">
        {{ status }}
      </p>
      <p v-if="error" class="ui-auth-error">
        {{ error }}
      </p>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="ui-auth-label">Correo electrónico</label>
          <input
            v-model="form.email"
            type="email"
            class="input input-bordered ui-input h-12 w-full"
            :class="{ 'input-error': form.errors.email }"
            autocomplete="email"
            placeholder="tu@email.com"
          />
          <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.email }}</p>
        </div>

        <button type="submit" class="ui-btn ui-btn-primary h-12 w-full" :disabled="form.processing">
          {{ form.processing ? 'Verificando...' : 'Continuar' }}
        </button>
      </form>
    </section>
  </main>
</template>
