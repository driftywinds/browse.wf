<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
	<title>Prime Vault | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container pt-3">
		<h3>In Rotation</h3>
		<ul id="inrotation"></ul>
		<h3>Prime Resurgence</h3>
		<ul id="resurgence"></ul>
		<h3>Vaulted</h3>
		<ul id="vaulted"></ul>
	</div>
	<?php require "components/commonjs.html"; ?>
	<script src="typestripped/prime-vault.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
