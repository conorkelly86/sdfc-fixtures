<?php
/**
 * Standalone Fixtures Fetcher
 * Fetches home fixtures from the Galway FA AJAX endpoint and the FAI Connect report API,
 * merges them, and outputs them.
 */

// Kick-off times from both sources are Irish local time.
date_default_timezone_set('Europe/Dublin');

// Configuration
define('GFA_FIXTURE_API_URL', 'https://galwayfa.ie/wp-admin/admin-ajax.php');
define('GFA_API_ACTION', 'fixtures');
define('GFA_CLUB_ID', '2786');
define('GFA_CLUB_NAME_BASE', 'Salthill Devon');

// FAI Connect saved "Matches" report (COMET Data). The API key comes from the FAICONNECT_API_KEY env var.
// 1000 is the maximum page size the API allows.
define('FAICONNECT_API_URL', 'https://faiconnect.ie/data-backend/api/public/areports/run/0/1000/');

/**
 * Fetches fixtures from Galway FA and filters for games (home or away) inside the window.
 * $is_home: true for home games (team is home_team), false for away games (team is away_team).
 */
function fetch_galway_fixtures($today_start, $window_end, $is_home = true) {
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
        echo "Error: Galway FA API request failed. HTTP Code: {$http_code}. Error: {$error}\n";
        return array();
    }

    if (empty($body)) {
        echo "Error: Galway FA returned an empty response body.\n";
        return array();
    }

    // 4. Parse the API Response (HTML)
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<html><body>' . $body . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $fixture_nodes = $xpath->query('//*[@data-hometeam]');

    if (!$fixture_nodes || $fixture_nodes->length === 0) {
        echo "Error: Could not find any Galway FA fixtures with 'data-hometeam' attribute.\n";
        return array();
    }

    // 5. Process and Filter Fixtures
    $fixtures_found = [];
    $base_club_name_lower = strtolower(GFA_CLUB_NAME_BASE);

    foreach ($fixture_nodes as $fixture_node) {
        // Extract data from HTML attributes
        $home_team = trim($fixture_node->getAttribute('data-hometeam') ?? '');
        $away_team = trim($fixture_node->getAttribute('data-awayteam') ?? '');
        $date_str = trim($fixture_node->getAttribute('data-date') ?? '');
        $time = trim($fixture_node->getAttribute('data-time') ?? '');
        $comp = trim($fixture_node->getAttribute('data-compname') ?? 'Competition Missing');

        $fixture_timestamp = strtotime($date_str . ' ' . $time);

        // Filter 1: Only check fixtures from today through the end of the window
        if (!$fixture_timestamp || $fixture_timestamp < $today_start || $fixture_timestamp > $window_end) {
            continue;
        }

        // Filter 2: Only games where the club is in the position requested
        if ($is_home) {
            if (!str_starts_with(strtolower($home_team), $base_club_name_lower)) {
                continue;
            }
        } else {
            if (!str_starts_with(strtolower($away_team), $base_club_name_lower)) {
                continue;
            }
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

    return $fixtures_found;
}

/**
 * Fetches fixtures from the FAI Connect "Matches" report and filters for games (home or away) inside the window.
 * $is_home: true for home games, false for away games.
 * Skipped (with a message) when FAICONNECT_API_KEY is not set, so the Galway FA fixtures still publish.
 */
function fetch_faiconnect_fixtures($today_start, $window_end, $is_home = true) {
    $api_key = getenv('FAICONNECT_API_KEY');
    if (!$api_key) {
        echo "FAICONNECT_API_KEY not set, skipping FAI Connect.\n";
        return array();
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FAICONNECT_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'sdfc-fixtures-bot/1.0 (+https://github.com/conorkelly86/sdfc-fixtures)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('API_KEY: ' . $api_key, 'Accept: application/json'));

    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = json_decode((string) $body, true);
    if ($error || $http_code !== 200 || !is_array($data) || !isset($data['results'])) {
        echo "Error: FAI Connect API request failed. HTTP Code: {$http_code}. Error: {$error}\n";
        return array();
    }

    if (!empty($data['lastPage'])) {
        echo "Warning: FAI Connect report has more than one page; only the first 1000 rows were read.\n";
    }

    $fixtures_found = array();
    foreach ($data['results'] as $row) {
        $fixture = parse_faiconnect_fixture($row, $is_home);
        if ($fixture && $fixture['timestamp'] >= $today_start && $fixture['timestamp'] <= $window_end) {
            $fixtures_found[] = $fixture;
        }
    }

    return $fixtures_found;
}

/**
 * Converts one FAI Connect report row into a fixture, or null if it doesn't match the requested role.
 * Team names are only available inside "Home - Away score", and some contain " - " themselves
 * (e.g. "Tuam Celtic - Reserves"), so the home name is everything before the first " - ",
 * which Salthill Devon team names never contain.
 * $is_home: true to match if Salthill is home team, false if away team.
 */
function parse_faiconnect_fixture($row, $is_home = true) {
    // ENTERED matches have no date yet; postponed/played ones are not upcoming fixtures.
    if (($row['matchStatus'] ?? '') !== 'SCHEDULED' || empty($row['matchDate'])) {
        return null;
    }

    $description = trim($row['matchDescription'] ?? '');
    $teams = explode(' - ', preg_replace('/\s+(\d+:\d+|-:-)\s*$/', '', $description), 2);
    if (count($teams) !== 2) {
        return null;
    }

    $home = trim($teams[0]);
    $away = trim($teams[1]);
    $base_lower = strtolower(GFA_CLUB_NAME_BASE);

    // Check if Salthill is in the requested role
    if ($is_home) {
        if (!str_starts_with(strtolower($home), $base_lower)) {
            return null;
        }
    } else {
        if (!str_starts_with(strtolower($away), $base_lower)) {
            return null;
        }
    }

    // matchDate is epoch milliseconds (UTC); the script timezone renders it as Irish local time.
    $timestamp = intdiv((int) $row['matchDate'], 1000);

    return array(
        'home_team' => $home,
        'away_team' => $away,
        'date' => date('D, M j, Y', $timestamp),
        'time' => date('H:i', $timestamp),
        'competition' => trim($row['name'] ?? 'Competition Missing'),
        'timestamp' => $timestamp,
    );
}

/**
 * Returns the page stylesheet as a single line.
 * WordPress turns blank lines in post content into stray </p><p> tags, even inside <style>, which
 * breaks the CSS. Keep the page HTML free of blank lines and keep these rules on one line.
 *
 * The theme styles h1/h2/p/header globally and puts post content in a flex column that grows to fit
 * its widest child, so every rule is scoped under .sdfc-fx and the phone layout drops the table.
 */
function fixtures_css() {
    $styles = array(
        '.team-banner--sec{display:none}',
        'header{position:relative!important}',
        'div>header{position:relative!important;transform:none!important;top:auto!important;z-index:auto!important}',
        '.sdfc-fx{font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;width:100%;max-width:100%;margin:0;background:#fff}',
        '.sdfc-fx,.sdfc-fx *{box-sizing:border-box}',
        '.sdfc-fx header.sdfc-fx-head{background:#004d99;color:#fff;padding:25px 30px;text-align:center}',
        '.sdfc-fx .sdfc-fx-title{margin:0;color:#fff;font-size:2.2em;letter-spacing:1px;overflow-wrap:anywhere}',
        '.sdfc-fx .sdfc-fx-sub{margin:5px 0 0;color:#fff;opacity:.9;font-size:1.1em}',
        '.sdfc-fx .sdfc-fx-main{padding:20px 30px}',
        '.sdfc-fx-tabs{display:flex;border-bottom:2px solid #eee;margin:0 0 20px 0;gap:0}',
        '.sdfc-fx-tab-label{padding:12px 20px;cursor:pointer;color:#666;font-weight:500;border:none;background:none;border-bottom:3px solid transparent;transition:color 0.2s,border-color 0.2s}',
        '.sdfc-fx-tab-label.active{color:#004d99;border-bottom-color:#004d99}',
        '.sdfc-fx-tab-content{display:none}',
        '.sdfc-fx-tab-content.active{display:block}',
        '.sdfc-fx .sdfc-fx-day{margin:30px 0 20px;padding-bottom:5px;border-bottom:2px solid #ffcc00;color:#004d99;font-size:1.5em}',
        '.sdfc-fx .sdfc-fx-table{width:100%;border-collapse:collapse;margin-bottom:25px}',
        '.sdfc-fx .sdfc-fx-table th{background:#f4f4f4;color:#333}',
        '.sdfc-fx .sdfc-fx-table th,.sdfc-fx .sdfc-fx-table td{padding:12px;text-align:left;overflow-wrap:anywhere}',
        '.sdfc-fx .sdfc-fx-table td{border-bottom:1px solid #eee}',
        '.sdfc-fx .sdfc-fx-table tbody tr:nth-child(even) td{background:#fafafa}',
        '.sdfc-fx .sdfc-fx-table tbody tr:last-child td{border-bottom:2px solid #004d99}',
        '.sdfc-fx .sdfc-fx-table th.sdfc-fx-time{width:15%}',
        '.sdfc-fx .sdfc-fx-table th.sdfc-fx-match{width:45%}',
        '.sdfc-fx .sdfc-fx-table th.sdfc-fx-comp{width:40%}',
        '.sdfc-fx .sdfc-fx-table td.sdfc-fx-time{font-weight:bold;color:#cc0000}',
        '.sdfc-fx .sdfc-fx-match strong{color:#42bcd9}',
        '.sdfc-fx footer.sdfc-fx-foot{background:#f4f4f4;padding:15px 30px;text-align:center}',
        '.sdfc-fx .sdfc-fx-note{margin:0;color:#777;font-style:italic;font-size:.9em}',
        '@media (max-width:600px){.sdfc-fx header.sdfc-fx-head{padding:18px 14px}.sdfc-fx .sdfc-fx-title{font-size:1.45em;line-height:1.2;letter-spacing:.5px}.sdfc-fx .sdfc-fx-sub{font-size:.95em}.sdfc-fx .sdfc-fx-main{padding:14px 12px}.sdfc-fx-tabs{margin:0 -12px 20px -12px;padding:0 12px}.sdfc-fx-tab-label{padding:10px 16px;font-size:.95em}.sdfc-fx .sdfc-fx-day{margin:24px 0 12px;font-size:1.2em}.sdfc-fx .sdfc-fx-table,.sdfc-fx .sdfc-fx-table tbody,.sdfc-fx .sdfc-fx-table tr,.sdfc-fx .sdfc-fx-table td{display:block;width:100%}.sdfc-fx .sdfc-fx-table thead{display:none}.sdfc-fx .sdfc-fx-table tr{padding:10px 12px;border-bottom:1px solid #eee}.sdfc-fx .sdfc-fx-table tbody tr:nth-child(even){background:#fafafa}.sdfc-fx .sdfc-fx-table tbody tr:last-child{border-bottom:2px solid #004d99}.sdfc-fx .sdfc-fx-table tbody tr:nth-child(n) td{padding:2px 0;border:0;background:none}.sdfc-fx .sdfc-fx-table td.sdfc-fx-time{font-size:1.05em}.sdfc-fx .sdfc-fx-table td.sdfc-fx-comp{color:#555;font-style:italic;font-size:.9em}.sdfc-fx footer.sdfc-fx-foot{padding:12px}}',
    );
    return '<style>' . implode('', $styles) . '</style><script>document.addEventListener("DOMContentLoaded",function(){var labels=document.querySelectorAll(".sdfc-fx-tab-label"),contents=document.querySelectorAll(".sdfc-fx-tab-content");labels.forEach(function(label){label.addEventListener("click",function(){labels.forEach(function(l){l.classList.remove("active")});contents.forEach(function(c){c.classList.remove("active")});label.classList.add("active");var tab=label.getAttribute("data-tab");var content=document.querySelector(".sdfc-fx-tab-content[data-tab=\'"+tab+"\']");if(content)content.classList.add("active")})});if(labels.length>0)labels[0].classList.add("active");if(contents.length>0)contents[0].classList.add("active")});</script>';
}

/**
 * Renders one day's heading and fixtures table. No blank lines (see fixtures_css()).
 */
function render_fixtures_day($date, $day_fixtures) {
    $html = "<h2 class=\"sdfc-fx-day\">{$date}</h2><table class=\"sdfc-fx-table\"><thead><tr><th class=\"sdfc-fx-time\">Kick-off</th><th class=\"sdfc-fx-match\">Match</th><th class=\"sdfc-fx-comp\">Competition</th></tr></thead><tbody>";
    foreach ($day_fixtures as $fixture) {
        $time_12hr = date('g:i a', $fixture['timestamp']);
        $html .= "<tr><td class=\"sdfc-fx-time\">{$time_12hr}</td><td class=\"sdfc-fx-match\"><strong>{$fixture['home_team']}</strong> vs {$fixture['away_team']}</td><td class=\"sdfc-fx-comp\">{$fixture['competition']}</td></tr>";
    }
    return $html . "</tbody></table>";
}

/**
 * Builds the page HTML (WordPress format) for home and away fixtures. No blank lines (see fixtures_css()).
 */
function build_fixtures_html($fixtures_home, $fixtures_away) {
    $all_fixtures = array_merge($fixtures_home, $fixtures_away);
    if (empty($all_fixtures)) {
        return "<p style=\"text-align: center; margin: 10px 0 30px; font-size: 1.1em;\">No fixtures scheduled for the next 7 days.</p>\n";
    }
    usort($all_fixtures, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    $first_date = date('M j', $all_fixtures[0]['timestamp']);
    $last_date = date('M j, Y', $all_fixtures[count($all_fixtures) - 1]['timestamp']);
    $html = fixtures_css();
    $html .= "<div class=\"sdfc-fx\"><header class=\"sdfc-fx-head\"><h1 class=\"sdfc-fx-title\">Salthill Devon Fixtures</h1><p class=\"sdfc-fx-sub\">NFP Park, Drom | This Week: {$first_date} - {$last_date} ⚽</p></header><main class=\"sdfc-fx-main\"><div class=\"sdfc-fx-tabs\"><label class=\"sdfc-fx-tab-label\" data-tab=\"home\">Home</label><label class=\"sdfc-fx-tab-label\" data-tab=\"away\">Away</label></div>";
    $html .= render_fixtures_tab('home', $fixtures_home);
    $html .= render_fixtures_tab('away', $fixtures_away);
    $html .= "</main><footer class=\"sdfc-fx-foot\"><p class=\"sdfc-fx-note\">⚠️ Please note: Kick-off times and dates are subject to change. Always check the official Galway FA website.</p></footer></div>";
    return $html;
}

/**
 * Renders a tab's fixtures content. Called for both 'home' and 'away' tabs.
 */
function render_fixtures_tab($tab_id, $fixtures) {
    if (empty($fixtures)) {
        return "<div class=\"sdfc-fx-tab-content\" data-tab=\"{$tab_id}\"><p style=\"text-align:center;margin:20px 0;color:#666;\">No " . ($tab_id === 'home' ? 'home' : 'away') . " fixtures scheduled for this week.</p></div>";
    }
    usort($fixtures, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    $html = "<div class=\"sdfc-fx-tab-content\" data-tab=\"{$tab_id}\">";
    $fixtures_by_date = [];
    foreach ($fixtures as $fixture) {
        $fixtures_by_date[date('l, F j, Y', $fixture['timestamp'])][] = $fixture;
    }
    foreach ($fixtures_by_date as $date => $day_fixtures) {
        $html .= render_fixtures_day($date, $day_fixtures);
    }
    $html .= "</div>";
    return $html;
}

/**
 * Collects fixtures from every source for the coming week, builds the page HTML and publishes it.
 */
function update_fixtures() {
    $today_start = strtotime('today'); // Start of today (00:00:00)
    $window_days = (int) (getenv('FIXTURES_DAYS') ?: 7);
    $window_end = strtotime("+{$window_days} days", $today_start);

    // Fetch both home and away fixtures
    $faiconnect_home = fetch_faiconnect_fixtures($today_start, $window_end, true);
    $faiconnect_away = fetch_faiconnect_fixtures($today_start, $window_end, false);
    $galway_home = fetch_galway_fixtures($today_start, $window_end, true);
    $galway_away = fetch_galway_fixtures($today_start, $window_end, false);

    echo 'Home: FAI Connect ' . count($faiconnect_home) . ', Galway FA ' . count($galway_home) . '; ';
    echo 'Away: FAI Connect ' . count($faiconnect_away) . ', Galway FA ' . count($galway_away) . "\n";

    // The sources do not overlap: Galway FA lists the age-grade competitions, FAI Connect the adult
    // men's leagues. Team names differ between them, so matches could not be de-duplicated by name anyway.
    $fixtures_home = array_merge($faiconnect_home, $galway_home);
    $fixtures_away = array_merge($faiconnect_away, $galway_away);

    // 7. Build the page HTML
    $html_output = build_fixtures_html($fixtures_home, $fixtures_away);

    // Write to file (always, for local review)
    $output_file = __DIR__ . '/fixtures-output.html';
    file_put_contents($output_file, $html_output);
    echo "HTML output written to: {$output_file}\n";
    echo "Total fixtures found: " . (count($fixtures_home) + count($fixtures_away)) . "\n";

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
    curl_setopt($ch, CURLOPT_USERAGENT, 'sdfc-fixtures-bot/1.0 (+https://github.com/conorkelly86/sdfc-fixtures)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials,
        'Accept: application/json',
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

    if ($http_code !== 200 && $http_code !== 201) {
        $body = json_decode($response, true);
        $message = $body['message'] ?? $response;
        echo "WordPress update failed (HTTP {$http_code}): {$message}\n";
        exit(1);
    }

    verify_wordpress_saved($response, $html, $page_id, $http_code);
    echo "WordPress post {$page_id} updated and verified (HTTP {$http_code}).\n";
}

/**
 * A 200 alone is not proof of a save: confirm WordPress echoed back this post with our content.
 */
function verify_wordpress_saved($response, $html, $page_id, $http_code) {
    $body = json_decode($response, true);
    $saved = is_array($body) ? ($body['content']['raw'] ?? null) : null;
    $normalise = function ($s) {
        return trim(str_replace("\r\n", "\n", (string) $s));
    };

    if ($saved !== null && (int) ($body['id'] ?? 0) === (int) $page_id && $normalise($saved) === $normalise($html)) {
        return;
    }

    echo "WordPress returned HTTP {$http_code} but the content was NOT saved as sent.\n";
    echo 'Response id: ' . ($body['id'] ?? 'none') . ', saved length: ' . ($saved === null ? 'none' : strlen($saved)) . ', sent length: ' . strlen($html) . "\n";
    echo 'Response start: ' . substr(trim(preg_replace('/\\s+/', ' ', strip_tags((string) $response))), 0, 600) . "\n";
    exit(1);
}

// Run the script
update_fixtures();
?>
