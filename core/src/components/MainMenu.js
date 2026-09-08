/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import Vue from 'vue'
import AppMenu from './AppMenu.vue'
import AppRail from './AppRail.vue'

/**
 * Set up the main menu component ("AppMenu")
 * This is the top left menu where users can navigate between different apps.
 */
export function setUp() {
	Vue.mixin({
		methods: {
			t,
			n,
		},
	})

	const container = document.getElementById('header-start__appmenu')
	if (!container) {
		// no container, possibly we're on a public page
		return
	}
	const AppMenuApp = Vue.extend(AppMenu)
	const appMenu = new AppMenuApp({}).$mount(container)

	// The left rail is the primary app switcher on desktop; the header menu stays
	// mounted because it is what mobile widths fall back to.
	const railContainer = document.getElementById('app-rail')
	const appRail = railContainer
		? new (Vue.extend(AppRail))({}).$mount(railContainer)
		: null

	Object.assign(OC, {
		setNavigationCounter(id, counter) {
			appMenu.setNavigationCounter(id, counter)
			appRail?.setNavigationCounter(id, counter)
		},
	})
}
