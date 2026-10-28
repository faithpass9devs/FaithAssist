<script setup>
import { Link } from '@inertiajs/vue3';
import { UserRound } from 'lucide-vue-next';
import AppPagination from '../../../components/AppPagination.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

defineProps({
  child: { type: Object, required: true },
  attendances: { type: Object, required: true },
});
</script>

<template>
  <AppShell :page-title="`Panel de ${child.full_name}`">
    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="flex items-center gap-2 text-xl font-black text-slate-800 dark:text-slate-100">
            <UserRound class="h-5 w-5 text-sky-600 dark:text-sky-400" />
            {{ child.full_name }}
          </h1>
          <p class="mt-1 font-mono text-xs text-slate-500">{{ child.code }}</p>
        </div>

        <Link href="/children" class="btn btn-sm btn-outline rounded-xl">Volver a niños</Link>
      </div>

      <div class="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-2 lg:grid-cols-3 dark:text-slate-300">
        <p><strong>Iglesia:</strong> {{ child.church || '—' }}</p>
        <p><strong>Comunidad:</strong> {{ child.community || '—' }}</p>
        <p><strong>Estado:</strong> {{ child.status || '—' }}</p>
        <p><strong>Fecha de nacimiento:</strong> {{ child.birthdate || '—' }}</p>
        <p><strong>Teléfono:</strong> {{ `${child.phone_lada || ''}${child.phone || ''}` || '—' }}</p>
        <p><strong>Email:</strong> {{ child.email || '—' }}</p>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Asistencia</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
              <th class="px-4 py-3 font-semibold">Fin de semana</th>
              <th class="px-4 py-3 font-semibold">Misa</th>
              <th class="px-4 py-3 font-semibold">Ubicación</th>
              <th class="px-4 py-3 font-semibold">Entrada</th>
              <th class="px-4 py-3 font-semibold">Salida</th>
              <th class="px-4 py-3 font-semibold">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="attendance in attendances.data"
              :key="attendance.id"
              class="border-b border-slate-100 dark:border-slate-800"
            >
              <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ attendance.weekend_name || '—' }}</td>
              <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ attendance.mass_name || '—' }}</td>
              <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ attendance.location || '—' }}</td>
              <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ attendance.check_in_at || '—' }}</td>
              <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ attendance.check_out_at || '—' }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="attendance.valid ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'"
                >
                  {{ attendance.valid ? 'Válida' : 'Pendiente' }}
                </span>
              </td>
            </tr>

            <tr v-if="attendances.data.length === 0">
              <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                Este niño no tiene asistencias registradas.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <AppPagination
      :links="attendances.links"
      :from="attendances.from"
      :to="attendances.to"
      :total="attendances.total"
    />
  </AppShell>
</template>
