import DefaultTheme from 'vitepress/theme'
import type { Theme } from 'vitepress'
import ComparisonSection from './ComparisonSection.vue'
import './custom.css'

export default {
   extends: DefaultTheme,
   enhanceApp({ app }) {
      app.component('ComparisonSection', ComparisonSection)
   },
} satisfies Theme
