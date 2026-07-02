<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  status: { type: String, default: null },
  error: { type: String, default: null },
  countryCodes: { type: Array, default: () => [] },
  countryCode: { type: String, default: '521' },
  phone: { type: String, default: '' },
  email: { type: String, default: '' },
  registeredPhoneLast4: { type: String, default: '' },
});

const form = useForm({
  whatsapp_country_code: props.countryCode,
  whatsapp_phone: props.phone,
});

const submit = () => {
  form.post('/forgot-password', { preserveScroll: true });
};
</script>

<template>
  <Head title="Verificación de teléfono" />

  <main class="ui-auth-page px-4 py-10">
    <section class="ui-auth-card relative mx-auto p-7 sm:p-8">
      <div class="mb-6 flex items-center justify-between">
        <p class="ui-auth-kicker">FaithAssist</p>
        <Link href="/forgot-password/email" class="ui-auth-back-link">Volver</Link>
      </div>

      <div class="mb-6">
        <div class="ui-auth-step">
          <span class="ui-auth-step-index">2</span>
          Paso 2 de 4
        </div>
        <h1 class="ui-auth-title">Verificación de teléfono</h1>
        <p class="ui-auth-subtitle">Confirma el número para enviarte el código de seguridad.</p>
      </div>

      <div class="mb-6 grid grid-cols-4 gap-2">
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-off" />
        <div class="ui-auth-progress-off" />
      </div>

      <p v-if="email" class="ui-auth-info">
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cuenta</span>
        <span class="font-semibold">{{ email }}</span>
      </p>

      <p v-if="registeredPhoneLast4" class="ui-auth-note">
        Ingresa el número registrado con terminación <span class="font-semibold">{{ registeredPhoneLast4 }}</span>.
      </p>

      <p v-if="status" class="ui-auth-success">
        {{ status }}
      </p>
      <p v-if="error" class="ui-auth-error">
        {{ error }}
      </p>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="ui-auth-label">Teléfono de contacto</label>
          <p class="ui-auth-help">Asegúrate de que sea correcto para recibir el código de verificación.</p>

          <div class="flex gap-2">
            <select
              v-model="form.whatsapp_country_code"
              class="select select-bordered ui-select h-12 w-32 shrink-0"
              :class="{ 'select-error': form.errors.whatsapp_country_code }"
            >
              <option v-for="code in countryCodes" :key="code.value" :value="code.value">
                {{ code.label }}
              </option>
            </select>
            <input
              v-model="form.whatsapp_phone"
              type="text"
              class="input input-bordered ui-input h-12 w-full"
              :class="{ 'input-error': form.errors.whatsapp_phone }"
              placeholder="5512345678"
              autocomplete="tel-national"
            />
          </div>
          <p v-if="form.errors.whatsapp_country_code" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.whatsapp_country_code }}</p>
          <p v-if="form.errors.whatsapp_phone" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.whatsapp_phone }}</p>
        </div>

        <button type="submit" class="ui-btn ui-btn-primary h-12 w-full" :disabled="form.processing">
          {{ form.processing ? 'Enviando código...' : 'Enviar código' }}
        </button>
      </form>
    </section>
  </main>
</template>
