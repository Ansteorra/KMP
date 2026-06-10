# Frontend assets guide

## Purpose

Own source JavaScript, CSS, Vite entrypoints, frontend dependencies, and build-time asset contracts for the CakePHP app.

## Ownership

- `vite.config.js` owns frontend entrypoints, output paths, manifest generation, chunking, and copied static assets.
- `assets/js/index.js` starts global frontend behavior.
- `assets/js/controllers-entry.js` discovers controllers and services for bundling.
- `assets/css` owns CSS entry files.
- Plugin frontend source remains in plugin `assets` directories and is wired through Vite inputs when needed.

## Local Contracts

- Use Vite only; do not add Laravel Mix or Webpack configuration.
- `npm run dev` builds development assets and `npm run build` builds production assets.
- Vite outputs into `webroot` with hashed filenames and a manifest; templates should rely on the app asset helper rather than hard-coded built filenames.
- The build copies FontAwesome webfonts to `webroot/fonts` and the PDF.js worker to `webroot/js/pdf.worker.min.mjs`.
- Bootstrap, Stimulus, Turbo, and shared utilities are app-level dependencies, not template-level script tags.

## Work Guidance

1. Add new entrypoints only when an existing entrypoint cannot reasonably own the asset.
2. Keep frontend modules small and import dependencies through JavaScript rather than template globals.
3. Preserve public asset paths expected by CakePHP helpers and existing templates.
4. For user-facing frontend behavior, check `app/assets/js/controllers/AGENTS.md`.

## Verification

- JavaScript unit tests: `npm run test:js`
- Vite build: `npm run dev`
- Production build when changing build config: `npm run build`

## Child AGENTS index

| Path | Purpose |
| --- | --- |
| `app/assets/js/controllers/AGENTS.md` | Stimulus controller naming, lifecycle, accessibility, cleanup, and tests |
