<?php namespace ProcessWire\BsProcessEditorial\Ui;

/**
 * 4-Spalten-Shell: Rail | Tree | Main | (Details im Content).
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

		ob_start();
		?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $this->e($title) ?> · Redaktion</title>
	<link rel="stylesheet" href="<?= $this->e($assetUrl) ?>css/editorial.css">
	<?php if ($themeStyle): ?>
	<style id="bpe-theme"><?= $themeStyle ?></style>
	<?php endif; ?>
</head>
<body class="bpe-body" data-bpe-shell>
	<div class="bpe-shell" data-rail="expanded" data-tree="<?= $treeCollapsed ? 'collapsed' : 'open' ?>">
		<aside class="bpe-rail" aria-label="Hauptmodule">
			<div class="bpe-rail__top">
				<a class="bpe-rail__brand" href="<?= $this->e($baseUrl) ?>" title="Redaktion">
					<span class="bpe-rail__logo">R</span>
					<span class="bpe-rail__brand-text">Redaktion</span>
				</a>
				<button type="button" class="bpe-rail__toggle" data-bpe-rail-toggle title="Menü umschalten" aria-label="Menü umschalten">
					<?= Icons::svg('panel-left') ?>
				</button>
			</div>
			<nav class="bpe-rail__nav">
				<?php foreach ($railItems as $item): ?>
					<?php
					$id = $item['id'] ?? '';
					$type = $item['type'] ?? '';
					$href = $type === 'dashboard' ? $baseUrl : $baseUrl . 'nav/' . rawurlencode($id) . '/';
					$active = $railActive === $id;
					?>
					<a class="bpe-rail__item<?= $active ? ' is-active' : '' ?>" href="<?= $this->e($href) ?>" title="<?= $this->e($item['label'] ?? $id) ?>">
						<span class="bpe-rail__icon"><?= Icons::svg($item['icon'] ?? 'layers') ?></span>
						<span class="bpe-rail__label"><?= $this->e($item['label'] ?? $id) ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="bpe-rail__footer">
				<?php if ($userName): ?>
					<span class="bpe-rail__user" title="<?= $this->e($userName) ?>">
						<span class="bpe-rail__avatar"><?= $this->e(mb_strtoupper(mb_substr($userName, 0, 1))) ?></span>
						<span class="bpe-rail__label"><?= $this->e($userName) ?><?php if ($isDemo): ?> <em>Demo</em><?php endif; ?></span>
					</span>
				<?php endif; ?>
				<a class="bpe-rail__item" href="<?= $this->e($baseUrl . 'logout/') ?>" title="Abmelden">
					<span class="bpe-rail__icon"><?= Icons::svg('log-out') ?></span>
					<span class="bpe-rail__label">Abmelden</span>
				</a>
			</div>
		</aside>

		<aside class="bpe-sidebar" aria-label="Inhaltsnavigation">
			<div class="bpe-sidebar__head">
				<h2 class="bpe-sidebar__title"><?= $this->e($treeTitle) ?></h2>
				<button type="button" class="bpe-sidebar__collapse" data-bpe-tree-toggle title="Baum einklappen" aria-label="Baum einklappen">
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

		<button type="button" class="bpe-tree-expand" data-bpe-tree-toggle hidden aria-label="Baum einblenden">
			<?= Icons::svg('chevrons-right') ?>
		</button>

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
