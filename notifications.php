<?php
require_once __DIR__ . '/notif/db.php';
$user = require_login();
$cfg  = get_user_config((int)$user['id']);
$is_admin = (bool)$user['is_admin'];
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
	<title>Notifications | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="Configure Apprise push notifications for Warframe world-state events.">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
	<style>
		.filter-tag { cursor:pointer; user-select:none; }
		.filter-tag input { cursor:pointer; }
		code.url-example { word-break:break-all; font-size:.8rem; }
		#endpoints-list .input-group { margin-bottom:.35rem; }
		.section-label { font-size:.78rem; font-weight:600; text-transform:uppercase;
		                 letter-spacing:.05em; color:var(--bs-secondary-color); margin-bottom:.4rem; }
	</style>
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container py-4" style="max-width:860px">

		<!-- Header bar with user info -->
		<div class="d-flex align-items-center mb-1 gap-3">
			<h2 class="mb-0">Notifications</h2>
			<span class="text-secondary ms-auto" style="font-size:.9rem">
				Signed in as <strong><?=htmlspecialchars($user['username'])?></strong>
				<?php if ($is_admin): ?><span class="badge bg-warning text-dark ms-1">admin</span><?php endif; ?>
			</span>
			<?php if ($is_admin): ?>
			<a href="/admin-users" class="btn btn-sm btn-outline-secondary">Manage users</a>
			<?php endif; ?>
			<a href="/logout" class="btn btn-sm btn-outline-danger">Log out</a>
		</div>
		<p class="text-secondary mb-4" style="font-size:.9rem">
			Send push notifications via <a href="https://github.com/caronc/apprise" target="_blank" rel="noopener">Apprise</a>
			whenever world-state events change. A background daemon running inside the container
			polls the world state every 60 s and dispatches notifications — no browser tab needed.
		</p>

		<!-- ── 1. Apprise server ──────────────────────────────────────────── -->
		<div class="card mb-4">
			<div class="card-header"><h5 class="mb-0">1 · Apprise Server</h5></div>
			<div class="card-body">
				<p class="text-secondary mb-2" style="font-size:.9rem">
					Run <a href="https://github.com/caronc/apprise-api" target="_blank" rel="noopener">apprise-api</a>
					alongside this container (see the docker-compose snippet in
					<code>docker-compose.yml</code>) and paste its internal URL here.
					The daemon will POST to <code>/notify</code> on that server.
				</p>
				<div class="input-group mb-2">
					<span class="input-group-text">Server URL</span>
					<input type="url" id="apprise-url" class="form-control"
						placeholder="http://apprise:8000"
						value="<?=htmlspecialchars($cfg['apprise_server'])?>">
					<button class="btn btn-outline-secondary" id="test-btn" type="button">Test</button>
				</div>
				<div id="test-result" class="d-none alert py-2 mb-0"></div>
			</div>
		</div>

		<!-- ── 2. Endpoints ──────────────────────────────────────────────── -->
		<div class="card mb-4">
			<div class="card-header"><h5 class="mb-0">2 · Notification Endpoints</h5></div>
			<div class="card-body">
				<p class="text-secondary mb-2" style="font-size:.9rem">
					Add one or more <a href="https://github.com/caronc/apprise/wiki" target="_blank" rel="noopener">Apprise URLs</a>.
					Leave empty to use whatever defaults are configured on the Apprise server.
				</p>
				<div id="endpoints-list">
				<?php foreach (($cfg['endpoints'] ?: ['']) as $ep): ?>
					<div class="input-group">
						<input type="text" class="form-control font-monospace endpoint-input"
							placeholder="tgram://BotToken/ChatID"
							value="<?=htmlspecialchars($ep)?>"
							autocomplete="off">
						<button class="btn btn-outline-danger remove-endpoint" type="button">×</button>
					</div>
				<?php endforeach; ?>
				</div>
				<button class="btn btn-sm btn-outline-secondary mt-2" id="add-endpoint-btn" type="button">+ Add endpoint</button>
				<details class="mt-3">
					<summary class="text-secondary" style="cursor:pointer;font-size:.85rem">Common Apprise URL examples</summary>
					<table class="table table-sm table-borderless mt-2" style="font-size:.82rem">
						<tbody>
							<tr><th style="white-space:nowrap">Telegram</th><td><code class="url-example">tgram://BotToken/ChatID</code></td></tr>
							<tr><th style="white-space:nowrap">Discord</th><td><code class="url-example">discord://WebhookID/WebhookToken</code></td></tr>
							<tr><th style="white-space:nowrap">Gotify</th><td><code class="url-example">gotify://hostname/AppToken</code></td></tr>
							<tr><th style="white-space:nowrap">Pushover</th><td><code class="url-example">pover://UserKey@AppToken</code></td></tr>
							<tr><th style="white-space:nowrap">Slack</th><td><code class="url-example">slack://TokenA/TokenB/TokenC/Channel</code></td></tr>
							<tr><th style="white-space:nowrap">Ntfy</th><td><code class="url-example">ntfy://topic  or  ntfy://host/topic</code></td></tr>
						</tbody>
					</table>
				</details>
			</div>
		</div>

		<!-- ── 3. Events & Filters ───────────────────────────────────────── -->
		<div class="card mb-4">
			<div class="card-header"><h5 class="mb-0">3 · Events &amp; Filters</h5></div>
			<div class="card-body">
				<p class="text-secondary mb-3" style="font-size:.9rem">
					Choose which events trigger a notification. For Fissures and Arbitrations
					you can filter by tier and/or mission type — leave a group empty to match all.
				</p>

				<?php
				$SIMPLE_EVENTS = [
					['key'=>'nightfall',   'label'=>'Plains of Eidolon Nightfall (30 s warning)'],
					['key'=>'news',        'label'=>'News'],
					['key'=>'darvo',       'label'=>"Darvo's Deal"],
					['key'=>'sortie',      'label'=>'Sortie'],
					['key'=>'litesortie',  'label'=>'Archon Hunt'],
					['key'=>'baro',        'label'=>"Baro Ki'Teer arrival"],
					['key'=>'alerts',      'label'=>'Alerts'],
					['key'=>'bounties',    'label'=>'Bounties'],
					['key'=>'teshin',      'label'=>'Vendors / Steel Path Honors'],
					['key'=>'circuit',     'label'=>'Weekly Missions reset'],
					['key'=>'labconquest', 'label'=>'Deep Archimedea'],
					['key'=>'hexconquest', 'label'=>'Temporal Archimedea'],
				];
				$FISSURE_TIERS = ['Lith','Meso','Neo','Axi','Requiem','Omnia'];
				$MISSION_TYPES = [
					'Assassination','Assault','Capture','Crossfire','Defense',
					'Disruption','Excavation','Exterminate','Hijack','Hive',
					'Infested Salvage','Interception','Mobile Defense','Orphix',
					'Pursuit','Rescue','Rush','Sabotage','Skirmish','Spy',
					'Survival','Volatile',
				];

				function checkbox(string $id, string $label, bool $checked): void {
					$c = $checked ? ' checked' : '';
					echo '<div class="form-check form-check-inline filter-tag">'
						. '<input class="form-check-input" type="checkbox" id="'.$id.'" '.$c.'>'
						. '<label class="form-check-label" for="'.$id.'">'.htmlspecialchars($label).'</label>'
						. '</div>';
				}
				function filter_checkboxes(string $prefix, array $items, array $selected): void {
					foreach ($items as $item) {
						$id = $prefix . preg_replace('/\s+/', '_', $item);
						checkbox($id, $item, in_array($item, $selected));
					}
				}
				?>

				<!-- Simple toggles -->
				<div class="section-label">World State Events</div>
				<div class="mb-3 d-flex flex-wrap gap-1">
				<?php foreach ($SIMPLE_EVENTS as $ev): ?>
					<?php checkbox('ev-'.$ev['key'], $ev['label'], !empty($cfg['events'][$ev['key']])); ?>
				<?php endforeach; ?>
				</div>

				<!-- Normal fissures -->
				<div class="section-label mt-2">Void Fissures — Normal</div>
				<div class="mb-1"><small class="text-secondary">Tier (empty = all)</small></div>
				<div class="mb-2 d-flex flex-wrap gap-1">
					<?php filter_checkboxes('ft-', $FISSURE_TIERS, $cfg['fissure_filters']['tiers']); ?>
				</div>
				<div class="mb-1"><small class="text-secondary">Mission type (empty = all)</small></div>
				<div class="mb-3 d-flex flex-wrap gap-1">
					<?php filter_checkboxes('fm-', $MISSION_TYPES, $cfg['fissure_filters']['types']); ?>
				</div>

				<!-- SP fissures -->
				<div class="section-label mt-2">Void Fissures — Steel Path</div>
				<div class="mb-1"><small class="text-secondary">Tier (empty = all)</small></div>
				<div class="mb-2 d-flex flex-wrap gap-1">
					<?php filter_checkboxes('sft-', $FISSURE_TIERS, $cfg['sp_fissure_filters']['tiers']); ?>
				</div>
				<div class="mb-1"><small class="text-secondary">Mission type (empty = all)</small></div>
				<div class="mb-3 d-flex flex-wrap gap-1">
					<?php filter_checkboxes('sfm-', $MISSION_TYPES, $cfg['sp_fissure_filters']['types']); ?>
				</div>

				<!-- Arbitration -->
				<div class="section-label mt-2">Arbitration</div>
				<div class="mb-1"><small class="text-secondary">Mission type (empty = notify on every rotation)</small></div>
				<div class="d-flex flex-wrap gap-1">
					<?php filter_checkboxes('at-', $MISSION_TYPES, $cfg['arby_filters']); ?>
				</div>

			</div>
		</div>

		<!-- Save -->
		<div class="d-flex align-items-center gap-3">
			<button class="btn btn-primary" id="save-btn" type="button">Save settings</button>
			<span id="save-status" class="text-secondary" style="font-size:.9rem"></span>
		</div>

	</div><!-- /container -->

	<?php require "components/commonjs.html"; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
	// ── Constants (mirrored from PHP) ────────────────────────────────────────
	const SIMPLE_EVENT_KEYS = <?=json_encode(array_column($SIMPLE_EVENTS,'key'))?>;
	const FISSURE_TIERS     = <?=json_encode($FISSURE_TIERS)?>;
	const MISSION_TYPES     = <?=json_encode($MISSION_TYPES)?>;

	// ── Endpoint rows ─────────────────────────────────────────────────────────
	document.getElementById("add-endpoint-btn").addEventListener("click", () => {
		const ig = document.createElement("div");
		ig.className = "input-group";
		ig.innerHTML = `<input type="text" class="form-control font-monospace endpoint-input"
			placeholder="tgram://BotToken/ChatID" autocomplete="off">
			<button class="btn btn-outline-danger remove-endpoint" type="button">×</button>`;
		document.getElementById("endpoints-list").appendChild(ig);
		ig.querySelector("input").focus();
	});

	document.getElementById("endpoints-list").addEventListener("click", e => {
		if (e.target.classList.contains("remove-endpoint")) {
			e.target.closest(".input-group").remove();
		}
	});

	// ── Collect payload ───────────────────────────────────────────────────────
	function collect() {
		const events = {};
		for (const key of SIMPLE_EVENT_KEYS) {
			const cb = document.getElementById("ev-" + key);
			if (cb && cb.checked) events[key] = true;
		}

		function checkedList(prefix, items) {
			return items.filter(i => {
				const cb = document.getElementById(prefix + i.replace(/\s+/g,"_"));
				return cb && cb.checked;
			});
		}

		return {
			apprise_server:     document.getElementById("apprise-url").value.trim(),
			endpoints:          Array.from(document.querySelectorAll(".endpoint-input"))
			                        .map(i=>i.value.trim()).filter(Boolean),
			events,
			fissure_filters:    { tiers: checkedList("ft-",  FISSURE_TIERS), types: checkedList("fm-",  MISSION_TYPES) },
			sp_fissure_filters: { tiers: checkedList("sft-", FISSURE_TIERS), types: checkedList("sfm-", MISSION_TYPES) },
			arby_filters:       checkedList("at-", MISSION_TYPES),
		};
	}

	// ── Save ──────────────────────────────────────────────────────────────────
	document.getElementById("save-btn").addEventListener("click", async () => {
		const status = document.getElementById("save-status");
		status.textContent = "Saving…";
		try {
			const res = await fetch("/notif/save", {
				method: "POST",
				headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
				body: JSON.stringify(collect()),
			});
			const data = await res.json();
			if (res.ok) {
				status.textContent = "✓ Saved";
				setTimeout(() => { status.textContent = ""; }, 2500);
			} else {
				status.textContent = "✗ " + (data.error || "Save failed");
			}
		} catch(e) {
			status.textContent = "✗ " + e.message;
		}
	});

	// ── Test ──────────────────────────────────────────────────────────────────
	document.getElementById("test-btn").addEventListener("click", async () => {
		const el = document.getElementById("test-result");
		el.className = "alert py-2 mb-0 alert-secondary";
		el.classList.remove("d-none");
		el.textContent = "Sending test notification…";

		// Save current config first so the server uses fresh values
		await fetch("/notif/save", {
			method: "POST",
			headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
			body: JSON.stringify(collect()),
		});

		try {
			const res  = await fetch("/notif/test", {
				method: "POST",
				headers: { "X-Requested-With": "XMLHttpRequest" },
			});
			const data = await res.json();
			if (data.ok) {
				el.className = "alert py-2 mb-0 alert-success";
				el.textContent = "✓ Test notification sent.";
			} else {
				el.className = "alert py-2 mb-0 alert-danger";
				el.textContent = "✗ " + (data.error || ("HTTP " + data.status));
			}
		} catch(e) {
			el.className = "alert py-2 mb-0 alert-danger";
			el.textContent = "✗ " + e.message;
		}
	});
	</script>
</body>
</html>