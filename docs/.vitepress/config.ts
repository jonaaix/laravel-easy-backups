import { defineConfig } from 'vitepress'

const isDeploy = process.env.NODE_ENV === 'production'

export default defineConfig({
   title: 'Laravel Easy Backups',
   description: 'Fluent and flexible application backups for Laravel.',
   base: isDeploy ? '/laravel-easy-backups/' : '/',
   cleanUrls: true,
   lastUpdated: true,
   srcExclude: ['**/README.md'],

   head: [
      ['link', { rel: 'icon', href: '/img/logo2.png' }],
   ],

   rewrites: {
      'docs/10-getting-started.md': 'docs/getting-started.md',
      'docs/20-creating-backups.md': 'docs/creating-backups.md',
      'docs/30-restoring-backups.md': 'docs/restoring-backups.md',
      'docs/40-common-recipes.md': 'docs/common-recipes.md',
      'docs/50-artisan-commands.md': 'docs/artisan-commands.md',
      'docs/60-api-reference.md': 'docs/api-reference.md',
   },

   markdown: {
      theme: {
         light: 'github-light',
         dark: 'github-dark',
      },
   },

   themeConfig: {
      logo: '/img/logo2.png',
      siteTitle: 'Laravel Easy Backups',

      nav: [
         { text: 'Documentation', link: '/docs/getting-started' },
         { text: 'GitHub', link: 'https://github.com/jonaaix/laravel-easy-backups' },
      ],

      sidebar: {
         '/docs/': [
            {
               text: 'Documentation',
               items: [
                  { text: 'Getting Started', link: '/docs/getting-started' },
                  { text: 'Creating Backups', link: '/docs/creating-backups' },
                  { text: 'Restoring Backups', link: '/docs/restoring-backups' },
                  { text: 'Common Recipes', link: '/docs/common-recipes' },
                  { text: 'Artisan Commands', link: '/docs/artisan-commands' },
                  { text: 'API Reference', link: '/docs/api-reference' },
               ],
            },
         ],
      },

      socialLinks: [
         { icon: 'github', link: 'https://github.com/jonaaix/laravel-easy-backups' },
      ],

      footer: {
         message: '',
         copyright: `Copyright © ${new Date().getFullYear()} Laravel Easy Backups`,
      },

      search: { provider: 'local' },
   },
})
