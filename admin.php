<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
	<title>Admin | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
	<style>
		.user-row { display: flex; align-items: center; gap: .75rem; padding: .5rem .75rem; border-radius: .5rem; }
		.user-row:nth-child(odd) { background: var(--bs-secondary-bg); }
		.admin-badge { font-size: .7rem; padding: .125rem .5rem; border-radius: 1rem; background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text); }
	</style>
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container pt-3">
		<h3 class="mb-3">🛠️ Admin Panel</h3>

		<div id="status-msg" class="alert d-none" role="alert"></div>

		<!-- Auth check -->
		<div id="not-admin-msg" class="alert alert-warning d-none">You need admin access to view this page.</div>

		<div id="admin-content" class="d-none">
			<!-- User List -->
			<div class="card mb-3">
				<h5 class="card-header d-flex justify-content-between align-items-center">
					Users
					<span id="user-count" class="badge text-bg-secondary"></span>
				</h5>
				<div class="card-body" id="users-list">
					<p class="text-body-secondary">Loading...</p>
				</div>
			</div>

			<!-- Create User -->
			<div class="card mb-3">
				<h5 class="card-header">Create User</h5>
				<div class="card-body">
					<div class="row g-2">
						<div class="col-md-4">
							<input id="new-username" type="text" class="form-control" placeholder="Username" />
						</div>
						<div class="col-md-4">
							<input id="new-password" type="password" class="form-control" placeholder="Password" />
						</div>
						<div class="col-md-4">
							<button class="btn btn-success w-100" onclick="createUser()">+ Create</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php require "components/commonjs.html"; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
		async function apiCall(url, options = {}) {
			try {
				const res = await fetch(url, {
					headers: { "Content-Type": "application/json" },
					...options,
				});
				return await res.json();
			} catch (e) {
				return { success: false, message: "Network error: " + e.message };
			}
		}

		function showStatus(msg, type) {
			const el = document.getElementById("status-msg");
			el.textContent = msg;
			el.className = "alert alert-" + type + " show";
			el.classList.remove("d-none");
			clearTimeout(el._timeout);
			el._timeout = setTimeout(() => el.classList.add("d-none"), 6000);
		}

		async function loadUsers() {
			const res = await apiCall("api/admin.php?action=users", { method: "GET" });
			const list = document.getElementById("users-list");
			const count = document.getElementById("user-count");

			if (res.success) {
				count.textContent = res.users.length + " user" + (res.users.length !== 1 ? "s" : "");
				list.innerHTML = "";
				for (const user of res.users) {
					const div = document.createElement("div");
					div.className = "user-row";
					div.innerHTML = `
						<span class="flex-grow-1">
							<strong>${escHtml(user.username)}</strong>
							${user.is_admin ? '<span class="admin-badge ms-1">Admin</span>' : ""}
							<br><span class="text-body-secondary small">Created ${new Date(user.created_at * 1000).toLocaleDateString()}</span>
						</span>
						${user.is_admin ? "" : `<button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id}, '${escHtml(user.username)}')">Delete</button>`}
					`;
					list.appendChild(div);
				}
			} else {
				list.innerHTML = `<p class="text-danger">${res.message}</p>`;
			}
		}

		function escHtml(s) { return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"); }

		async function createUser() {
			const username = document.getElementById("new-username").value.trim();
			const password = document.getElementById("new-password").value;
			if (!username || !password) { showStatus("Please fill in both fields.", "warning"); return; }

			const res = await apiCall("api/admin.php?action=create_user", {
				method: "POST",
				body: JSON.stringify({ username, password }),
			});

			if (res.success) {
				showStatus("User '" + username + "' created.", "success");
				document.getElementById("new-username").value = "";
				document.getElementById("new-password").value = "";
				loadUsers();
			} else {
				showStatus(res.message, "danger");
			}
		}

		async function deleteUser(id, username) {
			if (!confirm("Delete user '" + username + "'? This cannot be undone.")) return;

			const res = await apiCall("api/admin.php?action=delete_user", {
				method: "POST",
				body: JSON.stringify({ user_id: id }),
			});

			if (res.success) {
				showStatus("User deleted.", "success");
				loadUsers();
			} else {
				showStatus(res.message, "danger");
			}
		}

		async function checkAccess() {
			const sess = await apiCall("api/auth.php?action=session", { method: "GET" });
			if (sess.logged_in && sess.user.is_admin) {
				document.getElementById("admin-content").classList.remove("d-none");
				loadUsers();
			} else {
				document.getElementById("not-admin-msg").classList.remove("d-none");
				if (sess.logged_in) {
					document.getElementById("not-admin-msg").textContent = "You are logged in but do not have admin privileges.";
				}
			}
		}

		document.addEventListener("DOMContentLoaded", checkAccess);

		document.getElementById("new-password").addEventListener("keydown", function(e) {
			if (e.key === "Enter") createUser();
		});
	</script>
</body>
</html>
