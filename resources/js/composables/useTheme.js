import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const THEMES = ['light', 'dark'];
const PALETTE_STORAGE_KEY = 'faithassist.ui.palette';
const CUSTOM_COLOR_STORAGE_KEY = 'faithassist.ui.customColor';
const CUSTOM_PALETTE_ID = 'custom';
const RESET_NEUTRAL_PALETTE_ID = 'neutral';
const DEFAULT_CUSTOM_COLOR = '#3b82f6';

export const PALETTE_OPTIONS = [
  {
    id: 'custom',
    label: 'Personalizado',
    swatch: DEFAULT_CUSTOM_COLOR,
  },
  {
    id: RESET_NEUTRAL_PALETTE_ID,
    label: 'Neutro',
    swatch: '#ffffff',
    light: {
      shellBg: '#ffffff',
      shellBgImage: 'none',
      authBg: '#ffffff',
      authBgImage: 'none',
      topbarBg: '#ffffff',
      topbarBorder: '#ffffff',
      topbarShadow: 'none',
      brandColor: '#000000',
    },
    dark: {
      shellBg: '#000000',
      shellBgImage: 'none',
      authBg: '#000000',
      authBgImage: 'none',
      topbarBg: '#000000',
      topbarBorder: '#000000',
      topbarShadow: 'none',
      brandColor: '#ffffff',
    },
  },
  {
    id: 'azure',
    label: 'Azul Clasico',
    swatch: '#2f5d9b',
    light: {
      shellBg: '#f2f6fc',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(47, 93, 155, 0.22) 0%, rgba(242, 246, 252, 0) 58%), linear-gradient(145deg, #f7fbff 0%, #e9f0f8 100%)',
      authBg: '#eef4fb',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(47, 93, 155, 0.16) 0%, rgba(238, 244, 251, 0) 55%), linear-gradient(145deg, #f7fbff 0%, #e8f0f8 100%)',
      topbarBg: 'rgba(236, 243, 251, 0.96)',
      topbarBorder: 'rgba(148, 163, 184, 0.68)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: '#0b1220',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(47, 93, 155, 0.25) 0%, rgba(11, 18, 32, 0) 60%), linear-gradient(145deg, #0b1220 0%, #111c30 100%)',
      authBg: '#0b1220',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(47, 93, 155, 0.2) 0%, rgba(11, 18, 32, 0) 55%), linear-gradient(145deg, #0b1220 0%, #111c30 100%)',
      topbarBg: 'rgba(11, 18, 32, 0.9)',
      topbarBorder: 'rgba(30, 41, 59, 0.9)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.35)',
      brandColor: '#f8fafc',
    },
  },
  {
    id: 'steel',
    label: 'Azul Acero',
    swatch: '#4a5c6a',
    light: {
      shellBg: '#f2f5f8',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(74, 92, 106, 0.18) 0%, rgba(242, 245, 248, 0) 58%), linear-gradient(145deg, #f7fafc 0%, #e9eef2 100%)',
      authBg: '#edf2f6',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(74, 92, 106, 0.14) 0%, rgba(237, 242, 246, 0) 55%), linear-gradient(145deg, #f7fafc 0%, #e7edf2 100%)',
      topbarBg: 'rgba(235, 240, 245, 0.96)',
      topbarBorder: 'rgba(148, 163, 184, 0.62)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#111827',
    },
    dark: {
      shellBg: '#06141b',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(74, 92, 106, 0.2) 0%, rgba(6, 20, 27, 0) 60%), linear-gradient(145deg, #06141b 0%, #11212d 100%)',
      authBg: '#06141b',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(74, 92, 106, 0.22) 0%, rgba(6, 20, 27, 0) 55%), linear-gradient(145deg, #06141b 0%, #11212d 100%)',
      topbarBg: 'rgba(17, 33, 45, 0.9)',
      topbarBorder: 'rgba(37, 55, 69, 0.9)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.38)',
      brandColor: '#e5e7eb',
    },
  },
  {
    id: 'forest',
    label: 'Verde Bosque',
    swatch: '#235347',
    light: {
      shellBg: '#eff6f3',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(35, 83, 71, 0.18) 0%, rgba(239, 246, 243, 0) 58%), linear-gradient(145deg, #f7fcf9 0%, #e8f2ed 100%)',
      authBg: '#ecf5f0',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(35, 83, 71, 0.14) 0%, rgba(236, 245, 240, 0) 55%), linear-gradient(145deg, #f7fcf9 0%, #e6f0ea 100%)',
      topbarBg: 'rgba(233, 243, 237, 0.96)',
      topbarBorder: 'rgba(110, 138, 126, 0.58)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: '#051f20',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(35, 83, 71, 0.22) 0%, rgba(5, 31, 32, 0) 60%), linear-gradient(145deg, #051f20 0%, #0b2b26 100%)',
      authBg: '#051f20',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(35, 83, 71, 0.24) 0%, rgba(5, 31, 32, 0) 55%), linear-gradient(145deg, #051f20 0%, #0b2b26 100%)',
      topbarBg: 'rgba(11, 43, 38, 0.9)',
      topbarBorder: 'rgba(22, 56, 50, 0.9)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.38)',
      brandColor: '#ecfdf5',
    },
  },
  {
    id: 'violet',
    label: 'Violeta Corporativo',
    swatch: '#6b46c1',
    light: {
      shellBg: '#f5f2fb',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(107, 70, 193, 0.2) 0%, rgba(245, 242, 251, 0) 58%), linear-gradient(145deg, #faf7ff 0%, #eee7fa 100%)',
      authBg: '#f1ecf9',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(107, 70, 193, 0.14) 0%, rgba(241, 236, 249, 0) 55%), linear-gradient(145deg, #faf7ff 0%, #ece5f8 100%)',
      topbarBg: 'rgba(238, 232, 248, 0.96)',
      topbarBorder: 'rgba(167, 139, 250, 0.55)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#111827',
    },
    dark: {
      shellBg: '#1f1535',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(107, 70, 193, 0.2) 0%, rgba(31, 21, 53, 0) 60%), linear-gradient(145deg, #1f1535 0%, #2d1f4f 100%)',
      authBg: '#1f1535',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(107, 70, 193, 0.22) 0%, rgba(31, 21, 53, 0) 55%), linear-gradient(145deg, #1f1535 0%, #2d1f4f 100%)',
      topbarBg: 'rgba(45, 31, 79, 0.9)',
      topbarBorder: 'rgba(76, 29, 149, 0.8)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.38)',
      brandColor: '#f5f3ff',
    },
  },
  {
    id: 'cobalt',
    label: 'Cobalto',
    swatch: '#1d5fd0',
    light: {
      shellBg: '#eff4fd',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(29, 95, 208, 0.2) 0%, rgba(239, 244, 253, 0) 58%), linear-gradient(145deg, #f6f9ff 0%, #e8effb 100%)',
      authBg: '#ebf2fd',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(29, 95, 208, 0.14) 0%, rgba(235, 242, 253, 0) 55%), linear-gradient(145deg, #f7faff 0%, #e7effb 100%)',
      topbarBg: 'rgba(233, 241, 252, 0.96)',
      topbarBorder: 'rgba(96, 165, 250, 0.52)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: '#112246',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(29, 95, 208, 0.24) 0%, rgba(17, 34, 70, 0) 60%), linear-gradient(145deg, #112246 0%, #1a2f5a 100%)',
      authBg: '#112246',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(29, 95, 208, 0.22) 0%, rgba(17, 34, 70, 0) 55%), linear-gradient(145deg, #112246 0%, #1a2f5a 100%)',
      topbarBg: 'rgba(17, 34, 70, 0.9)',
      topbarBorder: 'rgba(37, 99, 235, 0.7)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.38)',
      brandColor: '#dbeafe',
    },
  },
  {
    id: 'slate',
    label: 'Pizarra',
    swatch: '#596579',
    light: {
      shellBg: '#f2f4f8',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(89, 101, 121, 0.16) 0%, rgba(242, 244, 248, 0) 58%), linear-gradient(145deg, #f8f9fc 0%, #e9edf3 100%)',
      authBg: '#eef2f7',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(89, 101, 121, 0.12) 0%, rgba(238, 242, 247, 0) 55%), linear-gradient(145deg, #f8f9fc 0%, #e8edf3 100%)',
      topbarBg: 'rgba(234, 239, 246, 0.96)',
      topbarBorder: 'rgba(148, 163, 184, 0.55)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: '#1f2735',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(89, 101, 121, 0.22) 0%, rgba(31, 39, 53, 0) 60%), linear-gradient(145deg, #1f2735 0%, #2e3645 100%)',
      authBg: '#1f2735',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(89, 101, 121, 0.2) 0%, rgba(31, 39, 53, 0) 55%), linear-gradient(145deg, #1f2735 0%, #2e3645 100%)',
      topbarBg: 'rgba(31, 39, 53, 0.92)',
      topbarBorder: 'rgba(100, 116, 139, 0.65)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.38)',
      brandColor: '#e2e8f0',
    },
  },
  {
    id: 'teal',
    label: 'Teal',
    swatch: '#0f766e',
    light: {
      shellBg: '#edf8f7',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(15, 118, 110, 0.2) 0%, rgba(237, 248, 247, 0) 58%), linear-gradient(145deg, #f6fcfb 0%, #e4f2f0 100%)',
      authBg: '#e9f6f5',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(15, 118, 110, 0.14) 0%, rgba(233, 246, 245, 0) 55%), linear-gradient(145deg, #f6fcfb 0%, #e2f1ee 100%)',
      topbarBg: 'rgba(229, 243, 241, 0.96)',
      topbarBorder: 'rgba(94, 234, 212, 0.45)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: '#052f2d',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(15, 118, 110, 0.24) 0%, rgba(5, 47, 45, 0) 60%), linear-gradient(145deg, #052f2d 0%, #0b4a45 100%)',
      authBg: '#052f2d',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(15, 118, 110, 0.22) 0%, rgba(5, 47, 45, 0) 55%), linear-gradient(145deg, #052f2d 0%, #0b4a45 100%)',
      topbarBg: 'rgba(5, 47, 45, 0.92)',
      topbarBorder: 'rgba(45, 212, 191, 0.52)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#ccfbf1',
    },
  },
  {
    id: 'mint',
    label: 'Menta',
    swatch: '#70c7b7',
    light: {
      shellBg: '#edf9f7',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(112, 199, 183, 0.2) 0%, rgba(237, 249, 247, 0) 58%), linear-gradient(145deg, #f6fdfa 0%, #e2f3ef 100%)',
      authBg: '#ebf8f5',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(112, 199, 183, 0.14) 0%, rgba(235, 248, 245, 0) 55%), linear-gradient(145deg, #f6fdfa 0%, #e1f2ed 100%)',
      topbarBg: 'rgba(229, 244, 240, 0.96)',
      topbarBorder: 'rgba(94, 234, 212, 0.42)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: '#0f3d39',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(112, 199, 183, 0.24) 0%, rgba(15, 61, 57, 0) 60%), linear-gradient(145deg, #0f3d39 0%, #16564f 100%)',
      authBg: '#0f3d39',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(112, 199, 183, 0.2) 0%, rgba(15, 61, 57, 0) 55%), linear-gradient(145deg, #0f3d39 0%, #16564f 100%)',
      topbarBg: 'rgba(15, 61, 57, 0.92)',
      topbarBorder: 'rgba(94, 234, 212, 0.45)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#d1fae5',
    },
  },
  {
    id: 'green',
    label: 'Verde Vivo',
    swatch: '#3a8f3a',
    light: {
      shellBg: '#f0f8ec',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(58, 143, 58, 0.2) 0%, rgba(240, 248, 236, 0) 58%), linear-gradient(145deg, #f8fdf6 0%, #e9f4e4 100%)',
      authBg: '#edf7e9',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(58, 143, 58, 0.14) 0%, rgba(237, 247, 233, 0) 55%), linear-gradient(145deg, #f8fdf6 0%, #e7f2e2 100%)',
      topbarBg: 'rgba(232, 243, 227, 0.96)',
      topbarBorder: 'rgba(134, 239, 172, 0.5)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: '#214f21',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(58, 143, 58, 0.24) 0%, rgba(33, 79, 33, 0) 60%), linear-gradient(145deg, #214f21 0%, #2f6f2f 100%)',
      authBg: '#214f21',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(58, 143, 58, 0.2) 0%, rgba(33, 79, 33, 0) 55%), linear-gradient(145deg, #214f21 0%, #2f6f2f 100%)',
      topbarBg: 'rgba(33, 79, 33, 0.92)',
      topbarBorder: 'rgba(74, 222, 128, 0.52)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#dcfce7',
    },
  },
  {
    id: 'olive',
    label: 'Oliva',
    swatch: '#576854',
    light: {
      shellBg: '#f2f6ef',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(87, 104, 84, 0.18) 0%, rgba(242, 246, 239, 0) 58%), linear-gradient(145deg, #f9fcf7 0%, #eaf1e6 100%)',
      authBg: '#eff5ec',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(87, 104, 84, 0.13) 0%, rgba(239, 245, 236, 0) 55%), linear-gradient(145deg, #f8fcf5 0%, #e7efe2 100%)',
      topbarBg: 'rgba(235, 241, 231, 0.96)',
      topbarBorder: 'rgba(163, 177, 152, 0.55)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#1f2937',
    },
    dark: {
      shellBg: '#2f3a2d',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(87, 104, 84, 0.24) 0%, rgba(47, 58, 45, 0) 60%), linear-gradient(145deg, #2f3a2d 0%, #3f4a3d 100%)',
      authBg: '#2f3a2d',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(87, 104, 84, 0.2) 0%, rgba(47, 58, 45, 0) 55%), linear-gradient(145deg, #2f3a2d 0%, #3f4a3d 100%)',
      topbarBg: 'rgba(47, 58, 45, 0.92)',
      topbarBorder: 'rgba(163, 177, 152, 0.45)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#ecfdf5',
    },
  },
  {
    id: 'amber',
    label: 'Ambar',
    swatch: '#c6a814',
    light: {
      shellBg: '#faf5df',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(198, 168, 20, 0.2) 0%, rgba(250, 245, 223, 0) 58%), linear-gradient(145deg, #fffbe9 0%, #f3e9be 100%)',
      authBg: '#f8f2d8',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(198, 168, 20, 0.14) 0%, rgba(248, 242, 216, 0) 55%), linear-gradient(145deg, #fffbe8 0%, #f2e7bc 100%)',
      topbarBg: 'rgba(247, 239, 211, 0.96)',
      topbarBorder: 'rgba(217, 179, 75, 0.58)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#3f3200',
    },
    dark: {
      shellBg: '#4d4100',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(198, 168, 20, 0.24) 0%, rgba(77, 65, 0, 0) 60%), linear-gradient(145deg, #4d4100 0%, #706100 100%)',
      authBg: '#4d4100',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(198, 168, 20, 0.22) 0%, rgba(77, 65, 0, 0) 55%), linear-gradient(145deg, #4d4100 0%, #706100 100%)',
      topbarBg: 'rgba(77, 65, 0, 0.92)',
      topbarBorder: 'rgba(250, 204, 21, 0.55)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#fef3c7',
    },
  },
  {
    id: 'orange',
    label: 'Naranja',
    swatch: '#c46a1b',
    light: {
      shellBg: '#fbeee3',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(196, 106, 27, 0.2) 0%, rgba(251, 238, 227, 0) 58%), linear-gradient(145deg, #fff6ef 0%, #f6dfcd 100%)',
      authBg: '#f9eadf',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(196, 106, 27, 0.14) 0%, rgba(249, 234, 223, 0) 55%), linear-gradient(145deg, #fff5ed 0%, #f4ddca 100%)',
      topbarBg: 'rgba(248, 231, 218, 0.96)',
      topbarBorder: 'rgba(251, 146, 60, 0.55)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#3b1f08',
    },
    dark: {
      shellBg: '#552b06',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(196, 106, 27, 0.24) 0%, rgba(85, 43, 6, 0) 60%), linear-gradient(145deg, #552b06 0%, #7a3f0a 100%)',
      authBg: '#552b06',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(196, 106, 27, 0.2) 0%, rgba(85, 43, 6, 0) 55%), linear-gradient(145deg, #552b06 0%, #7a3f0a 100%)',
      topbarBg: 'rgba(85, 43, 6, 0.92)',
      topbarBorder: 'rgba(249, 115, 22, 0.55)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#ffedd5',
    },
  },
  {
    id: 'rose',
    label: 'Rosa',
    swatch: '#b55a7b',
    light: {
      shellBg: '#fbecf1',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(181, 90, 123, 0.2) 0%, rgba(251, 236, 241, 0) 58%), linear-gradient(145deg, #fff5f7 0%, #f7dde6 100%)',
      authBg: '#f9e9ee',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(181, 90, 123, 0.14) 0%, rgba(249, 233, 238, 0) 55%), linear-gradient(145deg, #fff4f7 0%, #f5dbe4 100%)',
      topbarBg: 'rgba(248, 231, 238, 0.96)',
      topbarBorder: 'rgba(244, 114, 182, 0.5)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#3a1020',
    },
    dark: {
      shellBg: '#5d2b3d',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(181, 90, 123, 0.24) 0%, rgba(93, 43, 61, 0) 60%), linear-gradient(145deg, #5d2b3d 0%, #7e3a52 100%)',
      authBg: '#5d2b3d',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(181, 90, 123, 0.2) 0%, rgba(93, 43, 61, 0) 55%), linear-gradient(145deg, #5d2b3d 0%, #7e3a52 100%)',
      topbarBg: 'rgba(93, 43, 61, 0.92)',
      topbarBorder: 'rgba(244, 114, 182, 0.5)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#fce7f3',
    },
  },
  {
    id: 'mauve',
    label: 'Malva',
    swatch: '#8f6d7a',
    light: {
      shellBg: '#f7edf1',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(143, 109, 122, 0.18) 0%, rgba(247, 237, 241, 0) 58%), linear-gradient(145deg, #fdf7f9 0%, #f0dfe6 100%)',
      authBg: '#f4e9ed',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(143, 109, 122, 0.14) 0%, rgba(244, 233, 237, 0) 55%), linear-gradient(145deg, #fcf6f8 0%, #eedde4 100%)',
      topbarBg: 'rgba(243, 231, 236, 0.96)',
      topbarBorder: 'rgba(196, 163, 176, 0.52)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#2f1d24',
    },
    dark: {
      shellBg: '#4b3a42',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(143, 109, 122, 0.24) 0%, rgba(75, 58, 66, 0) 60%), linear-gradient(145deg, #4b3a42 0%, #654e58 100%)',
      authBg: '#4b3a42',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(143, 109, 122, 0.2) 0%, rgba(75, 58, 66, 0) 55%), linear-gradient(145deg, #4b3a42 0%, #654e58 100%)',
      topbarBg: 'rgba(75, 58, 66, 0.92)',
      topbarBorder: 'rgba(196, 163, 176, 0.45)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#f5eaf0',
    },
  },
  {
    id: 'orchid',
    label: 'Orquidea',
    swatch: '#b065b1',
    light: {
      shellBg: '#f9edf9',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(176, 101, 177, 0.2) 0%, rgba(249, 237, 249, 0) 58%), linear-gradient(145deg, #fff6ff 0%, #f2e0f3 100%)',
      authBg: '#f7eaf7',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(176, 101, 177, 0.14) 0%, rgba(247, 234, 247, 0) 55%), linear-gradient(145deg, #fff5ff 0%, #f0ddef 100%)',
      topbarBg: 'rgba(245, 231, 245, 0.96)',
      topbarBorder: 'rgba(217, 70, 239, 0.45)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#351135',
    },
    dark: {
      shellBg: '#5e2c5e',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(176, 101, 177, 0.24) 0%, rgba(94, 44, 94, 0) 60%), linear-gradient(145deg, #5e2c5e 0%, #7d3a7d 100%)',
      authBg: '#5e2c5e',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(176, 101, 177, 0.2) 0%, rgba(94, 44, 94, 0) 55%), linear-gradient(145deg, #5e2c5e 0%, #7d3a7d 100%)',
      topbarBg: 'rgba(94, 44, 94, 0.92)',
      topbarBorder: 'rgba(217, 70, 239, 0.45)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#fdf4ff',
    },
  },
  {
    id: 'purple',
    label: 'Purpura',
    swatch: '#7e62bd',
    light: {
      shellBg: '#f4f0fb',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(126, 98, 189, 0.2) 0%, rgba(244, 240, 251, 0) 58%), linear-gradient(145deg, #faf7ff 0%, #e9e1f8 100%)',
      authBg: '#f1ecf9',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(126, 98, 189, 0.14) 0%, rgba(241, 236, 249, 0) 55%), linear-gradient(145deg, #faf7ff 0%, #e7def6 100%)',
      topbarBg: 'rgba(238, 232, 248, 0.96)',
      topbarBorder: 'rgba(167, 139, 250, 0.5)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#211336',
    },
    dark: {
      shellBg: '#4a3a72',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(126, 98, 189, 0.24) 0%, rgba(74, 58, 114, 0) 60%), linear-gradient(145deg, #4a3a72 0%, #634d96 100%)',
      authBg: '#4a3a72',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(126, 98, 189, 0.2) 0%, rgba(74, 58, 114, 0) 55%), linear-gradient(145deg, #4a3a72 0%, #634d96 100%)',
      topbarBg: 'rgba(74, 58, 114, 0.92)',
      topbarBorder: 'rgba(167, 139, 250, 0.45)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#ede9fe',
    },
  },
  {
    id: 'graphite',
    label: 'Grafito',
    swatch: '#4b5563',
    light: {
      shellBg: '#f3f4f6',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(75, 85, 99, 0.18) 0%, rgba(243, 244, 246, 0) 58%), linear-gradient(145deg, #fafafa 0%, #eceef1 100%)',
      authBg: '#eff1f3',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(75, 85, 99, 0.14) 0%, rgba(239, 241, 243, 0) 55%), linear-gradient(145deg, #f9fafb 0%, #eaedf1 100%)',
      topbarBg: 'rgba(235, 238, 242, 0.96)',
      topbarBorder: 'rgba(148, 163, 184, 0.56)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#111827',
    },
    dark: {
      shellBg: '#1f2937',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(75, 85, 99, 0.24) 0%, rgba(31, 41, 55, 0) 60%), linear-gradient(145deg, #1f2937 0%, #374151 100%)',
      authBg: '#1f2937',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(75, 85, 99, 0.2) 0%, rgba(31, 41, 55, 0) 55%), linear-gradient(145deg, #1f2937 0%, #374151 100%)',
      topbarBg: 'rgba(31, 41, 55, 0.92)',
      topbarBorder: 'rgba(107, 114, 128, 0.55)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#f3f4f6',
    },
  },
  {
    id: 'cyan',
    label: 'Cian',
    swatch: '#0891b2',
    light: {
      shellBg: '#ecf9fc',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(8, 145, 178, 0.2) 0%, rgba(236, 249, 252, 0) 58%), linear-gradient(145deg, #f5fcff 0%, #e3f3f8 100%)',
      authBg: '#e9f7fb',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(8, 145, 178, 0.14) 0%, rgba(233, 247, 251, 0) 55%), linear-gradient(145deg, #f4fcff 0%, #e1f2f7 100%)',
      topbarBg: 'rgba(228, 245, 250, 0.96)',
      topbarBorder: 'rgba(34, 211, 238, 0.48)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#082f49',
    },
    dark: {
      shellBg: '#0b3a46',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(8, 145, 178, 0.24) 0%, rgba(11, 58, 70, 0) 60%), linear-gradient(145deg, #0b3a46 0%, #115565 100%)',
      authBg: '#0b3a46',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(8, 145, 178, 0.2) 0%, rgba(11, 58, 70, 0) 55%), linear-gradient(145deg, #0b3a46 0%, #115565 100%)',
      topbarBg: 'rgba(11, 58, 70, 0.92)',
      topbarBorder: 'rgba(34, 211, 238, 0.5)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#cffafe',
    },
  },
  {
    id: 'indigo',
    label: 'Indigo',
    swatch: '#4f46e5',
    light: {
      shellBg: '#f0f2ff',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(79, 70, 229, 0.2) 0%, rgba(240, 242, 255, 0) 58%), linear-gradient(145deg, #f7f8ff 0%, #e7e9fb 100%)',
      authBg: '#eceeff',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(79, 70, 229, 0.14) 0%, rgba(236, 238, 255, 0) 55%), linear-gradient(145deg, #f7f8ff 0%, #e5e8fb 100%)',
      topbarBg: 'rgba(234, 236, 252, 0.96)',
      topbarBorder: 'rgba(129, 140, 248, 0.55)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#1e1b4b',
    },
    dark: {
      shellBg: '#2a2760',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(79, 70, 229, 0.24) 0%, rgba(42, 39, 96, 0) 60%), linear-gradient(145deg, #2a2760 0%, #3b3688 100%)',
      authBg: '#2a2760',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(79, 70, 229, 0.2) 0%, rgba(42, 39, 96, 0) 55%), linear-gradient(145deg, #2a2760 0%, #3b3688 100%)',
      topbarBg: 'rgba(42, 39, 96, 0.92)',
      topbarBorder: 'rgba(129, 140, 248, 0.5)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#e0e7ff',
    },
  },
  {
    id: 'ruby',
    label: 'Rubi',
    swatch: '#be123c',
    light: {
      shellBg: '#fdf0f4',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(190, 18, 60, 0.18) 0%, rgba(253, 240, 244, 0) 58%), linear-gradient(145deg, #fff7f9 0%, #f8e2e9 100%)',
      authBg: '#fbecf1',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(190, 18, 60, 0.13) 0%, rgba(251, 236, 241, 0) 55%), linear-gradient(145deg, #fff6f8 0%, #f6dfe7 100%)',
      topbarBg: 'rgba(248, 231, 238, 0.96)',
      topbarBorder: 'rgba(244, 63, 94, 0.5)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#4c0519',
    },
    dark: {
      shellBg: '#5b1026',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(190, 18, 60, 0.24) 0%, rgba(91, 16, 38, 0) 60%), linear-gradient(145deg, #5b1026 0%, #7d1635 100%)',
      authBg: '#5b1026',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(190, 18, 60, 0.2) 0%, rgba(91, 16, 38, 0) 55%), linear-gradient(145deg, #5b1026 0%, #7d1635 100%)',
      topbarBg: 'rgba(91, 16, 38, 0.92)',
      topbarBorder: 'rgba(244, 63, 94, 0.5)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#ffe4e6',
    },
  },
  {
    id: 'terracotta',
    label: 'Terracota',
    swatch: '#b4533f',
    light: {
      shellBg: '#fcf0eb',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(180, 83, 63, 0.18) 0%, rgba(252, 240, 235, 0) 58%), linear-gradient(145deg, #fff7f3 0%, #f5e2d9 100%)',
      authBg: '#f9ebe5',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(180, 83, 63, 0.13) 0%, rgba(249, 235, 229, 0) 55%), linear-gradient(145deg, #fff6f2 0%, #f3dfd5 100%)',
      topbarBg: 'rgba(246, 231, 223, 0.96)',
      topbarBorder: 'rgba(251, 146, 60, 0.45)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#431407',
    },
    dark: {
      shellBg: '#5a2f24',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(180, 83, 63, 0.24) 0%, rgba(90, 47, 36, 0) 60%), linear-gradient(145deg, #5a2f24 0%, #7b4132 100%)',
      authBg: '#5a2f24',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(180, 83, 63, 0.2) 0%, rgba(90, 47, 36, 0) 55%), linear-gradient(145deg, #5a2f24 0%, #7b4132 100%)',
      topbarBg: 'rgba(90, 47, 36, 0.92)',
      topbarBorder: 'rgba(251, 146, 60, 0.45)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#ffedd5',
    },
  },
  {
    id: 'chocolate',
    label: 'Chocolate',
    swatch: '#7c4a2d',
    light: {
      shellBg: '#f8efe8',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(124, 74, 45, 0.18) 0%, rgba(248, 239, 232, 0) 58%), linear-gradient(145deg, #fcf6f2 0%, #efdfd3 100%)',
      authBg: '#f6ece4',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(124, 74, 45, 0.13) 0%, rgba(246, 236, 228, 0) 55%), linear-gradient(145deg, #fcf5f1 0%, #eddccf 100%)',
      topbarBg: 'rgba(242, 230, 220, 0.96)',
      topbarBorder: 'rgba(180, 120, 87, 0.45)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#3f1f10',
    },
    dark: {
      shellBg: '#3f2619',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(124, 74, 45, 0.24) 0%, rgba(63, 38, 25, 0) 60%), linear-gradient(145deg, #3f2619 0%, #5a3724 100%)',
      authBg: '#3f2619',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(124, 74, 45, 0.2) 0%, rgba(63, 38, 25, 0) 55%), linear-gradient(145deg, #3f2619 0%, #5a3724 100%)',
      topbarBg: 'rgba(63, 38, 25, 0.92)',
      topbarBorder: 'rgba(180, 120, 87, 0.45)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#fef3e2',
    },
  },
  {
    id: 'sky',
    label: 'Cielo',
    swatch: '#3b82f6',
    light: {
      shellBg: '#eff7ff',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(59, 130, 246, 0.2) 0%, rgba(239, 247, 255, 0) 58%), linear-gradient(145deg, #f7fbff 0%, #e6f1fd 100%)',
      authBg: '#ebf4ff',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(59, 130, 246, 0.14) 0%, rgba(235, 244, 255, 0) 55%), linear-gradient(145deg, #f7fbff 0%, #e3effd 100%)',
      topbarBg: 'rgba(232, 242, 255, 0.96)',
      topbarBorder: 'rgba(96, 165, 250, 0.55)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0c254a',
    },
    dark: {
      shellBg: '#132c52',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(59, 130, 246, 0.24) 0%, rgba(19, 44, 82, 0) 60%), linear-gradient(145deg, #132c52 0%, #1e3e72 100%)',
      authBg: '#132c52',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(59, 130, 246, 0.2) 0%, rgba(19, 44, 82, 0) 55%), linear-gradient(145deg, #132c52 0%, #1e3e72 100%)',
      topbarBg: 'rgba(19, 44, 82, 0.92)',
      topbarBorder: 'rgba(96, 165, 250, 0.52)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#dbeafe',
    },
  },
  {
    id: 'lavender',
    label: 'Lavanda',
    swatch: '#a78bfa',
    light: {
      shellBg: '#f5f2ff',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(167, 139, 250, 0.2) 0%, rgba(245, 242, 255, 0) 58%), linear-gradient(145deg, #fbf9ff 0%, #ece7fb 100%)',
      authBg: '#f2efff',
      authBgImage:
        'radial-gradient(120% 100% at 12% 2%, rgba(167, 139, 250, 0.14) 0%, rgba(242, 239, 255, 0) 55%), linear-gradient(145deg, #faf8ff 0%, #eae4fa 100%)',
      topbarBg: 'rgba(239, 234, 252, 0.96)',
      topbarBorder: 'rgba(167, 139, 250, 0.52)',
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#2e1065',
    },
    dark: {
      shellBg: '#3b2b6a',
      shellBgImage:
        'radial-gradient(120% 100% at 10% 0%, rgba(167, 139, 250, 0.24) 0%, rgba(59, 43, 106, 0) 60%), linear-gradient(145deg, #3b2b6a 0%, #503a8e 100%)',
      authBg: '#3b2b6a',
      authBgImage:
        'radial-gradient(130% 110% at 10% 0%, rgba(167, 139, 250, 0.2) 0%, rgba(59, 43, 106, 0) 55%), linear-gradient(145deg, #3b2b6a 0%, #503a8e 100%)',
      topbarBg: 'rgba(59, 43, 106, 0.92)',
      topbarBorder: 'rgba(167, 139, 250, 0.5)',
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#ede9fe',
    },
  },
];

