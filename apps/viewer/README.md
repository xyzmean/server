<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Files viewer for nextcloud

Show your latest holiday photos and videos like in the movies. Show a glimpse of your latest novel directly from your nextcloud. Choose the best GIF of your collection thanks to the direct view of your favorites files!

![viewer](https://raw.githubusercontent.com/nextcloud/screenshots/master/apps/Viewer/viewer.png?v=2)

## 📋 Current support
- Images
- Videos

## 🏗 Development setup
The app ships with the server and is built by the server frontend build, so all
commands are run from the repository root:

- `npm run build` builds every app, `npm run dev` rebuilds on change.
- The bundle is emitted as `dist/viewer-init.mjs`.

### 🧪 Running tests
Unit and component tests run with [Vitest](https://vitest.dev): `npm run test apps/viewer`.

End-to-end tests run with [Playwright](https://playwright.dev) and live in
`tests/playwright/e2e/viewer`: `npm run playwright -- viewer`.

## API

The viewer's integration API — registering your own handler, opening the viewer
from your code, and loading it on a page of your own — is documented with the
package that provides it, in
[`src/api_package/README.md`](src/api_package/README.md).

Apps built against the old `OCA.Viewer` global will need changes: see
[migrating from `OCA.Viewer`](src/api_package/README.md#-migrating-from-ocaviewer).
