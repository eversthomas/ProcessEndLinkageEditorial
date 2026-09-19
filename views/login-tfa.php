<?php
/** @var string $title */
/** @var string $action */
/** @var string $error */
/** @var string $assetUrl */
/** @var string $baseUrl */
/** @var string $brandName */
/** @var string|null $brandLogoUrl */
$brandName = $brandName ?? 'Redaktion';
$brandLogoUrl = $brandLogoUrl ?? null;
?><!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="<?= htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8') ?>css/editorial.css?v=2">
</head>
<body class="bpe-body bpe-body--login">
	<main class="bpe-login">
		<div class="bpe-login__panel">
			<div class="bpe-login__branding">
				<?php if ($brandLogoUrl): ?>
					<img class="bpe-login__logo" src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>">
				<?php endif; ?>
				<p class="bpe-login__brand"><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></p>
			</div>
			<h1 class="bpe-login__title">Bestätigungscode</h1>
			<p class="bpe-login__lead">Geben Sie den Code aus Ihrer Authenticator-App ein.</p>

			<?php if ($error): ?>
				<div class="bpe-flash bpe-flash--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
			<?php endif; ?>

			<form class="bpe-login__form" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
				<?= $csrf ?? '' ?>
				<div class="bpe-field">
					<label class="bpe-field__label" for="code">Code</label>
					<input class="bpe-input" type="text" id="code" name="code" inputmode="numeric"
						autocomplete="one-time-code" pattern="[0-9]*" maxlength="10" required autofocus>
				</div>
				<button type="submit" class="bpe-btn bpe-btn--primary bpe-btn--block">Bestätigen</button>
			</form>
			<p class="bpe-login__hint"><a href="<?= htmlspecialchars($baseUrl . 'login/', ENT_QUOTES, 'UTF-8') ?>">Zurück zum Login</a></p>
		</div>
	</main>
</body>
</html>
