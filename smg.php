<?php
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	echo 'PHP Version: ' . phpversion();

	// polyfill
	if (! function_exists('str_starts_with')) {
		function str_starts_with($haystack, $needle) {
			return substr($haystack, 0, strlen($needle)) === $needle;
		}
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		set_time_limit(0); // Allow script to run indefinitely for larger crawls
		$startUrl   = filter_var($_POST['url'], FILTER_VALIDATE_URL);
		$blacklist  = array_filter(array_map('trim', explode("\n", $_POST['blacklist'])));
		$outputFile = 'sitemap.xml';

		if (! $startUrl) {
			die('Invalid URL.');
		}

		$visited = [];
		$urls    = [];
		$log     = [];

		function crawl($url, $baseUrl, $blacklist, &$visited, &$urls, &$log) {
			// Skip already visited URLs or those not starting with the base URL
			if (isset($visited[$url]) || ! str_starts_with($url, $baseUrl)) {
				return;
			}

			// Exclude URLs with fragments (#anchors)
			if (strpos($url, '#') !== false) {
				$log[] = "[" . date('Y-m-d H:i:s') . "] Skipped (anchor): $url";
				return;
			}
			// Exclude email links
			if (str_starts_with($url, 'mailto:')) {
				$log[] = "[" . date('Y-m-d H:i:s') . "] Skipped (mailto): $url";
				return;
			}
			// Exclude telephone links
			if (str_starts_with($url, 'tel:')) {
				$log[] = "[" . date('Y-m-d H:i:s') . "] Skipped (tel): $url";
				return;
			}
			// Exclude javascript:() links
			if (str_starts_with($url, 'javascript:')) {
				$log[] = "[" . date('Y-m-d H:i:s') . "] Skipped (javascript): $url";
				return;
			}
			// Exclude external links
			if (! str_starts_with($url, $baseUrl)) {
				$log[] = "[" . date('Y-m-d H:i:s') . "] Skipped (external): $url";
				return;
			}
			// Exclude non-html links
			$excludedExtensions = ['jpg', 'png', 'gif', 'pdf', 'docx', 'zip'];
			if (preg_match('/\.' . implode('|', $excludedExtensions) . '$/i', $url)) {
				$log[] = "[" . date('Y-m-d H:i:s') . "] Skipped (file type): $url";
				return;
			}

			$visited[$url] = true;

			global $log;
			$log[] = "[" . date('Y-m-d H:i:s') . "] Crawling: $url";

			$html = @file_get_contents($url);
			if ($html === false) {
				$log[] = "[" . date('Y-m-d H:i:s') . "] Failed to access: $url";
				return;
			}

			$urls[] = $url;

			preg_match_all('/<a\s+href="([^"]+)"/i', $html, $matches);
			foreach ($matches[1] as $link) {
				$link = resolveUrl($link, $baseUrl);
				if (! inBlacklist($link, $blacklist)) {
					crawl($link, $baseUrl, $blacklist, $visited, $urls, $log);
				}
			}
		}

		function resolveUrl($url, $baseUrl) {
			return parse_url($url, PHP_URL_SCHEME) ? $url : rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
		}

		function inBlacklist($url, $blacklist) {
			foreach ($blacklist as $rule) {
				if (stripos($url, $rule) !== false) {
					return true;
				}
			}
			return false;
		}

		crawl($startUrl, $startUrl, $blacklist, $visited, $urls, $log);

		// Generate sitemap
		$sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
		$sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

		foreach ($urls as $url) {
			$sitemap .= "  <url>" . PHP_EOL;
			$sitemap .= "    <loc>" . htmlspecialchars($url) . "</loc>" . PHP_EOL;

			// Determine last modified date dynamically
			$parsedUrl = parse_url($url);
			$path      = isset($parsedUrl['path']) ? $parsedUrl['path'] : '/';
			$filePath  = $_SERVER['DOCUMENT_ROOT'] . $path;

			// Check if file exists and get last modification time
			if (file_exists($filePath)) {
				$lastModified = date('Y-m-d', filemtime($filePath));
			} else {
				$lastModified = date('Y-m-d'); // Default to today's date
			}
			$sitemap .= "    <lastmod>$lastModified</lastmod>" . PHP_EOL;

			                        // Add change frequency
			$changeFreq = 'weekly'; // Default
			if ($url === $startUrl) {
				$changeFreq = 'daily'; // Homepage or important pages
			}
			$sitemap .= "    <changefreq>$changeFreq</changefreq>" . PHP_EOL;

			                   // Add priority
			$priority = '0.5'; // Default priority
			if ($url === $startUrl) {
				$priority = '1.0'; // Homepage
			}
			$sitemap .= "    <priority>$priority</priority>" . PHP_EOL;

			$sitemap .= "  </url>" . PHP_EOL;
		}
		$sitemap .= '</urlset>' . PHP_EOL;

		file_put_contents($outputFile, $sitemap);

		// log things
		echo "<pre>" . implode("\n", $log) . "</pre>";
		echo '<p><strong>' . count($urls) . ' URLs added to sitemap.</strong></p>';
		echo '<p>Sitemap saved as <a href="' . htmlspecialchars($outputFile) . '">' . htmlspecialchars($outputFile) . '</a></p>';
		exit;
	}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sitemap Generator</title>
	<style>
		html{
			scroll-behavior: smooth;
/*			scroll-padding-top: 160px;*/
			font-size: min(4vmin, calc(100% + .125vmax));
			/* the base padding size var(--p-unit) is for top/bottom on sections at desktop res, use half of that for left/right, lower than desktop res not accounted for in this file */
			--p-unit: 3rem;
			--p-unit-tb: var(--p-unit);
			--p-unit-lr: calc(var(--p-unit) / 2);
			/* see type-scale.com */
			--type-scale: 1.200;
			/* do some letter-spacing scaling similar to your type scale value */
			--ls-scale: .0120em;
			--vs-lh: 1.5;
			--vs-gap: calc(var(--p-unit) / 2);
		}
		body{
			margin:0;
			/* remove empty space below footer on short content pages 1/2 */
			display: flex;
			flex-direction: column;
			min-height: 100vh;
		}
		footer {
			/* remove empty space below footer on short content pages 2/2 */
			margin-top: auto;
		}
		header,main,footer{
/*			border:1px solid red;*/
			display: flex;
			flex-direction: column;
			flex-wrap: wrap;
/*			width: calc(100% - (var(--p-unit-lr) *2));*/
			gap: var(--vs-gap);
			padding: 0 var(--p-unit-lr);
		}
		main{
			padding: var(--p-unit-tb) var(--p-unit-lr);
		}
		header *,main *,footer *{
/*			border:1px solid green;*/
		}
		form{
			display: flex ;
			flex-direction: column;
			gap: var(--vs-gap);
		}
		input, textarea{
			display:block;
			width: 100%;
			padding: 6px 12px;
			font-size: 16px;
			font-weight: 400;
			line-height: 1.5;
			color: #212529;
			background-color: #fff;
			background-clip: padding-box;
			border: 1px solid #ced4da;
			appearance: none;
			border-radius: 4px;
			transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
			&:focus{
			    color: #212529;
			    background-color: #fff;
			    border-color: #86b7fe;
			    outline: 0;
			    box-shadow: 0 0 0 0.25rem rgb(13 110 253 / 25%);
			}
		}
		button{
			cursor: pointer;
			outline: 0;
			display: inline-block;
			font-weight: 400;
			line-height: 1.5;
			text-align: center;
			background-color: transparent;
			border: 1px solid transparent;
			padding: 6px 12px;
			font-size: 1rem;
			border-radius: .25rem;
			transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
			color: #0d6efd;
			border-color: #0d6efd;
			&:hover {
			    color: #fff;
			    background-color: #0d6efd;
			    border-color: #0d6efd;
			}
		}
	</style>
</head>
<body>
	<header>
		<h1>Sitemap Generator</h1>
	</header>
	<main>
		<form method="post">
			<label>
				Website URL:
				<input type="url" name="url" required>
			</label>
			<label>
				Blacklist (one rule per line):
				<textarea name="blacklist" rows="5" placeholder="Example: &#10;/admin&#10;/example-folder"></textarea>
			</label>
			<button type="submit">Generate Sitemap</button>
		</form>
	</main>
	<footer>
		<p style="margin:0;text-align:center;">&copy; 2024.11.19</script>
		</p>
	</footer>
</body>
</html>
