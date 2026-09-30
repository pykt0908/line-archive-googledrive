import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#06C755', // LINE Green
          'primary-darken': '#059440',
          secondary: '#1E293B', // Slate Dark
          accent: '#0284C7',
          background: '#F8FAFC',
          surface: '#FFFFFF',
          info: '#0284C7',
          success: '#10B981',
          warning: '#F59E0B',
          error: '#EF4444',
        },
      },
    },
  },
})
