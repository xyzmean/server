<?php
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2011-2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/**
 * @var \OC_Defaults $theme
 * @var array $_
 */

$getUserAvatar = static function (int $size) use ($_): string {
	return \OCP\Server::get(\OCP\IURLGenerator::class)->linkToRoute('core.avatar.getAvatar', [
		'userId' => $_['user_uid'],
		'size' => $size,
		'v' => $_['userAvatarVersion']
	]);
}

?><!DOCTYPE html>
<html class="ng-csp" data-placeholder-focus="false" lang="<?php p($_['language']); ?>" data-locale="<?php p($_['locale']); ?>" translate="no" >
	<head data-user="<?php p($_['user_uid']); ?>" data-user-displayname="<?php p($_['user_displayname']); ?>" data-requesttoken="<?php p($_['requesttoken']); ?>">
		<meta charset="utf-8">
		<title>
			<?php
				p(!empty($_['pageTitle']) && (empty($_['application']) || $_['pageTitle'] !== $_['application']) ? $_['pageTitle'] . ' - ' : '');
p(!empty($_['application']) ? $_['application'] . ' - ' : '');
p($theme->getTitle());
?>
		</title>
		<meta name="csp-nonce" nonce="<?php p($_['cspNonce']); /* Do not pass into "content" to prevent exfiltration */ ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0<?php if (isset($_['viewport_maximum_scale'])) {
			p(', maximum-scale=' . $_['viewport_maximum_scale']);
		} ?>">

		<?php if ($theme->getiTunesAppId() !== '') { ?>
		<meta name="apple-itunes-app" content="app-id=<?php p($theme->getiTunesAppId()); ?>">
		<?php } ?>
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black">
		<meta name="apple-mobile-web-app-title" content="<?php p((!empty($_['application']) && $_['appid'] != 'files')? $_['application']:$theme->getTitle()); ?>">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="theme-color" content="<?php p($theme->getColorPrimary()); ?>">
		<link rel="icon" href="<?php print_unescaped(image_path($_['appid'], 'favicon.ico')); /* IE11+ supports png */ ?>">
		<link rel="apple-touch-icon" href="<?php print_unescaped(image_path($_['appid'], 'favicon-touch.png')); ?>">
		<link rel="apple-touch-icon-precomposed" href="<?php print_unescaped(image_path($_['appid'], 'favicon-touch.png')); ?>">
		<link rel="mask-icon" sizes="any" href="<?php print_unescaped(image_path($_['appid'], 'favicon-mask.svg')); ?>" color="<?php p($theme->getColorPrimary()); ?>">
		<link rel="manifest" href="<?php print_unescaped(image_path($_['appid'], 'manifest.json')); ?>" crossorigin="use-credentials">
		<?php emit_css_loading_tags($_); ?>
		<?php emit_script_loading_tags($_); ?>
		<?php print_unescaped($_['headers']); ?>
	</head>
	<body dir="<?php p($_['direction']); ?>" id="<?php p($_['bodyid']);?>" <?php foreach ($_['enabledThemes'] as $themeId) {
		p("data-theme-$themeId ");
	}?> data-themes=<?php p(join(',', $_['enabledThemes'])) ?>>
		<?php include 'layout.noscript.warning.php'; ?>
		<?php include 'layout.initial-state.php'; ?>

		<div id="skip-actions">
			<?php if ($_['id-app-content'] !== null) { ?><a href="<?php p($_['id-app-content']); ?>" class="button primary skip-navigation skip-content"><?php p($l->t('Skip to main content')); ?></a><?php } ?>
			<?php if ($_['id-app-navigation'] !== null) { ?><a href="<?php p($_['id-app-navigation']); ?>" class="button primary skip-navigation"><?php p($l->t('Skip to navigation of app')); ?></a><?php } ?>
		</div>

		<?php
		// xcloud: a static copy of the rail, same markup as core/src/components/
		// AppRail.vue, so the rail is on screen from the very first frame. The
		// live rail is mounted by core-main 60–700 ms later (measured on the lab)
		// and replaces this node; until then it flashed empty on every page load,
		// and with page transitions it visibly disappeared and came back.
		// Header nodes the live rail hosts (search, notifications, contacts, user
		// menu) are JS apps and arrive with it; their place is reserved here so
		// nothing below shifts when they do.
		$xcRailIds = $_['railApps'] ?? [];
		$xcNav = [];
		foreach (($_['navigation'] ?? []) as $xcEntry) {
			$xcNav[$xcEntry['id']] = $xcEntry;
		}
		$xcVisible = $xcRailIds === [] ? array_values($xcNav) : array_values(array_filter(array_map(static fn ($id) => $xcNav[$id] ?? null, $xcRailIds)));
		$xcMore = $xcRailIds === [] ? [] : array_values(array_filter($xcNav, static fn ($e) => !in_array($e['id'], $xcRailIds, true)));
		$xcMoreActive = array_filter($xcMore, static fn ($e) => $e['active'] ?? false) !== [];
		$xcSettings = $_['settingsNavigation']['xcloud_settings'] ?? $_['settingsNavigation']['settings'] ?? null;
		?>
		<nav id="app-rail" class="app-rail" aria-label="<?php p($l->t('Applications')); ?>">
			<div class="app-rail__logo">
				<a class="app-rail__static-logo" href="<?php print_unescaped($_['logoUrl'] ?: link_to('', 'index.php')); ?>" aria-hidden="true" tabindex="-1"><div class="logo logo-icon"></div></a>
			</div>
			<ul class="app-rail__list">
				<?php foreach ($xcVisible as $xcApp) { ?>
				<li class="app-rail__item">
					<a class="app-rail__link<?php if ($xcApp['active'] ?? false) { p(' app-rail__link--active'); } ?>" href="<?php p($xcApp['href']); ?>"<?php if ($xcApp['active'] ?? false) { ?> aria-current="page"<?php } ?>>
						<?php if ($xcApp['active'] ?? false) { ?><span class="app-rail__indicator" aria-hidden="true"></span><?php } ?>
						<span class="app-rail__icon-wrapper"><img class="app-rail__icon" src="<?php p($xcApp['icon']); ?>" alt="" aria-hidden="true"></span>
						<span class="app-rail__label"><?php p($xcApp['name']); ?></span>
					</a>
				</li>
				<?php } ?>
				<?php if ($xcMore !== []) { ?>
				<li class="app-rail__item app-rail__item--more">
					<button type="button" class="app-rail__link app-rail__link--more<?php if ($xcMoreActive) { p(' app-rail__link--active'); } ?>" aria-haspopup="menu" aria-expanded="false">
						<?php if ($xcMoreActive) { ?><span class="app-rail__indicator" aria-hidden="true"></span><?php } ?>
						<span class="app-rail__icon-wrapper"><span class="app-rail__more-glyph" aria-hidden="true"></span></span>
						<span class="app-rail__label"><?php p($l->t('Apps')); ?></span>
					</button>
				</li>
				<?php } ?>
			</ul>
			<span class="app-rail__spacer" aria-hidden="true"></span>
			<div class="app-rail__tools app-rail__tools--reserved" aria-hidden="true"></div>
			<?php if ($xcSettings !== null) { ?>
			<a class="app-rail__link app-rail__link--compact<?php if ($xcSettings['active'] ?? false) { p(' app-rail__link--active'); } ?>" href="<?php p($xcSettings['href']); ?>">
				<?php if ($xcSettings['active'] ?? false) { ?><span class="app-rail__indicator" aria-hidden="true"></span><?php } ?>
				<span class="app-rail__icon-wrapper"><img class="app-rail__icon" src="<?php print_unescaped(image_path('core', 'actions/settings.svg')); ?>" alt="" aria-hidden="true"></span>
				<span class="hidden-visually"><?php p($xcSettings['name']); ?></span>
			</a>
			<?php } ?>
			<div class="app-rail__user app-rail__user--reserved" aria-hidden="true">
				<?php if ($_['user_uid']) { ?><img class="app-rail__static-avatar" src="<?php p(\OCP\Server::get(\OCP\IURLGenerator::class)->linkToRoute('core.avatar.getAvatar', ['userId' => $_['user_uid'], 'size' => 64])); ?>" alt="" width="36" height="36"><?php } ?>
			</div>
		</nav>
		<?php // Prerender a rail entry while the pointer rests on it (Chrome's
		// «moderate» eagerness: ~200 ms hover or pointerdown), so the click shows an
		// already rendered page instead of loading one. Document rules match the
		// live rail too, whenever it replaces the static copy. The open app is
		// excluded — prerendering the page one is on is waste. ?>
		<script type="speculationrules" nonce="<?php p($_['cspNonce']); ?>">{"prerender":[{"where":{"and":[{"selector_matches":"#app-rail a.app-rail__link"},{"not":{"selector_matches":".app-rail__link--active"}}]},"eagerness":"moderate"}]}</script>

		<header id="header">
			<div class="header-start">
				<a href="<?php print_unescaped($_['logoUrl'] ?: link_to('', 'index.php')); ?>"
					aria-label="<?php p($l->t('Go to %s', [$_['logoUrl'] ?: $_['defaultAppName']])); ?>"
					id="nextcloud">
					<div class="logo logo-icon"></div>
				</a>

				<nav id="header-start__appmenu"></nav>
			</div>

			<div class="header-end">
				<div id="unified-search"></div>
				<div id="notifications"></div>
				<div id="contactsmenu"></div>
				<div id="user-menu"></div>
			</div>
		</header>

		<div id="content" class="app-<?php p($_['appid']) ?>">
			<h1 class="hidden-visually" id="page-heading-level-1">
				<?php p((!empty($_['application']) && !empty($_['pageTitle']) && $_['application'] != $_['pageTitle'])
					? $_['application'] . ': ' . $_['pageTitle']
					: (!empty($_['pageTitle']) ? $_['pageTitle'] : $theme->getName())
				); ?>
			</h1>
			<?php print_unescaped($_['content']); ?>
		</div>
		<div id="profiler-toolbar"></div>
	</body>
</html>
