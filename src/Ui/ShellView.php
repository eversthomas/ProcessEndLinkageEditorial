<?php namespace ProcessWire\BsProcessEditorial\Ui;

/**
 * 4-Spalten-Shell: Rail | Tree | Stage (Header / Main / Footer).
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
		$railItems = $vars['railItems'] ?? [];
		$railActive = $vars['railActive'] ?? null;
		$treeHtml = $vars['treeHtml'] ?? '';
		$treeTitle = $vars['treeTitle'] ?? 'Inhalte';
		$treeCollapsed = !empty($vars['treeCollapsed']);
		$themeStyle = $vars['themeStyle'] ?? '';
		$dataSource = $vars['dataSource'] ?? null;
		$needsTinyMce = !empty($vars['needsTinyMce']);
		$tinyMceUrl = $vars['tinyMceUrl'] ?? '';
		$headerMeta = $vars['headerMeta'] ?? null;
		$brandName = (string) ($vars['brandName'] ?? 'Redaktion');
		$brandLogoUrl = $vars['brandLogoUrl'] ?? null;
		$brandInitial = mb_strtoupper(mb_substr($brandName, 0, 1));
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
	<link rel="stylesheet" href="<?= $this->e($assetUrl) ?>css/broadsheet.css">
	<link rel="stylesheet" href="<?= $this->e($assetUrl) ?>css/editorial.css">
	<?php if ($themeStyle): ?>
	<style id="bpe-theme"><?= $themeStyle ?></style>
	<?php endif; ?>
</head>
<body class="bpe-body<?= $designSkin === 'daten' ? ' bpe-skin-daten' : '' ?>" data-bpe-shell data-design="<?= $this->e($designSkin) ?>">
	<div class="bpe-shell" data-rail="expanded" data-tree="<?= $treeCollapsed ? 'collapsed' : 'open' ?>">
		<aside class="bpe-rail" aria-label="Hauptmodule">
			<div class="bpe-rail__top">
				<a class="bpe-rail__brand" href="<?= $this->e($baseUrl) ?>" data-tooltip="<?= $this->e($brandName) ?>" aria-label="<?= $this->e($brandName) ?>">
					<?php if ($brandLogoUrl): ?>
						<img class="bpe-rail__logo-img" src="<?= $this->e($brandLogoUrl) ?>" alt="<?= $this->e($brandName) ?>">
					<?php else: ?>
						<span class="bpe-rail__logo"><?= $this->e($brandInitial) ?></span>
					<?php endif; ?>
					<span class="bpe-rail__brand-text"><?= $this->e($brandName) ?></span>
				</a>
				<button type="button" class="bpe-rail__toggle" data-bpe-rail-toggle data-tooltip="Menü ein-/ausklappen" aria-label="Menü ein-/ausklappen" title="Menü ein-/ausklappen">
					<?= Icons::svg('panel-left') ?>
				</button>
			</div>
			<nav class="bpe-rail__nav">
				<?php foreach ($railItems as $item): ?>
					<?php
					$id = $item['id'] ?? '';
					$type = $item['type'] ?? '';
					$label = (string) ($item['label'] ?? $id);
					$href = $type === 'dashboard' ? $baseUrl : $baseUrl . 'nav/' . rawurlencode($id) . '/';
					$active = $railActive === $id;
					?>
					<a class="bpe-rail__item<?= $active ? ' is-active' : '' ?>"
					   href="<?= $this->e($href) ?>"
					   data-tooltip="<?= $this->e($label) ?>"
					   aria-label="<?= $this->e($label) ?>"
					   title="<?= $this->e($label) ?>">
						<span class="bpe-rail__icon"><?= Icons::svg($item['icon'] ?? 'layers') ?></span>
						<span class="bpe-rail__label"><?= $this->e($label) ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="bpe-rail__footer">
				<?php if ($userName): ?>
					<span class="bpe-rail__user" data-tooltip="<?= $this->e($userName) ?>" title="<?= $this->e($userName) ?>">
						<span class="bpe-rail__avatar"><?= $this->e(mb_strtoupper(mb_substr($userName, 0, 1))) ?></span>
						<span class="bpe-rail__label"><?= $this->e($userName) ?><?php if ($isDemo): ?> <em>Demo</em><?php endif; ?></span>
					</span>
				<?php endif; ?>
				<a class="bpe-rail__item" href="<?= $this->e($baseUrl . 'logout/') ?>" data-tooltip="Abmelden" aria-label="Abmelden" title="Abmelden">
					<span class="bpe-rail__icon"><?= Icons::svg('log-out') ?></span>
					<span class="bpe-rail__label">Abmelden</span>
				</a>
			</div>
		</aside>

		<aside class="bpe-sidebar" aria-label="Inhaltsnavigation">
			<div class="bpe-sidebar__head">
				<h2 class="bpe-sidebar__title"><?= $this->e($treeTitle) ?></h2>
				<button type="button" class="bpe-sidebar__collapse" data-bpe-tree-toggle data-tooltip="Navigation einklappen" title="Navigation einklappen" aria-label="Navigation einklappen">
					<?= Icons::svg('chevrons-left') ?>
				</button>
			</div>
			<div class="bpe-sidebar__body">
				<?= $treeHtml ?: '<p class="bpe-tree__empty">Wählen Sie ein Modul links.</p>' ?>
			</div>
			<?php if ($dataSource): ?>
				<div class="bpe-sidebar__foot">Daten: <?= $this->e($dataSource) ?></div>
			<?php endif; ?>
		</aside>

		<div class="bpe-stage">
			<header class="bpe-topbar">
				<div class="bpe-topbar__left">
					<button type="button" class="bpe-tree-expand" data-bpe-tree-toggle data-tooltip="Navigation einblenden" title="Navigation einblenden" aria-label="Navigation einblenden">
						<?= Icons::svg('chevrons-right') ?>
					</button>
					<a class="bpe-topbar__branding" href="<?= $this->e($baseUrl) ?>">
						<?php if ($brandLogoUrl): ?>
							<img class="bpe-topbar__logo" src="<?= $this->e($brandLogoUrl) ?>" alt="<?= $this->e($brandName) ?>">
						<?php endif; ?>
						<span class="bpe-topbar__branding-text">
							<span class="bpe-topbar__brand"><?= $this->e($brandName) ?></span>
							<span class="bpe-topbar__title"><?= $this->e($title) ?></span>
						</span>
					</a>
				</div>
				<div class="bpe-topbar__right">
					<?php if ($headerMeta): ?>
						<span class="bpe-topbar__meta"><?= $this->e($headerMeta) ?></span>
					<?php endif; ?>
					<?php if ($userName): ?>
						<span class="bpe-topbar__user"><?= $this->e($userName) ?></span>
					<?php endif; ?>
				</div>
			</header>

			<main class="bpe-main">
				<?php if (!empty($flash)): ?>
					<div class="bpe-flash bpe-flash--ok" role="status" data-bpe-flash>
						<span><?= $this->e($flash) ?></span>
						<button type="button" class="bpe-flash__close" aria-label="Schließen" data-bpe-flash-close>&times;</button>
					</div>
				<?php endif; ?>
				<?= $content ?>
			</main>

			<footer class="bpe-footer">
				<p class="bpe-footer__copy">
					<span>Tom Evers</span>
					<span class="bpe-footer__sep" aria-hidden="true">|</span>
					<a href="https://bezugssysteme.de" target="_blank" rel="noopener noreferrer">bezugssysteme.de</a>
				</p>
			</footer>
		</div>
	</div>
	<script src="<?= $this->e($assetUrl) ?>js/editorial.js"></script>
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
