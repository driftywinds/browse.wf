<?php
/**
 * worldstate.php — self-hosted Warframe world state proxy ("oracle-lite")
 *
 * The upstream browse.wf "oracle" (https://oracle.browse.wf/worldState.json)
 * blocks third-party deployments, so this endpoint serves the raw world state
 * ourselves:
 *
 *   1. Serve the cached copy (default TTL 45 s, matching the site's ~60 s polling).
 *   2. On cache miss, fetch Digital Extremes' official endpoint:
 *        https://api.warframe.com/cdn/worldState.php
 *   3. If DE is unreachable, fall back to the community GitHub mirror:
 *        https://raw.githubusercontent.com/calamity-inc/warframe-worldstate-history/senpai/worldState.json
 *   4. If every upstream fails, serve the last cached copy (stale) instead of erroring.
 *
 * The payload is the raw MongoDB-extended JSON the frontend (live.ts) and the
 * notification daemon (notifyd.php) already know how to parse — no schema changes.
 *
 * Environment variables
 *   WORLDSTATE_TTL     cache lifetime in seconds (default 45)
 *   WORLDSTATE_CACHE   cache file path (default /data/worldstate-cache.json, else temp dir)
 *   WORLDSTATE_DE_URL  primary upstream URL (default the official DE endpoint)
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

$ttl     = (int)(getenv('WORLDSTATE_TTL') ?: 45);
$deUrl   = getenv('WORLDSTATE_DE_URL') ?: 'https://api.warframe.com/cdn/worldState.php';
$mirror  = 'https://raw.githubusercontent.com/calamity-inc/warframe-worldstate-history/senpai/worldState.json';

$cachePath = getenv('WORLDSTATE_CACHE');
if (!$cachePath)
{
	$cachePath = '/data/worldstate-cache.json';
	if (!is_dir('/data') || !is_writable('/data'))
	{
		$cachePath = sys_get_temp_dir() . '/browse-wf-worldstate-cache.json';
	}
}

function http_get(string $url): ?string
{
	if (function_exists('curl_init'))
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_USERAGENT      => 'browse.wf-selfhost/1.0',
			CURLOPT_ENCODING       => '', // accept gzip, auto-decompress
		]);
		$body = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return ($code === 200 && $body) ? $body : null;
	}

	// Fallback for environments without the curl extension
	$ctx = stream_context_create([
		'http' => [
			'method'  => 'GET',
			'timeout' => 20,
			'header'  => "User-Agent: browse.wf-selfhost/1.0\r\n",
			'ignore_errors' => true,
		],
	]);
	$body = @file_get_contents($url, false, $ctx);
	return ($body !== false) ? $body : null;
}

function is_worldstate(string $body): bool
{
	$data = json_decode($body, true);
	return is_array($data) && isset($data['Events']);
}

// Serve the cache while it's still fresh
$stale = null;
if (is_file($cachePath))
{
	$stale = @file_get_contents($cachePath);
	$age   = time() - (int)@filemtime($cachePath);
	if ($age < $ttl && $stale)
	{
		echo $stale;
		exit;
	}
}

// Primary upstream: Digital Extremes' official world state endpoint
$body = http_get($deUrl);
if ($body && is_worldstate($body))
{
	@file_put_contents($cachePath, $body);
	echo $body;
	exit;
}

// Fallback upstream: the community GitHub mirror (updated ~daily)
$body = http_get($mirror);
if ($body && is_worldstate($body))
{
	@file_put_contents($cachePath, $body);
	echo $body;
	exit;
}

// Everything failed — serve stale cache rather than erroring out
if ($stale)
{
	header('X-WorldState-Stale: 1');
	echo $stale;
	exit;
}

http_response_code(503);
echo json_encode(['error' => 'worldState unavailable']);
