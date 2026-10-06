<?php
// ==============================================================================
// Lexmation Stock Terminal - API & Sync Handler
// ==============================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? '';
$data_dir = __DIR__ . '/data';

if ($action === 'stats') {
    $file = $data_dir . '/stats.json';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['error' => 'Stats file not found']);
    }
    exit;
}

if ($action === 'trades') {
    $file = $data_dir . '/trades.json';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['error' => 'Trades file not found']);
    }
    exit;
}

if ($action === 'news') {
    $file = $data_dir . '/news.json';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['error' => 'News file not found']);
    }
    exit;
}

if ($action === 'market_brief') {
    $file = $data_dir . '/market_brief.json';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['error' => 'Market brief not yet generated', 'generated' => false]);
    }
    exit;
}

if ($action === 'rag_context') {
    $ticker = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($_GET['ticker'] ?? ''));
    if (empty($ticker)) {
        echo json_encode(['error' => 'Missing ticker parameter']);
        exit;
    }
    $script = __DIR__ . '/rag_engine.py';
    $output = [];
    $ret = 0;
    exec("python3 " . escapeshellarg($script) . " " . escapeshellarg($ticker) . " 2>&1", $output, $ret);
    $context_text = implode("\n", $output);
    echo json_encode([
        'ticker' => $ticker,
        'rag_context' => $context_text,
        'generated_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_market_brief') {
    // Called by n8n workflow — requires secret header
    $secret = $_SERVER['HTTP_X_LEXMATION_SECRET'] ?? '';
    if ($secret !== 'mkt_brief_s3cr3t_2026') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $body = file_get_contents('php://input');
    if (empty($body)) {
        echo json_encode(['error' => 'Empty body']);
        exit;
    }
    $decoded = json_decode($body, true);
    if (!$decoded) {
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }
    $file = $data_dir . '/market_brief.json';
    file_put_contents($file, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'saved_at' => date('Y-m-d H:i:s'), 'file' => 'market_brief.json']);
    exit;
}

if ($action === 'run_daily_scan') {
    // Trigger n8n webhook for 10 daily stocks
    $ch = curl_init('https://n8n.lexmation.com/webhook/daily-stocks-scan');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['source' => 'web_terminal', 'requested_at' => date('Y-m-d H:i:s')]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        echo json_encode([
            'success' => true,
            'message' => 'Дневният анализ на 10-те акции стартира успешно. Поради 32-сек. паузи между тях анализът ще отнеме ~8-9 минути.',
            'status' => 'started',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Грешка при извикване на n8n уебхука: ' . ($err ?: "HTTP $http_code"),
            'response' => $response
        ]);
    }
    exit;
}

