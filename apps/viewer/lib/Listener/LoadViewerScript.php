<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Viewer\Listener;

use OCA\Viewer\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IPreview;
use OCP\Util;

/**
 * Registers the viewer's built-in handlers on every page a user can open a
 * file from.
 *
 * The script this adds no longer carries the viewer itself: it registers the
 * handlers for images, video and audio, and offers this copy of the library
 * as the page's implementation. The viewer is fetched when a file is actually
 * opened, so pages where nothing is opened pay only for the registration.
 *
 * @template-implements IEventListener<Event>
 * @psalm-api
 */
class LoadViewerScript implements IEventListener {
	public function __construct(
		private IInitialState $initialStateService,
		private IPreview $previewManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'init');
		Util::addInitScript(Application::APP_ID, 'init');
		$this->initialStateService->provideInitialState('enabled_preview_providers', array_keys($this->previewManager->getProviders()));
	}
}
