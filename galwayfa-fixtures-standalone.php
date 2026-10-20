<?php
/**
 * Standalone Galway FA Fixtures Fetcher
 * Fetches home fixtures from the Galway FA AJAX endpoint and outputs them.
 */

// Configuration
define('GFA_FIXTURE_API_URL', 'https://galwayfa.ie/wp-admin/admin-ajax.php');
define('GFA_API_ACTION', 'fixtures');
define('GFA_CLUB_ID', '2786');
define('GFA_CLUB_NAME_BASE', 'Salthill Devon');

/**
 * Fetches fixtures from Galway FA and filters for home games in the next 7 days.
 */
function fetch_galway_fixtures() {
    // 1. Prepare API Request Parameters
    $request_params = array(
        'action' => GFA_API_ACTION,
        'club_id' => GFA_CLUB_ID,
        'competition_id' => '',
        'team_id' => '',
        'displayResults' => 'on',
    );

    // 2. Make the API Call using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GFA_FIXTURE_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Referer: https://galwayfa.ie/',
        'Origin: https://galwayfa.ie',
        'X-Requested-With: XMLHttpRequest',
    ));

    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // 3. Error Handling for API Request
    if ($error || $http_code !== 200) {
        echo "Error: API request failed. HTTP Code: {$http_code}. Error: {$error}\n";
        return;
    }

    if (empty($body)) {
        echo "Error: Received empty response body.\n";
        return;
    }

    // 4. Parse the API Response (HTML)
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<html><body>' . $body . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $fixture_nodes = $xpath->query('//*[@data-hometeam]');

    if (!$fixture_nodes || $fixture_nodes->length === 0) {
        echo "Error: Could not find any fixtures with 'data-hometeam' attribute.\n";
        return;
    }

    // 5. Process and Filter Fixtures
    $fixtures_found = [];
    $today_start = strtotime('today'); // Start of today (00:00:00)
    $one_week_from_today = strtotime('+7 days', $today_start);
    $base_club_name_lower = strtolower(GFA_CLUB_NAME_BASE);

    foreach ($fixture_nodes as $fixture_node) {
        // Extract data from HTML attributes
        $home_team = trim($fixture_node->getAttribute('data-hometeam') ?? '');
        $away_team = trim($fixture_node->getAttribute('data-awayteam') ?? '');
        $date_str = trim($fixture_node->getAttribute('data-date') ?? '');
        $time = trim($fixture_node->getAttribute('data-time') ?? '');
        $comp = trim($fixture_node->getAttribute('data-compname') ?? 'Competition Missing');

        $fixture_timestamp = strtotime($date_str . ' ' . $time);

        // Filter 1: Only check fixtures from today through the next 7 days
        if (!$fixture_timestamp || $fixture_timestamp < $today_start || $fixture_timestamp > $one_week_from_today) {
            continue;
        }

        // Filter 2: Only Home Games (using partial match)
        if (!str_starts_with(strtolower($home_team), $base_club_name_lower)) {
            continue;
        }

        // 6. Collect Fixture Data
        $fixtures_found[] = array(
            'home_team' => $home_team,
            'away_team' => $away_team,
            'date' => date('D, M j, Y', $fixture_timestamp),
            'time' => date('H:i', $fixture_timestamp),
            'competition' => $comp,
            'timestamp' => $fixture_timestamp
        );
    }

    // 7. Output Results as HTML to file (WordPress format)
    $html_output = '';

    if (empty($fixtures_found)) {
        $html_output .= "<p style=\"text-align: center; margin: 10px 0 30px; font-size: 1.1em;\">No home fixtures scheduled for the next 7 days at NFP Park, Drom.</p>\n";
    } else {
        // Sort by timestamp
        usort($fixtures_found, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        // Get date range for header
        $first_date = date('M j', $fixtures_found[0]['timestamp']);
        $last_date = date('M j, Y', $fixtures_found[count($fixtures_found) - 1]['timestamp']);

        // Add header
        $html_output .= "<style>.team-banner--sec {display: none;}  /* Prevent sticky header behavior */\n";
        $html_output .= "  header {\n";
        $html_output .= "    position: static !important;\n";
        $html_output .= "    position: relative !important;\n";
        $html_output .= "  }\n";
        $html_output .= "  \n";
        $html_output .= "  /* Target the specific header div if needed */\n";
        $html_output .= "  div[style*=\"background-color: #004d99\"] header,\n";
        $html_output .= "  div > header {\n";
        $html_output .= "    position: static !important;\n";
        $html_output .= "    position: relative !important;\n";
        $html_output .= "    transform: none !important;\n";
        $html_output .= "    top: auto !important;\n";
        $html_output .= "    z-index: auto !important;\n";
        $html_output .= "  }</style>\n";
        $html_output .= "<div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; width: 100%; margin: 0; background-color: #ffffff; border-radius: 0; box-shadow: none;\">\n\n";
        $html_output .= "    <header style=\"background-color: #004d99; color: white; padding: 25px 30px; border-radius: 0; text-align: center;\">\n";
        $html_output .= "        <h1 style=\"margin: 0; font-size: 2.2em; letter-spacing: 1px;\">Salthill Devon Home Fixtures</h1>\n";
        $html_output .= "        <p style=\"margin: 5px 0 0; font-size: 1.1em; opacity: 0.9; color: white;\">NFP Park, Drom | This Week: {$first_date} - {$last_date} ⚽</p>\n";
        $html_output .= "    </header>\n\n";
        $html_output .= "    <main style=\"padding: 20px 30px;\">\n\n";
        $html_output .= "        <p style=\"text-align: center; margin: 10px 0 30px; font-size: 1.1em;\">\n";
        $html_output .= "            Come out and support Salthill Devon across all teams this week!\n";
        $html_output .= "        </p>\n\n";

        // Group fixtures by date
        $fixtures_by_date = [];
        foreach ($fixtures_found as $fixture) {
            $date_key = date('l, F j, Y', $fixture['timestamp']);
            if (!isset($fixtures_by_date[$date_key])) {
                $fixtures_by_date[$date_key] = [];
            }
            $fixtures_by_date[$date_key][] = $fixture;
        }

        // Output each day's fixtures
        foreach ($fixtures_by_date as $date => $day_fixtures) {
            $html_output .= "        <h2 style=\"color: #004d99; border-bottom: 2px solid #ffcc00; padding-bottom: 5px; margin-top: 30px; font-size: 1.5em;\">\n";
            $html_output .= "            {$date}\n";
            $html_output .= "        </h2>\n\n";
            $html_output .= "        <table style=\"width: 100%; border-collapse: collapse; margin-bottom: 25px;\">\n";
            $html_output .= "            <thead>\n";
            $html_output .= "                <tr style=\"background-color: #f4f4f4;\">\n";
            $html_output .= "                    <th style=\"padding: 12px; text-align: left; width: 15%; color: #333;\">Kick-off</th>\n";
            $html_output .= "                    <th style=\"padding: 12px; text-align: left; width: 45%; color: #333;\">Match</th>\n";
            $html_output .= "                    <th style=\"padding: 12px; text-align: left; width: 40%; color: #333;\">Competition</th>\n";
            $html_output .= "                </tr>\n";
            $html_output .= "            </thead>\n";
            $html_output .= "            <tbody>\n";

            $fixture_count = count($day_fixtures);
            foreach ($day_fixtures as $index => $fixture) {
                $is_last = ($index === $fixture_count - 1);
                $time_12hr = date('g:i a', $fixture['timestamp']);
                $bg_style = ($index % 2 === 1) ? ' background-color: #fafafa;' : '';
                $border_style = $is_last ? ' border-bottom: 2px solid #004d99;' : '';

                $html_output .= "                <tr>\n";
                $html_output .= "                    <td style=\"padding: 12px; border-bottom: 1px solid #eee;{$border_style} font-weight: bold; color: #cc0000;{$bg_style}\">{$time_12hr}</td>\n";
                $html_output .= "                    <td style=\"padding: 12px; border-bottom: 1px solid #eee;{$border_style}{$bg_style}\"><strong style=\"color: #42bcd9;\">{$fixture['home_team']}</strong> vs {$fixture['away_team']}</td>\n";
                $html_output .= "                    <td style=\"padding: 12px; border-bottom: 1px solid #eee;{$border_style}{$bg_style}\">{$fixture['competition']}</td>\n";
                $html_output .= "                </tr>\n\n";
            }

            $html_output .= "            </tbody>\n";
            $html_output .= "        </table>\n\n";
        }

        $html_output .= "    </main>\n\n";
        $html_output .= "    <footer style=\"background-color: #f4f4f4; padding: 15px 30px; border-radius: 0; text-align: center;\">\n";
        $html_output .= "        <p style=\"margin: 0; font-style: italic; color: #777; font-size: 0.9em;\">\n";
        $html_output .= "            ⚠️ Please note: Kick-off times and dates are subject to change. Always check the official Galway FA website.\n";
        $html_output .= "        </p>\n";
        $html_output .= "    </footer>\n";
        $html_output .= "</div>\n";
    }

    // Write to file (always, for local review)
    $output_file = __DIR__ . '/fixtures-output.html';
    file_put_contents($output_file, $html_output);
    echo "HTML output written to: {$output_file}\n";
    echo "Total fixtures found: " . count($fixtures_found) . "\n";

    // Post to WordPress if credentials are available in environment
    $wp_url      = getenv('WP_SITE_URL');
    $wp_user     = getenv('WP_USERNAME');
    $wp_password = getenv('WP_APP_PASSWORD');
    $wp_page_id  = getenv('WP_PAGE_ID');

    if ($wp_url && $wp_user && $wp_password && $wp_page_id) {
        post_to_wordpress($html_output, $wp_url, $wp_user, $wp_password, $wp_page_id);
    } else {
        echo "WordPress credentials not set — skipping WordPress update.\n";
    }
}

/**
 * Updates a WordPress page with the given HTML content via the REST API.
 */
function post_to_wordpress($html, $site_url, $username, $app_password, $page_id) {
    $endpoint = rtrim($site_url, '/') . '/wp-json/wp/v2/posts/' . intval($page_id);
    $credentials = base64_encode($username . ':' . $app_password);

    $payload = json_encode(['content' => $html]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload),
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "WordPress update failed (curl error): {$error}\n";
        exit(1);
    }

    if ($http_code === 200 || $http_code === 201) {
        echo "WordPress page updated successfully (HTTP {$http_code}).\n";
    } else {
        $body = json_decode($response, true);
        $message = $body['message'] ?? $response;
        echo "WordPress update failed (HTTP {$http_code}): {$message}\n";
        exit(1);
    }
}

// Run the script
fetch_galway_fixtures();
?>
