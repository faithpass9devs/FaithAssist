<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  child: { type: Object, required: true },
  levels: { type: Array, default: () => [] },
});

const form = useForm({
  child_id: props.child.id,
  to_level_ids: [],
  notes: '',
});

const destinationLevels = computed(() => {
  if (!props.child.diocese_id) return props.levels;

  return props.levels.filter((level) => level.diocese_id === props.child.diocese_id);
});

const toggleDestinationLevel = (levelId) => {
  const normalizedId = Number(levelId);
  if (form.to_level_ids.includes(normalizedId)) {
    form.to_level_ids = form.to_level_ids.filter((id) => id !== normalizedId);
    return;
  }

  form.to_level_ids = [...form.to_level_ids, normalizedId];
};

const submit = () => {
  form.post('/reinscripciones', { preserveScroll: true });
};
</script>

<template>
  <AppShell :page-title="'Registrar reinscripción'">
    <CatalogHeader
      title="Registrar reinscripción"
      subtitle="Confirma la información del niño y selecciona los niveles destino"
      back-href="/reinscripciones"
      :icon="ArrowLeftRight"
    />

    <section
      class="rounded-2xl border border-rose-200 bg-rose-50/70 p-5 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/20"
    >
      <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <p
            class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-rose-700 dark:text-rose-300"
          >
            <UserRound class="h-4 w-4" />
            Niño seleccionado
          </p>
          <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">
            {{ child.full_name }}
          </h2>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ child.code }} · {{ child.church }} · {{ child.community }}
          </p>
        </div>
        <Link href="/reinscripciones" class="btn btn-ghost btn-sm">Cambiar niño</Link>
      </div>

      <div class="grid gap-4 lg:grid-cols-[1fr_1.3fr]">
        <div class="rounded-2xl border border-white/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
          <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
            Información del niño
          </h3>
          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt class="font-semibold text-slate-400">Nacimiento</dt>
              <dd class="font-bold text-slate-700 dark:text-slate-200">{{ child.birthdate }}</dd>
            </div>
            <div>
              <dt class="font-semibold text-slate-400">Correo</dt>
              <dd class="font-bold text-slate-700 dark:text-slate-200">{{ child.email || 'Sin correo' }}</dd>
            </div>
            <div>
              <dt class="font-semibold text-slate-400">Teléfono</dt>
              <dd class="font-bold text-slate-700 dark:text-slate-200">{{ child.phone || 'Sin teléfono' }}</dd>
            </div>
            <div>
              <dt class="font-semibold text-slate-400">Emergencia</dt>
              <dd class="font-bold text-slate-700 dark:text-slate-200">
                {{ child.emergency_phone || 'Sin teléfono' }}
              </dd>
            </div>
          </dl>

          <div class="mt-5">
            <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
              Niveles actuales
            </h3>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="level in child.levels"
                :key="level.assignment_id"
                class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-sm font-bold text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300"
              >
                {{ level.name }}
              </span>
            </div>
          </div>
        </div>

        <form
          class="rounded-2xl border border-white/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
          @submit.prevent="submit"
        >
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
              Niveles a asignar
            </h3>
            <span class="text-xs font-semibold text-slate-400">
              {{ form.to_level_ids.length }} seleccionado(s)
            </span>
          </div>

          <div class="grid gap-3">
            <button
              v-for="level in destinationLevels"
              :key="level.id"
              type="button"
              class="rounded-2xl border p-4 text-left transition"
              :class="
                form.to_level_ids.includes(level.id)
                  ? 'border-rose-500 bg-rose-50 text-rose-800 shadow-sm dark:border-rose-400 dark:bg-rose-950/30 dark:text-rose-200'
                  : 'border-slate-200 bg-white text-slate-700 hover:border-rose-200 hover:bg-rose-50/60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-rose-700'
              "
              @click="toggleDestinationLevel(level.id)"
            >
              <span class="flex items-center gap-3">
                <span
                  class="flex h-5 w-5 items-center justify-center rounded border text-xs font-bold"
                  :class="
                    form.to_level_ids.includes(level.id)
                      ? 'border-rose-500 bg-rose-600 text-white'
                      : 'border-slate-300 text-transparent dark:border-slate-600'
                  "
                >
                  ✓
                </span>
                <span class="font-semibold">{{ level.name }}</span>
              </span>
            </button>
          </div>

          <textarea
            v-model="form.notes"
            class="textarea textarea-bordered mt-4 w-full"
            rows="3"
            placeholder="Observaciones de reinscripción..."
          />

          <p v-if="form.hasErrors" class="mt-2 text-xs font-semibold text-red-500">
            {{ form.errors.child_id || form.errors.to_level_ids || form.errors.notes }}
          </p>

          <div class="mt-4 flex justify-end gap-2">
            <Link href="/reinscripciones" class="btn btn-ghost btn-sm">Cancelar</Link>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
              Registrar reinscripción
            </button>
          </div>
        </form>
      </div>
    </section>
  </AppShell>
</template>
