<?php
require_once __DIR__ . '/notif/db.php';
session_start_safe();

$error   = '';
$mode    = 'login';
$no_users = user_count() === 0;

if ($no_users) {
    $mode = 'register';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? 'login';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($action === 'register') {
        $mode = 'register';
        if (strlen($username) < 2) {
            $error = 'Username must be at least 2 characters.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (!$no_users) {
            $error = 'Registration is closed. Ask an admin to create your account.';
        } else {
            try {
                $uid = create_user($username, $password, true);
                $_SESSION['user_id'] = $uid;
                header('Location: /notifications');
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        $user = verify_user($username, $password);
        if (!$user) {
            $error = 'Invalid username or password.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $redir = $_GET['next'] ?? '/notifications';
            header('Location: ' . $redir);
            exit;
        }
    }
}

if (current_user()) {
    header('Location: /notifications');
    exit;
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
	<title>Login | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container py-5" style="max-width:420px">
		<h2 class="mb-1"><?=$no_users ? 'Create Admin Account' : ($mode === 'register' ? 'Register' : 'Login')?></h2>
		<?php if ($no_users): ?>
		<p class="text-secondary mb-4">No accounts exist yet. The first account created becomes the admin.</p>
		<?php else: ?>
		<p class="text-secondary mb-4">Sign in to manage your notification settings.</p>
		<?php endif; ?>

		<?php if ($error): ?>
		<div class="alert alert-danger py-2"><?=htmlspecialchars($error)?></div>
		<?php endif; ?>

		<div class="card">
			<div class="card-body">
				<form method="post" autocomplete="on">
					<input type="hidden" name="action" value="<?=$mode?>">
					<div class="mb-3">
						<label class="form-label" for="username">Username</label>
						<input type="text" class="form-control" id="username" name="username"
							value="<?=htmlspecialchars($_POST['username'] ?? '')?>"
							autocomplete="username" required autofocus>
					</div>
					<div class="mb-3">
						<label class="form-label" for="password">Password</label>
						<input type="password" class="form-control" id="password" name="password"
							autocomplete="<?=$mode === 'register' ? 'new-password' : 'current-password'?>" required>
					</div>
					<button class="btn btn-primary w-100" type="submit">
						<?=$no_users ? 'Create account &amp; continue' : ($mode === 'register' ? 'Register' : 'Log in')?>
					</button>
				</form>
			</div>
		</div>
	</div>
	<?php require "components/commonjs.html"; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>