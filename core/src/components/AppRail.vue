<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<nav id="app-rail" class="app-rail" :aria-label="t('core', 'Applications')">
		<!-- The header home link (#nextcloud) is moved in here on desktop, see placeHeaderNodes(). -->
		<div ref="logo" class="app-rail__logo" />

		<ul class="app-rail__list">
			<li v-for="app in visibleApps" :key="app.id" class="app-rail__item">
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

			<!-- Apps outside the configured rail list: one folded entry with a panel. -->
			<li v-if="moreApps.length" ref="more" class="app-rail__item app-rail__item--more">
				<button
					type="button"
					class="app-rail__link app-rail__link--more"
					:class="{ 'app-rail__link--active': moreActive }"
					aria-haspopup="menu"
					:aria-expanded="moreOpen ? 'true' : 'false'"
					@click="moreOpen = !moreOpen">
					<span class="app-rail__icon-wrapper">
						<span class="app-rail__more-glyph" aria-hidden="true" />
						<span v-if="moreUnread > 0" class="app-rail__badge">
							{{ formatCounter(moreUnread) }}
							<span class="hidden-visually">{{ n('core', '%n notification', '%n notifications', moreUnread) }}</span>
						</span>
					</span>
					<span class="app-rail__label">{{ t('core', 'Apps') }}</span>
				</button>
				<div v-if="moreOpen" class="app-rail__more" role="menu">
					<a
						v-for="app in moreApps"
						:key="app.id"
						class="app-rail__more-entry"
						:class="{ 'app-rail__more-entry--active': app.active }"
						:href="app.href"
						:target="app.target ? '_blank' : undefined"
						:rel="app.target ? 'noopener noreferrer' : undefined"
						role="menuitem"
						:aria-current="app.active ? 'page' : undefined">
						<img class="app-rail__more-icon" :src="app.icon" alt="" aria-hidden="true">
						<span class="app-rail__more-name">{{ app.name }}</span>
						<span v-if="app.unread > 0" class="app-rail__more-counter">{{ formatCounter(app.unread) }}</span>
					</a>
				</div>
			</li>
		</ul>

		<span class="app-rail__spacer" aria-hidden="true" />

		<!-- The header tools (#unified-search, #notifications, #contactsmenu) are moved in here on desktop, see placeHeaderNodes(). -->
		<div ref="tools" class="app-rail__tools" />

		<a
			v-if="settingsEntry"
			class="app-rail__link app-rail__link--compact"
			:class="{ 'app-rail__link--active': settingsEntry.active }"
			:href="settingsEntry.href"
			:aria-current="settingsEntry.active ? 'page' : undefined">
			<span class="app-rail__icon-wrapper">
				<img
					class="app-rail__icon"
					:src="settingsIcon"
					alt=""
					aria-hidden="true">
			</span>
			<!-- The entry name arrives already translated from the server. -->
			<span class="hidden-visually">{{ settingsEntry.name }}</span>
		</a>

		<!-- The header user menu (#user-menu) is moved in here on desktop, see placeHeaderNodes(). -->
		<div ref="userMenu" class="app-rail__user" />
	</nav>
</template>

<script lang="ts">
import type { INavigationEntry } from '../types/navigation.d.ts'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { imagePath } from '@nextcloud/router'
import { defineComponent } from 'vue'

/**
 * Mirror of `$breakpoint-mobile` in core/css/variables.scss: below it rail.scss
 * hides the rail and gives the header its own app menu, logo and user menu back.
 */
const DESKTOP_QUERY = '(min-width: 1025px)'

/**
 * Header nodes the rail hosts on desktop, as [selector, $refs key of its host].
 *
 * They are moved rather than copied: the logo is the "home" link and the user
 * menu is a mounted Vue app with the real avatar, user status and settings
 * entries behind it. A second copy of either would be a decoration that does
 * not do what the original does. With every header node hosted here, rail.scss
 * hides the header itself on desktop and sets --header-height to zero.
 */
const RELOCATED_NODES = [
	['#nextcloud', 'logo'],
	// The design has no header at all: search, notifications and the contacts
	// menu live at the bottom of the rail, above settings and the avatar. They
	// share one host and arrive in this order. Unified search mounts with
	// `el: '#unified-search'`, and Vue 2 replaces that node with the component
	// root, which carries no id — hence the class fallback.
	['#unified-search, .unified-search-menu', 'tools'],
	['#notifications', 'tools'],
	['#contactsmenu', 'tools'],
	['#user-menu', 'userMenu'],
] as const

export default defineComponent({
	name: 'AppRail',

	data() {
		const settingsEntries = loadState<Record<string, INavigationEntry>>('core', 'settingsNavEntries', {})

		return {
			// Same initial state the header app menu consumes, so the rail lists
			// exactly the apps (and the order) the user configured in settings.
			apps: loadState<INavigationEntry[]>('core', 'apps', []),
			// Admin-side composition of the rail (core/xcloud_rail_apps): the ids
			// to show, in this order. Anything else folds into «More». Empty means
			// the stock behaviour — every app, user order.
			railApps: loadState<string[]>('core', 'railApps', []),
			moreOpen: false,
			// Personal settings, from the same initial state the user menu reads:
			// already localized, already carrying the right target and active flag.
			settingsEntry: settingsEntries.settings ?? null,
			// The entry ships a person glyph (settings/personal.svg), which right
			// above the avatar reads as a second profile link rather than as
			// settings — and the rail shows no label to correct that. Core's own
			// generic settings action is the cog the design asks for.
			settingsIcon: imagePath('core', 'actions/settings.svg'),
		}
	},

	computed: {
		visibleApps(): INavigationEntry[] {
			if (!this.railApps.length) {
				return this.apps
			}
			return this.railApps
				.map((id) => this.apps.find((app) => app.id === id))
				.filter((app): app is INavigationEntry => app !== undefined)
		},

		moreApps(): INavigationEntry[] {
			if (!this.railApps.length) {
				return []
			}
			return this.apps.filter((app) => !this.railApps.includes(app.id))
		},

		moreActive(): boolean {
			return this.moreApps.some((app) => app.active)
		},

		moreUnread(): number {
			return this.moreApps.reduce((sum, app) => sum + (app.unread || 0), 0)
		},
	},

	watch: {
		moreOpen(open: boolean) {
			// The panel closes on a click anywhere outside it and on Escape.
			if (open) {
				document.addEventListener('click', this.onDocumentClick, true)
				document.addEventListener('keydown', this.onDocumentKeydown)
			} else {
				document.removeEventListener('click', this.onDocumentClick, true)
				document.removeEventListener('keydown', this.onDocumentKeydown)
			}
		},
	},

	mounted() {
		// Not reactive on purpose: neither takes part in rendering.
		this.homes = new Map()
		this.desktop = window.matchMedia(DESKTOP_QUERY)

		this.placeHeaderNodes()

		this.desktop.addEventListener('change', this.onLayoutChange)
		// UserMenu.setUp() runs after MainMenu.setUp() and replaces the node we
		// just adopted with the mounted menu, so claim the result once it says so.
		subscribe('core:user-menu:mounted', this.onLayoutChange)
	},

	beforeDestroy() {
		this.desktop.removeEventListener('change', this.onLayoutChange)
		unsubscribe('core:user-menu:mounted', this.onLayoutChange)
		// Do not take the header's own nodes down with the rail.
		this.placeHeaderNodes(true)
	},

	methods: {
		onDocumentClick(event: MouseEvent) {
			const host = this.$refs.more as HTMLElement | undefined
			if (host && !host.contains(event.target as Node)) {
				this.moreOpen = false
			}
		},

		onDocumentKeydown(event: KeyboardEvent) {
			if (event.key === 'Escape') {
				this.moreOpen = false
			}
		},

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

		/**
		 * Event handler wrapper: listeners pass an event, placeHeaderNodes takes a flag.
		 */
		onLayoutChange() {
			this.placeHeaderNodes()
		},

		/**
		 * Move the shared header nodes between the header and the rail.
		 *
		 * The rail hosts them only while it is visible; at mobile widths they go
		 * back where the template put them, because there the stock header is the
		 * whole navigation.
		 *
		 * @param evacuate return every node to the header regardless of width
		 */
		placeHeaderNodes(evacuate = false) {
			for (const [selector, ref] of RELOCATED_NODES) {
				const node = document.querySelector(selector) as HTMLElement | null
				if (!node) {
					continue
				}

				// Captured on the first pass, while the node is still in the header.
				if (!this.homes.has(selector)) {
					this.homes.set(selector, { parent: node.parentElement, next: node.nextElementSibling })
				}

				const home = this.homes.get(selector)
				if (!evacuate && this.desktop.matches) {
					const host = this.$refs[ref] as HTMLElement | undefined
					if (host && node.parentElement !== host) {
						host.appendChild(node)
					}
				} else if (home.parent && node.parentElement !== home.parent) {
					// The sibling may itself have been replaced by a Vue root since
					// (the app menu is), in which case appending restores the order.
					const before = home.next?.parentElement === home.parent ? home.next : null
					home.parent.insertBefore(node, before)
				}
			}
		},
	},
})
</script>
