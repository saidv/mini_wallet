import { createVuetify } from 'vuetify';
import '@mdi/font/css/materialdesignicons.css';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';
import { MINI_WALLET } from '@/theme/LightTheme';

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'MINI_WALLET',
    themes: {
      MINI_WALLET,
    },
  },
  defaults: {
    VBtn: {
      rounded: 'md',
      elevation: 0,
      color: 'primary',
    },
    VCard: {
      rounded: 'md',
    },
    VTextField: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
    },
    VTextarea: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
    },
    VSelect: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
    },
    VListItem: {
      minHeight: '45px',
    },
    VTooltip: {
      location: 'top',
    },
  },
});
