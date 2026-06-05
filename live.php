<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
	<title>Live World State | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="This tool shows you everything that's going on in Warframe right now in a hopefully useful way.">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
	<style>
		abbr { text-decoration: underline dotted; text-decoration-skip-ink: none; }
		[data-bs-toggle=tooltip] { cursor: help; }
		[data-notif-toggle], [data-notif-toggle] > span, .completion-check { text-decoration:none;cursor:pointer;color:inherit }
		.completion-check { display: inline-block; width: 15px }
		.card-block:not(:last-child) { margin-bottom: .5rem; }
		[data-collapse-toggle], [data-collapse-toggle] > span { cursor:pointer }
		.card:has([data-collapse-toggle].engaged) {
			& .card-header { border-bottom: none }
			& > :not(.card-header) { display: none !important }
		}
	</style>
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container-fluid pt-3">
		<script>window.LIVE_VERSION=0;</script>
		<div id="update-prompt" class="alert alert-warning d-none" role="alert">We've made some changes to reduce server load. Please refresh the page.</div>
		<div class="row g-3 mb-xl-3">
			<div class="col-xl-4">
				<div class="row g-3">
					<div class="col-xl-12 col-md-6">
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="environments"></span> Environments</h5>
								<a class="m-auto me-0" data-notif-toggle="nightfall"></a>
							</div>
							<div class="card-body overflow-auto">
								<table class="table table-hover table-sm table-borderless mb-0">
									<tr>
										<th id="poe-name" class="w-50">Plains of Eidolon / Earth</th>
										<td id="poe" class="w-50">Fetching data...</td>
									</tr>
									<tr>
										<th id="vallis-name" class="w-50">Orb Vallis</th>
										<td id="vallis" class="w-50"></td>
									</tr>
									<tr>
										<th id="deimos-name" class="w-50">Cambion Drift</th>
										<td id="deimos" class="w-50">Fetching data...</td>
									</tr>
									<tr>
										<th id="duviri-name" class="w-50">Duviri</th>
										<td id="duviri" class="w-50">Fetching data...</td>
									</tr>
									<tr>
										<th id="zariman-name" class="w-50">Zariman</th>
										<td id="zariman" class="w-50">Fetching data...</td>
									</tr>
								</table>
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="news"></span> News</h5>
								<a class="m-auto me-0" data-notif-toggle="news"></a>
							</div>
							<div class="card-body overflow-auto" id="news-body" style="height:167px">
								Loading...
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="darvo"></span> <span id="darvo-header">Darvo's Deal</span></h5>
								<a class="m-auto me-0" data-notif-toggle="darvo"></a>
							</div>
							<div class="d-flex">
								<img style="height:64px;width:64px;margin:10px" id="darvo-icon" />
								<div class="card-body ps-1">
									<p class="mb-1"><b id="darvo-item">Loading...</b> &middot; <span id="darvo-stock">0/0</span> In Stock</p>
									<p class="card-text"><del id="darvo-ogprice">0</del> <b id="darvo-price">0</b> Platinum (-<span id="darvo-discount">0</span>%)</p>
								</div>
							</div>
						</div>
						<div class="card mb-3">
							<h5 class="card-header"><span data-collapse-toggle="arby"></span> <span id="arby-header">Arbitration</span></h5>
							<div class="card-body">
								<p class="card-text"><b id="arby-what">Loading...</b> <span id="arby-where"></span> (<span id="arby-tier">F</span> Tier)</p>
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="alerts"></span> Alerts</h5>
								<a class="m-auto me-0" data-notif-toggle="alerts"></a>
							</div>
							<div class="card-body" id="alerts-body">Loading...</div>
						</div>
						<div class="card">
							<h5 class="card-header"><span data-collapse-toggle="goals"></span> Events</h5>
							<div class="card-body" id="goals-body">Loading...</div>
						</div>
					</div>
					<div class="col-xl-12 col-md-6">
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="sortie"></span> <span id="sortie-header">Sortie</span></h5>
								<a class="m-auto me-0" data-notif-toggle="sortie"></a>
							</div>
							<div class="card-body">
								<table class="table table-sm table-borderless table-hover mb-0" id="sortie-table">
									<tr><th>Fetching data...</th></tr>
									<tr><td>&nbsp;</td></tr>
									<tr><td>&nbsp;</td></tr>
								</table>
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="litesortie"></span> <span id="litesortie-header">Archon Hunt</h5>
								<a class="m-auto me-0" data-notif-toggle="litesortie"></a>
							</div>
							<div class="card-body" id="litesortie-body">Fetching data...</div>
						</div>
						<div class="card mb-3">
							<h5 class="card-header"><span data-collapse-toggle="incursions"></span> <span id="incursions-header">Steel Path Incursions</h5>
							<div class="card-body overflow-auto" id="incursions-body">
								<span class="d-block mb-1"><b>Fetching data...</b></span>
								<span class="d-block mb-1">&nbsp;</span>
								<span class="d-block mb-1">&nbsp;</span>
								<span class="d-block mb-1">&nbsp;</span>
								<span class="d-block mb-1">&nbsp;</span>
								<span class="d-block">&nbsp;</span>
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="vendors"></span> <span id="vendors-header">Vendors</h5>
								<a class="m-auto me-0" data-notif-toggle="teshin"></a>
							</div>
							<div class="card-body">
								<p class="mb-1">Steel Path Honors: <b id="teshin-offer"></b> <span id="teshin-check"></span></p>
								<p class="mb-0">Iron Wake <span id="ironwake-check"></span></p>
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="weekly-missions"></span> <span id="circuit-header">Weekly Missions</h5>
								<a class="m-auto me-0" data-notif-toggle="circuit"></a>
							</div>
							<div class="card-body">
								<p class="mb-1">Help Clem <span id="clem-check"></span></p>
								<p class="mb-1">Ayatan Treasure Hunt <span id="maroo-check"></span></p>
								<p class="mb-1">The Circuit (Normal): <b id="circuit-frames">Loading...</b> <span id="circuit-frames-check"></span></p>
								<p class="mb-1">The Circuit (Steel Path): <b id="circuit-weapons"></b> <span id="circuit-weapons-check"></span></p>
								<p class="mb-1">Netracells <span id="netracell-checks"></span></p>
								<p class="mb-1">Break Narmer <span id="kahl-checks"></span></p>
								<p class="mb-0">Descendia <span id="descent-checks"></span></p>
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="baro"></span> <span id="baro-header">Baro Ki'Teer</h5>
								<a class="m-auto me-0" data-notif-toggle="baro"></a>
							</div>
							<div class="card-body">
								<p id="baro-soon" class="mb-0">Baro's next visit will be at <b class="baro-where">Loading...</b>.</p>
								<div id="baro-now" class="d-none">
									<p>Baro is currently at <b class="baro-where"></b>.</p>
									<table class="table table-sm table-hover table-borderless mb-0" id="baro-table"></table>
								</div>
							</div>
						</div>
						<div class="card">
							<h5 class="card-header"><span data-collapse-toggle="pgr"></span> KinePage</h5>
							<div class="card-body" id="pgr">No new messages. Scanning...</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-xl-8">
				<div class="card mb-3">
					<div class="card-header d-flex">
						<h5 class="mb-0"><span data-collapse-toggle="bounties"></span> <span id="bounties-header">Bounties</h5>
						<a class="m-auto me-0" data-notif-toggle="bounties"></a>
					</div>
					<div class="card-body overflow-auto" id="bounties-body">
						<p>Rotation <b id="bounty-rot">?</b> (<span id="bounty-rot-rewards">Loading</span>) &middot; Vault Rotation <b id="vault-rot">?</b> (<span id="vault-rot-rewards">Loading</span>)</p>
						<h5 id="ZarimanSyndicate-name">The Holdfasts</h5>
						<table class="table table-hover table-sm table-borderless" id="ZarimanSyndicate-table">
							<tr>
								<th class="mission">Fetching data...</th>
								<td class="challenge"></td>
								<td>50-55</td>
								<td>1/2&nbsp;<abbr class="vq-abbr">VQ</abbr></td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>60-65</td>
								<td>2/3&nbsp;<abbr class="vq-abbr">VQ</abbr></td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>70-75</td>
								<td>3/5&nbsp;<abbr class="vq-abbr">VQ</abbr></td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>90-95</td>
								<td>4/6&nbsp;<abbr class="vq-abbr">VQ</abbr></td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>110-115</td>
								<td>5/8&nbsp;<abbr class="vq-abbr">VQ</abbr></td>
							</tr>
						</table>
						<h5 id="EntratiLabSyndicate-name">Cavia</h5>
						<table class="table table-hover table-sm table-borderless" id="EntratiLabSyndicate-table">
							<tr>
								<th class="mission">Fetching data...</th>
								<td class="challenge"></td>
								<td>55-60</td>
								<td>1000/<wbr/>1500</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>65-70</td>
								<td>2000/<wbr/>3000</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>75-80</td>
								<td>3000/<wbr/>4500</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>95-100</td>
								<td>4000/<wbr/>6000</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td>115-120</td>
								<td>5000/<wbr/>7500</td>
							</tr>
						</table>
						<h5 id="HexSyndicate-name">The Hex</h5>
						<table class="table table-hover table-sm table-borderless mb-0" id="HexSyndicate-table">
							<tr>
								<th class="mission">Fetching data...</th>
								<td class="challenge"></td>
								<td class="ally"></td>
								<td>65-70</td>
								<td>1000/<wbr/>1500</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td class="ally"></td>
								<td>75-80</td>
								<td>2000/<wbr/>3000</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td class="ally"></td>
								<td>85-90</td>
								<td>3000/<wbr/>4500</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td class="ally"></td>
								<td>95-100</td>
								<td>4000/<wbr/>6000</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td class="ally"></td>
								<td>105-110</td>
								<td>5000/<wbr/>7500</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge"></td>
								<td class="ally"></td>
								<td>115-120</td>
								<td>6000/<wbr/>9000</td>
							</tr>
							<tr>
								<th class="mission"></th>
								<td class="challenge" colspan="2"></td>
								<td>125-130</td>
								<td>7500/<wbr/>11250</td>
							</tr>
						</table>
					</div>
				</div>
				<div class="row g-3">
					<div class="col-md-6">
						<div class="card mb-3">
							<h5 class="card-header"><span data-collapse-toggle="fissures"></span> Void Fissures (Normal)</h5>
							<div class="card-body overflow-auto">
								<table class="table table-sm table-hover table-borderless mb-0" id="fissures-table"><tr><th>Loading...</th></tr></table>
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<div class="card mb-3">
							<h5 class="card-header"><span data-collapse-toggle="sp-fissures"></span> Void Fissures (Steel Path)</h5>
							<div class="card-body overflow-auto">
								<table class="table table-sm table-hover table-borderless mb-0" id="sp-fissures-table"><tr><th>Loading...</th></tr></table>
							</div>
						</div>
					</div>
				</div>
				<div class="row g-3 mb-3">
					<div class="col-xxl-8">
						<div class="card mb-3">
							<h5 class="card-header"><span data-collapse-toggle="invasions"></span> <span id="invasions-header">Invasions</h5>
							<div class="card-body overflow-auto">
								<table class="table table-sm table-hover table-borderless mb-0" id="invasions-table"><tr><td>Loading...</td></tr></table>
							</div>
						</div>
						<div class="card mb-3">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="labconquest"></span> <span id="labConquest-header">Deep Archimedea</h5>
								<a class="m-auto me-0" data-notif-toggle="labconquest"></a>
							</div>
							<div class="card-body overflow-auto">
								<table class="table table-sm table-borderless table-hover mb-2" id="labConquest-missions">
									<tr><th>Fetching data...</th></tr>
									<tr><td>&nbsp;</td></tr>
									<tr><td>&nbsp;</td></tr>
								</table>
								<table class="table table-sm table-borderless mb-0">
									<tr id="labConquest-fv"><td>&nbsp;</td></tr>
								</table>
							</div>
						</div>
						<div class="card">
							<div class="card-header d-flex">
								<h5 class="mb-0"><span data-collapse-toggle="hexconquest"></span> <span id="hexConquest-header">Temporal Archimedea</h5>
								<a class="m-auto me-0" data-notif-toggle="hexconquest"></a>
							</div>
							<div class="card-body overflow-auto">
								<table class="table table-sm table-borderless table-hover mb-2" id="hexConquest-missions">
									<tr><th>Fetching data...</th></tr>
									<tr><td>&nbsp;</td></tr>
									<tr><td>&nbsp;</td></tr>
								</table>
								<table class="table table-sm table-borderless mb-0">
									<tr id="hexConquest-fv"><td>&nbsp;</td></tr>
								</table>
							</div>
						</div>
					</div>
					<div class="col-xxl-4">
						<div class="card mb-3">
							<h5 class="card-header"><span data-collapse-toggle="rj-fissures"></span> Void Storms (Railjack)</h5>
							<div class="card-body overflow-auto">
								<table class="table table-sm table-hover table-borderless mb-0" id="rj-fissures-table"><tr><th>Loading...</th></tr></table>
							</div>
						</div>
					</div>
				</div>
				
			</div>
		</div>
		<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
	</div>
	<?php require "components/commonjs.html"; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script src="typestripped/live.js?lv0"></script>
</body>
</html>
