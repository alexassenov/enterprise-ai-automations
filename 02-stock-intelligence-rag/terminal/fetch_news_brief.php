<?php
// ==============================================================================
// Lexmation Stock Terminal - Live Market Snapshot & RSS News Fetcher for Market Brief
// Strictly fetches verified, LIVE financial news from the last 24-48 hours
// plus REAL-TIME market quotes (S&P 500, Nasdaq, 10Y Yield, Gold, Oil, Bitcoin)
// and current active portfolio positions for direct tactical instructions.
// ==============================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Secret check — only n8n workflow should call this
$secret = $_SERVER['HTTP_X_LEXMATION_SECRET'] ?? $_GET['secret'] ?? '';
if ($secret !== 'mkt_brief_s3cr3t_2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 1. Fetch Real-Time Market Quotes
$market_symbols = [
    "^GSPC"   => "S&P 500 (^GSPC)",
    "^IXIC"   => "Nasdaq Composite (^IXIC)",
    "^TNX"    => "10-Year US Treasury Yield (^TNX)",
    "GC=F"    => "Gold Futures (GC=F)",
    "CL=F"    => "Crude Oil WTI (CL=F)",
    "BTC-USD" => "Bitcoin USD (BTC-USD)"
];

$market_snapshot_data = [];
$today_str = date('d.m.Y H:i');
$snapshot_lines = ["=== ЖИВИ ПАЗАРНИ КОТИРОВКИ В МОМЕНТА (ДНЕС: {$today_str} UTC) ==="];

foreach ($market_symbols as $sym => $label) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($sym) . "?interval=1d&range=5d";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
    $resp = curl_exec($ch);
    $data = json_decode($resp, true);
    $meta = $data["chart"]["result"][0]["meta"] ?? [];
    $price = $meta["regularMarketPrice"] ?? null;
    $prev_close = $meta["chartPreviousClose"] ?? null;
    
    if ($price !== null && $prev_close) {
        $change = $price - $prev_close;
        $pct = ($change / $prev_close) * 100;
        $sign = $change >= 0 ? "+" : "";
        
        if ($sym === "^TNX") {
            $formatted = "{$price}% ({$sign}" . number_format($change * 100, 1) . " bps за деня)";
            $snapshot_lines[] = "- {$label}: {$formatted}";
            $market_snapshot_data[$sym] = ['label' => $label, 'value' => $price, 'change_bps' => round($change * 100, 1)];
        } else {
            $formatted_price = number_format($price, 2);
            $formatted = "\${$formatted_price} ({$sign}" . number_format($pct, 2) . "% за деня)";
            $snapshot_lines[] = "- {$label}: {$formatted}";
            $market_snapshot_data[$sym] = ['label' => $label, 'price' => $price, 'change_pct' => round($pct, 2)];
        }
    }
}
$snapshot_lines[] = "================================================================";
$market_snapshot_text = implode("\n", $snapshot_lines);

// 2. Load Active Trades Portfolio Context
$portfolio_context = "";
$trades_file = __DIR__ . '/data/trades.json';
if (file_exists($trades_file)) {
    $all_trades = json_decode(file_get_contents($trades_file), true);
    if (is_array($all_trades)) {
        $open_trades_list = [];
        $pending_trades_list = [];
        foreach ($all_trades as $tr) {
            $sym = $tr['ticker'] ?? '';
            $verdict = strtoupper($tr['verdict'] ?? '');
            $outcome = strtoupper($tr['outcome'] ?? '');
            $ent = $tr['trigger_price'] ?? ($tr['entry_price'] ?? '');
            $sl = $tr['stop_loss'] ?? '';
            $tp = $tr['target_price'] ?? '';

            if ($outcome === 'OPEN') {
                $open_trades_list[] = "- {$sym}: {$verdict} (Ниво: \${$ent}, Stop: \${$sl}, Target: \${$tp})";
            }
        }
        if (!empty($open_trades_list)) {
            $portfolio_context = "=== НАШИТЕ АКТИВНИ ОТВОРЕНИ ПОЗИЦИИ В МОМЕНТА ===\n" . implode("\n", $open_trades_list) . "\n";
            $portfolio_context .= "- Наблюдаван основен списък (Watchlist): NBIS, APP, META, CRDO, MU, IREN, HIMS, EOSE, OUST, CRWV\n==============================================\n";
        }
    }
}