if ($action === 'sync') {
    // Run sync script
    $script = __DIR__ . '/sync_n8n.py';
    $output = [];
    $return_var = 0;
    exec("python3 " . escapeshellarg($script) . " 2>&1", $output, $return_var);
    
    echo json_encode([
        'success' => ($return_var === 0),
        'output' => implode("\n", $output),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($action === 'eod_summary') {
    // 1. Run sync script first to ensure fresh data
    $script = __DIR__ . '/sync_n8n.py';
    $output = [];
    $return_var = 0;
    exec("python3 " . escapeshellarg($script) . " 2>&1", $output, $return_var);
    
    // 2. Load stats and trades
    $stats_file = $data_dir . '/stats.json';
    $trades_file = $data_dir . '/trades.json';
    $stats = file_exists($stats_file) ? json_decode(file_get_contents($stats_file), true) : [];
    $trades = file_exists($trades_file) ? json_decode(file_get_contents($trades_file), true) : [];
    
    // 3. Determine active session date
    $session_date = '';
    if (!empty($trades)) {
        foreach ($trades as $t) {
            $dt = substr($t['date_time'] ?? '', 0, 10);
            if (empty($session_date) || $dt > $session_date) {
                $session_date = $dt;
            }
        }
    }
    if (empty($session_date)) {
        $session_date = date('Y-m-d');
    }
    
    $open_trades = [];
    $closed_trades = [];
    $pending_trades = [];
    
    foreach ($trades as $t) {
        $dt = substr($t['date_time'] ?? '', 0, 10);
        if ($dt === $session_date) {
            $out = $t['outcome'] ?? 'PENDING';
            if ($out === 'OPEN') {
                $open_trades[] = $t;
            } elseif (strpos($out, 'WIN') !== false || strpos($out, 'LOSS') !== false) {
                $closed_trades[] = $t;
            } else {
                $pending_trades[] = $t;
            }
        }
    }
    
    $balance = (float)($stats['current_balance'] ?? 1000.0);
    $roi = (float)($stats['roi_percent'] ?? 0.0);
    $win_rate = (float)($stats['win_rate'] ?? 0.0);
    $win_count = (int)($stats['win_trades'] ?? 0);
    $loss_count = (int)($stats['loss_trades'] ?? 0);
    $net_r = (float)($stats['total_pnl_r'] ?? 0.0);
    
    $msg = "🏁 <b>LEXMATION — ДНЕВЕН ПАЗАРЕН ДОКЛАД (EOD)</b>\n";
    $msg .= "📅 <i>" . htmlspecialchars($session_date) . " | Затваряне на сесията</i>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "💼 <b>Баланс:</b> $" . number_format($balance, 2) . " (<b>" . ($roi >= 0 ? "+" : "") . number_format($roi, 2) . "% ROI</b>)\n";
    $msg .= "🎯 <b>Win Rate:</b> " . number_format($win_rate, 1) . "% (" . $win_count . "W / " . $loss_count . "L | Общо: " . ($net_r >= 0 ? "+" : "") . number_format($net_r, 1) . "R)\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    
    if (!empty($open_trades)) {
        $msg .= "🚀 <b>АКТИВНИ ОТВОРЕНИ ПОЗИЦИИ:</b>\n";
        foreach ($open_trades as $t) {
            $tk = htmlspecialchars($t['ticker'] ?? '');
            $v = htmlspecialchars($t['verdict'] ?? '');
            $curr = (float)($t['current_price'] ?? 0);
            $pnl = htmlspecialchars($t['realized_pnl'] ?? '0.0R');
            $icon = (strpos($pnl, '+') !== false) ? "🟢" : ((strpos($pnl, '-') !== false) ? "🔴" : "🟡");
            $note = htmlspecialchars($t['management_note'] ?? '');
            $msg .= "• <b>#" . $tk . "</b> (" . $v . "): $" . number_format($curr, 2) . " | " . $icon . " <b>" . $pnl . "</b>\n";
            if (!empty($note)) {
                $msg .= "  ↳ 🔄 <i>" . $note . "</i>\n";
            }
        }
    } else {
        $msg .= "ℹ️ <i>Няма активни отворени позиции за деня.</i>\n";
    }
    
    if (!empty($closed_trades)) {
        $msg .= "\n🏁 <b>ЗАТВОРЕНИ СДЕЛКИ ДНЕС:</b>\n";
        foreach ($closed_trades as $t) {
            $tk = htmlspecialchars($t['ticker'] ?? '');
            $out = htmlspecialchars($t['outcome'] ?? '');
            $pnl = htmlspecialchars($t['realized_pnl'] ?? '');
            $icon = (strpos($out, 'WIN') !== false) ? "🏆" : "🛑";
            $msg .= "• " . $icon . " <b>#" . $tk . "</b>: " . $out . " (" . $pnl . ")\n";
        }
    }
    
    if (!empty($pending_trades)) {
        $pending_tickers = [];
        foreach ($pending_trades as $t) {
            $pending_tickers[] = "#" . htmlspecialchars($t['ticker'] ?? '');
        }
        $msg .= "\n⏳ <b>Чакащи без активиран тригер (" . count($pending_trades) . "):</b>\n" . implode(", ", $pending_tickers) . "\n";
    }
    
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= '🌐 <a href="https://stocks.lexmation.com">stocks.lexmation.com</a>';
    
    echo json_encode([
        'success' => true,
        'session_date' => $session_date,
        'telegram_text' => $msg,
        'open_count' => count($open_trades),
        'closed_count' => count($closed_trades),
        'pending_count' => count($pending_trades),
        'balance' => $balance,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'daily_scan_summary') {
    // 1. Run sync script to ensure fresh data
    $script = __DIR__ . '/sync_n8n.py';
    $output = [];
    $return_var = 0;
    exec("python3 " . escapeshellarg($script) . " 2>&1", $output, $return_var);

    // 2. Load stats
    $stats_file = $data_dir . '/stats.json';
    $stats = file_exists($stats_file) ? json_decode(file_get_contents($stats_file), true) : [];
    
    $top_5 = $stats['daily_top_5'] ?? [];
    $reserve = $stats['daily_reserve'] ?? [];
    
    $date_formatted = date('d.m.Y');
    
    $msg = "📊 <b>LEXMATION — ДНЕВЕН АНАЛИЗ ЗАВЪРШЕН (10/10 АКЦИИ)</b>\n";
    $msg .= "📅 <i>" . $date_formatted . " г. | Официален преглед</i>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🏆 <b>ТОП 5 ПОДБОР ЗА ТЪРГОВИЯ:</b>\n\n";
    
    $medals = ['🥇', '🥈', '🥉', '⭐', '⭐'];
    foreach ($top_5 as $idx => $t) {
        $medal = $medals[$idx] ?? '⭐';
        $tk = htmlspecialchars($t['ticker'] ?? '');
        $v = htmlspecialchars($t['verdict'] ?? 'WATCH');
        $trig = number_format((float)($t['trigger_price'] ?? 0), 2);
        $sl = number_format((float)($t['stop_loss'] ?? 0), 2);
        $tp = number_format((float)($t['target_price'] ?? 0), 2);
        $grd = htmlspecialchars($t['setup_grade'] ?? 'B');
        $cat = htmlspecialchars($t['catalyst_headline'] ?? '');
        if (mb_strlen($cat) > 110) {
            $cat = mb_substr($cat, 0, 107) . '...';
        }
        
        $msg .= $medal . " <b>#" . $tk . "</b> (" . $v . ($grd === 'A+' ? ' / Grade A+' : '') . ")\n";
        $msg .= "• Вход: $" . $trig . " | Стоп: $" . $sl . " | Цел: $" . $tp . "\n";
        if (!empty($cat)) {
            $msg .= "• <i>" . $cat . "</i>\n";
        }
        $msg .= "\n";
    }
    
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📋 <b>РЕЗЕРВ (5 АКЦИИ):</b>\n";
    foreach ($reserve as $t) {
        $tk = htmlspecialchars($t['ticker'] ?? '');
        $trig = number_format((float)($t['trigger_price'] ?? 0), 2);
        $sl = number_format((float)($t['stop_loss'] ?? 0), 2);
        $tp = number_format((float)($t['target_price'] ?? 0), 2);
        $msg .= "• <b>#" . $tk . ":</b> Вход $" . $trig . " | SL $" . $sl . " | TP $" . $tp . "\n";
    }
    
    $events = $stats['position_management_events'] ?? [];
    $today_events = [];
    $today_str = date('Y-m-d');
    foreach ($events as $ev) {
        if (($ev['scan_date'] ?? '') === $today_str) {
            $today_events[] = $ev;
        }
    }
    if (!empty($today_events)) {
        $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🔄 <b>АКТУАЛИЗАЦИЯ НА ОТВОРЕНИ ПОЗИЦИИ:</b>\n";
        foreach ($today_events as $ev) {
            $tk = htmlspecialchars($ev['ticker'] ?? '');
            $adj_str = htmlspecialchars(implode(" | ", $ev['adjustments'] ?? []));
            $msg .= "• <b>#" . $tk . ":</b> " . $adj_str . "\n";
        }
    }
    
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "💼 <b>Портфолио модел:</b> 5 слота по $200 (Макс. $1,000 капитал)\n";
    $msg .= '🌐 <i>Следете изпълнението на живо: <a href="https://stocks.lexmation.com">stocks.lexmation.com</a></i>';
    
    echo json_encode([
        'success' => true,
        'telegram_text' => $msg,
        'top_5_count' => count($top_5),
        'reserve_count' => count($reserve),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'service' => 'Lexmation Stock Intel API',
    'status' => 'operational',
    'endpoints' => [
        '?action=stats' => 'Get overall statistics and equity curve',
        '?action=trades' => 'Get all signals and trades history',
        '?action=news' => 'Get 24h news catalysts from Perplexity',
        '?action=sync' => 'Trigger sync from n8n executions',
        '?action=eod_summary' => 'Sync terminal & generate consolidated EOD report',
        '?action=daily_scan_summary' => 'Sync terminal & generate Top 10 scan summary'
    ]
]);
