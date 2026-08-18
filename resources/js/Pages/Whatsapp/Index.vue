<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { MessageCircle, FileText, Send, History, RefreshCcw, X } from 'lucide-vue-next';
import AppShell from '../../components/layouts/AppShell.vue';
import CatalogHeader from '../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  countryCodes: { type: Array, default: () => [] },
  selectedCountryCode: { type: String, default: '521' },
});

const form = ref({
  to_country_code: props.selectedCountryCode,
  to_phone: '',
  caption: 'Te compartimos el gafete en PDF.',
  pdf: null,
});

const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const fileName = ref('');
const history = ref([]);
const showHistory = ref(false);

const historyCount = computed(() => history.value.length);

const onlyNumbers = () => {
  form.value.to_phone = form.value.to_phone.replace(/\D/g, '').slice(0, 15);
};

const handleFile = (event) => {
  const file = event.target.files[0];

  form.value.pdf = file;
  fileName.value = file ? file.name : '';
};

const toggleHistory = async () => {
  showHistory.value = !showHistory.value;

  if (showHistory.value) {
    await loadHistory();
  }
};

const sendWhatsapp = async () => {
  loading.value = true;
  successMessage.value = '';
  errorMessage.value = '';

  if (form.value.to_phone.length < 7) {
    errorMessage.value = 'Ingresa un número de teléfono válido.';
    loading.value = false;
    return;
  }

  if (!form.value.pdf) {
    errorMessage.value = 'Selecciona un archivo PDF antes de enviar.';
    loading.value = false;
    return;
  }

  const formData = new FormData();
  formData.append('to_country_code', form.value.to_country_code);
  formData.append('to_phone', form.value.to_phone);
  formData.append('caption', form.value.caption);
  formData.append('pdf', form.value.pdf);

  try {
    const response = await fetch('/whatsapp/send', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute('content'),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: formData,
    });

    const data = await response.json();

    if (!response.ok) {
      throw data;
    }

    successMessage.value = data.message || 'PDF enviado correctamente por WhatsApp.';

    form.value.pdf = null;
    fileName.value = '';

    await loadHistory();
  } catch (error) {
    console.error('Error al enviar WhatsApp:', error);

    errorMessage.value =
      error.message ||
      error.error ||
      'No se pudo enviar el archivo por WhatsApp. Intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

const loadHistory = async () => {
  try {
    const response = await fetch('/whatsapp/history-json', {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) {
      throw new Error('No se pudo cargar el historial de envíos.');
    }

    history.value = await response.json();
  } catch (error) {
    console.error('Error al cargar historial:', error);
    history.value = [];
  }
};

const formatDate = (date) => {
  if (!date) {
    return '';
  }

  return new Date(date).toLocaleString('es-MX', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const statusClass = (status) => {
  if (status === 'sent') {
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200';
  }

  if (status === 'failed') {
    return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200';
  }

  return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200';
};

onMounted(() => {
  loadHistory();
});
</script>

<template>
  <AppShell :page-title="'WhatsApp'">
    <CatalogHeader
      title="WhatsApp"
      subtitle="Envio de gafetes PDF por WhatsApp"
      back-href="/"
      :count="historyCount"
      :icon="MessageCircle"
    />

    <section
      class="mx-auto mt-6 w-full max-w-7xl overflow-hidden rounded-4xl border border-slate-200 bg-white shadow-[0_16px_50px_-30px_rgba(15,23,42,0.35)] transition-colors duration-300 dark:border-slate-800 dark:bg-slate-900 dark:shadow-[0_16px_50px_-30px_rgba(0,0,0,0.6)]"
    >
      <header class="border-b border-slate-200 px-6 py-5 sm:px-7 dark:border-slate-800">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 class="text-xl font-black tracking-tight text-sky-700 dark:text-slate-50">
              Enviar gafete PDF
            </h2>

            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Captura el teléfono del padre o tutor, selecciona el archivo PDF y envíalo por WhatsApp.
            </p>
          </div>

          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-600 transition hover:border-slate-300 hover:bg-white hover:text-sky-700 hover:shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-800"
            @click="toggleHistory"
          >
            <History class="h-4 w-4" />
            Historial
            <span
              class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-black text-sky-700 dark:bg-sky-900/40 dark:text-sky-200"
            >
              {{ historyCount }}
            </span>
          </button>
        </div>
      </header>

      <form class="grid gap-6 p-6 sm:p-7 lg:grid-cols-[1fr_0.9fr]" @submit.prevent="sendWhatsapp">
        <div class="space-y-6">
          <div>
            <label class="mb-2 block text-sm font-extrabold text-slate-700 dark:text-slate-200">
              Teléfono del destinatario
            </label>

            <div class="flex gap-2">
              <select
                v-model="form.to_country_code"
                class="select select-bordered h-12 w-32 shrink-0 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
              >
                <option v-for="code in countryCodes" :key="code.value" :value="code.value">
                  {{ code.label }}
                </option>
              </select>

              <input
                v-model="form.to_phone"
                type="text"
                maxlength="15"
                inputmode="numeric"
                class="input input-bordered h-12 w-full rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                placeholder="5512345678"
                required
                @input="onlyNumbers"
              />
            </div>

            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
              Selecciona la lada y luego escribe el número local (solo dígitos).
            </p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-extrabold text-slate-700 dark:text-slate-200">
              Mensaje
            </label>

            <textarea
              v-model="form.caption"
              rows="5"
              class="w-full resize-none rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-sky-600 focus:bg-white focus:ring-4 focus:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:bg-slate-900"
              placeholder="Te compartimos el gafete en PDF."
            ></textarea>
          </div>
        </div>

        <div class="flex flex-col justify-between gap-6">
          <div>
            <label class="mb-2 block text-sm font-extrabold text-slate-700 dark:text-slate-200">
              Archivo PDF
            </label>

            <label
              class="group flex min-h-42.5 cursor-pointer flex-col items-center justify-center gap-4 rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-center transition hover:border-sky-600 hover:bg-sky-50/60 dark:border-slate-700 dark:bg-slate-950 dark:hover:bg-slate-800"
            >
              <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 transition group-hover:bg-sky-100 group-hover:text-sky-700 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
              >
                <FileText class="h-6 w-6" />
              </div>

              <div class="max-w-full">
                <p class="truncate font-black text-slate-700 dark:text-slate-100">
                  {{ fileName || 'Seleccionar archivo PDF' }}
                </p>

                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Tamaño maximo configurado: 20 MB
                </p>
              </div>

              <input
                type="file"
                accept="application/pdf"
                class="hidden"
                required
                @change="handleFile"
              />
            </label>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-sky-700 px-5 py-4 text-sm font-black text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Send class="h-4 w-4" />
            {{ loading ? 'Enviando PDF...' : 'Enviar PDF por WhatsApp' }}
          </button>
        </div>
      </form>

      <div class="px-6 pb-6 sm:px-7">
        <div
          v-if="successMessage"
          class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200"
        >
          {{ successMessage }}
        </div>

        <div
          v-if="errorMessage"
          class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200"
        >
          {{ errorMessage }}
        </div>
      </div>

      <div
        v-if="showHistory"
        class="border-t border-slate-200 px-6 py-5 sm:px-7 dark:border-slate-800"
      >
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-lg font-black text-sky-700 dark:text-sky-200">
              Historial de envios
            </h3>

            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Ultimos registros generados por el modulo.
            </p>
          </div>

          <div class="flex gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-sky-700 transition hover:bg-sky-50 dark:border-slate-700 dark:bg-slate-800 dark:text-sky-200"
              @click="loadHistory"
            >
              <RefreshCcw class="h-3.5 w-3.5" />
              Actualizar
            </button>

            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-500 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
              @click="showHistory = false"
            >
              <X class="h-3.5 w-3.5" />
              Cerrar
            </button>
          </div>
        </div>

        <div
          v-if="history.length === 0"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
        >
          No hay envios registrados.
        </div>

        <div
          v-else
          class="max-h-90 overflow-auto rounded-2xl border border-slate-200 dark:border-slate-700"
        >
          <table class="w-full text-left text-sm">
            <thead
              class="sticky top-0 bg-slate-50 text-xs uppercase tracking-[0.16em] text-slate-500 dark:bg-slate-800 dark:text-slate-300"
            >
              <tr>
                <th class="px-4 py-3">Telefono</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3">Fecha</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr
                v-for="item in history"
                :key="item.id"
                class="bg-white dark:bg-slate-900"
              >
                <td class="px-4 py-3 font-bold text-slate-700 dark:text-slate-100">
                  {{ item.to_phone }}
                </td>

                <td class="px-4 py-3">
                  <span
                    class="rounded-full px-3 py-1 text-[11px] font-black uppercase"
                    :class="statusClass(item.status)"
                  >
                    {{ item.status }}
                  </span>
                </td>

                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                  {{ formatDate(item.created_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </AppShell>
</template>
