<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
	<title>Notifications | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
	<style>
		.section-card { margin-bottom: 1rem; }
		.subscription-group { margin-left: 1.5rem; }
		.form-switch { padding-left: 2.5em; }
		.event-label { cursor: pointer; user-select: none; }
		.filter-row { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; margin-top: .25rem; margin-left: 1.5rem; }
		.filter-row label { font-size: .875rem; color: var(--bs-secondary-color); }
		.filter-row select { width: auto; min-width: 120px; }
		#status-msg { transition: opacity .3s; }
		#status-msg.fade-out { opacity: 0; }
		.auth-section { max-width: 400px; }
		.config-section { display: none; }
		.config-section.visible { display: block; }
		.badge-tag { font-size: .75rem; padding: .125rem .5rem; border-radius: 1rem; background: var(--bs-secondary-bg); color: var(--bs-body-color); display: inline-block; margin: .125rem; }
	</style>
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container pt-3">
		<h3 class="mb-3">🔔 Notifications</h3>

		<div id="status-msg" class="alert d-none" role="alert"></div>

		<!-- Auth Section -->
		<div id="auth-section" class="auth-section">
			<!-- Login Form -->
			<div id="login-form" class="card section-card">
				<h5 class="card-header">Log In</h5>
				<div class="card-body">
					<div class="mb-3">
						<label for="login-username" class="form-label">Username</label>
						<input id="login-username" type="text" class="form-control" autocomplete="username" />
					</div>
					<div class="mb-3">
						<label for="login-password" class="form-label">Password</label>
						<input id="login-password" type="password" class="form-control" autocomplete="current-password" />
					</div>
					<button class="btn btn-primary" onclick="doLogin()">Log In</button>
					<button class="btn btn-outline-secondary ms-2" onclick="showRegister()">Create Account</button>
				</div>
			</div>
			<!-- Register Form (hidden by default) -->
			<div id="register-form" class="card section-card d-none">
				<h5 class="card-header">Create Account</h5>
				<div class="card-body">
					<p class="text-body-secondary small">The first account created on this server becomes the admin.</p>
					<div class="mb-3">
						<label for="reg-username" class="form-label">Username</label>
						<input id="reg-username" type="text" class="form-control" autocomplete="username" />
					</div>
					<div class="mb-3">
						<label for="reg-password" class="form-label">Password</label>
						<input id="reg-password" type="password" class="form-control" autocomplete="new-password" />
					</div>
					<div class="mb-3">
						<label for="reg-password2" class="form-label">Confirm Password</label>
						<input id="reg-password2" type="password" class="form-control" autocomplete="new-password" />
					</div>
					<button class="btn btn-success" onclick="doRegister()">Create Account</button>
					<button class="btn btn-outline-secondary ms-2" onclick="showLogin()">Back to Log In</button>
				</div>
			</div>
		</div>

		<!-- Config Section (visible when logged in) -->
		<div id="config-section" class="config-section">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<span>Logged in as <b id="logged-in-user"></b></span>
				<button class="btn btn-sm btn-outline-danger" onclick="doLogout()">Log Out</button>
			</div>

			<!-- Apprise Server -->
			<div class="card section-card">
				<h5 class="card-header">Apprise Server</h5>
				<div class="card-body">
					<div class="mb-3">
						<label for="apprise-url" class="form-label">Apprise Server URL</label>
						<input id="apprise-url" type="url" class="form-control" placeholder="http://apprise:8000" />
						<div class="form-text">The URL of your running <a href="https://github.com/caronc/apprise-api" target="_blank">Apprise API</a> server (e.g. <code>http://192.168.1.100:8000</code>).</div>
					</div>
					<div class="mb-3">
						<label for="apprise-endpoints" class="form-label">Notification Endpoints / Tags</label>
						<textarea id="apprise-endpoints" class="form-control" rows="2" placeholder="discord://webhook_id/webhook_token&#10;slack://token_a/token_b/token_c&#10;tagos"></textarea>
						<div class="form-text">One URL or tag per line. These are passed to the Apprise server for each notification. Leave empty to use the server's default configured endpoints.</div>
					</div>
				</div>
			</div>

			<!-- Event Subscriptions -->
			<div class="card section-card">
				<h5 class="card-header">Event Subscriptions</h5>
				<div class="card-body">
					<p class="text-body-secondary small mb-3">Toggle which events should trigger Apprise notifications. For Fissures and Arbitrations you can filter by mission type and tier.</p>

					<div id="subscriptions-list"></div>
				</div>
			</div>

			<!-- Actions -->
			<div class="d-flex gap-2 mb-3">
				<button id="save-btn" class="btn btn-primary" onclick="saveConfig()">💾 Save Config</button>
				<button id="test-btn" class="btn btn-outline-info" onclick="testNotification()">🧪 Send Test Notification</button>
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

		function showLogin() {
			document.getElementById("login-form").classList.remove("d-none");
			document.getElementById("register-form").classList.add("d-none");
		}

		function showRegister() {
			document.getElementById("login-form").classList.add("d-none");
			document.getElementById("register-form").classList.remove("d-none");
		}

		async function doLogin() {
			const username = document.getElementById("login-username").value.trim();
			const password = document.getElementById("login-password").value;
			if (!username || !password) { showStatus("Please fill in both fields.", "warning"); return; }
			const res = await apiCall("api/auth.php?action=login", {
				method: "POST",
				body: JSON.stringify({ username, password }),
			});
			if (res.success) {
				showStatus("Logged in as " + res.user.username, "success");
				checkSession();
			} else {
				showStatus(res.message, "danger");
			}
		}

		async function doRegister() {
			const username = document.getElementById("reg-username").value.trim();
			const password = document.getElementById("reg-password").value;
			const password2 = document.getElementById("reg-password2").value;
			if (!username || !password) { showStatus("Please fill in all fields.", "warning"); return; }
			if (password !== password2) { showStatus("Passwords do not match.", "warning"); return; }
			const res = await apiCall("api/auth.php?action=register", {
				method: "POST",
				body: JSON.stringify({ username, password }),
			});
			if (res.success) {
				showStatus(res.message, "success");
				checkSession();
			} else {
				showStatus(res.message, "danger");
			}
		}

		async function doLogout() {
			await apiCall("api/auth.php?action=logout", { method: "POST" });
			checkSession();
		}

		// Subscription definitions
		const SUBSCRIPTIONS = [
			{ key: "sortie", label: "Sortie", desc: "New sortie available" },
			{ key: "litesortie", label: "Archon Hunt", desc: "New Archon Hunt (weekly)" },
			{ key: "alert", label: "Alerts", desc: "New alerts" },
			{ key: "darvo", label: "Darvo's Deal", desc: "New Darvo daily deal" },
			{ key: "baro", label: "Baro Ki'Teer", desc: "Baro arrives at a relay" },
			{ key: "bounties", label: "Bounties", desc: "New bounty rotation" },
			{ key: "nightfall", label: "Nightfall", desc: "30s before Plains of Eidolon nightfall" },
			{ key: "news", label: "News", desc: "New news/redtext posts" },
			{ key: "teshin", label: "Vendors", desc: "Teshin/Iron Wake weekly refresh (weekly)" },
			{ key: "circuit", label: "Weekly Missions", desc: "Weekly missions refresh (weekly)" },
			{ key: "labconquest", label: "Deep Archimedea", desc: "Deep Archimedea refresh (weekly)" },
			{ key: "hexconquest", label: "Temporal Archimedea", desc: "Temporal Archimedea refresh (weekly)" },
			{ key: "invasions", label: "Invasions", desc: "New invasions detected" },
			{ key: "weekly", label: "Weekly Rollover", desc: "Combined weekly notification (when week resets)" },
		];

		const FISSURE_MISSION_TYPES = [
			"MT_SURVIVAL", "MT_DEFENSE", "MT_EXCAVATE", "MT_TERRITORY", "MT_SABOTAGE",
			"MT_CAPTURE", "MT_MOBILE_DEFENSE", "MT_RESCUE", "MT_EXTERMINATION",
			"MT_HIVE", "MT_ASSAULT", "MT_PURIFY", "MT_EVACUATION", "MT_ARTIFACT",
			"MT_CORRUPTION", "MT_VOID_CASCADE", "MT_ARMAGEDDON", "MT_ALCHEMY",
		];

		const FISSURE_TIERS = ["VoidT1", "VoidT2", "VoidT3", "VoidT4", "VoidT5", "VoidT6"];

		const TIER_LABELS = { VoidT1: "Lith", VoidT2: "Meso", VoidT3: "Neo", VoidT4: "Axi", VoidT5: "Requiem", VoidT6: "Omnia" };

		const ARBY_MISSION_TYPES = [
			"MT_SURVIVAL", "MT_DEFENSE", "MT_TERRITORY", "MT_EXCAVATE",
			"MT_PURIFY", "MT_EVACUATION", "MT_ARTIFACT",
			"MT_CORRUPTION", "MT_VOID_CASCADE", "MT_ARMAGEDDON", "MT_ALCHEMY",
		];

		function buildSubscriptionsUI(config) {
			const container = document.getElementById("subscriptions-list");
			container.innerHTML = "";

			// Simple event toggles
			for (const sub of SUBSCRIPTIONS) {
				const div = document.createElement("div");
				div.className = "form-check form-switch mb-2";
				div.innerHTML = `
					<input class="form-check-input" type="checkbox" id="sub-${sub.key}" ${config.subscriptions[sub.key] ? "checked" : ""}>
					<label class="form-check-label event-label" for="sub-${sub.key}">
						<strong>${sub.label}</strong>
						<br><span class="text-body-secondary small">${sub.desc}</span>
					</label>`;
				container.appendChild(div);
			}

			// Fissures (granular)
			{
				const fiss = config.subscriptions.fissures || {};
				const section = document.createElement("div");
				section.className = "mt-3 pt-3 border-top";
				section.innerHTML = `
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="sub-fissures-enabled" ${fiss.enabled ? "checked" : ""}>
						<label class="form-check-label event-label" for="sub-fissures-enabled"><strong>Fissures (Granular)</strong></label>
					</div>
					<div id="fissures-filters" class="filter-row ${fiss.enabled ? "" : "d-none"}">`;

				const filtersDiv = section.querySelector("#fissures-filters");

				// Mission type multi-select
				filtersDiv.innerHTML += `<label>Mission Types:</label>
					<select id="fissures-mission-types" class="form-select form-select-sm" multiple size="4">
						<option value="" ${(!fiss.mission_types || fiss.mission_types.length === 0) ? "selected" : ""}>Any</option>
						${FISSURE_MISSION_TYPES.map(mt => `<option value="${mt}" ${(fiss.mission_types || []).includes(mt) ? "selected" : ""}>${mt.replace("MT_", "")}</option>`).join("")}
					</select>`;

				filtersDiv.innerHTML += `<label>Tiers:</label>
					<select id="fissures-tiers" class="form-select form-select-sm" multiple size="3">
						<option value="" ${(!fiss.tiers || fiss.tiers.length === 0) ? "selected" : ""}>Any</option>
						${FISSURE_TIERS.map(t => `<option value="${t}" ${(fiss.tiers || []).includes(t) ? "selected" : ""}>${TIER_LABELS[t] || t}</option>`).join("")}
					</select>`;

				container.appendChild(section);

				// Toggle filter visibility
				document.getElementById("sub-fissures-enabled").addEventListener("change", function() {
					document.getElementById("fissures-filters").classList.toggle("d-none", !this.checked);
				});
			}

			// Arbitration (granular)
			{
				const arbySub = config.subscriptions.arbys || {};
				const section = document.createElement("div");
				section.className = "mt-3 pt-3 border-top";
				section.innerHTML = `
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" id="sub-arbys-enabled" ${arbySub.enabled ? "checked" : ""}>
						<label class="form-check-label event-label" for="sub-arbys-enabled"><strong>Arbitrations (Granular)</strong></label>
					</div>
					<div id="arbys-filters" class="filter-row ${arbySub.enabled ? "" : "d-none"}">`;

				const filtersDiv = section.querySelector("#arbys-filters");

				filtersDiv.innerHTML += `<label>Mission Types:</label>
					<select id="arbys-mission-types" class="form-select form-select-sm" multiple size="4">
						<option value="" ${(!arbySub.mission_types || arbySub.mission_types.length === 0) ? "selected" : ""}>Any</option>
						${ARBY_MISSION_TYPES.map(mt => `<option value="${mt}" ${(arbySub.mission_types || []).includes(mt) ? "selected" : ""}>${mt.replace("MT_", "")}</option>`).join("")}
					</select>`;

				container.appendChild(section);

				document.getElementById("sub-arbys-enabled").addEventListener("change", function() {
					document.getElementById("arbys-filters").classList.toggle("d-none", !this.checked);
				});
			}
		}

		function readSubscriptionsFromUI() {
			const subs = {};
			for (const sub of SUBSCRIPTIONS) {
				subs[sub.key] = document.getElementById("sub-" + sub.key).checked;
			}

			// Fissures
			const fissEnabled = document.getElementById("sub-fissures-enabled").checked;
			const fissMT = Array.from(document.getElementById("fissures-mission-types").selectedOptions)
				.map(o => o.value).filter(v => v !== "");
			const fissTiers = Array.from(document.getElementById("fissures-tiers").selectedOptions)
				.map(o => o.value).filter(v => v !== "");
			subs.fissures = { enabled: fissEnabled, mission_types: fissMT, tiers: fissTiers };

			// Arbitrations
			const arbyEnabled = document.getElementById("sub-arbys-enabled").checked;
			const arbyMT = Array.from(document.getElementById("arbys-mission-types").selectedOptions)
				.map(o => o.value).filter(v => v !== "");
			subs.arbys = { enabled: arbyEnabled, mission_types: arbyMT };

			return subs;
		}

		async function saveConfig() {
			const btn = document.getElementById("save-btn");
			btn.disabled = true;
			btn.textContent = "Saving...";

			const config = {
				apprise_server_url: document.getElementById("apprise-url").value.trim(),
				endpoints: document.getElementById("apprise-endpoints").value.trim(),
				subscriptions: readSubscriptionsFromUI(),
			};

			const res = await apiCall("api/notifications.php?action=config", {
				method: "POST",
				body: JSON.stringify({ config }),
			});

			if (res.success) {
				showStatus("Config saved!", "success");
			} else {
				showStatus(res.message, "danger");
			}

			btn.disabled = false;
			btn.textContent = "💾 Save Config";
		}

		async function testNotification() {
			const btn = document.getElementById("test-btn");
			btn.disabled = true;
			btn.textContent = "Sending...";

			const res = await apiCall("api/notifications.php?action=test", { method: "POST" });

			if (res.success) {
				showStatus("✅ Test notification sent! Check your notification endpoint.", "success");
			} else {
				showStatus("❌ " + res.message, "danger");
			}

			btn.disabled = false;
			btn.textContent = "🧪 Send Test Notification";
		}

		async function loadConfig() {
			const res = await apiCall("api/notifications.php?action=config", { method: "GET" });
			if (res.success) {
				const config = res.config;
				document.getElementById("apprise-url").value = config.apprise_server_url || "";
				document.getElementById("apprise-endpoints").value = config.endpoints || "";
				buildSubscriptionsUI(config);
			}
		}

		async function checkSession() {
			const res = await apiCall("api/auth.php?action=session", { method: "GET" });

			const authSection = document.getElementById("auth-section");
			const configSection = document.getElementById("config-section");

			if (res.logged_in) {
				authSection.classList.add("d-none");
				configSection.classList.add("visible");
				document.getElementById("logged-in-user").textContent = res.user.username;
				loadConfig();
			} else {
				authSection.classList.remove("d-none");
				configSection.classList.remove("visible");
				// Post login redirect: if coming from navbar login prompt
				const params = new URLSearchParams(location.search);
				if (params.has("login")) {
					document.getElementById("login-username").focus();
				}
			}
		}

		// Allow Enter key on login/register
		document.addEventListener("DOMContentLoaded", function() {
			document.getElementById("login-password").addEventListener("keydown", function(e) {
				if (e.key === "Enter") doLogin();
			});
			document.getElementById("reg-password2").addEventListener("keydown", function(e) {
				if (e.key === "Enter") doRegister();
			});

			checkSession();
		});
	</script>
</body>
</html>