const PALETTE_IDS = PALETTE_OPTIONS.map((palette) => palette.id);
const DEFAULT_PALETTE = 'steel';

function hexToRgb(hex) {
  const normalized = hex.replace('#', '').trim();

  if (normalized.length !== 6) {
    return [74, 92, 106];
  }

  const r = Number.parseInt(normalized.slice(0, 2), 16);
  const g = Number.parseInt(normalized.slice(2, 4), 16);
  const b = Number.parseInt(normalized.slice(4, 6), 16);

  if ([r, g, b].some((channel) => Number.isNaN(channel))) {
    return [74, 92, 106];
  }

  return [r, g, b];
}

function mixRgb(colorA, colorB, amount) {
  return colorA.map((channel, index) =>
    Math.round(channel + (colorB[index] - channel) * amount),
  );
}

function rgbToCss(rgb, alpha = 1) {
  return `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${alpha})`;
}

function isHexColor(value) {
  return /^#[0-9a-fA-F]{6}$/.test(String(value ?? ''));
}

export function normalizeCustomColor(value, fallback = DEFAULT_CUSTOM_COLOR) {
  return isHexColor(value) ? value.toLowerCase() : fallback;
}

function generateCustomPalette(hexColor) {
  const base = hexToRgb(hexColor);
  const white = [255, 255, 255];
  const black = [0, 0, 0];

  const lightShell = mixRgb(base, white, 0.9);
  const lightAuth = mixRgb(base, white, 0.86);
  const lightTopbar = mixRgb(base, white, 0.88);

  const darkShell = mixRgb(base, black, 0.7);
  const darkAuth = mixRgb(base, black, 0.66);
  const darkTopbar = mixRgb(base, black, 0.62);

  return {
    id: CUSTOM_PALETTE_ID,
    label: 'Personalizado',
    swatch: hexColor,
    light: {
      shellBg: rgbToCss(lightShell),
      shellBgImage: `radial-gradient(120% 100% at 10% 0%, ${rgbToCss(base, 0.22)} 0%, rgba(255,255,255,0) 58%), linear-gradient(145deg, ${rgbToCss(mixRgb(base, white, 0.97))} 0%, ${rgbToCss(mixRgb(base, white, 0.9))} 100%)`,
      authBg: rgbToCss(lightAuth),
      authBgImage: `radial-gradient(120% 100% at 12% 2%, ${rgbToCss(base, 0.18)} 0%, rgba(255,255,255,0) 55%), linear-gradient(145deg, ${rgbToCss(mixRgb(base, white, 0.97))} 0%, ${rgbToCss(mixRgb(base, white, 0.88))} 100%)`,
      topbarBg: rgbToCss(lightTopbar, 0.96),
      topbarBorder: rgbToCss(base, 0.5),
      topbarShadow: '0 1px 0 rgba(15, 23, 42, 0.05)',
      brandColor: '#0f172a',
    },
    dark: {
      shellBg: rgbToCss(darkShell),
      shellBgImage: `radial-gradient(120% 100% at 10% 0%, ${rgbToCss(base, 0.24)} 0%, rgba(0,0,0,0) 60%), linear-gradient(145deg, ${rgbToCss(mixRgb(base, black, 0.76))} 0%, ${rgbToCss(mixRgb(base, black, 0.62))} 100%)`,
      authBg: rgbToCss(darkAuth),
      authBgImage: `radial-gradient(130% 110% at 10% 0%, ${rgbToCss(base, 0.22)} 0%, rgba(0,0,0,0) 55%), linear-gradient(145deg, ${rgbToCss(mixRgb(base, black, 0.74))} 0%, ${rgbToCss(mixRgb(base, black, 0.6))} 100%)`,
      topbarBg: rgbToCss(darkTopbar, 0.92),
      topbarBorder: rgbToCss(base, 0.52),
      topbarShadow: '0 1px 0 rgba(0, 0, 0, 0.4)',
      brandColor: '#f8fafc',
    },
  };
}

