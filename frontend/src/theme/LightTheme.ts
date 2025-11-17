import type { ThemeDefinition } from 'vuetify';

export const PrimaryColor = '#9dc1f4ff';
export const PrimaryDarkColor = '#6397ebff';
export const PrimaryLightColor = '#e6f4ff';
export const PrimaryLightColorForDark = '#111a2c';

const MINI_WALLET: ThemeDefinition = {
  dark: false,
  variables: {
    'border-color': '#f0f0f0',
    'carousel-control-size': 10,
    gradient:
      'linear-gradient(250.38deg, rgba(var(--v-theme-primary), var(--v-shadow-opacity)) 2.39%, rgba(var(--v-theme-primary), var(--v-half-opacity)) 34.42%, rgb(var(--v-theme-primary)) 60.95%, rgba(var(--v-theme-darkprimary), var(--v-medium-opacity)) 84.83%, rgb(var(--v-theme-darkprimary)) 104.37%)',
    gradientRtl:
      'linear-gradient(250.38deg, rgb(var(--v-theme-darkprimary)) 2.39%, rgba(var(--v-theme-darkprimary), var(--v-medium-opacity)) 34.42%, rgb(var(--v-theme-primary)) 60.95%, rgba(var(--v-theme-primary), var(--v-half-opacity)) 84.83%, rgba(var(--v-theme-primary), var(--v-shadow-opacity)) 104.37%)',
    gradient2:
      'linear-gradient(to right, rgb(var(--v-theme-darkprimary)), rgb(var(--v-theme-primary)))',
    'card-shadow': '0px 1px 4px rgba(0, 0, 0, 0.08)',
    'gradient-opacity': 0.2,
    'medium-opacity': 0.85,
    'chart-opacity': 0.6,
    'half-opacity': 0.5,
    'high-opacity': 1,
    'shadow-opacity': 0.08,
  },
  colors: {
    primary: PrimaryColor,
    secondary: '#8c8c8c',
    info: '#13c2c2',
    success: '#52c41a',
    accent: '#FFAB91',
    warning: '#faad14',
    error: '#ff4d4f',
    lightprimary: PrimaryLightColor,
    lightsecondary: '#f5f5f5',
    lightsuccess: '#EAFCD4',
    lighterror: '#FFE7D3',
    lightwarning: '#FFF6D0',
    darkText: '#212121',
    lightText: '#8c8c8c',
    darkprimary: PrimaryDarkColor,
    darksecondary: '#7a7878',
    borderLight: '#e6ebf1',
    inputBorder: '#a1a1a5',
    containerBg: '#fafafb',
    surface: '#fff',
    'on-surface-variant': '#fff',
    'surface-light': '#fff',
    gray100: '#f5f5f5',
    primary200: '#a1d2ff',
    secondary200: '#eeeeee',
  },
};

export { MINI_WALLET };
