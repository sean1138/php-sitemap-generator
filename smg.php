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
		set_time_limit(0);       // Allow script to run indefinitely for large crawls
		ob_implicit_flush(true); // Enable implicit flush for immediate output
		header('Content-Type: text/html; charset=UTF-8');
		echo "<link rel=\"stylesheet\" href=\"smg.css\"><div class=\"log\" id=\"logContainer\"><div id=\"logOutput\">";
		echo "<progress id=\"crawlProgress\" value=\"0\" max=\"100\" style=\"display:block; position: fixed; top: 2em; right: 2em;\"></progress>
		<script>
					// update progress bar
					const progressElement = document.getElementById('crawlProgress');
					let totalUrls = 1; // Start with 1 to prevent division by zero
					let crawledUrls = 0;
					// Get the div element
					let divElement = document.getElementById(\"logContainer\");

					function updateProgress() {
						crawledUrls++;
						progressElement.value = (crawledUrls / totalUrls) * 100;
						// Scroll to the bottom of the div
						divElement.scrollTop = divElement.scrollHeight;
					}

					function setTotalUrls(count) {
						totalUrls = count;
						progressElement.max = count;
					}
				</script>";
		$estimatedTotalUrls = 100; // Replace this with an actual estimation if available
		echo "<script>setTotalUrls($estimatedTotalUrls);</script>";
		flush();
		ob_flush();

		// Start URL and blacklist configuration
		$startUrl   = filter_var($_POST['url'], FILTER_VALIDATE_URL);
		$blacklist  = array_filter(array_map('trim', explode("\n", $_POST['blacklist'])));
		$outputFile = 'sitemap.xml';

		if (! $startUrl) {
			die('Invalid URL.');
		}

		$visited = [];
		$urls    = [];

		function log_message($message) {
			echo "<p>[" . date('Y-m-d H:i:s') . "] $message" . "</p>" . PHP_EOL;
			flush();    // Send output to the browser
			ob_flush(); // Ensure output is sent immediately
		}

		function crawl($url, $baseUrl, $blacklist, &$visited, &$urls) {
			// List of file extensions to exclude
			$excludedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'];

			if (isset($visited[$url]) || ! str_starts_with($url, $baseUrl)) {
				return;
			}

			// Exclude unwanted URLs
			if (strpos($url, '#') !== false || str_starts_with($url, 'mailto:') ||
				str_starts_with($url, 'tel:') || str_starts_with($url, 'javascript:')) {
				log_message("<span class=\"skip\">Skipped: <a href=\"$url\" target=\"_blank\">$url</a></span>");
				return;
			}

			// Exclude URLs with specific file extensions
			$extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
			if (in_array($extension, $excludedExtensions)) {
				log_message("<span class=\"excluded\">Excluded file type: <a href=\"$url\" target=\"_blank\">$url</a></span>");
				return;
			}

			$visited[$url] = true;
			log_message("<span class=\"crawl\">Crawling: <a href=\"$url\" target=\"_blank\">$url</a></span>");
			echo '<script>updateProgress();</script>';
			flush();
			ob_flush();

			$html = @file_get_contents($url);
			if ($html === false) {
				log_message("<span class=\"fail\">Failed to access: <a href=\"$url\" target=\"_blank\">$url</a></span>");
				return;
			}

			// Check for noindex meta tag
			if (preg_match('/<meta\s+name=["\']robots["\']\s+content=["\']noindex["\']/', $html)) {
				log_message("<span class=\"skip\">Skipped (noindex): <a href=\"$url\" target=\"_blank\">$url</a></span>");
				return;
			}

			// Check for canonical URL
			if (preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']+)["\']/', $html, $matches)) {
				$canonicalUrl = resolveUrl($matches[1], $url);
				if ($canonicalUrl !== $url) {
					log_message("<span class=\"info\">Using canonical URL: <a href=\"$canonicalUrl\" target=\"_blank\">$canonicalUrl</a> instead of <a href=\"$url\" target=\"_blank\">$url</a></span>");
					// Check if we've already visited the canonical URL
					if (isset($visited[$canonicalUrl])) {
						return; // Skip processing since it's already visited
					}
					$visited[$canonicalUrl] = true; // Mark the canonical URL as visited
					$url = $canonicalUrl; // Update URL to canonical
				}
			}

			$urls[] = $url;

			preg_match_all('/<a\s+href="([^"]+)"/i', $html, $matches);
			foreach ($matches[1] as $link) {
				$link = resolveUrl($link, $baseUrl);
				if (! inBlacklist($link, $blacklist)) {
					crawl($link, $baseUrl, $blacklist, $visited, $urls);
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

		// Begin crawling process
		log_message("Starting crawl for: $startUrl");
		crawl($startUrl, $startUrl, $blacklist, $visited, $urls);
		log_message("Crawling completed.");

		// Generate sitemap
		$sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
		$sitemap .= '<?xml-stylesheet type="text/css" href="sitemap.css"?>' . PHP_EOL;
		$sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

		// configurable change frequnecy
		foreach ($urls as $url) {
			$changefreq = 'weekly'; // Default
			$priority   = '0.5';    // Default

			// Customize based on URL patterns
			if ($url === $startUrl) { // Main page
				$changefreq = 'daily';
				$priority   = '1.0';
			} elseif (strpos($url, '/blog') !== false) { // Blog pages
				$changefreq = 'daily';
				$priority   = '0.8';
			} elseif (strpos($url, '/product') !== false) { // Product pages
				$changefreq = 'weekly';
				$priority   = '0.7';
			} elseif (strpos($url, '/about') !== false || strpos($url, '/contact') !== false) { // Static pages
				$changefreq = 'monthly';
				$priority   = '0.3';
			}

			$sitemap .= "  <url>" . PHP_EOL;
			$sitemap .= "    <loc>" . htmlspecialchars($url) . "</loc>" . PHP_EOL;

			$parsedUrl = parse_url($url);
			$path      = isset($parsedUrl['path']) && is_string($parsedUrl['path']) ? $parsedUrl['path'] : '/'; // Ensure $path is always a string
			$filePath  = $_SERVER['DOCUMENT_ROOT'] . $path;

			// Ensure $filePath exists and is a valid file
			if (is_string($filePath) && file_exists($filePath)) {
				$lastModified = date('Y-m-d', filemtime($filePath));
			} else {
				$lastModified = date('Y-m-d'); // Fallback to current date
			}

			$sitemap .= "    <lastmod>$lastModified</lastmod>" . PHP_EOL;
			$sitemap .= "    <changefreq>$changefreq</changefreq>" . PHP_EOL;
			$sitemap .= "    <priority>$priority</priority>" . PHP_EOL;
			$sitemap .= "  </url>" . PHP_EOL;
		}

		$sitemap .= '</urlset>' . PHP_EOL;
		file_put_contents($outputFile, $sitemap);

		log_message("Sitemap generated: $outputFile");
		echo '</div></div>';
		echo "<div class=\"msg\">";
		echo "<p><strong>" . count($urls) . " URLs added to sitemap.</strong></p>";
		echo '<p>Sitemap saved as <a href="' . htmlspecialchars($outputFile) . '">' . htmlspecialchars($outputFile) . '</a></p></div>';
		echo "<script>
					progressElement.value = 100; // Ensure it reaches max
					// Scroll to the bottom of the div
					divElement.scrollTop = divElement.scrollHeight;
				</script>";
		exit;
	}

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sitemap Generator</title>
	<link rel="stylesheet" href="smg.css">
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
		<p style="margin:0;text-align:center;">VSXD 2024.11.19</p>
	</footer>
</body>
</html>
