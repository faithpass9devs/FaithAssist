<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
  status: { type: String, default: null },
  error: { type: String, default: null },
  maskedPhone: { type: String, default: '' },
});

const form = useForm({
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post('/forgot-password/reset', { preserveScroll: true });
};
</script>

<template>
  <Head title="Restablecer contrasena" />

  <main class="ui-auth-page px-4 py-10">
    <section class="ui-auth-card relative mx-auto p-7 sm:p-8">
      <div class="mb-6 flex items-center justify-between">
        <p class="ui-auth-kicker">FaithAssist</p>
        <Link href="/forgot-password/code" class="ui-auth-back-link">Volver</Link>
      </div>

      <div class="mb-6">
        <div class="ui-auth-step">
          <span class="ui-auth-step-index">4</span>
          Paso 4 de 4
        </div>
        <h1 class="ui-auth-title">Restablecer contraseña</h1>
        <p class="ui-auth-subtitle">Define una nueva contraseña para la cuenta asociada a {{ maskedPhone || 'tu teléfono' }}.</p>
      </div>

      <div class="mb-6 grid grid-cols-4 gap-2">
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-on" />
      </div>

      <p v-if="status" class="ui-auth-success">
        {{ status }}
      </p>
      <p v-if="error" class="ui-auth-error">
        {{ error }}
      </p>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="ui-auth-label">Nueva contraseña</label>
          <input
            v-model="form.password"
            type="password"
            class="input input-bordered ui-input h-12 w-full"
            :class="{ 'input-error': form.errors.password }"
            autocomplete="new-password"
          />
        </div>

        <div>
          <label class="ui-auth-label">Confirmar contraseña</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            class="input input-bordered ui-input h-12 w-full"
            autocomplete="new-password"
          />
        </div>

        <p v-if="form.errors.password" class="text-xs font-medium text-red-600">{{ form.errors.password }}</p>

        <button type="submit" class="ui-btn ui-btn-primary h-12 w-full" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : 'Restablecer contraseña' }}
        </button>
      </form>
    </section>
  </main>
</template>
