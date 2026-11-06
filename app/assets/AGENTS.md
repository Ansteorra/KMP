# Frontend assets guide

## Purpose

Own source JavaScript, CSS, Vite entrypoints, frontend dependencies, and build-time asset contracts for the CakePHP app.

## Ownership

- `vite.config.js` owns frontend entrypoints, output paths, manifest generation, chunking, and copied static assets.
- `assets/js/index.js` starts global frontend behavior.
- `assets/js/public-event.js` starts the limited public-page runtime: Bootstrap, accessible confirmations, public gathering behavior, and public-calendar row disclosures and bounded activity filtering. Public pages keep native form submission.
- `assets/css/modal.css` owns shared dialog scrolling for both the app and public gathering stylesheets.
- `assets/js/controllers-entry.js` discovers controllers and services for bundling.
- `assets/css` owns CSS entry files.
- Plugin frontend source remains in plugin `assets` directories and is wired through Vite inputs when needed.

## Local Contracts

- Use Vite only; do not add Laravel Mix or Webpack configuration.
- `npm run dev` builds development assets and `npm run build` builds production assets.
- Vite runs the pinned icon importer before bundling; `resources/icons` owns reviewed selections, compatibility and generated catalog data. See `app/docs/icons.md`. Ship `webroot/kmp-icons` with matching bundles and PHP resolver; never import upstream icon APIs at runtime.
- Vite outputs into `webroot` with hashed filenames and a manifest; templates should rely on the app asset helper rather than hard-coded built filenames.
- The build copies FontAwesome webfonts to `webroot/fonts` and the PDF.js worker to `webroot/js/pdf.worker.min.mjs`.
- Bootstrap, Stimulus, Turbo, and shared utilities are app-level dependencies, not template-level script tags.
- Use `ui-navigation-service.js` for document and local UI history. Drive is gated by `KMP_TURBO_ENABLED`; fallback stops Turbo’s document-history observer. Preserve Turbo state, draft protection, and fresh snapshot context checks. Run the real Back and detail-tab browser checks after Turbo upgrades. See `app/docs/hotwire-interactions.md`.

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