export function normalizeTheme(value, fallback = 'light') {
  return THEMES.includes(value) ? value : fallback;
}

export function normalizePalette(value, fallback = DEFAULT_PALETTE) {
  return PALETTE_IDS.includes(value) ? value : fallback;
}

export function getInitialTheme(preferredTheme = null) {
  const fallback =
    typeof document !== 'undefined'
      ? normalizeTheme(document.documentElement.dataset.theme, 'light')
      : 'light';

  return normalizeTheme(preferredTheme, fallback);
}

function getStoredPalette() {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    return window.localStorage.getItem(PALETTE_STORAGE_KEY);
  } catch (error) {
    return null;
  }
}

function setStoredPalette(value) {
  if (typeof window === 'undefined') {
    return;
  }

  try {
    window.localStorage.setItem(PALETTE_STORAGE_KEY, value);
  } catch (error) {
    // Ignore storage errors (private mode / quota).
  }
}

function getStoredCustomColor() {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    return window.localStorage.getItem(CUSTOM_COLOR_STORAGE_KEY);
  } catch (error) {
    return null;
  }
}

function setStoredCustomColor(value) {
  if (typeof window === 'undefined') {
    return;
  }

  try {
    window.localStorage.setItem(CUSTOM_COLOR_STORAGE_KEY, value);
  } catch (error) {
    // Ignore storage errors (private mode / quota).
  }
}

