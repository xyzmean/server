<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Viewer\AppInfo;

use OCA\Viewer\Listener\LoadViewerScript;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent as BeforePublicTemplateRenderedEvent;

/**
 * @psalm-api
 */
class Application extends App implements IBootstrap {
	public const string APP_ID = 'viewer';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		// Every page a file can be opened from, rather than an event apps
		// have to dispatch: registering the handlers is cheap now, and the
		// viewer itself is only fetched when a file is opened.
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, LoadViewerScript::class);
		$context->registerEventListener(BeforePublicTemplateRenderedEvent::class, LoadViewerScript::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
	}
}
