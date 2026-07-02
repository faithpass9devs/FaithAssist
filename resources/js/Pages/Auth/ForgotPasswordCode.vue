<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
  status: { type: String, default: null },
  error: { type: String, default: null },
  maskedPhone: { type: String, default: '' },
});

const digits = ref(['', '', '', '', '', '']);
const form = useForm({ code: '' });

const handleInput = (index, value) => {
  if (!/^\d*$/.test(value)) {
    digits.value[index] = '';
    return;
  }

  digits.value[index] = value.slice(-1);

  if (value && index < 5) {
    const nextInput = document.querySelector(`input[data-digit="${index + 1}"]`);
    nextInput?.focus();
  }
};

const handleKeyDown = (index, event) => {
  if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
    const prevInput = document.querySelector(`input[data-digit="${index - 1}"]`);
    prevInput?.focus();
  }
};

const submit = () => {
  form.code = digits.value.join('');
  form.post('/forgot-password/code', { preserveScroll: true });
};
</script>

<template>
  <Head title="Validar codigo" />

  <main class="ui-auth-page px-4 py-10">
    <section class="ui-auth-card relative mx-auto p-7 sm:p-8">
      <div class="mb-6 flex items-center justify-between">
        <p class="ui-auth-kicker">FaithAssist</p>
        <Link href="/forgot-password" class="ui-auth-back-link">Volver</Link>
      </div>

      <div class="mb-6">
        <div class="ui-auth-step">
          <span class="ui-auth-step-index">3</span>
          Paso 3 de 4
        </div>
        <h1 class="ui-auth-title">Validar código</h1>
        <p class="ui-auth-subtitle">Ingresa el código de seis dígitos enviado a {{ maskedPhone || 'tu teléfono' }}.</p>
      </div>

      <div class="mb-6 grid grid-cols-4 gap-2">
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-on" />
        <div class="ui-auth-progress-off" />
      </div>

      <p v-if="status" class="ui-auth-success">
        {{ status }}
      </p>
      <p v-if="error" class="ui-auth-error">
        {{ error }}
      </p>

      <form class="space-y-5" @submit.prevent="submit">
        <div>
          <label class="ui-auth-label mb-2">Código de 6 dígitos</label>
          <div class="flex justify-center gap-2 sm:gap-3">
            <input
              v-for="(digit, index) in digits"
              :key="index"
              :value="digit"
              :data-digit="index"
              type="text"
              inputmode="numeric"
              maxlength="1"
              class="ui-auth-code-input"
              :class="{ 'border-red-400': form.errors.code }"
              @input="handleInput(index, $event.target.value)"
              @keydown="handleKeyDown(index, $event)"
            />
          </div>
          <p v-if="form.errors.code" class="mt-2 text-center text-xs font-medium text-red-600">{{ form.errors.code }}</p>
        </div>

        <button type="submit" class="ui-btn ui-btn-primary h-12 w-full" :disabled="form.processing">
          {{ form.processing ? 'Validando...' : 'Validar código' }}
        </button>
      </form>
    </section>
  </main>
</template>