export function getInitialPalette(preferredPalette = null) {
  const fallback =
    typeof document !== 'undefined'
      ? normalizePalette(document.documentElement.dataset.uiPalette, DEFAULT_PALETTE)
      : DEFAULT_PALETTE;

  return normalizePalette(preferredPalette ?? getStoredPalette(), fallback);
}

export function getInitialCustomColor(preferredColor = null) {
  return normalizeCustomColor(preferredColor ?? getStoredCustomColor(), DEFAULT_CUSTOM_COLOR);
}

export function applyPalette(paletteId, theme = 'light', customColor = null) {
  if (typeof document === 'undefined') {
    return;
  }

  const customPalette = generateCustomPalette(
    normalizeCustomColor(customColor ?? getInitialCustomColor(), DEFAULT_CUSTOM_COLOR),
  );
  const selectedPalette =
    paletteId === CUSTOM_PALETTE_ID
      ? customPalette
      : PALETTE_OPTIONS.find((palette) => palette.id === paletteId);
  const normalizedPalette = selectedPalette ?? PALETTE_OPTIONS[0];
  const mode = theme === 'dark' ? 'dark' : 'light';
  const tokens = normalizedPalette[mode];
  const [accentR, accentG, accentB] = hexToRgb(normalizedPalette.swatch);
  const shellAccentAlpha = mode === 'dark' ? '0.20' : '0.36';
  const authAccentAlpha = mode === 'dark' ? '0.18' : '0.40';
  const topbarAccentAlpha = mode === 'dark' ? '0.14' : '0.20';

  document.documentElement.dataset.uiPalette = normalizedPalette.id;
  document.documentElement.style.setProperty('--ui-shell-bg', tokens.shellBg);
  document.documentElement.style.setProperty('--ui-shell-bg-image', tokens.shellBgImage);
  document.documentElement.style.setProperty('--ui-auth-bg', tokens.authBg);
  document.documentElement.style.setProperty('--ui-auth-bg-image', tokens.authBgImage);
  document.documentElement.style.setProperty('--ui-topbar-bg', tokens.topbarBg);
  document.documentElement.style.setProperty('--ui-topbar-border', tokens.topbarBorder);
  document.documentElement.style.setProperty('--ui-topbar-shadow', tokens.topbarShadow);
  document.documentElement.style.setProperty('--ui-brand-color', tokens.brandColor);
  document.documentElement.style.setProperty('--ui-palette-rgb', `${accentR} ${accentG} ${accentB}`);
  document.documentElement.style.setProperty('--ui-shell-accent-alpha', shellAccentAlpha);
  document.documentElement.style.setProperty('--ui-auth-accent-alpha', authAccentAlpha);
  document.documentElement.style.setProperty('--ui-topbar-accent-alpha', topbarAccentAlpha);
}

