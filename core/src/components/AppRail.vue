<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<nav id="app-rail" class="app-rail" :aria-label="t('core', 'Applications')">
		<ul class="app-rail__list">
			<li v-for="app in apps" :key="app.id" class="app-rail__item">
				<a
					class="app-rail__link"
					:class="{ 'app-rail__link--active': app.active }"
					:href="app.href"
					:target="app.target ? '_blank' : undefined"
					:rel="app.target ? 'noopener noreferrer' : undefined"
					:aria-current="app.active ? 'page' : undefined">
					<span class="app-rail__icon-wrapper">
						<img
							class="app-rail__icon"
							:src="app.icon"
							alt=""
							aria-hidden="true">
						<span v-if="app.unread > 0" class="app-rail__badge">
							{{ formatCounter(app.unread) }}
							<span class="hidden-visually">{{ n('core', '%n notification', '%n notifications', app.unread) }}</span>
						</span>
					</span>
					<span class="app-rail__label">{{ app.name }}</span>
				</a>
			</li>
		</ul>
	</nav>
</template>

<script lang="ts">
import type { INavigationEntry } from '../types/navigation.d.ts'

import { loadState } from '@nextcloud/initial-state'
import { defineComponent } from 'vue'

export default defineComponent({
	name: 'AppRail',

	data() {
		return {
			// Same initial state the header app menu consumes, so the rail lists
			// exactly the apps (and the order) the user configured in settings.
			apps: loadState<INavigationEntry[]>('core', 'apps', []),
		}
	},

	methods: {
		/**
		 * Keep the unread badge in sync with OC.setNavigationCounter.
		 *
		 * @param id navigation entry id
		 * @param counter new unread count
		 */
		setNavigationCounter(id: string, counter: number) {
			this.apps = this.apps.map((app) => app.id === id ? { ...app, unread: counter } : app)
		},

		/**
		 * Three digits do not fit the badge, so anything past 99 is capped.
		 *
		 * @param counter unread count
		 */
		formatCounter(counter: number): string {
			return counter > 99 ? '99+' : String(counter)
		},
	},
})
</script>
