<?php
/** @var string $title */
/** @var string $content */
/** @var string $baseUrl */
/** @var string $assetUrl */
/** @var string|null $userName */
/** @var string|null $flash */
/** @var string|null $navActive */
/** @var array $navItems */
/** @var string|null $dataSource */
$navItems = $navItems ?? [];
?><!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · Redaktion</title>
	<link rel="stylesheet" href="<?= htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8') ?>css/editorial.css">
</head>
<body class="bpe-body">
	<div class="bpe-shell">
		<aside class="bpe-nav" aria-label="Hauptnavigation">
			<div class="bpe-nav__brand">
				<a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">Redaktion</a>
			</div>
			<nav class="bpe-nav__list">
				<?php foreach ($navItems as $item): ?>
					<?php
					$name = $item['name'] ?? '';
					$label = $item['label'] ?? $name;
					$href = $baseUrl . rawurlencode($name) . '/';
					?>
					<a class="bpe-nav__item<?= $navActive === $name ? ' is-active' : '' ?>"
					   href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
						<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="bpe-nav__footer">
				<?php if (!empty($dataSource)): ?>
					<span class="bpe-nav__source">Daten: <?= htmlspecialchars($dataSource, ENT_QUOTES, 'UTF-8') ?></span>
				<?php endif; ?>
				<?php if ($userName): ?>
					<span class="bpe-nav__user"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
					<a class="bpe-nav__logout" href="<?= htmlspecialchars($baseUrl . 'logout/', ENT_QUOTES, 'UTF-8') ?>">Abmelden</a>
				<?php endif; ?>
			</div>
		</aside>
		<main class="bpe-main">
			<?php if (!empty($flash)): ?>
				<div class="bpe-flash bpe-flash--ok" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
			<?php endif; ?>
			<?= $content ?>
		</main>
	</div>
	<script src="<?= htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8') ?>js/editorial.js"></script>
</body>
</html>
