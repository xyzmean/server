<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Avatar;

use Imagick;
use OC\User\User;
use OCP\Color;
use OCP\Files\NotFoundException;
use OCP\IAvatar;
use OCP\IConfig;
use OCP\Image;
use Psr\Log\LoggerInterface;

/**
 * This class gets and sets users avatars.
 */
abstract class Avatar implements IAvatar {
	/**
	 * https://github.com/sebdesign/cap-height -- for 500px height
	 * Automated check: https://codepen.io/skjnldsv/pen/PydLBK/
	 * Noto Sans cap-height is 0.715 and we want a 200px caps height size
	 * (0.4 letter-to-total-height ratio, 500*0.4=200), so: 200/0.715 = 280px.
	 * Since we start from the baseline (text-anchor) we need to
	 * shift the y axis by 100px (half the caps height): 500/2+100=350
	 *
	 * xcloud: the design sets initials at 0.34–0.40 of the circle and bold
	 * (13px in 36px, 10.5px in 26px); 280px bold two-letter initials touched
	 * the edge of the circle. 190px = 0.38; caps height 0.715*190 = 136, so
	 * the baseline sits at 500/2+68 = 318.
	 */
	private string $svgTemplate = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
		<svg width="{size}" height="{size}" version="1.1" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
			<rect width="100%" height="100%" fill="#{fill}"></rect>
			<text x="50%" y="318" style="font-weight:bold;font-size:190px;font-family:\'Noto Sans\';text-anchor:middle;fill:#{fgFill}">{letter}</text>
		</svg>';

