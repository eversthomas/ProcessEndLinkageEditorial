<?php namespace ProcessWire\BsProcessEditorial\Ui;

/**
 * App-Shell: Kopfzeile + Navigationsfläche + Hauptbereich.
 * Desktop: Navigationsfläche persistent links. Mobil: Kopfzeile + untere Tab-Leiste,
 * Navigationsfläche als Overlay (Tabs „Inhalte"/„Mehr" öffnen dieselbe Fläche).
 */
class ShellView {

	public function render(array $vars): string {
		$baseUrl = $vars['baseUrl'] ?? '/';
		$assetUrl = $vars['assetUrl'] ?? '';
		$title = $vars['title'] ?? 'Redaktion';
		$content = $vars['content'] ?? '';
		$userName = $vars['userName'] ?? null;
		$isDemo = !empty($vars['isDemo']);
		$flash = $vars['flash'] ?? null;
		$treeHtml = $vars['treeHtml'] ?? '';
		$treeTitle = $vars['treeTitle'] ?? 'Inhalte';
		$themeStyle = $vars['themeStyle'] ?? '';
		$needsTinyMce = !empty($vars['needsTinyMce']);
		$tinyMceUrl = $vars['tinyMceUrl'] ?? '';
		$brandName = (string) ($vars['brandName'] ?? 'Redaktion');
		$brandLogoUrl = $vars['brandLogoUrl'] ?? null;
		$brandInitial = mb_strtoupper(mb_substr($brandName, 0, 1));
		$primaryAction = $vars['primaryAction'] ?? null; // ['label' => ..., 'url' => ...]
		$designSkin = (string) ($vars['designSkin'] ?? 'legacy');
		if ($designSkin !== 'daten') {
			$designSkin = 'legacy';
		}

		ob_start();
		?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $this->e($title) ?> · <?= $this->e($brandName) ?></title>
	<link rel="stylesheet" href="<?= $this->e($assetUrl) ?>css/broadsheet.css?v=2">
	<link rel="stylesheet" href="<?= $this->e($assetUrl) ?>css/editorial.css?v=5">
	<?php if ($themeStyle): ?>
	<style id="bpe-theme"><?= $themeStyle ?></style>
	<?php endif; ?>
</head>
<body class="bpe-body<?= $designSkin === 'daten' ? ' bpe-skin-daten' : '' ?>" data-bpe-shell data-design="<?= $this->e($designSkin) ?>">
	<div class="bpe-app" data-nav="closed">
		<header class="bpe-appbar">
			<a class="bpe-appbar__brand" href="<?= $this->e($baseUrl) ?>" aria-label="<?= $this->e($brandName) ?>">
				<?php if ($brandLogoUrl): ?>
					<img class="bpe-appbar__logo" src="<?= $this->e($brandLogoUrl) ?>" alt="<?= $this->e($brandName) ?>">
				<?php else: ?>
					<span class="bpe-appbar__logo bpe-appbar__logo--fallback"><?= $this->e($brandInitial) ?></span>
				<?php endif; ?>
				<span class="bpe-appbar__brand-text"><?= $this->e($brandName) ?></span>
			</a>
			<span class="bpe-appbar__title"><?= $this->e($title) ?></span>
			<div class="bpe-appbar__actions">
				<?php if ($primaryAction && !empty($primaryAction['url'])): ?>
					<a class="bpe-appbar__cta" href="<?= $this->e($primaryAction['url']) ?>"><?= $this->e($primaryAction['label'] ?? 'Neu') ?></a>
				<?php endif; ?>
				<?php if ($userName): ?>
					<span class="bpe-appbar__user" title="<?= $this->e($userName) ?>">
						<span class="bpe-appbar__avatar"><?= $this->e(mb_strtoupper(mb_substr($userName, 0, 1))) ?></span>
						<span class="bpe-appbar__username"><?= $this->e($userName) ?><?php if ($isDemo): ?> <em>Demo</em><?php endif; ?></span>
					</span>
				<?php endif; ?>
				<a class="bpe-appbar__logout" href="<?= $this->e($baseUrl . 'logout/') ?>">Abmelden</a>
			</div>
		</header>

		<div class="bpe-appshell">
			<aside class="bpe-navpanel" data-bpe-navpanel aria-label="Navigation">
				<div class="bpe-navpanel__head">
					<span class="bpe-navpanel__title"><?= $this->e($treeTitle) ?></span>
					<button type="button" class="bpe-navpanel__close" data-bpe-nav-close aria-label="Schließen">✕</button>
				</div>
				<div class="bpe-navpanel__body">
					<?= $treeHtml ?: '<p class="bpe-navpanel__empty">Keine Inhalte verfügbar.</p>' ?>
				</div>
				<div class="bpe-navpanel__foot" data-bpe-nav-foot>
					<?php if ($userName): ?>
						<p class="bpe-navpanel__account">Angemeldet als <strong><?= $this->e($userName) ?></strong><?php if ($isDemo): ?> · Demo<?php endif; ?></p>
					<?php endif; ?>
					<a class="bpe-navpanel__logout" href="<?= $this->e($baseUrl . 'logout/') ?>">Abmelden</a>
					<p class="bpe-navpanel__credit">
						<span>Tom Evers</span>
						<span aria-hidden="true"> · </span>
						<a href="https://bezugssysteme.de" target="_blank" rel="noopener noreferrer">bezugssysteme.de</a>
					</p>
				</div>
			</aside>
			<div class="bpe-navpanel__scrim" data-bpe-nav-close></div>

			<main class="bpe-main">
				<?php if (!empty($flash)): ?>
					<div class="bpe-flash bpe-flash--ok" role="status" data-bpe-flash>
						<span><?= $this->e($flash) ?></span>
						<button type="button" class="bpe-flash__close" aria-label="Schließen" data-bpe-flash-close>&times;</button>
					</div>
				<?php endif; ?>
				<?= $content ?>
			</main>
		</div>

		<nav class="bpe-tabbar" aria-label="Hauptnavigation">
			<a class="bpe-tabbar__item" href="<?= $this->e($baseUrl) ?>">Start</a>
			<button type="button" class="bpe-tabbar__item" data-bpe-nav-open="body">Inhalte</button>
			<button type="button" class="bpe-tabbar__item" data-bpe-nav-open="foot">Mehr</button>
		</nav>
	</div>

	<script src="<?= $this->e($assetUrl) ?>js/editorial.js?v=2"></script>
	<?php if ($needsTinyMce && $tinyMceUrl): ?>
	<script src="<?= $this->e($tinyMceUrl) ?>"></script>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		if (!window.tinymce) return;
		tinymce.init({
			selector: 'textarea.bpe-input--html',
			menubar: false,
			plugins: 'lists link code',
			toolbar: 'bold italic underline | bullist numlist | link | removeformat | code',
			height: 360,
			branding: false,
			promotion: false,
			content_style: 'body{font-family:system-ui,sans-serif;font-size:15px;line-height:1.5}'
		});
	});
	</script>
	<?php endif; ?>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	protected function e(mixed $v): string {
		return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
	}
}