// 3. Fetch Live RSS Feeds (Strict 48-Hour Freshness)
$feeds = [
    ['name' => 'CNBC Top News',       'url' => 'https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100003114'],
    ['name' => 'CNBC World Markets',  'url' => 'https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100727362'],
    ['name' => 'MarketWatch',         'url' => 'https://feeds.marketwatch.com/marketwatch/topstories/'],
    ['name' => 'Google Business News','url' => 'https://news.google.com/rss/headlines/section/topic/BUSINESS?hl=en-US&gl=US&ceid=US:en'],
    ['name' => 'CoinDesk Crypto',     'url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/'],
    ['name' => 'Investing.com',       'url' => 'https://www.investing.com/rss/news.rss']
];

$articles = [];
$fetched_feeds = [];
$errors = [];
$now_ts = time();
$max_age_seconds = 86400 * 2; // Strict 48-hour freshness filter

foreach ($feeds as $feed) {
    $ctx = stream_context_create([
        'http' => [
            'timeout'         => 7,
            'follow_location' => 1,
            'max_redirects'   => 3,
            'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'header'          => "Accept: application/rss+xml,application/xml,text/xml,*/*\r\n"
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    $xml = @file_get_contents($feed['url'], false, $ctx);
    if (!$xml || strlen($xml) < 100) {
        $errors[] = $feed['name'] . ': fetch failed';
        continue;
    }

    // Extract <item> elements from RSS
    preg_match_all('/<item[^>]*>([\s\S]*?)<\/item>/i', $xml, $items);
    $count = 0;
    foreach ($items[1] as $item) {
        if ($count >= 7) break;

        // Check publication date
        preg_match('/<pubDate>(.*?)<\/pubDate>/i', $item, $pdm);
        $pub_str = trim($pdm[1] ?? '');
        if ($pub_str) {
            $item_ts = strtotime($pub_str);
            if ($item_ts && ($now_ts - $item_ts > $max_age_seconds)) {
                continue;
            }
        }

        // Extract title
        preg_match('/<title>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?<\/title>/i', $item, $tm);
        $title = trim(strip_tags($tm[1] ?? ''));
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Extract description
        preg_match('/<description>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?<\/description>/i', $item, $dm);
        $desc = trim(strip_tags($dm[1] ?? ''));
        $desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $desc = substr($desc, 0, 250);

        if (strlen($title) > 15) {
            $date_badge = $pub_str ? date('Y-m-d H:i', strtotime($pub_str)) : 'Today';
            $articles[] = "[{$feed['name']} - {$date_badge}] {$title}. {$desc}";
            $count++;
        }
    }
    if ($count > 0) {
        $fetched_feeds[] = $feed['name'] . " ({$count} articles)";
    }
}

// Deduplicate and limit to 35 articles
$articles = array_unique($articles);
$articles = array_slice($articles, 0, 35);

// Prepend market snapshot and portfolio to news text
$full_news_text = $market_snapshot_text . "\n\n" . $portfolio_context . "\n=== АКТУАЛНИ ФИНАНСОВИ И МАКРО НОВИНИ ЗА ПОСЛЕДНИТЕ 24-48 ЧАСА ===\n" . implode("\n---\n", $articles);

echo json_encode([
    'success'         => true,
    'market_snapshot' => $market_snapshot_data,
    'article_count'   => count($articles),
    'news_text'       => $full_news_text,
    'feeds_fetched'   => $fetched_feeds,
    'errors'          => $errors,
    'fetched_at'      => date('c')
], JSON_UNESCAPED_UNICODE);
