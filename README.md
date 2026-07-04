# Feuer-Einsatzberichte

## License and usage rights

This plugin is proprietary software.

The source code may be visible in this repository, but public repository access does not grant any right to install, copy, modify, distribute, sublicense, host, or use the plugin.

Use is permitted only for persons or organizations that have personally agreed usage rights with the developer, Walter Faerber. See [LICENSE.md](LICENSE.md) and [NOTICE.md](NOTICE.md).

WordPress plugin for fire department incident reports with:

- internal report creation and editing
- participant and organization management
- map preview generation
- galleries and comments
- statistics, activity map and archives
- self-hosted plugin updates via `wdmin.com`

## Repository purpose

This repository stores the plugin source code.
Production updates are served from:

- `https://wdmin.com/plugins/feuer-einsatzberichte/release/latest/update-manifest.json`
- `https://wdmin.com/plugins/feuer-einsatzberichte/release/<version>/feuer-einsatzberichte-<version>.zip`
- `https://wdmin.com/plugins/feuer-einsatzberichte/release/latest/feuer-einsatzberichte-latest.zip`

## Important folders

- `includes/` PHP logic and classes
- `templates/` admin and frontend templates
- `assets/` CSS and JavaScript
- `docs/` technical documentation
- `tools/` release and snapshot helpers
- `Version/` local full-version snapshots

## Release flow

1. Bump plugin version.
2. Update `update-manifest.json` and `release-notes.json`.
3. Build the release ZIP.
4. Upload or mirror the generated `release/<version>/` and `release/latest/` folders to `wdmin.com/plugins/feuer-einsatzberichte/release/`.
5. Create GitHub tag/release for source tracking.

The release ZIP is built with normalized `/` archive paths and neutral ZIP attributes so WordPress updates on Linux hosts do not depend on Windows-style archive metadata.

Further details:

- `docs/PLUGIN_FUNCTIONS_AND_FILES.md`
- `docs/UPDATE_HOSTING_AND_GITHUB.md`
