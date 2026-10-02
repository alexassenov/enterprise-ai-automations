<?php
// ==============================================================================
// Lexmation Stock Terminal - Real-Time Live Stock Quotes Endpoint
// ==============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

$cache_file = __DIR__ . '/data/live_quotes_cache.json';
$cache_ttl = 8; // 8 seconds cache to avoid upstream rate limits and keep response instant

$trades_file = __DIR__ . '/data/trades.json';
$trades_mtime = file_exists($trades_file) ? filemtime($trades_file) : 0;
$latest_trade_time = '';
$trade_count = 0;
$tickers = ['META', 'NBIS', 'CRDO', 'MU', 'CRWV', 'EOSE'];

if (file_exists($trades_file)) {
    $raw_trades = json_decode(file_get_contents($trades_file), true);
    if (is_array($raw_trades)) {
        $trade_count = count($raw_trades);
        $latest_trade_time = $raw_trades[0]['date_time'] ?? '';
        foreach ($raw_trades as $t) {
            $sym = strtoupper(trim($t['ticker'] ?? ''));
            if (!empty($sym) && !in_array($sym, $tickers)) {
                $tickers[] = $sym;
            }
        }
    }
}

// Check cache (only valid if newer than trades_file modification)
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl) && (filemtime($cache_file) >= $trades_mtime)) {
    $cached = @file_get_contents($cache_file);
    if (!empty($cached)) {
        echo $cached;
        exit;
    }
}

// Fetch live quotes from Yahoo Finance Chart API
$results = [];
$now_iso = date('c');

foreach ($tickers as $symbol) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1m&range=1d";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $json = json_decode($response, true);
        $res = $json['chart']['result'][0] ?? null;
        if ($res && isset($res['meta'])) {
            $meta = $res['meta'];
            $price = round((float)($meta['regularMarketPrice'] ?? 0), 2);
            $prev_close = round((float)($meta['chartPreviousClose'] ?? $price), 2);
            $change_dollars = round($price - $prev_close, 2);
            $change_pct = $prev_close > 0 ? round(($change_dollars / $prev_close) * 100, 2) : 0.0;

            $results[$symbol] = [
                'ticker' => $symbol,
                'price' => $price,
                'price_formatted' => '$' . number_format($price, 2),
                'prev_close' => $prev_close,
                'change_dollars' => $change_dollars,
                'change_pct' => $change_pct,
                'change_pct_formatted' => ($change_pct >= 0 ? '+' : '') . number_format($change_pct, 2) . '%',
                'is_positive' => $change_dollars >= 0,
                'timestamp' => time()
            ];
            continue;
        }
    }

    // Fallback if network issue
    $results[$symbol] = [
        'ticker' => $symbol,
        'price' => 0.0,
        'price_formatted' => '--',
        'prev_close' => 0.0,
        'change_dollars' => 0.0,
        'change_pct' => 0.0,
        'change_pct_formatted' => '0.00%',
        'is_positive' => true,
        'timestamp' => time()
    ];
}

// Check for Target or Stop hit across active open positions
$target_hit_detected = false;
$stop_hit_detected = false;
$hit_details = [];

if (is_array($raw_trades)) {
    foreach ($raw_trades as $t) {
        $out = strtoupper($t['outcome'] ?? '');
        $is_open = ($out === 'OPEN' || strpos($out, 'OPEN') !== false || $out === 'IN PROGRESS');
        if ($is_open) {
            $sym = strtoupper(trim($t['ticker'] ?? ''));
            $v = strtoupper(trim($t['verdict'] ?? 'WATCH'));
            $target = (float)($t['target_price'] ?? 0);
            $stop = (float)($t['stop_loss'] ?? 0);
            $entry = (float)($t['current_price'] ?? 0);
            $live_p = $results[$sym]['price'] ?? 0;
            
            if ($live_p > 0 && $target > 0) {
                $is_bullish = ($v === 'BUY') || ($v === 'WATCH' && ($target > $entry || $target > $stop));
                if ($is_bullish) {
                    if ($live_p >= $target) {
                        $target_hit_detected = true;
                        $hit_details[] = ['ticker' => $sym, 'type' => 'TARGET', 'price' => $live_p, 'target' => $target];
                    } elseif ($stop > 0 && $live_p <= $stop) {
                        $stop_hit_detected = true;
                        $hit_details[] = ['ticker' => $sym, 'type' => 'STOP', 'price' => $live_p, 'stop' => $stop];
                    }
                } else {
                    if ($live_p <= $target) {
                        $target_hit_detected = true;
                        $hit_details[] = ['ticker' => $sym, 'type' => 'TARGET', 'price' => $live_p, 'target' => $target];
                    } elseif ($stop > 0 && $live_p >= $stop) {
                        $stop_hit_detected = true;
                        $hit_details[] = ['ticker' => $sym, 'type' => 'STOP', 'price' => $live_p, 'stop' => $stop];
                    }
                }
            }
        }
    }
}

// If target or stop hit detected, trigger sync_n8n.py in background to close trade & free slot immediately
if ($target_hit_detected || $stop_hit_detected) {
    $sync_script = __DIR__ . '/sync_n8n.py';
    @exec("/usr/bin/python3 " . escapeshellarg($sync_script) . " > /dev/null 2>&1 &");
}

$stats_file = __DIR__ . '/data/stats.json';
$stats_data = file_exists($stats_file) ? @json_decode(file_get_contents($stats_file), true) : [];
$portfolio_cash = $stats_data['portfolio_cash'] ?? [];
$closed_trades_count = (int)($stats_data['closed_trades'] ?? 6);

$output = json_encode([
    'status' => 'ok',
    'updated_at' => $now_iso,
    'trades_mtime' => $trades_mtime,
    'trades_hash' => file_exists($trades_file) ? md5_file($trades_file) : '',
    'latest_trade_time' => $latest_trade_time,
    'trade_count' => $trade_count,
    'closed_count' => $closed_trades_count,
    'portfolio_cash' => $portfolio_cash,
    'target_hit_detected' => $target_hit_detected,
    'stop_hit_detected' => $stop_hit_detected,
    'hit_details' => $hit_details,
    'quotes' => $results
]);

@file_put_contents($cache_file, $output);
echo $output;
