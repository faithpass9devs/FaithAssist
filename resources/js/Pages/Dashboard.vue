<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  ArrowLeftRight,
  BookOpen,
  Building2,
  CalendarDays,
  Church,
  ClipboardCheck,
  Home,
  KeyRound,
  Landmark,
  LayoutGrid,
  MapPinned,
  MessageCircle,
  QrCode,
  ShieldCheck,
  Tags,
  Users,
} from 'lucide-vue-next';
import AppShell from '../components/layouts/AppShell.vue';

const page = usePage();

const permissions = computed(() => page.props.auth?.permissions ?? []);

const hasPermission = (item) => {
  if (item.permission) {
    return permissions.value.includes(item.permission);
  }

  const moduleKey = item.moduleKey;

  if (!moduleKey) {
    return true;
  }

  return permissions.value.includes(`${moduleKey}.show`);
};

const modules = computed(() =>
  [
    {
      name: 'Regiones',
      accent: 'from-slate-400 via-slate-300 to-slate-200',
      titleClass: 'text-sky-700',
      items: [
        {
          label: 'Estados',
          icon: MapPinned,
          href: '/estados',
          moduleKey: 'estados',
        },
        {
          label: 'Municipios',
          icon: Building2,
          href: '/municipios',
          moduleKey: 'municipios',
        },
        {
          label: 'Comunidades',
          icon: Users,
          href: '/comunidades',
          moduleKey: 'comunidades',
        },
      ],
    },
    {
      name: 'Eclesiásticos',
      accent: 'from-blue-200 via-sky-100 to-slate-100',
      titleClass: 'text-sky-700',
      items: [
        {
          label: 'Diócesis',
          icon: Landmark,
          href: '/diocesis',
          moduleKey: 'diocesis',
        },
        {
          label: 'Decanatos',
          icon: BookOpen,
          href: '/decanatos',
          moduleKey: 'decanato',
        },
        {
          label: 'Parroquias',
          icon: Home,
          href: '/parroquias',
          moduleKey: 'parroquias',
        },
        {
          label: 'Iglesias',
          icon: Landmark,
          href: '/capillas',
          moduleKey: 'capillas',
        },
      ],
    },
    {
      name: 'Operación',
      accent: 'from-blue-200 via-sky-100 to-slate-100',
      titleClass: 'text-sky-700',
      items: [
        { label: 'Periodos', icon: CalendarDays, href: '/periodos', moduleKey: 'periodos' },
        {
          label: 'Tipos de Movimiento del Periodo',
          icon: Tags,
          href: '/tipos-movimientos-periodo',
          moduleKey: 'tipos_movimientos_periodo',
        },
        {
          label: 'Movimientos del Periodo',
          icon: ArrowLeftRight,
          href: '/periodo-movimientos',
          moduleKey: 'periodo_movimientos',
        },
        { label: 'Niveles', icon: LayoutGrid, href: '/niveles', moduleKey: 'niveles' },
      ],
    },
    {
      name: 'Catecismo',
      accent: 'from-amber-200 via-orange-100 to-slate-100',
      titleClass: 'text-sky-700',
      items: [
        { label: 'Niños', icon: Users, href: '/children', moduleKey: 'children' },
        {
          label: 'Reinscripciones',
          icon: ArrowLeftRight,
          href: '/reinscripciones',
          moduleKey: 'reinscripciones',
        },
      ],
    },
    {
      name: 'Misas',
      accent: 'from-purple-200 via-fuchsia-100 to-slate-100',
      titleClass: 'text-sky-700',
      items: [
        {
          label: 'Fines de semana',
          icon: CalendarDays,
          href: '/fines-semana-misas',
          moduleKey: 'weekends',
        },
        {
          label: 'Misas',
          icon: Church,
          href: '/misas',
          moduleKey: 'masses',
        },
        {
          label: 'Asistencias',
          icon: QrCode,
          href: '/asistencias',
          moduleKey: 'mass_attendance',
        },
        {
          label: 'Tipos de incidencias',
          icon: Tags,
          href: '/tipos-incidencias',
          moduleKey: 'tipos_incidencias',
        },
        {
          label: 'Incidencias de asistencia',
          icon: ClipboardCheck,
          href: '/incidencias-asistencia',
          moduleKey: 'incidencias_asistencia',
        },
      ],
    },
    {
      name: 'Seguridad',
      accent: 'from-blue-200 via-sky-100 to-slate-100',
      titleClass: 'text-sky-700',
      items: [
        {
          label: 'Módulos',
          icon: LayoutGrid,
          href: '/modulos',
          moduleKey: 'modulos',
        },
        {
          label: 'Permisos',
          icon: KeyRound,
          href: '/permisos',
          moduleKey: 'permisos',
        },
        {
          label: 'Roles',
          icon: ShieldCheck,
          href: '/roles',
          moduleKey: 'roles',
        },
        {
          label: 'Usuarios',
          icon: Users,
          href: '/usuarios',
          moduleKey: 'usuarios',
        },
      ],
    },
    {
      name: 'Comunicación',
      accent: 'from-green-200 via-emerald-100 to-slate-100',
      titleClass: 'text-sky-700',
      items: [
        {
          label: 'WhatsApp',
          icon: MessageCircle,
          href: '/whatsapp',
          moduleKey: 'whatsapp',
          permission: 'whatsapp.send',
        },
      ],
    },
  ]
    .map((module) => ({
      ...module,
      items: module.items.filter((item) => hasPermission(item)),
    }))
    .filter((module) => module.items.length > 0),
);
</script>

<template>
  <AppShell :page-title="'Inicio'">
    <div class="mx-auto grid w-full max-w-7xl gap-6 xl:grid-cols-2">
      <article
        v-for="module in modules"
        :key="module.name"
        class="overflow-hidden rounded-4xl border border-slate-200 bg-white shadow-[0_16px_50px_-30px_rgba(15,23,42,0.35)] transition-colors duration-300 dark:border-slate-800 dark:bg-slate-900 dark:shadow-[0_16px_50px_-30px_rgba(0,0,0,0.6)]"
      >
        <header class="border-b border-slate-200 px-6 py-5 sm:px-7" :class="module.accent">
          <h2
            class="text-2xl font-black tracking-tight dark:text-slate-50"
            :class="module.titleClass"
          >
            {{ module.name }}
          </h2>
        </header>

        <div class="grid gap-3 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
          <component
            :is="item.href ? Link : 'button'"
            v-for="item in module.items"
            :key="item.label"
            v-bind="item.href ? { href: item.href } : { type: 'button' }"
            class="group flex min-h-28 flex-col items-center justify-center rounded-[1.25rem] border border-slate-200 bg-slate-50 px-3 py-4 text-center transition duration-300 hover:-translate-y-1 hover:border-slate-300 hover:bg-white hover:shadow-lg dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-700 dark:hover:bg-slate-800"
            :class="{
              'opacity-50 cursor-not-allowed hover:translate-y-0 hover:shadow-none': !item.href,
            }"
          >
            <span
              class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-slate-600 shadow-sm ring-1 ring-slate-200 transition group-hover:bg-sky-100 group-hover:text-sky-700 group-hover:ring-sky-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700 dark:group-hover:bg-sky-900/30 dark:group-hover:text-sky-200 dark:group-hover:ring-sky-800"
            >
              <component :is="item.icon" class="h-5 w-5" />
            </span>

            <span
              class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-700 dark:text-slate-200"
            >
              {{ item.label }}
            </span>
          </component>
        </div>
      </article>
    </div>
  </AppShell>
</template>
