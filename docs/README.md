# Website

This website is built using [VitePress](https://vitepress.dev/), a Vite-powered static site generator.

## Installation

```bash
npm install
```

## Local Development

```bash
npm run dev
```

This command starts a local development server and opens up a browser window. Most changes are reflected live without having to restart the server.

## Build

```bash
npm run build
```

This command generates static content into the `.vitepress/dist` directory and can be served using any static contents hosting service.

## Preview

```bash
npm run preview
```

Locally serves the production build for a final smoke test.

## Deployment

The site is deployed automatically to GitHub Pages via the `Deploy Documentation` workflow on every push to `main`.