	public function __construct(
		protected IConfig $config,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * Returns the user display name.
	 */
	abstract public function getDisplayName(): string;

	/**
	 * Returns the first letter of the display name, or "?" if no name given.
	 */
	private function getAvatarText(): string {
		$displayName = $this->getDisplayName();
		if (empty($displayName) === true) {
			return '?';
		}
		$firstTwoLetters = array_map(function ($namePart) {
			return mb_strtoupper(mb_substr($namePart, 0, 1), 'UTF-8');
		}, explode(' ', $displayName, 2));
		return implode('', $firstTwoLetters);
	}

	/**
	 * @inheritdoc
	 */
	#[\Override]
	public function get(int $size = 64, bool $darkTheme = false) {
		try {
			$file = $this->getFile($size, $darkTheme);
		} catch (NotFoundException $e) {
			return false;
		}

		$avatar = new Image();
		$avatar->loadFromData($file->getContent());
		return $avatar;
	}

	/**
	 * {size} = 500
	 * {fill} = hex color to fill
	 * {letter} = Letter to display
	 *
	 * Generate SVG avatar
	 *
	 * @param int $size The requested image size in pixel
	 * @return string
	 *
	 */
	protected function getAvatarVector(string $userDisplayName, int $size, bool $darkTheme): string {
		[$bgRGB, $fgRGB] = $this->avatarColors($userDisplayName, $darkTheme);
		$fill = sprintf('%02x%02x%02x', $bgRGB->red(), $bgRGB->green(), $bgRGB->blue());
		$fgFill = sprintf('%02x%02x%02x', $fgRGB->red(), $fgRGB->green(), $fgRGB->blue());
		$text = $this->getAvatarText();
		$toReplace = ['{size}', '{fill}', '{fgFill}', '{letter}'];
		return str_replace($toReplace, [$size, $fill, $fgFill, $text], $this->svgTemplate);
	}

	/**
	 * Select the rendering font based on the user's display name and language
	 */
	private function getFont(string $userDisplayName): string {
		if (preg_match('/\p{Han}/u', $userDisplayName) === 1) {
			switch ($this->getAvatarLanguage()) {
				case 'zh_TW':
					return __DIR__ . '/../../../core/fonts/NotoSansTC-Regular.ttf';
				case 'zh_HK':
					return __DIR__ . '/../../../core/fonts/NotoSansHK-Regular.ttf';
				case 'ja':
					return __DIR__ . '/../../../core/fonts/NotoSansJP-Regular.ttf';
				case 'ko':
					return __DIR__ . '/../../../core/fonts/NotoSansKR-Regular.ttf';
				default:
					return __DIR__ . '/../../../core/fonts/NotoSansSC-Regular.ttf';
			}
		}
		return __DIR__ . '/../../../core/fonts/NotoSans-Bold.ttf';
	}

	/**
	 * Generate png avatar from svg with Imagick
	 */
	protected function generateAvatarFromSvg(string $userDisplayName, int $size, bool $darkTheme): ?string {
		if (!extension_loaded('imagick')) {
			return null;
		}
		$formats = Imagick::queryFormats();
		// Avatar generation breaks if RSVG format is enabled. Fall back to gd in that case
		if (in_array('RSVG', $formats, true)) {
			return null;
		}
		$text = $this->getAvatarText();
		try {
			$font = $this->getFont($text);
			$svg = $this->getAvatarVector($userDisplayName, $size, $darkTheme);
			$avatar = new Imagick();
			$avatar->setFont($font);
			$avatar->readImageBlob($svg);
			$avatar->setImageFormat('png');
			$image = new Image();
			$image->loadFromData((string)$avatar);
			return $image->data();
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Generate png avatar with GD
	 * @throws \Exception when an error occurs in gd calls
	 */
	protected function generateAvatar(string $userDisplayName, int $size, bool $darkTheme): string {
		$text = $this->getAvatarText();
		[$backgroundColor, $textColor] = $this->avatarColors($userDisplayName, $darkTheme);

		$im = imagecreatetruecolor($size, $size);
		if ($im === false) {
			throw new \Exception('Failed to create avatar image');
		}
		$background = imagecolorallocate(
			$im,
			$backgroundColor->red(),
			$backgroundColor->green(),
			$backgroundColor->blue()
		);
		$textColor = imagecolorallocate($im,
			$textColor->red(),
			$textColor->green(),
			$textColor->blue()
		);
		if ($background === false || $textColor === false) {
			throw new \Exception('Failed to create avatar image color');
		}
		imagefilledrectangle($im, 0, 0, $size, $size, $background);

		$font = $this->getFont($text);

		// xcloud: same proportion as the SVG path, 190/280 of upstream's 0.4
		$fontSize = $size * 0.27;
		[$x, $y] = $this->imageTTFCenter(
			$im, $text, $font, (int)$fontSize
		);

		imagettftext($im, $fontSize, 0, $x, $y, $textColor, $font, $text);

		ob_start();
		imagepng($im);
		$data = ob_get_contents();
		ob_end_clean();

		return $data;
	}

	/**
	 * Calculate real image ttf center
	 *
	 * @param \GdImage $image
	 * @param string $text text string
	 * @param string $font font path
	 * @param int $size font size
	 * @param int $angle
	 * @return array
	 */
	protected function imageTTFCenter(
		$image,
		string $text,
		string $font,
		int $size,
		int $angle = 0,
	): array {
		// Image width & height
		$xi = imagesx($image);
		$yi = imagesy($image);

		// bounding box
		$box = imagettfbbox($size, $angle, $font, $text);

		// imagettfbbox can return negative int
		$xr = abs(max($box[2], $box[4]));
		$yr = abs(max($box[5], $box[7]));

		// calculate bottom left placement
		$x = intval(($xi - $xr) / 2);
		$y = intval(($yi + $yr) / 2);

		return [$x, $y];
	}


	/**
	 * Convert a string to an integer evenly
	 * @param string $hash the text to parse
	 * @param int $maximum the maximum range
	 * @return int between 0 and $maximum
	 */
	private function hashToInt(string $hash, int $maximum): int {
		$final = 0;
		$result = [];

		// Splitting evenly the string
		for ($i = 0; $i < strlen($hash); $i++) {
			// chars in md5 goes up to f, hex:16
			$result[] = intval(substr($hash, $i, 1), 16) % 16;
		}
		// Adds up all results
		foreach ($result as $value) {
			$final += $value;
		}
		// chars in md5 goes up to f, hex:16
		return intval($final % $maximum);
	}

	/**
	 * xcloud: generated avatars are a filled circle in one Catppuccin accent,
	 * as the design draws them — the pastel Mocha tone with dark initials in
	 * the dark theme, the saturated Latte tone with white initials in the
	 * light one. Upstream fills the circle with 10% of the colour over black
	 * or white, which on the Catppuccin surfaces is the surface itself: the
	 * circle disappears, only coloured letters remain, and in an avatar stack
	 * the letters of neighbours run into each other.
	 *
	 * Each pair is [Mocha, Latte] of the same accent, so a person keeps their
	 * colour when switching themes. Yellow and flamingo are left out: white
	 * initials need their Latte tone darkened so far that it turns brown.
	 */
	private const XCLOUD_AVATAR_ACCENTS = [
		['cba6f7', '8839ef'], // mauve
		['89b4fa', '1e66f5'], // blue
		['74c7ec', '209fb5'], // sapphire
		['94e2d5', '179299'], // teal
		['a6e3a1', '40a02b'], // green
		['fab387', 'fe640b'], // peach
		['f5c2e7', 'ea76cb'], // pink
		['f38ba8', 'd20f39'], // red
		['b4befe', '7287fd'], // lavender
		['eba0ac', 'e64553'], // maroon
	];

	/** Catppuccin Mocha "crust": initials on the pastel circles */
	private const XCLOUD_AVATAR_INK_DARK = '11111b';

	/**
	 * Background and text colour of a generated avatar.
	 *
	 * The light theme writes white initials; several Latte accents (peach,
	 * yellow, green, teal…) are too light for white text, so the fill is
	 * darkened towards black until the initials reach WCAG AA (4.5:1) — the
	 * accent stays recognisable, the letters stay readable at 22 px.
	 *
	 * @return array{0: Color, 1: Color} [background, text]
	 */
	protected function avatarColors(string $hash, bool $darkTheme): array {
		$hash = strtolower($hash);
		if (preg_match('/^([0-9a-f]{4}-?){8}$/', $hash) !== 1) {
			$hash = md5($hash);
		}
		$hash = preg_replace('/[^0-9a-f]+/', '', $hash);
		// Not hashToInt(): it sums the hex digits, so the index clusters —
		// three of the four lab users landed on the same accent. The leading
		// 28 bits of the md5 are spread evenly.
		$pair = self::XCLOUD_AVATAR_ACCENTS[hexdec(substr($hash, 0, 7)) % count(self::XCLOUD_AVATAR_ACCENTS)];

		if ($darkTheme) {
			return [self::hexColor($pair[0]), self::hexColor(self::XCLOUD_AVATAR_INK_DARK)];
		}

		$white = new Color(255, 255, 255);
		$black = new Color(0, 0, 0);
		$accent = self::hexColor($pair[1]);
		$fill = $accent;
		for ($share = 0.95; self::contrast($fill, $white) < 4.5 && $share > 0.4; $share -= 0.05) {
			$fill = $accent->alphaBlending($share, $black);
		}
		return [$fill, $white];
	}

	private static function hexColor(string $hex): Color {
		return new Color(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
	}

	/** WCAG 2 contrast ratio of two opaque colours */
	private static function contrast(Color $a, Color $b): float {
		$luminance = static function (Color $c): float {
			$channel = static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
			return 0.2126 * $channel($c->redF()) + 0.7152 * $channel($c->greenF()) + 0.0722 * $channel($c->blueF());
		};
		$la = $luminance($a);
		$lb = $luminance($b);
		return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
	}

	/**
	 * @return Color Object containing r g b int in the range [0, 255]
	 */
	#[\Override]
	public function avatarBackgroundColor(string $hash): Color {
		// Normalize hash
		$hash = strtolower($hash);

		// Already a md5 hash?
		if (preg_match('/^([0-9a-f]{4}-?){8}$/', $hash, $matches) !== 1) {
			$hash = md5($hash);
		}

		// Remove unwanted char
		$hash = preg_replace('/[^0-9a-f]+/', '', $hash);

		$red = new Color(182, 70, 157);
		$yellow = new Color(221, 203, 85);
		$blue = new Color(0, 130, 201); // Nextcloud blue

		// Number of steps to go from a color to another
		// 3 colors * 6 will result in 18 generated colors
		$steps = 6;

		$palette1 = Color::mixPalette($steps, $red, $yellow);
		$palette2 = Color::mixPalette($steps, $yellow, $blue);
		$palette3 = Color::mixPalette($steps, $blue, $red);

		$finalPalette = array_merge($palette1, $palette2, $palette3);

		return $finalPalette[$this->hashToInt($hash, $steps * 3)];
	}

	/**
	 * Get the language to be used for avatar generation.
	 * This is used to determine the font to use for the avatar text (e.g. CJK characters).
	 */
	protected function getAvatarLanguage(): string {
		return $this->config->getSystemValueString('default_language', 'en');
	}
}