export function applyTheme(theme) {
  if (typeof document === 'undefined') {
    return;
  }

  document.documentElement.dataset.theme = theme;
  document.documentElement.classList.toggle('dark', theme === 'dark');
  document.documentElement.style.colorScheme = theme;
}

export function useTheme() {
  const page = usePage();
  const theme = ref(getInitialTheme(page.props.auth?.user?.ui_theme));
  const palette = ref(getInitialPalette(page.props.auth?.user?.ui_palette));
  const customColor = ref(getInitialCustomColor(page.props.auth?.user?.ui_custom_color));
  const savingTheme = ref(false);

  const isDark = computed(() => theme.value === 'dark');

  const syncTheme = (value) => {
    applyTheme(value);
    applyPalette(palette.value, value, customColor.value);

    if (page.props.auth?.user) {
      page.props.auth.user.ui_theme = value;
    }
  };

  const syncPalette = (value) => {
    const normalized = normalizePalette(value, palette.value);
    applyPalette(normalized, theme.value, customColor.value);
    setStoredPalette(normalized);

    if (page.props.auth?.user) {
      page.props.auth.user.ui_palette = normalized;
    }
  };

  const persistTheme = async (value, previousValue) => {
    if (!page.props.auth?.user) {
      return;
    }

    savingTheme.value = true;

    try {
      const response = await fetch('/profile/theme', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ theme: value }),
      });

      if (!response.ok) {
        theme.value = previousValue;
        throw new Error('No se pudo guardar la preferencia de tema.');
      }

      const json = await response.json();
      theme.value = normalizeTheme(json.theme, value);
    } finally {
      savingTheme.value = false;
    }
  };

  const toggleTheme = async () => {
    if (savingTheme.value) {
      return;
    }

    const previousValue = theme.value;
    theme.value = theme.value === 'dark' ? 'light' : 'dark';

    try {
      await persistTheme(theme.value, previousValue);
    } catch (error) {
      console.error(error);
    }
  };

  const setPalette = (value) => {
    palette.value = normalizePalette(value, palette.value);
  };

  const setCustomColor = (value) => {
    customColor.value = normalizeCustomColor(value, customColor.value);
    setStoredCustomColor(customColor.value);
    palette.value = CUSTOM_PALETTE_ID;
  };

  const resetPalette = () => {
    palette.value = RESET_NEUTRAL_PALETTE_ID;
    customColor.value = DEFAULT_CUSTOM_COLOR;
    setStoredCustomColor(DEFAULT_CUSTOM_COLOR);
  };

  onMounted(() => {
    syncTheme(theme.value);
    syncPalette(palette.value);
  });

  watch(theme, (value) => {
    syncTheme(value);
  });

  watch(palette, (value) => {
    syncPalette(value);
  });

  watch(customColor, (value) => {
    setStoredCustomColor(value);

    if (palette.value === CUSTOM_PALETTE_ID) {
      applyPalette(CUSTOM_PALETTE_ID, theme.value, value);
    }
  });

  return {
    theme,
    isDark,
    palette,
    customColor,
    paletteOptions: computed(() =>
      PALETTE_OPTIONS.map((option) =>
        option.id === CUSTOM_PALETTE_ID ? { ...option, swatch: customColor.value } : option,
      ),
    ),
    savingTheme,
    setPalette,
    setCustomColor,
    resetPalette,
    toggleTheme,
  };
}
