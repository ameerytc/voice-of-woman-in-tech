<?php $org = setting('org_name', 'Voice of Women in Tech'); ?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#160B2E">
<?= $meta ?? '<meta name="robots" content="noindex">' ?>
<link rel="icon" type="image/png" href="<?= e(asset('images/logo.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<title><?= e(($pageTitle ?? '') !== '' ? $pageTitle . ' · ' : '') ?><?= e($org) ?></title>
<?= $headExtra ?? '' ?>
</head>
