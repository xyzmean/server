/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, getRootUrl } from '@nextcloud/router'
import logger from '../logger.js'

/**
 *
 * @param {string} url the URL to check
 * @return {boolean}
 */
function isRelativeUrl(url) {
	return !url.startsWith('https://') && !url.startsWith('http://')
}

/**
 * @param {string} url The URL to check
 * @return {boolean} true if the URL points to this nextcloud instance
 */
function isNextcloudUrl(url) {
	const nextcloudBaseUrl = window.location.protocol + '//' + window.location.host + getRootUrl()
	// if the URL is absolute and starts with the baseUrl+rootUrl
	// OR if the URL is relative and starts with rootUrl
	return url.startsWith(nextcloudBaseUrl)
		|| (isRelativeUrl(url) && url.startsWith(getRootUrl()))
}

/**
 * Check if a user was logged in but is now logged-out.
 * If this is the case then the user will be forwarded to the login page.
 *
 * @return {Promise<void>}
 */
async function checkLoginStatus() {
	// skip if no logged in user
	if (getCurrentUser() === null) {
		return
	}

	// skip if already running
	if (checkLoginStatus.running === true) {
		return
	}

	// only run one request in parallel
	checkLoginStatus.running = true

	try {
		// We need to check this as a 401 in the first place could also come from other reasons
		const { status } = await window.fetch(generateUrl('/apps/files'))
		if (status === 401) {
			logger.warn('User session was terminated, forwarding to login page.')
			// The session ran out; the person did not log out. Keep the Chat's
			// stores: its encryption keys live only in this browser, and wiping
			// them makes the next login a new Matrix device that cannot read
			// the conversation history. A different person logging in next is
			// handled by the Chat's loader, which wipes a foreign session itself.
			await wipeBrowserStorages({ keepChat: true })
			window.location = generateUrl('/login?redirect_url={url}', {
				url: window.location.pathname + window.location.search + window.location.hash,
			})
		}
	} catch (error) {
		logger.warn('Could not check login-state', { error })
	} finally {
		delete checkLoginStatus.running
	}
}

/**
 * Storage of the embedded Chat (Element, xcloud_embed): its session, device id
 * and encryption keys. Element keeps them in localStorage keys and IndexedDB
 * databases of this same origin.
 */
const CHAT_KEY = /^(mx_|mxjssdk_|matrix-|xc_device_id$|xc_seeded_)/
const CHAT_DB = /^(matrix-js-sdk|matrix-react-sdk$)/

/**
 * Clear all Browser storages connected to current origin.
 *
 * @param {object} [options] options
 * @param {boolean} [options.keepChat] leave the embedded Chat's storage alone
 * @return {Promise<void>}
 */
export async function wipeBrowserStorages({ keepChat = false } = {}) {
	try {
		if (keepChat) {
			const keys = []
			for (let i = 0; i < window.localStorage.length; i++) {
				const key = window.localStorage.key(i)
				if (key !== null && !CHAT_KEY.test(key)) {
					keys.push(key)
				}
			}
			keys.forEach((key) => window.localStorage.removeItem(key))
		} else {
			window.localStorage.clear()
		}
		window.sessionStorage.clear()
		const indexedDBList = await window.indexedDB.databases()
		for (const indexedDB of indexedDBList) {
			if (keepChat && CHAT_DB.test(indexedDB.name)) {
				continue
			}
			await window.indexedDB.deleteDatabase(indexedDB.name)
		}
		logger.debug('Browser storages cleared')
	} catch (error) {
		logger.error('Could not clear browser storages', { error })
	}
}

/**
 * Intercept XMLHttpRequest and fetch API calls to add X-Requested-With header
 *
 * This is also done in @nextcloud/axios but not all requests pass through that
 */
export function interceptRequests() {
	XMLHttpRequest.prototype.open = (function(open) {
		return function(method, url) {
			open.apply(this, arguments)
			if (isNextcloudUrl(url)) {
				if (!this.getResponseHeader('X-Requested-With')) {
					this.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
				}
				this.addEventListener('loadend', function() {
					if (this.status === 401) {
						checkLoginStatus()
					}
				})
			}
		}
	})(XMLHttpRequest.prototype.open)

	window.fetch = (function(fetch) {
		return async (resource, options) => {
			// fetch allows the `input` to be either a Request object or any stringifyable value
			if (!isNextcloudUrl(resource.url ?? resource.toString())) {
				return await fetch(resource, options)
			}
			if (!options) {
				options = {}
			}
			if (!options.headers) {
				options.headers = new Headers()
			}

			if (options.headers instanceof Headers && !options.headers.has('X-Requested-With')) {
				options.headers.append('X-Requested-With', 'XMLHttpRequest')
			} else if (options.headers instanceof Object && !options.headers['X-Requested-With']) {
				options.headers['X-Requested-With'] = 'XMLHttpRequest'
			}

			const response = await fetch(resource, options)
			if (response.status === 401) {
				checkLoginStatus()
			}
			return response
		}
	})(window.fetch)
}
