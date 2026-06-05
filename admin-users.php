<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
	<title>Manage Users | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
</head>
<body data-bs-theme="dark">
<?php
require_once __DIR__ . '/notif/db.php';
$admin = require_admin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password']     ?? '';
        $is_admin = !empty($_POST['is_admin']);
        if (strlen($username) < 2) {
            $error = 'Username must be at least 2 characters.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                create_user($username, $password, $is_admin);
                $success = 'User "' . htmlspecialchars($username) . '" created.';
            } catch (Exception $e) {
                $error = 'Username already taken.';
            }
        }
    } elseif ($action === 'delete') {
        $del_id = (int)($_POST['user_id'] ?? 0);
        if ($del_id === (int)$admin['id']) {
            $error = "You can't delete your own account.";
        } elseif ($del_id > 0) {
            get_db()->prepare('DELETE FROM users WHERE id = ?')->execute([$del_id]);
            $success = 'User deleted.';
        }
    } elseif ($action === 'reset_password') {
        $uid      = (int)($_POST['user_id']  ?? 0);
        $password = $_POST['new_password']   ?? '';
        if (strlen($password) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($uid > 0) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            get_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);
            $success = 'Password updated.';
        }
    }
}

$users = get_db()->query('SELECT id, username, is_admin, created_at FROM users ORDER BY id ASC')->fetchAll();
?>
	<?php require "components/navbar.php"; ?>
	<div class="container py-4" style="max-width:700px">
		<div class="d-flex align-items-center mb-4 gap-3">
			<h2 class="mb-0">Manage Users</h2>
			<a href="/notifications" class="btn btn-sm btn-outline-secondary ms-auto">← Back</a>
		</div>

		<?php if ($error):   ?><div class="alert alert-danger py-2"><?=htmlspecialchars($error)?></div><?php endif; ?>
		<?php if ($success): ?><div class="alert alert-success py-2"><?=$success?></div><?php endif; ?>

		<!-- User list -->
		<div class="card mb-4">
			<div class="card-header"><h5 class="mb-0">Existing users</h5></div>
			<table class="table table-sm table-hover mb-0">
				<thead><tr><th>Username</th><th>Role</th><th>Created</th><th></th></tr></thead>
				<tbody>
				<?php foreach ($users as $u): ?>
				<tr>
					<td><?=htmlspecialchars($u['username'])?></td>
					<td><?=$u['is_admin'] ? '<span class="badge bg-warning text-dark">admin</span>' : '<span class="badge bg-secondary">user</span>'?></td>
					<td><small class="text-secondary"><?=date('Y-m-d', $u['created_at'])?></small></td>
					<td class="text-end">
						<!-- Reset password -->
						<button class="btn btn-xs btn-outline-secondary py-0 px-2"
							style="font-size:.75rem"
							data-bs-toggle="modal" data-bs-target="#pw-modal"
							data-uid="<?=(int)$u['id']?>"
							data-uname="<?=htmlspecialchars($u['username'])?>">pw</button>
						<?php if ($u['id'] != $admin['id']): ?>
						<form method="post" class="d-inline"
							onsubmit="return confirm('Delete user <?=htmlspecialchars(addslashes($u['username']))?>?')">
							<input type="hidden" name="action"  value="delete">
							<input type="hidden" name="user_id" value="<?=(int)$u['id']?>">
							<button class="btn btn-xs btn-outline-danger py-0 px-2"
								style="font-size:.75rem" type="submit">del</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Create user -->
		<div class="card">
			<div class="card-header"><h5 class="mb-0">Create user</h5></div>
			<div class="card-body">
				<form method="post" autocomplete="off">
					<input type="hidden" name="action" value="create">
					<div class="row g-2 mb-2">
						<div class="col-sm-5">
							<input type="text"     name="username" class="form-control" placeholder="Username" required>
						</div>
						<div class="col-sm-5">
							<input type="password" name="password" class="form-control" placeholder="Password (min 6 chars)" required>
						</div>
						<div class="col-sm-2 d-flex align-items-center">
							<div class="form-check mb-0">
								<input class="form-check-input" type="checkbox" name="is_admin" id="is_admin">
								<label class="form-check-label" for="is_admin">Admin</label>
							</div>
						</div>
					</div>
					<button class="btn btn-primary btn-sm" type="submit">Create</button>
				</form>
			</div>
		</div>

	</div>

	<!-- Reset password modal -->
	<div class="modal fade" id="pw-modal" tabindex="-1">
		<div class="modal-dialog modal-sm">
			<div class="modal-content">
				<form method="post">
					<input type="hidden" name="action"   value="reset_password">
					<input type="hidden" name="user_id"  id="pw-uid">
					<div class="modal-header">
						<h5 class="modal-title">Reset password for <span id="pw-uname"></span></h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<input type="password" name="new_password" class="form-control"
							placeholder="New password (min 6 chars)" required autocomplete="new-password">
					</div>
					<div class="modal-footer">
						<button class="btn btn-secondary btn-sm" data-bs-dismiss="modal" type="button">Cancel</button>
						<button class="btn btn-primary btn-sm" type="submit">Save</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php require "components/commonjs.html"; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
	document.getElementById("pw-modal").addEventListener("show.bs.modal", e => {
		const btn = e.relatedTarget;
		document.getElementById("pw-uid").value   = btn.dataset.uid;
		document.getElementById("pw-uname").textContent = btn.dataset.uname;
	});
	</script>
</body>
</html>
