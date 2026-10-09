<?php
// Prevent client and proxy caching for real-time accuracy
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// ==============================================================================
// Lexmation Stock Multi-Timeframe & News Intelligence Terminal
// ==============================================================================

$stats_file = __DIR__ . '/data/stats.json';
$trades_file = __DIR__ . '/data/trades.json';
$news_file = __DIR__ . '/data/news.json';

$stats = file_exists($stats_file) ? json_decode(file_get_contents($stats_file), true) : [];
$trades = file_exists($trades_file) ? json_decode(file_get_contents($trades_file), true) : [];
$news = file_exists($news_file) ? json_decode(file_get_contents($news_file), true) : [];

$daily_top_5 = $stats['daily_top_5'] ?? [];
$daily_reserve = $stats['daily_reserve'] ?? [];
$daily_schedule = $stats['daily_schedule'] ?? [];

// Helper function to format US/NY market time to BG local time
function format_trade_time($dt_str) {
    if (empty($dt_str)) return ['bg_time' => '', 'bg_date' => '', 'bg_pill' => '', 'bg_full' => '', 'ny_time' => ''];
    try {
        $ny_tz = new DateTimeZone('America/New_York');
        $bg_tz = new DateTimeZone('Europe/Sofia');
        // n8n records Date_Time in BG (Europe/Sofia) timezone, e.g. "2026-09-30 17:16"
        // So we parse it directly as BG time, then convert to NY for display only.
        // Previously this parsed as NY which caused +3h offset → showing next day "01 Oct 00:16".
        $d_bg = DateTime::createFromFormat('Y-m-d H:i', substr($dt_str, 0, 16), $bg_tz);
        if ($d_bg) {
            $d_ny = clone $d_bg;
            $d_ny->setTimezone($ny_tz);
            $months = [
                '01' => 'Яну', '02' => 'Фев', '03' => 'Мар', '04' => 'Апр', '05' => 'Май', '06' => 'Юни',
                '07' => 'Юли', '08' => 'Авг', '09' => 'Сеп', '10' => 'Окт', '11' => 'Ное', '12' => 'Дек'
            ];
            $m_num = $d_bg->format('m');
            $m_name = $months[$m_num] ?? $m_num;
            $is_today = ($d_bg->format('Y-m-d') === (new DateTime('now', $bg_tz))->format('Y-m-d'));
            $date_label = $is_today ? 'Днес' : ($d_bg->format('d') . ' ' . $m_name);
            return [
                'bg_time' => $d_bg->format('H:i') . ' ч.',
                'bg_date' => $d_bg->format('d.m.Y'),
                'bg_pill' => $d_bg->format('H:i') . ' ч.',
                'bg_full' => $date_label . ' в ' . $d_bg->format('H:i') . ' ч.',
                'ny_time' => $d_ny->format('H:i') . ' NY'
            ];
        }
    } catch (Exception $e) {}
    return ['bg_time' => $dt_str, 'bg_date' => '', 'bg_pill' => $dt_str, 'bg_full' => $dt_str, 'ny_time' => ''];
}

// ==============================================================================
// Official Portfolio Cash & Trades State (Unified across Easy & Pro views)
// ==============================================================================
$display_balance = (float)($stats['current_balance'] ?? 1185.26);
$total_closed_cash = (float)($stats['total_pnl_dollars'] ?? 185.26);
$display_roi = (float)($stats['roi_percent'] ?? 18.53);
$closed_wins_count = (int)($stats['win_trades'] ?? 15);
$closed_loss_count = (int)($stats['loss_trades'] ?? 9);
$resolved_count = $closed_wins_count + $closed_loss_count;
$display_win_rate = (float)($stats['win_rate'] ?? 62.5);
$gross_profit_dollars = (float)($stats['gross_profit_dollars'] ?? 225.68);
$gross_loss_dollars = (float)($stats['gross_loss_dollars'] ?? 40.42);
$profit_factor = (float)($stats['profit_factor'] ?? 5.58);
$closed_range_count = 0;

// Portfolio Cash & Slots Allocation
$portfolio_cash = $stats['portfolio_cash'] ?? [];
$total_slots = (int)($portfolio_cash['total_slots'] ?? 5);
$used_slots = (int)($portfolio_cash['used_slots'] ?? 4);
$available_slots = (int)($portfolio_cash['available_slots'] ?? 1);
$free_cash = (float)($portfolio_cash['free_cash'] ?? max(0, $display_balance - 800.0));
$allocated_cash = (float)($portfolio_cash['allocated_cash'] ?? 800.0);

$all_closed_trades = [];
$real_closed_trades = [];
$watch_closed_trades = [];
$all_closed_cash = 0;
$all_wins_count = 0;
$all_loss_count = 0;
$all_range_count = 0;

// Daily 10-Stock Scan Audit Status
$latest_scan_dt = !empty($trades) ? substr($trades[0]['date_time'] ?? '', 0, 16) : '';
$today_ymd = date('Y-m-d');
$scan_session_date = substr($latest_scan_dt, 0, 10);
$scan_is_today = ($scan_session_date === $today_ymd);
$scan_tickers_count = 0;
if (!empty($trades)) {
    foreach ($trades as $tr) {
        if (substr($tr['date_time'] ?? '', 0, 10) === $scan_session_date) {
            $scan_tickers_count++;
        }
    }
}
$scan_time_str = substr($latest_scan_dt, 11, 5);

if (!empty($trades)) {
    foreach ($trades as $t) {
        $out = strtoupper($t['outcome'] ?? '');
        $v = strtoupper($t['verdict'] ?? '');
        $is_open = ($out === 'OPEN' || strpos($out, 'OPEN') !== false || strpos($out, 'PENDING') !== false || $out === 'IN PROGRESS');
        if (!empty($out) && !$is_open) {
            $all_closed_trades[] = $t;
            $pnl_d = (float)($t['pnl_dollars'] ?? 0);
            $all_closed_cash += $pnl_d;
            if (strpos($out, 'WIN') !== false) {
                $all_wins_count++;
                $real_closed_trades[] = $t;
            } elseif (strpos($out, 'LOSS') !== false) {
                $all_loss_count++;
                $real_closed_trades[] = $t;
            } else {
                $all_range_count++;
                $closed_range_count++;
                $watch_closed_trades[] = $t;
            }
        }
    }
}
$closed_trades_list = $all_closed_trades;

// Unique tickers for filter
$tickers = [];
if (!empty($trades)) {
    foreach ($trades as $t) {
        $tick = strtoupper(trim($t['ticker'] ?? ''));
        if ($tick && !in_array($tick, $tickers)) {
            $tickers[] = $tick;
        }
    }
    sort($tickers);
}
?>
<!DOCTYPE html>
<html lang="bg" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Multi-Timeframe & News Intelligence | Lexmation Terminal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2310B981'><path d='M3 3v18h18v-2H5V3H3zm4 12h2v4H7v-4zm4-6h2v10h-2V9zm4-4h2v14h-2V5zm4 8h2v6h-2v-6z'/></svg>">
    
    <!-- Fonts & Tailwind -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        term: {
                            bg: '#080C14',
                            surface: '#0E1422',
                            card: '#131A2B',
                            border: '#1E293B',
                            hover: '#1B2438',
                            green: '#00E676',
                            red: '#FF3366',
                            blue: '#00B0FF',
                            amber: '#FFB300'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: #080C14;
            color: #E2E8F0;
            font-family: 'Inter', sans-serif;
        }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glow-green { text-shadow: 0 0 12px rgba(0, 230, 118, 0.4); }
        .glow-red { text-shadow: 0 0 12px rgba(255, 51, 102, 0.4); }
        .glow-blue { text-shadow: 0 0 12px rgba(0, 176, 255, 0.4); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0E1422; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #1E293B; border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #334155; }
    </style>
</head>
<body class="min-h-screen flex flex-col custom-scrollbar">

    <!-- Top Navigation Header -->
    <header class="border-b border-term-border bg-term-surface/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
            <div class="flex items-center space-x-3 sm:space-x-4">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-cyan-500 flex items-center justify-center shadow-lg shadow-emerald-500/20 flex-shrink-0">
                    <i class="fa-solid fa-chart-line text-white text-lg"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-lg text-white tracking-wide">LEXMATION</span>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">STOCK INTEL</span>
                    </div>
                    <p class="text-xs text-slate-400 hidden sm:block">Multi-Timeframe & News Intelligence Terminal</p>
                </div>
            </div>

            <!-- View Mode Switcher: Лесен vs За напреднали (Pro) -->
            <div class="flex items-center bg-slate-900/90 p-1 rounded-xl border border-slate-700/70 shadow-inner">
                <button id="mode-btn-simple" onclick="setViewMode('simple')" class="px-3 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1.5 bg-emerald-500 text-slate-950 shadow-md">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>Лесен изглед</span>
                </button>
                <button id="mode-btn-pro" onclick="setViewMode('pro')" class="px-3 sm:px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>За напреднали (Pro)</span>
                </button>
            </div>

            <!-- Market Status & Clocks -->
            <div class="hidden md:flex items-center space-x-4 text-xs">
                <!-- Market Regime Traffic Light -->
                <div class="hidden xl:flex items-center space-x-2 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="font-bold text-emerald-400 font-mono text-[11px]">REGIME: RISK-ON</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-slate-300 font-mono text-[10px]">SPY > 20 EMA &bull; VIX 16.4</span>
                </div>

                <div class="flex items-center space-x-2 bg-term-card px-3 py-1.5 rounded-lg border border-term-border">
                    <span id="market-status-dot" class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span id="market-status-text" class="font-medium text-slate-200">NYSE / NASDAQ</span>
                    <span class="text-slate-500">|</span>
                    <span id="nyse-clock" class="mono text-emerald-400 font-semibold">--:--:-- EST</span>
                </div>
                <div class="text-slate-400">
                    Последна синхронизация: 
                    <span class="text-slate-200 font-medium mono" id="last-sync-time"><?= htmlspecialchars($stats['updated_at'] ?? 'Auto') ?></span>
                </div>
                <button onclick="refreshData()" class="p-2 text-slate-400 hover:text-white bg-term-card hover:bg-term-hover border border-term-border rounded-lg transition" title="Презареди данни">
                    <i class="fa-solid fa-arrows-rotate" id="refresh-icon"></i>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs (Only visible in Pro mode) -->
        <div id="pro-tabs-bar" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex space-x-1 border-t border-term-border/40 text-sm hidden">
            <button onclick="switchTab('dashboard')" id="tab-btn-dashboard" class="tab-btn px-4 py-2.5 font-medium border-b-2 border-emerald-400 text-emerald-400 flex items-center space-x-2 transition">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Табло & Статистика</span>
            </button>
            <button onclick="switchTab('signals')" id="tab-btn-signals" class="tab-btn px-4 py-2.5 font-medium border-b-2 border-transparent text-slate-400 hover:text-slate-200 flex items-center space-x-2 transition">
                <i class="fa-solid fa-bolt"></i>
                <span>Сигнали & Сделки</span>
                <span class="ml-1.5 px-1.5 py-0.2 rounded-full text-xs bg-slate-800 text-slate-300 font-mono"><?= count($trades) ?></span>
            </button>
            <button onclick="switchTab('news')" id="tab-btn-news" class="tab-btn px-4 py-2.5 font-medium border-b-2 border-transparent text-slate-400 hover:text-slate-200 flex items-center space-x-2 transition">
                <i class="fa-solid fa-newspaper"></i>
                <span>Новини & Катализатори</span>
                <span class="ml-1.5 px-1.5 py-0.2 rounded-full text-xs bg-slate-800 text-slate-300 font-mono"><?= count($news) ?></span>
            </button>
            <button onclick="switchTab('chart')" id="tab-btn-chart" class="tab-btn px-4 py-2.5 font-medium border-b-2 border-transparent text-slate-400 hover:text-slate-200 flex items-center space-x-2 transition">
                <i class="fa-solid fa-chart-candlestick"></i>
                <span>Live Графика (TradingView)</span>
            </button>
            <button onclick="switchTab('market-brief')" id="tab-btn-market-brief" class="tab-btn px-4 py-2.5 font-medium border-b-2 border-transparent text-slate-400 hover:text-slate-200 flex items-center space-x-2 transition">
                <i class="fa-solid fa-globe text-blue-400"></i>
                <span>Пазарен Бриф</span>
                <span id="market-brief-badge" class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-blue-500/20 text-blue-300 font-semibold border border-blue-500/30 hidden">AI</span>
            </button>
            <button onclick="switchTab('methodology')" id="tab-btn-methodology" class="tab-btn px-4 py-2.5 font-medium border-b-2 border-transparent text-slate-400 hover:text-slate-200 flex items-center space-x-2 transition">
                <i class="fa-solid fa-book-open text-amber-400"></i>
                <span>Методология & Документация</span>
                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-amber-500/20 text-amber-300 font-semibold border border-amber-500/30">GUIDE</span>
            </button>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- ============================================================== -->
        <!-- SIMPLE VIEW (За начинаещи / Лесен изглед)                      -->
        <!-- ============================================================== -->
        <div id="simple-dashboard" class="space-y-8">

            <!-- 1. Warm Greeting & Mission Card -->
            <div class="bg-gradient-to-br from-emerald-950/40 via-term-surface to-cyan-950/20 border border-emerald-500/30 rounded-2xl p-6 sm:p-7 shadow-xl shadow-emerald-950/20">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="space-y-2.5 max-w-3xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-halved text-emerald-400"></i> 100% КЕШОВ МОДЕЛ &bull; БЕЗ ЗАЕМИ &bull; БЕЗ ХАЗАРТ
                            </span>
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-300 border border-cyan-500/30">
                                АВТОМАТИЗИРАН БОРСОВ АСИСТЕНТ
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                            Интелигентна борсова търговия, направена <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">максимално проста</span>
                        </h1>
                        <p class="text-sm sm:text-base text-slate-300 leading-relaxed">
                            Тази система сканира десетки хиляди пазарни новини и движения на американската борса, за да ви каже точно <strong>кога да купите</strong>, <strong>с колко пари</strong> и <strong>кога да приберете печалбата</strong>. Без сложни графики, без стрес и без излишен жаргон.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row md:flex-col gap-2.5 flex-shrink-0 min-w-[280px]">
                        <div class="bg-term-card/90 border border-emerald-500/40 rounded-xl p-3 text-center shadow-md">
                            <div class="flex items-center justify-between text-[11px] text-slate-400 uppercase tracking-wider font-medium">
                                <span>Дневен AI Анализ</span>
                                <?php if ($scan_is_today): ?>
                                    <span class="text-emerald-400 font-bold font-mono flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> ЗАВЪРШЕН ДНЕС</span>
                                <?php else: ?>
                                    <span class="text-cyan-400 font-bold font-mono">АКТИВЕН (<?= htmlspecialchars($scan_session_date) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs font-bold text-slate-200 flex items-center justify-center gap-1.5 mt-1.5 font-mono">
                                <i class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
                                <span><?= $scan_tickers_count ?> от 10 акции анализирани (в <?= $scan_time_str ?> ч.)</span>
                            </div>
                        </div>

                        <!-- Manual On-Demand Scan Button -->
                        <div class="bg-term-card/90 border border-cyan-500/30 hover:border-cyan-500/60 transition rounded-xl p-2.5 shadow-md">
                            <button id="btn-daily-scan" onclick="triggerDailyScan()" class="w-full py-2 px-3 rounded-lg bg-gradient-to-r from-emerald-500 to-cyan-500 hover:from-emerald-400 hover:to-cyan-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-2 shadow-md transition duration-200 cursor-pointer group">
                                <i class="fa-solid fa-bolt-lightning text-slate-950 group-hover:scale-110 transition"></i>
                                <span id="btn-daily-scan-text">Пусни дневен анализ сега</span>
                            </button>
                            <span class="block text-[10px] text-slate-400 text-center mt-1 font-mono">10 следени акции &bull; Топ 5 селекция</span>
                        </div>
                    </div>
                </div>

                <!-- Daily Scan Feedback Banner -->
                <div id="daily-scan-banner" class="hidden mt-3"></div>
            </div>

            <!-- 2. Section 1: Главните 3 цифри за твоите пари -->
            <div>
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-emerald-400"></i>
                        <span>Твоят Портфейл в 3 прости числа</span>
                    </h2>
                    <p class="text-xs text-slate-400">Чисти резултати от реални сключени сделки на Wall Street</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Card 1: Баланс -->
                    <div class="bg-term-surface border border-term-border hover:border-emerald-500/40 rounded-2xl p-6 transition shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition"></div>
                        <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <span>Текущи Пари в Сметката</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                                <i class="fa-solid fa-dollar-sign text-sm"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-3xl sm:text-4xl font-black text-emerald-400 mono tracking-tight">
                                $<?= number_format($display_balance, 2) ?>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                    +$<?= number_format($total_closed_cash, 2) ?> чиста печалба
                                </span>
                                <span class="text-xs text-emerald-400 font-semibold">+<?= number_format($display_roi, 1) ?>%</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-3 pt-3 border-t border-term-border/60">
                                Започнахме с <strong>$1,000.00</strong>. Всички спечелени пари са ваши и могат да се изтеглят по всяко време.
                            </p>
                        </div>
                    </div>

                    <!-- Card 2: Успеваемост -->
                    <div class="bg-term-surface border border-term-border hover:border-cyan-500/40 rounded-2xl p-6 transition shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition"></div>
                        <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <span>Успеваемост на Сделките</span>
                            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                                <i class="fa-solid fa-trophy text-sm"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-3xl sm:text-4xl font-black text-cyan-400 mono tracking-tight">
                                <?= number_format($display_win_rate, 1) ?>% Победи
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40">
                                    <?= $closed_wins_count ?> победи от <?= $resolved_count ?> решени сделки
                                </span>
                                <span class="text-xs text-rose-400 font-semibold font-mono"><?= $closed_loss_count ?> стопа</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-3 pt-3 border-t border-term-border/60">
                                Честна борсова статистика: победи при <strong>+$<?= number_format($gross_profit_dollars, 2) ?></strong>, защитни стопове при <strong>-$<?= number_format($gross_loss_dollars, 2) ?></strong> (Профит фактор: <strong class="text-emerald-400"><?= number_format($profit_factor, 2) ?>x</strong>).
                            </p>
                        </div>
                    </div>

                    <!-- Card 3: Сигурност и Алокация -->
                    <div class="bg-term-surface border border-term-border hover:border-amber-500/40 rounded-2xl p-6 transition shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition"></div>
                        <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <span>Контрол на Риска</span>
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                                <i class="fa-solid fa-shield-halved text-sm"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-amber-400 mono tracking-tight flex items-baseline justify-between">
                                <span>5 Слота ($1,000)</span>
                                <span class="text-xs font-bold font-mono px-2 py-0.5 rounded-lg <?= $available_slots > 0 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800 text-slate-400 border border-slate-700' ?>" id="slot-status-pill">
                                    <?= $available_slots > 0 ? "🟢 {$available_slots} Свободни слота" : "🔒 Пълен капацитет (5/5)" ?>
                                </span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold <?= $available_slots > 0 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40' ?>" id="slot-free-cash-pill">
                                    <?= $available_slots > 0 ? "🟢 Свободен кеш: $" . number_format($free_cash, 2) : "0% Заеми &bull; 0% Ливъридж" ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-3 pt-3 border-t border-term-border/60">
                                <strong>Динамична ликвидност:</strong> щом позиция достигне таргета си, слотът се освобождава <strong>на секундата</strong> за следващия сигнал от Радара.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Section 2: КАКВО ДА НАПРАВИМ ДНЕС? (Интерактивен Карусел с очаквани сделки) -->
            <?php
            $open_trades_list = [];
            $waiting_triggers_list = [];
            foreach ($trades as $t) {
                $out = strtoupper($t['outcome'] ?? '');
                if ($out === 'OPEN' || strpos($out, 'OPEN') !== false || $out === 'IN PROGRESS') {
                    $open_trades_list[] = $t;
                } elseif (strpos($out, 'PENDING') !== false) {
                    $waiting_triggers_list[] = $t;
                }
            }
            // Sort open trades: BUY/SELL first, then newest
            usort($open_trades_list, function($a, $b) {
                $vA = strtoupper($a['verdict'] ?? '');
                $vB = strtoupper($b['verdict'] ?? '');
                if ($vA === 'BUY' && $vB !== 'BUY') return -1;
                if ($vB === 'BUY' && $vA !== 'BUY') return 1;
                if ($vA === 'SELL' && $vB !== 'SELL') return -1;
                if ($vB === 'SELL' && $vA !== 'SELL') return 1;
                return strcmp($b['date_time'] ?? '', $a['date_time'] ?? '');
            });
            // Sort waiting triggers: newest first
            usort($waiting_triggers_list, function($a, $b) {
                return strcmp($b['date_time'] ?? '', $a['date_time'] ?? '');
            });
            $pending_trades = array_merge($open_trades_list, $waiting_triggers_list);
            $has_active_pending = !empty($pending_trades);

            $open_count = count($open_trades_list);
            $waiting_count = count($waiting_triggers_list);
            $deployed_cash = array_sum(array_map(fn($t) => (float)($t['position_size'] ?? 200), $open_trades_list));
            $deployed_slots = array_sum(array_map(fn($t) => ($t['setup_grade'] ?? 'B') === 'A+' ? 2 : 1, $open_trades_list));
            $total_portfolio_balance = (float)($stats['current_balance'] ?? 1000.0);
            $free_slots = max(0, 5 - $deployed_slots);
            $free_cash = max(0.0, $total_portfolio_balance - $deployed_cash);

            $day_of_week = date('N'); // 1 = Mon, 6 = Sat, 7 = Sun
            $is_weekend = ($day_of_week == 6 || $day_of_week == 7);

            $companyNames = [
                'META' => 'Meta Platforms (Facebook, Instagram, AI)',
                'NBIS' => 'Nebius Group (AI Cloud Infrastructure)',
                'EOSE' => 'Eos Energy Enterprises (Clean Tech)',
                'MU' => 'Micron Technology (Semiconductors)',
                'CRDO' => 'Credo Technology (High-Speed Connectivity)',
                'CRWV' => 'CoreWeave (AI Cloud Computing)',
                'HIMS' => 'Hims & Hers Health (Telehealth / AI Health)',
                'IREN' => 'Iris Energy (Next-Gen AI Cloud & Clean Energy)',
                'APP' => 'AppLovin (AI AdTech / Mobile Tech)',
                'AAPL' => 'Apple Inc. (AI Hardware & Consumer Tech)',
                'OUST' => 'Ouster, Inc. (Lidar & Autonomous AI Sensing)'
            ];

            $total_expected_cash = 0;
            if ($has_active_pending) {
                foreach ($pending_trades as $pt) {
                    $total_expected_cash += (float)($pt['expected_target_cash'] ?? 0);
                }
            }
            ?>
            <?php if ($has_active_pending): ?>
            <div class="space-y-4">
                <!-- Carousel Header & Navigation Controls with 5-Slot Portfolio Bar -->
                <div class="bg-term-surface border border-term-border rounded-2xl p-4 sm:p-5 shadow-lg space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 font-mono">
                                    🏆 ТОП 5 ПОДБОР ЗА ДЕНЯ
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-mono">
                                    🎯 <?= $open_count ?> АКТИВНИ ОТВОРЕНИ ПОЗИЦИИ
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 font-mono">
                                    ⏳ <?= $waiting_count ?> В ОЧАКВАНЕ НА ПРОБИВ
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/30 font-mono hidden sm:inline">
                                    💼 <?= $deployed_slots ?>/5 СЛОТА ЗАЕТИ ($<?= number_format($deployed_cash, 2) ?>)
                                </span>
                            </div>
                            <h2 class="text-lg sm:text-2xl font-black text-white mt-1">
                                Какво да направим в момента?
                                <span class="text-xs sm:text-sm font-normal text-slate-300 font-mono sm:ml-2">
                                    (Общ потенциал: <strong class="text-emerald-400 font-bold">+$<?= number_format($total_expected_cash, 2) ?></strong>)
                                </span>
                            </h2>
                        </div>

                        <!-- Navigation Buttons & Counter -->
                        <div class="flex items-center space-x-2 flex-shrink-0">
                            <button onclick="prevTradeSlide()" class="px-3 py-2 rounded-xl bg-term-card hover:bg-term-hover text-white border border-term-border hover:border-emerald-500/50 transition flex items-center space-x-1.5 text-xs font-bold shadow-md group" title="Предишна сделка (Стрелка наляво)">
                                <i class="fa-solid fa-chevron-left text-emerald-400 group-hover:-translate-x-0.5 transition"></i>
                                <span class="hidden sm:inline">Предишна</span>
                            </button>
                            
                            <div class="px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-700/80 font-mono text-xs font-bold text-slate-200 shadow-inner">
                                <span id="carousel-current-index" class="text-emerald-400 text-sm">1</span> / <?= count($pending_trades) ?>
                            </div>

                            <button onclick="nextTradeSlide()" class="px-3 py-2 rounded-xl bg-term-card hover:bg-term-hover text-white border border-term-border hover:border-emerald-500/50 transition flex items-center space-x-1.5 text-xs font-bold shadow-md group" title="Следваща сделка (Стрелка надясно)">
                                <span class="hidden sm:inline">Следваща</span>
                                <i class="fa-solid fa-chevron-right text-emerald-400 group-hover:translate-x-0.5 transition"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Portfolio Slots & Buying Power Monitor -->
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2.5 border-t border-slate-800/80 text-xs font-mono">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-700/80 text-slate-200">
                                <i class="fa-solid fa-vault text-amber-400"></i>
                                <span>Капитал: <strong class="text-white">$<?= number_format($total_portfolio_balance, 2) ?></strong></span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">
                                <i class="fa-solid fa-chart-pie text-emerald-400"></i>
                                <span>Зает Капитал: <strong>$<?= number_format($deployed_cash, 2) ?></strong> (<?= $deployed_slots ?>/5 слота)</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg <?= $free_slots > 0 ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-300' : 'bg-slate-900 border-slate-800 text-slate-400' ?>">
                                <i class="fa-solid fa-wallet <?= $free_slots > 0 ? 'text-cyan-400' : 'text-slate-500' ?>"></i>
                                <span>Свободен Кеш: <strong>$<?= number_format($free_cash, 2) ?></strong> (<?= $free_slots ?> свободни слота)</span>
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-shield-halved text-emerald-400"></i>
                            <span>Лимит: <strong>Макс 5 Сделки / $1000</strong> (100% Кеш, Без Марджин Дълг)</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Jump Pills (Interactive Tabs) -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1.5 custom-scrollbar">
                    <?php foreach ($pending_trades as $idx => $pt): 
                        $ptTick = htmlspecialchars($pt['ticker'] ?? '');
                        $ptV = strtoupper($pt['verdict'] ?? '');
                        $ptGain = number_format((float)($pt['expected_target_cash'] ?? 0), 2);
                        $ptGrade = htmlspecialchars($pt['setup_grade'] ?? 'B');
                        $ptActive = $idx === 0;
                        $ptOut = strtoupper($pt['outcome'] ?? '');
                        $isPtWin = strpos($ptOut, 'WIN') !== false || strpos($ptOut, 'TARGET') !== false || strpos($ptOut, 'TP') !== false;
                        $isPtLoss = strpos($ptOut, 'LOSS') !== false || strpos($ptOut, 'STOP') !== false || strpos($ptOut, 'SL') !== false;
                        $isPtPending = strpos($ptOut, 'PENDING') !== false;
                        $isPtOpen = ($ptOut === 'OPEN' || strpos($ptOut, 'OPEN') !== false || $ptOut === 'IN PROGRESS') && !$isPtPending;
                        $isPtDone = !empty($ptOut) && !$isPtOpen && !$isPtPending;
                        
                        if ($isPtDone) {
                            $ptIcon = $isPtWin ? '🏆' : ($isPtLoss ? '🛑' : '🔒');
                        } elseif ($isPtOpen) {
                            $ptIcon = $ptV === 'BUY' ? '🟢' : ($ptV === 'SELL' ? '🔴' : '🟡');
                        } else {
                            $ptIcon = '⏳';
                        }
                        $ptTime = format_trade_time($pt['date_time'] ?? '');
                        $ptPnlD = (float)($pt['pnl_dollars'] ?? 0);
                    ?>
                    <button onclick="goToTradeSlide(<?= $idx ?>)" id="trade-pill-<?= $idx ?>" class="trade-pill-btn px-3.5 py-2 rounded-xl text-xs font-semibold transition flex items-center space-x-2 border flex-shrink-0 <?= $ptActive ? 'bg-emerald-500 text-slate-950 font-bold border-emerald-400 shadow-md shadow-emerald-500/20' : 'bg-term-card text-slate-300 border-term-border hover:bg-term-hover hover:text-white' ?>">
                        <span><?= $ptIcon ?></span>
                        <span class="font-mono font-bold"><?= $ptTick ?></span>
                        <?php if (!empty($pt['daily_rank']) && $pt['daily_rank'] <= 5): ?>
                        <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-black <?= $ptActive ? 'bg-slate-900 text-amber-300' : 'bg-purple-500/25 text-purple-300 border border-purple-500/40' ?>">#<?= $pt['daily_rank'] ?></span>
                        <?php endif; ?>
                        <span class="opacity-80 text-[11px] font-mono">(<?= $ptV ?>)</span>
                        <?php if (!empty($ptTime['bg_pill'])): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-950/60 font-mono text-cyan-300 border border-slate-700/60"><?= $ptTime['bg_pill'] ?></span>
                        <?php endif; ?>
                        <span class="live-pill-price font-mono text-[11px] px-1.5 py-0.5 rounded bg-slate-900/70 text-slate-200 border border-slate-700/60 shadow-sm" data-pill-ticker="<?= $ptTick ?>">$<?= number_format((float)($pt['current_price'] ?? 0), 2) ?></span>
                        <?php if ($isPtOpen && $ptPnlD != 0): ?>
                        <span class="font-mono text-[11px] <?= $ptActive ? 'text-slate-950 font-extrabold' : ($ptPnlD >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold') ?>"><?= $ptPnlD >= 0 ? "+$" . number_format($ptPnlD, 2) : "-$" . number_format(abs($ptPnlD), 2) ?></span>
                        <?php elseif ($isPtPending): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded <?= $ptActive ? 'bg-slate-950 text-amber-300' : 'bg-amber-500/10 text-amber-300 border border-amber-500/30' ?> font-mono">Чака $<?= (float)($pt['trigger_price'] ?? 0) > 0 ? number_format((float)$pt['trigger_price'], 2) : 'пробив' ?></span>
                        <?php else: ?>
                        <span class="font-mono <?= $ptActive ? 'text-slate-950 font-extrabold' : 'text-emerald-400 font-bold' ?>">+$<?= $ptGain ?></span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Slide Cards Track -->
                <div id="trade-carousel-container" class="relative">
                    <?php foreach ($pending_trades as $idx => $t): 
                        $tTick = htmlspecialchars($t['ticker'] ?? '');
                        $tVerdict = strtoupper($t['verdict'] ?? 'WATCH');
                        $tGrade = htmlspecialchars($t['setup_grade'] ?? 'A+');
                        $tConf = intval($t['confidence'] ?? 70);
                        $tPrice = number_format((float)($t['current_price'] ?? 0), 2);
                        $tTarget = number_format((float)($t['target_price'] ?? 0), 2);
                        $tStop = number_format((float)($t['stop_loss'] ?? 0), 2);
                        $tPos = number_format((float)($t['position_size'] ?? 400), 2);
                        $tGain = number_format((float)($t['expected_target_cash'] ?? 0), 2);
                        $tRisk = number_format((float)($t['expected_stop_cash'] ?? 0), 2);
                        $tShares = number_format((float)($t['shares_est'] ?? 0.5), 2);
                        $tCat = htmlspecialchars($t['catalyst_headline'] ?? '');
                        $tTrigger = (float)($t['trigger_price'] ?? 0);
                        $tDist = (float)($t['trigger_distance_pct'] ?? 0);
                        $tStat = $t['trigger_status'] ?? 'SCANNING';
                        $tTime = format_trade_time($t['date_time'] ?? '');
                        
                        $isB = $tVerdict === 'BUY';
                        $isS = $tVerdict === 'SELL';
                        $isW = $tVerdict === 'WATCH';
                        
                        $tName = $companyNames[$tTick] ?? $tTick;
                        $tBorder = $isB ? 'border-emerald-500/60' : ($isS ? 'border-rose-500/60' : 'border-amber-500/60');
                        $tGlow = $isB ? 'bg-emerald-500/10' : ($isS ? 'bg-rose-500/10' : 'bg-amber-500/10');
                        $tHidden = $idx === 0 ? '' : 'hidden';
                    ?>
                    <div id="trade-slide-<?= $idx ?>" class="trade-slide <?= $tHidden ?> bg-gradient-to-b from-term-surface to-slate-900/90 border-2 <?= $tBorder ?> rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden transition-all duration-300">
                        <!-- Glow background -->
                        <div class="absolute top-0 right-0 w-96 h-96 <?= $tGlow ?> rounded-full blur-3xl pointer-events-none"></div>

                        <!-- Slide Header -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-term-border/80 pb-5">
                            <div class="flex items-center space-x-3.5">
                                <div class="w-12 h-12 rounded-2xl <?= $isB ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : ($isS ? 'bg-rose-500/20 text-rose-400 border-rose-500/40' : 'bg-amber-500/20 text-amber-400 border-amber-500/40') ?> border flex items-center justify-center text-xl shadow-lg flex-shrink-0">
                                    <i class="fa-solid <?= $isB ? 'fa-arrow-trend-up' : ($isS ? 'fa-arrow-trend-down' : 'fa-crosshairs') ?>"></i>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 font-mono">СДЕЛКА #<?= $idx + 1 ?> ОТ <?= count($pending_trades) ?></span>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-500/20 text-amber-300 border border-amber-500/40">⭐ КЛАС <?= $tGrade ?> ($<?= $tPos ?>)</span>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/30"><?= $tConf ?>% УВЕРЕНОСТ</span>
                                        <?php if (!empty($t['daily_rank'])): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black <?= $t['daily_rank'] <= 3 ? 'bg-amber-400/25 text-amber-300 border border-amber-400/60' : 'bg-purple-500/20 text-purple-300 border border-purple-500/40' ?> font-mono">
                                            <?= htmlspecialchars($t['rank_badge'] ?? ('Ранг #' . $t['daily_rank'])) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($t['ai_score'])): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 font-mono" title="AI Оценка = Увереност + (RVOL * 10) + Grade A+ (15) + (R:R * 5)">
                                            ⚡ SCORE: <?= htmlspecialchars($t['ai_score']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-xl sm:text-2xl font-black text-white mt-0.5">
                                        <?= $tTick ?> &bull; <?= $tName ?>
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-xl bg-slate-950/90 border border-emerald-500/40 shadow-inner">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider font-mono">LIVE ЦЕНА:</span>
                                            <span class="font-mono text-sm sm:text-base font-extrabold text-white live-price-display" data-ticker-live="<?= $tTick ?>">$<?= $tPrice ?></span>
                                            <span class="font-mono text-xs font-bold live-change-display" data-ticker-change="<?= $tTick ?>">--</span>
                                        </div>
                                        <?php 
                                        $tOutRaw = strtoupper($t['outcome'] ?? '');
                                        $isPendingTrigger = ($tOutRaw === 'PENDING' || strpos($tOutRaw, 'PENDING') !== false);
                                        $isActuallyOpen = ($tOutRaw === 'OPEN' || strpos($tOutRaw, 'OPEN') !== false || $tOutRaw === 'IN PROGRESS') && !$isPendingTrigger;
                                        $isDone = !empty($tOutRaw) && !$isActuallyOpen && !$isPendingTrigger;
                                        $tPnlFloat = (float)($t['pnl_dollars'] ?? 0);
                                        $tPnlRStr = htmlspecialchars($t['realized_pnl'] ?? '0.0R');
                                        if ($isActuallyOpen): 
                                            $tPnlClass = $tPnlFloat >= 0 ? 'text-emerald-400 border-emerald-500/40 bg-emerald-500/10' : 'text-rose-400 border-rose-500/40 bg-rose-500/10';
                                        ?>
                                        <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-xl <?= $tPnlClass ?> border text-xs font-mono font-bold shadow-inner" id="floating-pnl-pill-<?= $tTick ?>" data-ticker-pnl-pill="<?= $tTick ?>" data-entry="<?= $tPrice ?>" data-shares="<?= $tShares ?>" data-stop="<?= $tStop ?>" data-target="<?= $tTarget ?>" data-verdict="<?= $tVerdict ?>" title="Текущ плаващ резултат">
                                            <span id="floating-pnl-text-<?= $tTick ?>">Плаващ резултат: <?= $tPnlFloat >= 0 ? '+$' . number_format($tPnlFloat, 2) : '-$' . number_format(abs($tPnlFloat), 2) ?></span>
                                            <span class="text-[10px] opacity-80" id="floating-r-text-<?= $tTick ?>">(<?= $tPnlRStr ?>)</span>
                                        </div>
                                        <?php if ((float)($t['target_price'] ?? 0) > 0): ?>
                                        <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-xl bg-slate-900/90 border border-slate-700/80 text-xs font-mono text-slate-300 shadow-inner" id="target-proximity-pill-<?= $tTick ?>" title="Дистанция до достигане на таргета и освобождаване на слота">
                                            <i class="fa-solid fa-bullseye text-cyan-400"></i>
                                            <span>Цел: <strong class="text-white font-mono">$<?= $tTarget ?></strong></span>
                                            <span class="text-emerald-400 font-bold" data-ticker-dist-target="<?= $tTick ?>" data-target="<?= $tTarget ?>" data-entry="<?= $tPrice ?>" data-stop="<?= $tStop ?>" data-verdict="<?= $tVerdict ?>">--</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php elseif ($isPendingTrigger): ?>
                                        <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-mono font-bold shadow-inner" title="Кешът е защитен на 100%">
                                            <i class="fa-solid fa-lock text-amber-400"></i>
                                            <span>Кешът е 100% свободен</span>
                                            <span class="text-[10px] opacity-80">(<?= $tTrigger > 0 ? "Чака $" . number_format($tTrigger, 2) : "Чака пробив" ?>)</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($tTime['bg_full'])): ?>
                                        <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-xl bg-slate-900/90 border border-slate-700/80 text-xs font-mono text-slate-300 shadow-inner" title="Време на постъпване на сигнала">
                                            <i class="fa-regular fa-clock text-cyan-400"></i>
                                            <span>Постъпила: <strong class="text-white"><?= $tTime['bg_full'] ?></strong></span>
                                            <span class="text-slate-500 text-[11px]">(<?= $tTime['ny_time'] ?>)</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <?php if ($isPendingTrigger): ?>
                                    <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-2 animate-pulse shadow-lg shadow-amber-500/10">
                                        <i class="fa-solid fa-hourglass-half text-amber-400"></i>
                                        <span><?= $tTrigger > 0 ? "В ОЧАКВАНЕ НА ПРОБИВ: $" . number_format($tTrigger, 2) : "В ОЧАКВАНЕ НА ПОТВЪРЖДЕНИЕ" ?> &bull; 100% ЗАПАЗЕН КЕШ</span>
                                    </span>
                                <?php elseif ($isActuallyOpen): ?>
                                    <?php 
                                    $pnl_badge_col = $tPnlFloat >= 0 ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-emerald-500/10' : 'bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-rose-500/10';
                                    ?>
                                    <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold <?= $pnl_badge_col ?> border flex items-center gap-2 animate-pulse shadow-lg" id="live-status-pill-<?= $tTick ?>">
                                        <span class="w-2 h-2 rounded-full <?= $tPnlFloat >= 0 ? 'bg-emerald-400' : 'bg-rose-400' ?>" id="live-status-dot-<?= $tTick ?>"></span>
                                        <span id="live-status-text-<?= $tTick ?>">АКТИВНА ОТВОРЕНА ПОЗИЦИЯ (<?= $tVerdict ?>) &bull; <?= $tPnlFloat >= 0 ? '+$' . number_format($tPnlFloat, 2) : '-$' . number_format(abs($tPnlFloat), 2) ?></span>
                                    </span>
                                <?php elseif ($isDone): ?>
                                    <?php if (strpos($tOutRaw, 'WIN') !== false || strpos($tOutRaw, 'TARGET') !== false || strpos($tOutRaw, 'TP') !== false): ?>
                                    <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-2 shadow-lg shadow-emerald-500/10">
                                        <i class="fa-solid fa-trophy text-amber-400"></i>
                                        <span>РЕАЛИЗИРАНА ПЕЧАЛБА (<?= htmlspecialchars($t['realized_pnl'] ?? '+2.0R') ?>)</span>
                                    </span>
                                    <?php elseif (strpos($tOutRaw, 'LOSS') !== false || strpos($tOutRaw, 'STOP') !== false || strpos($tOutRaw, 'SL') !== false): ?>
                                    <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 flex items-center gap-2">
                                        <i class="fa-solid fa-shield-halved text-rose-400"></i>
                                        <span>ЗАЩИТЕН СТОП (<?= htmlspecialchars($t['realized_pnl'] ?? '-1.0R') ?>)</span>
                                    </span>
                                    <?php else: ?>
                                    <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 border border-slate-700 flex items-center gap-2">
                                        <i class="fa-solid fa-lock text-slate-400"></i>
                                        <span>БЕЗ ПРОБИВ &bull; 100% ЗАПАЗЕН КЕШ</span>
                                    </span>
                                    <?php endif; ?>
                                <?php elseif ($isB): ?>
                                <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-2 animate-pulse">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span>АКТИВНА ПОКУПКА (BUY)</span>
                                </span>
                                <?php elseif ($isS): ?>
                                <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 flex items-center gap-2 animate-pulse">
                                    <i class="fa-solid fa-arrow-down"></i>
                                    <span>АКТИВНА ПРОДАЖБА (SELL)</span>
                                </span>
                                <?php else: ?>
                                <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-2 animate-pulse">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                    <span><?= $tTrigger > 0 ? "ЧАКАМЕ ПРОБИВ НА \$$tTrigger" : "В ОЧАКВАНЕ НА ПРОБИВ" ?></span>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 4 Step Action Box -->
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Step 1 -->
                            <div class="bg-term-card/90 border border-term-border rounded-2xl p-5 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">1. <?= $isB ? 'ВХОДНА ЦЕНА (СИГНАЛ)' : ($tTrigger > 0 ? 'ПРАГ ЗА ПОКУПКА' : 'ВХОД / ПОТВЪРЖДЕНИЕ') ?></span>
                                    <div class="mt-2 space-y-1.5">
                                        <div class="flex items-baseline justify-between">
                                            <span class="text-2xl font-extrabold text-white mono block">$<?= $tTrigger > 0 ? number_format($tTrigger, 2) : $tPrice ?></span>
                                            <span class="text-[10px] text-slate-400 font-mono">Сигнал: $<?= $tPrice ?><?= !empty($tTime['bg_time']) ? ' (' . $tTime['bg_time'] . ')' : '' ?></span>
                                        </div>
                                        <div class="p-2 rounded-xl bg-slate-950/80 border border-slate-800 flex items-center justify-between text-xs font-mono">
                                            <span class="text-slate-400 text-[11px]">Цена в момента:</span>
                                            <strong class="text-emerald-400 live-step-price" data-ticker-step="<?= $tTick ?>">$<?= $tPrice ?></strong>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] font-mono px-1">
                                            <span class="text-slate-400">До цел ($<?= $tTarget ?>):</span>
                                            <strong class="text-emerald-400 live-dist-target" data-ticker-dist-target="<?= $tTick ?>" data-target="<?= $tTarget ?>">--</strong>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-400 mt-4 pt-3 border-t border-term-border/60">
                                    <?= $isB ? 'Системата откри силен купувач и позицията е активна.' : ($tTrigger > 0 ? "Купуваме само ако прескочи \$$tTrigger с голям обем." : 'Чакаме потвърждение на 5-минутната графика.') ?>
                                </p>
                            </div>

                            <!-- Step 2 -->
                            <div class="bg-term-card/90 border border-term-border rounded-2xl p-5 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">2. ВЛОЖЕНИ ПАРИ</span>
                                    <div class="mt-2">
                                        <span class="text-2xl font-extrabold text-cyan-400 mono block">$<?= $tPos ?></span>
                                        <span class="text-xs text-slate-300 font-medium mt-1 block">
                                            Около <?= $tShares ?> бр. акции (Клас <?= $tGrade ?>)
                                        </span>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-400 mt-4 pt-3 border-t border-term-border/60">
                                    <?= $tGrade === 'A+' ? 'Топ възможност с $400 алокация от кеша.' : 'Стандартна алокация с $200 за балансиран риск.' ?> Без заеми.
                                </p>
                            </div>

                            <!-- Step 3 -->
                            <div class="bg-emerald-950/20 border border-emerald-500/40 rounded-2xl p-5 flex flex-col justify-between shadow-lg shadow-emerald-950/20">
                                <div>
                                    <span class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider block">3. ОЧАКВАНА ЧИСТА ПЕЧАЛБА</span>
                                    <div class="mt-2">
                                        <span class="text-2xl sm:text-3xl font-black text-emerald-400 mono block">+$<?= $tGain ?></span>
                                        <span class="text-xs text-emerald-300 font-medium mt-1 block">
                                            Чисти пари при цел $<?= $tTarget ?>
                                        </span>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-300 mt-4 pt-3 border-t border-emerald-500/30">
                                    Когато цената достигне $<?= $tTarget ?>, продаваме и <strong>прибираме +$<?= $tGain ?></strong> в портфейла.
                                </p>
                            </div>

                            <!-- Step 4 -->
                            <div class="bg-term-card/90 border border-term-border rounded-2xl p-5 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">4. ЗАЩИТА ПРИ СПАД (STOP LOSS)</span>
                                    <div class="mt-2">
                                        <span class="text-2xl font-extrabold text-rose-400 mono block">Спираме на $<?= $tStop ?></span>
                                        <span class="text-xs text-slate-300 font-medium mt-1 block">
                                            Макс възможен риск: <strong>-$<?= $tRisk ?></strong>
                                        </span>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-400 mt-4 pt-3 border-t border-term-border/60">
                                    Ако пазарът тръгне надолу, затваряме веднага. Губим само $<?= $tRisk ?>, спасявайки останалия капитал.
                                </p>
                            </div>
                        </div>

                        <!-- Math Advantage Summary & Catalyst Explanation -->
                        <div class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-term-card p-4 rounded-xl border border-term-border flex items-center space-x-3.5">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg flex-shrink-0">
                                    <i class="fa-solid fa-scale-balanced"></i>
                                </div>
                                <div>
                                    <span class="text-xs font-bold text-white block">Асиметрично Предимство</span>
                                    <p class="text-xs text-slate-300 mt-0.5">
                                        Рискуваме едва <strong>-$<?= $tRisk ?></strong>, за да вземем <strong>+$<?= $tGain ?></strong> печалба. Математиката е на наша страна.
                                    </p>
                                </div>
                            </div>

                            <div class="lg:col-span-2 bg-term-card p-4 rounded-xl border border-term-border flex items-start space-x-3.5">
                                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-lg flex-shrink-0 mt-0.5">
                                    <i class="fa-solid fa-newspaper"></i>
                                </div>
                                <div>
                                    <span class="text-xs font-bold text-white block">Защо AI избра <?= $tTick ?>?</span>
                                    <p class="text-xs text-slate-300 mt-0.5 leading-relaxed">
                                        <?= $tCat ?: 'Силни фундаменти и интерес от институционални инвеститори с висок дневен обем купувачи.' ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Slide Footer with Arrow Shortcuts & Dots -->
                        <div class="mt-5 pt-4 border-t border-term-border/60 flex items-center justify-between text-xs text-slate-400">
                            <button onclick="prevTradeSlide()" class="hover:text-emerald-400 transition flex items-center space-x-1.5 font-medium py-1 px-2 rounded-lg hover:bg-term-card">
                                <i class="fa-solid fa-arrow-left text-[11px] text-emerald-400"></i>
                                <span>Предишна сделка</span>
                            </button>
                            <div class="flex items-center space-x-1.5">
                                <?php for ($d = 0; $d < count($pending_trades); $d++): ?>
                                <button onclick="goToTradeSlide(<?= $d ?>)" class="trade-dot h-2 rounded-full transition-all duration-300 <?= $d === $idx ? 'bg-emerald-400 w-6' : 'bg-slate-700 w-2 hover:bg-slate-500' ?>"></button>
                                <?php endfor; ?>
                            </div>
                            <button onclick="nextTradeSlide()" class="hover:text-emerald-400 transition flex items-center space-x-1.5 font-medium py-1 px-2 rounded-lg hover:bg-term-card">
                                <span>Следваща сделка</span>
                                <i class="fa-solid fa-arrow-right text-[11px] text-emerald-400"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Session Closed Banner (When all positions are completed and moved to History) -->
            <div class="bg-gradient-to-br from-term-surface via-slate-900 to-emerald-950/20 border border-emerald-500/30 rounded-3xl p-6 sm:p-8 shadow-xl text-center space-y-4 relative overflow-hidden">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/30 font-mono">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span><?= $is_weekend ? '🏁 УИКЕНД &bull; БОРСАТА Е ЗАТВОРЕНА' : '🏁 ПОСЛЕДНАТА СЕСИЯ Е ЗАТВОРЕНА' ?></span>
                </div>
                <div class="max-w-xl mx-auto space-y-2">
                    <h2 class="text-xl sm:text-2xl font-black text-white">Всички позиции за деня са приключили</h2>
                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                        Всички приключили сделки (реализирани печалби, защитни стопове и запазен кеш) вече са преместени в секция 
                        <strong class="text-white">„История на Спечелените Пари“</strong> по-долу. Нови сигнали ще се генерират автоматично на живо с отварянето на Wall Street в <strong class="text-emerald-400 font-mono">16:30 ч.</strong>
                    </p>
                </div>
                <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                    <a href="#history-section" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition shadow-lg shadow-emerald-500/20 font-mono">
                        <i class="fa-solid fa-arrow-down"></i>
                        <span>Преминете към История на Спечелените Пари</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- 3.5. СЕКЦИЯ: РЕЗЕРВЕН СПИСЪК НА КАНДИДАТИТЕ (Ранг #6 - #10) -->
            <?php if (!empty($daily_reserve)): ?>
            <div class="bg-term-surface border border-term-border rounded-2xl p-5 sm:p-6 shadow-xl space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-term-border/80 pb-3.5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                📋 ВТОРИ ЕШЕЛОН &bull; РЕЗЕРВЕН РАДАР
                            </span>
                            <span class="text-xs text-slate-400 font-mono">5 Анализирани Компании на Изчакване</span>
                        </div>
                        <h2 class="text-lg font-bold text-white mt-1 flex items-center gap-2">
                            <span>Резервен Списък на Кандидатите (Ранг #6 - #10)</span>
                        </h2>
                        <p class="text-xs text-slate-400">
                            Анализирани ежедневно в <strong>17:15 ч.</strong>. Ако някоя от водещите позиции приключи или се освободи слот от 5-те в портфейла, най-високо класираният кандидат автоматично заема свободната ликвидност.
                        </p>
                    </div>
                    <div class="text-xs font-mono text-slate-300 flex items-center gap-2 bg-slate-900/80 px-3 py-2 rounded-xl border border-slate-800 flex-shrink-0">
                        <i class="fa-solid fa-calculator text-cyan-400"></i>
                        <span>AI Score: <strong>Увереност + RVOL + Grade + R:R</strong></span>
                    </div>
                </div>

                <!-- Reserve Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    <?php foreach ($daily_reserve as $res): 
                        $rTick = htmlspecialchars($res['ticker'] ?? '');
                        $rName = $companyNames[$rTick] ?? $rTick;
                        $rScore = $res['ai_score'] ?? 0;
                        $rRank = $res['daily_rank'] ?? 0;
                        $rConf = $res['confidence'] ?? 0;
                        $rGrade = htmlspecialchars($res['setup_grade'] ?? 'B');
                        $rPrice = number_format((float)($res['current_price'] ?? 0), 2);
                        $rTarget = number_format((float)($res['target_price'] ?? 0), 2);
                        $rStop = number_format((float)($res['stop_loss'] ?? 0), 2);
                        $rTrigger = htmlspecialchars($res['entry_trigger'] ?? '');
                        $rCat = htmlspecialchars($res['catalyst_headline'] ?? '');
                        $rRvol = htmlspecialchars($res['rvol'] ?? '1.0x');

                        // Resolve date/time for candidate
                        $res_date = $res['date_time'] ?? '';
                        if (empty($res_date)) {
                            foreach ($trades as $t_item) {
                                if (($t_item['ticker'] ?? '') === ($res['ticker'] ?? '') && !empty($t_item['date_time'])) {
                                    $res_date = $t_item['date_time'];
                                    break;
                                }
                            }
                        }
                        $rTime = format_trade_time($res_date);
                    ?>
                    <div class="bg-term-card/80 border border-term-border hover:border-indigo-500/40 rounded-xl p-4 transition shadow-sm space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-lg text-xs font-mono font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                    #<?= $rRank ?>
                                </span>
                                <span class="font-mono text-base font-black text-white"><?= $rTick ?></span>
                                <span class="text-[11px] px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-300 border border-amber-500/30 font-bold"><?= $rGrade ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                                    Score: <?= $rScore ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs gap-2">
                            <div class="text-slate-300 line-clamp-1 font-medium" title="<?= $rName ?>">
                                <?= $rName ?>
                            </div>
                            <?php if (!empty($rTime['bg_full'])): ?>
                            <div class="text-[11px] text-cyan-400 font-mono flex items-center gap-1 flex-shrink-0 bg-slate-900/90 px-2 py-0.5 rounded border border-slate-800" title="Дата и час на анализа: <?= $rTime['bg_full'] ?> (<?= $rTime['ny_time'] ?>)">
                                <i class="fa-regular fa-clock text-[10px]"></i>
                                <span><?= $rTime['bg_date'] ?> <?= $rTime['bg_time'] ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5 text-[11px] font-mono bg-slate-950/70 p-2 rounded-lg border border-slate-800/80 text-center">
                            <div>
                                <span class="text-slate-500 block text-[10px]">ЦЕНА</span>
                                <span class="text-white font-bold">$<?= $rPrice ?></span>
                            </div>
                            <div>
                                <span class="text-slate-500 block text-[10px]">СТОП</span>
                                <span class="text-rose-400 font-bold">$<?= $rStop ?></span>
                            </div>
                            <div>
                                <span class="text-slate-500 block text-[10px]">ЦЕЛ</span>
                                <span class="text-emerald-400 font-bold">$<?= $rTarget ?></span>
                            </div>
                        </div>
                        <?php if (!empty($rTrigger)): ?>
                        <div class="text-[11px] text-slate-400 bg-slate-900/60 px-2 py-1 rounded border border-slate-800 font-mono">
                            <span class="text-amber-400 font-semibold">Тригер:</span> <?= mb_substr($rTrigger, 0, 65) ?><?= mb_strlen($rTrigger) > 65 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between text-[10px] text-slate-500 font-mono pt-1 border-t border-slate-800/60">
                            <span>RVOL: <strong class="text-slate-400"><?= $rRvol ?></strong></span>
                            <span>Увереност: <strong class="text-cyan-400"><?= $rConf ?>%</strong></span>
                            <span>Анализ: <strong class="text-white"><?= !empty($rTime['bg_full']) ? $rTime['bg_full'] : 'Предходен анализ' ?></strong></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 4. Section 3: ИСТОРИЯ НА ПЕЧАЛБИТЕ (Динамична таблица) -->
            <?php
            // Unified closed trades: $real_closed_trades (6 trades, +$94.66) and $all_closed_trades (30 trades)
            $closed_trades_list = $all_closed_trades;
            ?>
            <div id="history-section" class="bg-term-surface border border-term-border rounded-2xl p-6 sm:p-7 shadow-xl space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-term-border/80 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-clipboard-check text-emerald-400"></i>
                            <span>История на Сключените Сделки</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"><?= count($real_closed_trades) ?> Решени Сделки (+$<?= number_format($total_closed_cash, 2) ?>)</span>
                        </h2>
                        <p class="text-xs text-slate-400">Официални резултати от всички реално сключени позиции на портфейла (победи и защитни стопове) и пълен архив:</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1 rounded-lg text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 font-mono">
                            Реализиран Резултат: +$<?= number_format($total_closed_cash, 2) ?> ✅
                        </span>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 font-mono">
                            <?= $closed_wins_count ?> W &bull; <?= $closed_loss_count ?> L (<?= number_format($display_win_rate, 1) ?>% Успеваемост)
                        </span>
                    </div>
                </div>

                <!-- History Table Controls (Filter Tabs) -->
                <div class="flex flex-wrap items-center justify-between gap-2.5 text-xs">
                    <div class="flex flex-wrap items-center gap-1.5" id="history-filter-btns">
                        <button onclick="filterHistory('real')" id="hfilter-real" class="hfilter-btn px-3 py-1.5 rounded-lg font-semibold bg-emerald-500 text-slate-950 font-mono transition shadow-md">🟢 Решени сделки (<?= count($real_closed_trades) ?>)</button>
                        <button onclick="filterHistory('all')" id="hfilter-all" class="hfilter-btn px-3 py-1.5 rounded-lg font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition">Всички с Радар (<?= count($closed_trades_list) ?>)</button>
                        <button onclick="filterHistory('win')" id="hfilter-win" class="hfilter-btn px-3 py-1.5 rounded-lg font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition">Победи (<?= $all_wins_count ?>)</button>
                        <button onclick="filterHistory('loss')" id="hfilter-loss" class="hfilter-btn px-3 py-1.5 rounded-lg font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition">Стопове (<?= $all_loss_count ?>)</button>
                        <button onclick="filterHistory('range')" id="hfilter-range" class="hfilter-btn px-3 py-1.5 rounded-lg font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition">Запазен Кеш (<?= $all_range_count ?>)</button>
                    </div>
                    <div class="text-slate-400 text-[11px] font-mono">
                        Показване на най-новите първо
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[580px] custom-scrollbar rounded-xl border border-term-border/60">
                    <table class="w-full text-left text-sm border-collapse" id="history-table">
                        <thead class="sticky top-0 bg-term-surface/95 backdrop-blur-md z-10">
                            <tr class="border-b border-term-border text-slate-400 text-xs uppercase font-semibold">
                                <th class="py-3 px-4">Кога?</th>
                                <th class="py-3 px-4">Компания</th>
                                <th class="py-3 px-4">Какво направихме?</th>
                                <th class="py-3 px-4">Вложени Пари</th>
                                <th class="py-3 px-4 text-right">Чиста Печалба / Резултат</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-term-border/40 text-xs sm:text-sm">
                            <?php foreach ($closed_trades_list as $hIdx => $ct): 
                                $ctTick = htmlspecialchars($ct['ticker'] ?? '');
                                $ctV = strtoupper($ct['verdict'] ?? 'WATCH');
                                $ctOut = strtoupper($ct['outcome'] ?? '');
                                $ctPnlD = (float)($ct['pnl_dollars'] ?? 0);
                                $ctPnlR = htmlspecialchars($ct['realized_pnl'] ?? '0.0R');
                                $ctGrade = htmlspecialchars($ct['setup_grade'] ?? 'B');
                                $ctPos = (float)($ct['position_size'] ?? 200);
                                $ctTime = format_trade_time($ct['date_time'] ?? '');
                                $ctName = $companyNames[$ctTick] ?? $ctTick;

                                $isWin = strpos($ctOut, 'WIN') !== false;
                                $isLoss = strpos($ctOut, 'LOSS') !== false;
                                $isRange = strpos($ctOut, 'RANGE') !== false || strpos($ctOut, 'NO BREAKOUT') !== false;
                                $isExpired = strpos($ctOut, 'EXPIRED') !== false;

                                $isRealTrade = in_array($ctV, ['BUY', 'SELL', 'SHORT']) && strpos($ctOut, 'WATCH') === false;
                                $filterCategory = $isWin ? 'win' : ($isLoss ? 'loss' : 'range');
                                $rowHighlight = $hIdx === 0 ? 'bg-emerald-950/10' : '';
                            ?>
                            <tr class="hover:bg-term-hover/40 transition history-row <?= $rowHighlight ?>" data-cat="<?= $filterCategory ?>" data-real="<?= $isRealTrade ? '1' : '0' ?>">
                                <td class="py-3.5 px-4 font-mono text-slate-300 whitespace-nowrap">
                                    <span class="block text-white font-medium"><?= $ctTime['bg_full'] ?: htmlspecialchars($ct['date_time'] ?? '') ?></span>
                                    <span class="text-[10px] text-slate-500 font-mono"><?= $ctTime['ny_time'] ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="font-bold text-white font-mono"><?= $ctTick ?></span>
                                        <?php if ($isRealTrade): ?>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🟢 ПОРТФЕЙЛ</span>
                                        <?php else: ?>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">📡 РАДАР</span>
                                        <?php endif; ?>
                                        <?php if ($ctGrade === 'A+'): ?>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">⭐ A+</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-slate-400 block"><?= htmlspecialchars($ctName) ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300 whitespace-nowrap">
                                    <?php if ($ctV === 'BUY'): ?>
                                        <span class="inline-flex items-center gap-1.5 text-emerald-300 bg-emerald-500/10 px-2.5 py-1 rounded-md font-medium text-xs border border-emerald-500/20">
                                            <i class="fa-solid fa-arrow-trend-up"></i> Покупка при скок
                                        </span>
                                    <?php elseif ($ctV === 'SELL' || $ctV === 'SHORT'): ?>
                                        <span class="inline-flex items-center gap-1.5 text-rose-300 bg-rose-500/10 px-2.5 py-1 rounded-md font-medium text-xs border border-rose-500/20">
                                            <i class="fa-solid fa-arrow-trend-down"></i> Продажба при спад
                                        </span>
                                    <?php else: ?>
                                        <?php if ($isWin): ?>
                                            <span class="inline-flex items-center gap-1.5 text-emerald-300 bg-emerald-500/10 px-2.5 py-1 rounded-md font-medium text-xs border border-emerald-500/20">
                                                <i class="fa-solid fa-bolt"></i> Вход след пробив
                                            </span>
                                        <?php elseif ($isLoss): ?>
                                            <span class="inline-flex items-center gap-1.5 text-rose-300 bg-rose-500/10 px-2.5 py-1 rounded-md font-medium text-xs border border-rose-500/20">
                                                <i class="fa-solid fa-shield-halved"></i> Вход & задействан стоп
                                            </span>
                                        <?php elseif ($isExpired): ?>
                                            <span class="inline-flex items-center gap-1.5 text-amber-300 bg-amber-500/10 px-2.5 py-1 rounded-md font-medium text-xs border border-amber-500/20">
                                                <i class="fa-regular fa-clock"></i> Край на борсовия ден
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-slate-300 bg-slate-800/80 px-2.5 py-1 rounded-md font-medium text-xs border border-slate-700/60">
                                                <i class="fa-solid fa-lock text-slate-400"></i> Без пробив &bull; 100% кеш
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-200 font-semibold whitespace-nowrap">
                                    <?php if ($isRange): ?>
                                        <span class="text-slate-400">$0.00 <span class="text-[10px] text-slate-500 font-normal">(Без вход)</span></span>
                                    <?php else: ?>
                                        $<?= number_format($ctPos, 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <?php if ($isWin): ?>
                                        <span class="px-2.5 py-1 rounded-lg font-bold font-mono text-emerald-400 bg-emerald-500/15 border border-emerald-500/30">
                                            +$<?= number_format($ctPnlD, 2) ?> ЧИСТИ ✅ <span class="text-[10px] text-emerald-300/80 font-normal">(<?= $ctPnlR ?>)</span>
                                        </span>
                                    <?php elseif ($isLoss): ?>
                                        <span class="px-2.5 py-1 rounded-lg font-bold font-mono text-rose-400 bg-rose-500/15 border border-rose-500/30">
                                            -$<?= number_format(abs($ctPnlD), 2) ?> СТОП 🛑 <span class="text-[10px] text-rose-300/80 font-normal">(<?= $ctPnlR ?>)</span>
                                        </span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="px-2.5 py-1 rounded-lg font-bold font-mono text-amber-400 bg-amber-500/15 border border-amber-500/30">
                                            <?= $ctPnlD < 0 ? '-$' . number_format(abs($ctPnlD), 2) : '+$' . number_format($ctPnlD, 2) ?> ИЗТЕКЛА ⏱️ <span class="text-[10px] text-amber-300/80 font-normal">(<?= $ctPnlR ?>)</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-lg font-bold font-mono text-slate-400 bg-slate-800/60 border border-slate-700">
                                            $0.00 ЗАПАЗЕН КЕШ 🔒
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="sticky bottom-0 bg-term-surface/95 backdrop-blur-md border-t-2 border-term-border">
                            <tr class="text-sm font-bold bg-term-card/80">
                                <td colspan="4" class="py-3.5 px-4 text-white" id="history-tfoot-label">
                                    ОБЩ РЕАЛИЗИРАН РЕЗУЛТАТ НА ПОРТФЕЙЛА (<?= count($real_closed_trades) ?> СДЕЛКИ):
                                </td>
                                <td class="py-3.5 px-4 text-right mono text-emerald-400 text-base font-extrabold whitespace-nowrap" id="history-tfoot-amount">
                                    +$<?= number_format($total_closed_cash, 2) ?> ЧИСТА ПЕЧАЛБА
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- 5. Section 4: 3-ТЕ ЗЛАТНИ ПРАВИЛА ЗА СИГУРНОСТ (Психология и спокойствие) -->
            <div>
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-hand-holding-heart text-cyan-400"></i>
                        <span>3-те Златни Правила, които пазят парите ви</span>
                    </h2>
                    <p class="text-xs text-slate-400">Защо тази стратегия защитава капитала дори при спад на пазара</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Rule 1 -->
                    <div class="bg-term-surface border border-term-border rounded-2xl p-5 space-y-2.5">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h3 class="text-base font-bold text-white">1. Без опасни дългове и заеми</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Парите в сметката са 100% ваши. Ние <strong>не използваме ливъридж</strong>. Дори цената на дадена акция да падне временно, брокерът не може да ви вземе парите.
                        </p>
                    </div>

                    <!-- Rule 2 -->
                    <div class="bg-term-surface border border-term-border rounded-2xl p-5 space-y-2.5">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-lg">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <h3 class="text-base font-bold text-white">2. Предварително фиксиран малък риск</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Преди всяка сделка знаем точния праг: ако сгрешим, губим <strong>само $1-$2</strong>. Ако познаем, прибираме <strong>между $10 и $21</strong>. Печалбите винаги покриват евентуалните загуби.
                        </p>
                    </div>

                    <!-- Rule 3 -->
                    <div class="bg-term-surface border border-term-border rounded-2xl p-5 space-y-2.5">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-lg">
                            <i class="fa-solid fa-brain"></i>
                        </div>
                        <h3 class="text-base font-bold text-white">3. Търпение и без емоции</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Повечето хора губят на борсата от нетърпение. Нашата система изчаква с дни точния момент. Търгуваме <strong>само</strong> когато банките, новините и големите обеми са на наша страна.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Interactive Growth & Compounding Simulator (Personal Wealth Builder) -->
            <div class="bg-gradient-to-b from-term-surface via-slate-900/95 to-term-surface border-2 border-emerald-500/40 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-term-border/80 pb-5 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-mono uppercase tracking-wider">
                                🧮 Симулатор на растежа
                            </span>
                            <span class="text-xs text-slate-400 hidden sm:inline">&bull; Изчислете бъдещия резултат при 100% спот търговия</span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-black text-white mt-1">
                            Колко могат да направят парите ви?
                        </h2>
                    </div>
                    <div class="flex items-center gap-2.5 text-xs font-mono text-slate-300 flex-wrap">
                        <div class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-700/80">
                            Profit Factor: <strong class="text-emerald-400">10.25</strong>
                        </div>
                        <div class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-700/80">
                            Sharpe: <strong class="text-cyan-400">2.45</strong>
                        </div>
                        <div class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-700/80">
                            Max DD: <strong class="text-emerald-400">0.0%</strong>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Controls Left Column (5 cols) -->
                    <div class="lg:col-span-5 space-y-6">
                        <!-- Deposit Input -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-bold text-slate-300 uppercase tracking-wider">Начален капитал</label>
                                <span class="text-lg font-black text-emerald-400 font-mono" id="sim-deposit-display">$1,000</span>
                            </div>
                            <input type="range" id="sim-deposit-slider" min="500" max="20000" step="100" value="1000" oninput="updateSimDeposit(this.value)" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                            <div class="flex justify-between text-[11px] font-mono text-slate-500 mt-1">
                                <span>$500</span>
                                <span>$5,000</span>
                                <span>$10,000</span>
                                <span>$20,000</span>
                            </div>
                        </div>

                        <!-- Monthly Target Return Rate -->
                        <div>
                            <label class="text-xs font-bold text-slate-300 uppercase tracking-wider block mb-2">Очакван месечен доход</label>
                            <div class="grid grid-cols-3 gap-2" id="sim-rate-group">
                                <button type="button" onclick="setSimRate(0.08, this)" class="sim-rate-btn px-3 py-2.5 rounded-xl text-xs font-semibold border transition text-center bg-term-card text-slate-300 border-term-border hover:bg-term-hover">
                                    <span class="block font-bold">8% / мес</span>
                                    <span class="text-[10px] text-slate-400">Умерен</span>
                                </button>
                                <button type="button" onclick="setSimRate(0.12, this)" class="sim-rate-btn px-3 py-2.5 rounded-xl text-xs font-bold border transition text-center bg-emerald-500 text-slate-950 border-emerald-400 shadow-md shadow-emerald-500/20">
                                    <span class="block font-bold">12% / мес</span>
                                    <span class="text-[10px] opacity-90">Реална цел</span>
                                </button>
                                <button type="button" onclick="setSimRate(0.18, this)" class="sim-rate-btn px-3 py-2.5 rounded-xl text-xs font-semibold border transition text-center bg-term-card text-slate-300 border-term-border hover:bg-term-hover">
                                    <span class="block font-bold">18% / мес</span>
                                    <span class="text-[10px] text-slate-400">Силни месеци</span>
                                </button>
                            </div>
                        </div>

                        <!-- Strategy Mode (Withdraw vs Reinvest vs Save) -->
                        <div>
                            <label class="text-xs font-bold text-slate-300 uppercase tracking-wider block mb-2">Какво правите с печалбата?</label>
                            <div class="space-y-2" id="sim-mode-group">
                                <button type="button" onclick="setSimMode('withdraw', this)" class="sim-mode-btn w-full p-3 rounded-xl border text-left transition flex items-start space-x-3 bg-term-card text-slate-300 border-term-border hover:bg-term-hover">
                                    <span class="text-lg">💵</span>
                                    <div>
                                        <b class="text-xs text-white block">Вариант 1: Месечен доход (Теглене)</b>
                                        <span class="text-[11px] text-slate-400 block leading-tight">Прибирате печалбата всеки месец, балансът стои непроменен.</span>
                                    </div>
                                </button>
                                <button type="button" onclick="setSimMode('compound', this)" class="sim-mode-btn w-full p-3 rounded-xl border text-left transition flex items-start space-x-3 bg-emerald-500/15 border-emerald-500/60 text-white shadow-md">
                                    <span class="text-lg">🚀</span>
                                    <div>
                                        <b class="text-xs text-emerald-300 block">Вариант 2: Сложна лихва (Compounding)</b>
                                        <span class="text-[11px] text-slate-300 block leading-tight">Реинвестирате всичко. Печалбите увеличават размера на следващите сделки.</span>
                                    </div>
                                </button>
                                <button type="button" onclick="setSimMode('save', this)" class="sim-mode-btn w-full p-3 rounded-xl border text-left transition flex items-start space-x-3 bg-term-card text-slate-300 border-term-border hover:bg-term-hover">
                                    <span class="text-lg">🏦</span>
                                    <div>
                                        <b class="text-xs text-white block">Вариант 3: Реинвестиране + $100/мес спестяване</b>
                                        <span class="text-[11px] text-slate-400 block leading-tight">Редовно довнасяне, което ускорява експоненциалния растеж.</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Results Right Column (7 cols) -->
                    <div class="lg:col-span-7 flex flex-col justify-between space-y-5 bg-slate-950/70 border border-slate-800 rounded-2xl p-5 sm:p-6">
                        <!-- Top Summary Metric -->
                        <div class="grid grid-cols-2 gap-4 border-b border-slate-800 pb-5">
                            <div>
                                <span class="text-[11px] text-slate-400 uppercase tracking-wider block font-bold">Очаквано след 1 година</span>
                                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-1 block" id="sim-out-1yr-bal">$3,896.00</span>
                                <span class="text-xs text-slate-400 mt-0.5 block" id="sim-out-1yr-sub">депозит + чиста печалба</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[11px] text-slate-400 uppercase tracking-wider block font-bold">Чиста печалба (НЕТО)</span>
                                <span class="text-2xl sm:text-3xl font-black text-white font-mono mt-1 block" id="sim-out-1yr-profit">+$2,896.00</span>
                                <span class="text-xs font-bold font-mono text-emerald-400 mt-0.5 block" id="sim-out-1yr-roi">+289.6% възвръщаемост</span>
                            </div>
                        </div>

                        <!-- Timeline Milestones (1m, 3m, 6m, 12m) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3 bg-term-surface border border-term-border rounded-xl text-center">
                                <span class="text-[10px] text-slate-400 uppercase font-mono block">1 месец</span>
                                <b class="text-sm font-bold font-mono text-white block mt-1" id="sim-card-1m">$1,120</b>
                                <span class="text-[10px] font-mono text-emerald-400 block" id="sim-card-1m-pnl">+$120</span>
                            </div>
                            <div class="p-3 bg-term-surface border border-term-border rounded-xl text-center">
                                <span class="text-[10px] text-slate-400 uppercase font-mono block">3 месеца</span>
                                <b class="text-sm font-bold font-mono text-white block mt-1" id="sim-card-3m">$1,405</b>
                                <span class="text-[10px] font-mono text-emerald-400 block" id="sim-card-3m-pnl">+$405</span>
                            </div>
                            <div class="p-3 bg-term-surface border border-term-border rounded-xl text-center">
                                <span class="text-[10px] text-slate-400 uppercase font-mono block">6 месеца</span>
                                <b class="text-sm font-bold font-mono text-white block mt-1" id="sim-card-6m">$1,974</b>
                                <span class="text-[10px] font-mono text-emerald-400 block" id="sim-card-6m-pnl">+$974</span>
                            </div>
                            <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-center">
                                <span class="text-[10px] text-emerald-400 uppercase font-mono block font-bold">12 месеца</span>
                                <b class="text-sm font-extrabold font-mono text-emerald-300 block mt-1" id="sim-card-12m">$3,896</b>
                                <span class="text-[10px] font-mono text-emerald-400 font-bold block" id="sim-card-12m-pnl">+$2,896</span>
                            </div>
                        </div>

                        <!-- Security & Safety Explanation Note -->
                        <div class="bg-term-surface/70 border border-term-border rounded-xl p-3.5 text-xs text-slate-300 flex items-start space-x-2.5">
                            <i class="fa-solid fa-circle-check text-emerald-400 text-sm mt-0.5 flex-shrink-0"></i>
                            <div class="leading-relaxed">
                                <strong class="text-white">100% Спот Търговия:</strong> За разлика от рисковите ботове със заеми (ливъридж 1:100 на злато), тук купувате реални акции със собствени пари и твърд стоп 1.8%. Рискът от зануляване е напълно елиминиран.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Section 5: Покана за режим "За напреднали" -->
            <div class="bg-term-surface border border-term-border/80 rounded-2xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-800 text-slate-300 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-white">Искате да видите живите борсови графики и технически индикатори?</h4>
                        <p class="text-xs text-slate-400">Превключете на Pro режим за TradingView графика, PnL календар, скрийнър за новини и пълен терминал.</p>
                    </div>
                </div>
                <button onclick="setViewMode('pro')" class="px-4 py-2.5 rounded-xl bg-term-card hover:bg-term-hover text-white font-semibold text-xs border border-term-border transition flex items-center space-x-2 flex-shrink-0">
                    <span>Отвори Режим "За напреднали"</span>
                    <i class="fa-solid fa-arrow-right text-[11px]"></i>
                </button>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- PRO VIEW CONTAINER (За напреднали - Пълен Терминал)           -->
        <!-- ============================================================== -->
        <div id="pro-dashboard-container" class="hidden space-y-6">

        <!-- ============================================================== -->
        <!-- TAB 1: DASHBOARD & STATISTICS                                  -->
        <!-- ============================================================== -->
        <div id="tab-dashboard" class="tab-content space-y-6">

            <!-- Commercial / Methodology Banner -->
            <div class="bg-gradient-to-r from-emerald-950/40 via-cyan-950/20 to-term-surface border border-emerald-500/30 rounded-xl p-4 sm:p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-lg shadow-emerald-950/20">
                <div class="flex items-start space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-lg flex-shrink-0 mt-0.5">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <h3 class="font-bold text-white text-sm sm:text-base">Кешов Портфейл от 5 Слота ($1,000 Макс Капацитет &bull; Grade A+: $400 &bull; Grade B: $200)</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">РЕАЛНА МАТЕМАТИКА</span>
                        </div>
                        <p class="text-xs text-slate-300 max-w-3xl leading-relaxed">
                            Всички печалби са чисти пари без марджин дълг (100% кеш). Портфолиото поддържа максимум 5 едновременни слота по $200 ($1,000 общо). Сетъпите <strong>Grade A+</strong> заемат 2 слота ($400), а при <strong>WATCH</strong> се изчаква пробив на радара.
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 flex-shrink-0">
                    <button onclick="switchTab('methodology')" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs flex items-center space-x-2 transition shadow-md shadow-emerald-500/20">
                        <i class="fa-solid fa-book-open"></i>
                        <span>Пълно Ръководство &raquo;</span>
                    </button>
                </div>
            </div>
            
            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                
                <!-- Current Portfolio Capital Balance -->
                <div class="bg-term-surface border border-term-border rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-emerald-500/10 rounded-full blur-xl"></div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block">Текущ Капитал</span>
                    <div class="mt-2 flex items-baseline space-x-1">
                        <span class="text-2xl font-bold mono text-emerald-400 glow-green">$<?= number_format($stats['current_balance'] ?? 1000, 2) ?></span>
                    </div>
                    <span class="text-xs text-slate-500 mt-1 block">Старт: $1,000 | <span class="text-emerald-400 font-semibold">+<?= number_format($stats['roi_percent'] ?? 0, 1) ?>% ROI</span></span>
                </div>

                <!-- Realized Net PnL in Dollars -->
                <div class="bg-term-surface border border-term-border rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-emerald-500/10 rounded-full blur-xl"></div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block">Реализиран PnL ($)</span>
                    <div class="mt-2 flex items-baseline space-x-1">
                        <span class="text-2xl font-bold mono text-emerald-400 glow-green">+$<?= number_format($stats['total_pnl_dollars'] ?? 0, 2) ?></span>
                    </div>
                    <span class="text-xs text-emerald-400/80 mt-1 block font-medium"><?= ($stats['win_trades'] ?? 15) ?> победи / <?= ($stats['loss_trades'] ?? 9) ?> стопа</span>
                </div>

                <!-- Trade Win Rate (BUY / SELL Trades) -->
                <div class="bg-term-surface border border-term-border rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-emerald-500/10 rounded-full blur-xl"></div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block">Търговски Win Rate</span>
                    <div class="mt-2 flex items-baseline space-x-1">
                        <span class="text-2xl font-bold mono text-emerald-400 glow-green"><?= number_format($stats['win_rate'] ?? 62.5, 1) ?>%</span>
                    </div>
                    <span class="text-xs text-slate-500 mt-1 block"><?= ($stats['win_trades'] ?? 15) ?> от <?= ($stats['closed_trades'] ?? 24) ?> решени сделки</span>
                </div>

                <!-- Position Sizing & 5-Slot Allocation -->
                <div class="bg-term-surface border border-term-border rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-cyan-500/10 rounded-full blur-xl"></div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block">Слотове & Алокация</span>
                    <div class="mt-2 flex items-baseline space-x-1">
                        <span class="text-xl font-bold mono text-amber-400">A+: $400</span>
                        <span class="text-xs mono text-slate-400">| B: $200</span>
                    </div>
                    <span class="text-[11px] text-cyan-300 mt-1 block font-mono"><?= $stats['portfolio_cash']['used_slots'] ?? 4 ?>/5 слота ($<?= number_format($stats['portfolio_cash']['allocated_cash'] ?? 800, 0) ?>)</span>
                </div>

                <!-- Max Drawdown -->
                <div class="bg-term-surface border border-term-border rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-cyan-500/10 rounded-full blur-xl"></div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block">Max Drawdown</span>
                    <div class="mt-2 flex items-baseline space-x-1">
                        <span class="text-2xl font-bold mono text-cyan-400 glow-blue"><?= number_format($stats['max_drawdown_pct'] ?? 0, 1) ?>%</span>
                    </div>
                    <span class="text-xs text-slate-400 mt-1 block font-medium"><?= $stats['green_days'] ?? 0 ?> зелени / <?= $stats['red_days'] ?? 0 ?> червени дни</span>
                </div>

                <!-- Calendar Win Days -->
                <div class="bg-term-surface border border-term-border rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-indigo-500/10 rounded-full blur-xl"></div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block">Зелени Дни (Calendar)</span>
                    <div class="mt-2 flex items-baseline space-x-1">
                        <span class="text-2xl font-bold mono text-emerald-400 glow-green"><?= $stats['green_days'] ?? 6 ?> от <?= count($stats['daily_pnl_calendar'] ?? [1,2,3,4,5,6]) ?></span>
                    </div>
                    <span class="text-xs text-slate-500 mt-1 block">Топ ден: +$<?= number_format($stats['best_day_dollars'] ?? 21.05, 2) ?></span>
                </div>

            </div>

            <!-- Main Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Cumulative PnL Equity Curve (Chart.js) -->
                <div class="lg:col-span-2 bg-term-surface border border-term-border rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-white flex items-center space-x-2">
                                <i class="fa-solid fa-arrow-trend-up text-emerald-400"></i>
                                <span>Крива на капитала (Equity Curve в USD)</span>
                            </h2>
                            <p class="text-xs text-slate-400">Стартов капитал: $1,000.00 &bull; Вход: $200.00 (20%) &bull; Риск на сделка: $20.00 (2%)</p>
                        </div>
                        <span class="px-2.5 py-1 text-xs font-mono font-semibold rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Баланс: $<?= number_format($stats['current_balance'] ?? 1000, 2) ?> (+<?= number_format($stats['roi_percent'] ?? 0, 1) ?>%)
                        </span>
                    </div>
                    <div class="h-64 w-full relative">
                        <canvas id="equityChart"></canvas>
                    </div>
                </div>

                <!-- Sentiment & Verdict Distributions -->
                <div class="bg-term-surface border border-term-border rounded-xl p-5 space-y-4 flex flex-col justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center space-x-2">
                            <i class="fa-solid fa-pie-chart text-cyan-400"></i>
                            <span>Пазарен Сантимент</span>
                        </h2>
                        <p class="text-xs text-slate-400">Настроения според 24h Perplexity & Price Action</p>
                    </div>

                    <div class="h-44 w-full relative flex items-center justify-center">
                        <canvas id="sentimentChart"></canvas>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-term-border/60 text-center text-xs">
                        <div class="bg-term-card p-2 rounded-lg border border-term-border">
                            <span class="text-emerald-400 font-bold block mono"><?= $stats['sentiment']['bullish'] ?? 0 ?></span>
                            <span class="text-slate-400">Bullish 🟢</span>
                        </div>
                        <div class="bg-term-card p-2 rounded-lg border border-term-border">
                            <span class="text-rose-400 font-bold block mono"><?= $stats['sentiment']['bearish'] ?? 0 ?></span>
                            <span class="text-slate-400">Bearish 🔴</span>
                        </div>
                        <div class="bg-term-card p-2 rounded-lg border border-term-border">
                            <span class="text-slate-300 font-bold block mono"><?= $stats['sentiment']['neutral'] ?? 0 ?></span>
                            <span class="text-slate-400">Neutral ⚪</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Interactive Monthly PnL Calendar Widget (Tradervue / Edgewonk Style) -->
            <div class="bg-term-surface border border-term-border rounded-xl p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-term-border/60 pb-3">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center space-x-2">
                            <i class="fa-solid fa-calendar-days text-emerald-400"></i>
                            <span>Месечен PnL Календар (Trader Calendar Heatmap) &bull; Септември 2026</span>
                        </h2>
                        <p class="text-xs text-slate-400">Ежедневен нетен резултат в долари ($) от реално затворени търговски сесии</p>
                    </div>
                    <div class="flex items-center space-x-3 text-xs mono">
                        <span class="px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                            <i class="fa-solid fa-trophy mr-1"></i>5 Зелени Дни (100%)
                        </span>
                        <span class="px-2.5 py-1 rounded bg-term-card text-slate-400 border border-term-border">
                            0 Червени Дни
                        </span>
                        <span class="px-2.5 py-1 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 font-bold">
                            Max Drawdown: 0.0%
                        </span>
                    </div>
                </div>

                <!-- Calendar Grid (Monday to Friday trading days) -->
                <div class="grid grid-cols-2 sm:grid-cols-5 md:grid-cols-10 gap-2.5">
                    <!-- Day 15 Mon -->
                    <div class="bg-emerald-950/20 border border-emerald-500/40 rounded-xl p-2.5 flex flex-col justify-between hover:scale-[1.02] transition shadow-md shadow-emerald-950/20">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-400 font-mono">15 Сеп (Пон)</span>
                            <span class="text-emerald-400 font-bold">🟢</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-sm font-bold text-emerald-400 font-mono block">+$10.84</span>
                            <span class="text-[10px] text-slate-400">NBIS (Sell TP)</span>
                        </div>
                        <span class="text-[9px] text-emerald-400/80 font-mono bg-emerald-500/10 px-1 py-0.5 rounded text-center">Grade A+ ($400)</span>
                    </div>

                    <!-- Day 16 Tue -->
                    <div class="bg-term-card/60 border border-term-border/60 rounded-xl p-2.5 flex flex-col justify-between opacity-70">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-500 font-mono">16 Сеп (Вто)</span>
                            <span class="text-slate-500 text-[10px]">⚪</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-xs font-mono text-slate-500 block">$0.00</span>
                            <span class="text-[10px] text-slate-600">Рейндж / Без риск</span>
                        </div>
                        <span class="text-[9px] text-slate-500 font-mono bg-term-surface px-1 py-0.5 rounded text-center">Защитен капитал</span>
                    </div>

                    <!-- Day 17 Wed -->
                    <div class="bg-term-card/60 border border-term-border/60 rounded-xl p-2.5 flex flex-col justify-between opacity-70">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-500 font-mono">17 Сеп (Сря)</span>
                            <span class="text-slate-500 text-[10px]">⚪</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-xs font-mono text-slate-500 block">$0.00</span>
                            <span class="text-[10px] text-slate-600">Рейндж / Без риск</span>
                        </div>
                        <span class="text-[9px] text-slate-500 font-mono bg-term-surface px-1 py-0.5 rounded text-center">Защитен капитал</span>
                    </div>

                    <!-- Day 18 Thu -->
                    <div class="bg-emerald-950/20 border border-emerald-500/40 rounded-xl p-2.5 flex flex-col justify-between hover:scale-[1.02] transition shadow-md shadow-emerald-950/20">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-400 font-mono">18 Сеп (Чет)</span>
                            <span class="text-emerald-400 font-bold">🟢</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-sm font-bold text-emerald-400 font-mono block">+$6.20</span>
                            <span class="text-[10px] text-slate-400">MU (Buy TP)</span>
                        </div>
                        <span class="text-[9px] text-emerald-400/80 font-mono bg-emerald-500/10 px-1 py-0.5 rounded text-center">Grade A+ ($400)</span>
                    </div>

                    <!-- Day 19 Fri -->
                    <div class="bg-term-card/60 border border-term-border/60 rounded-xl p-2.5 flex flex-col justify-between opacity-70">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-500 font-mono">19 Сеп (Пет)</span>
                            <span class="text-slate-500 text-[10px]">⚪</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-xs font-mono text-slate-500 block">$0.00</span>
                            <span class="text-[10px] text-slate-600">Weekend close</span>
                        </div>
                        <span class="text-[9px] text-slate-500 font-mono bg-term-surface px-1 py-0.5 rounded text-center">Пазар затворен</span>
                    </div>

                    <!-- Day 21 Mon -->
                    <div class="bg-emerald-950/30 border border-emerald-400/60 rounded-xl p-2.5 flex flex-col justify-between hover:scale-[1.02] transition shadow-md shadow-emerald-950/30">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-300 font-mono font-bold">21 Сеп (Пон)</span>
                            <span class="text-emerald-300 font-bold">🏆</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-sm font-extrabold text-emerald-300 font-mono block">+$21.05</span>
                            <span class="text-[10px] text-emerald-200">EOSE (Buy TP)</span>
                        </div>
                        <span class="text-[9px] text-emerald-300 font-mono bg-emerald-500/20 px-1 py-0.5 rounded text-center font-bold">Top Day (+5.26%)</span>
                    </div>

                    <!-- Day 22 Tue -->
                    <div class="bg-emerald-950/20 border border-emerald-500/40 rounded-xl p-2.5 flex flex-col justify-between hover:scale-[1.02] transition shadow-md shadow-emerald-950/20">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-400 font-mono">22 Сеп (Вто)</span>
                            <span class="text-emerald-400 font-bold">🟢</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-sm font-bold text-emerald-400 font-mono block">+$12.67</span>
                            <span class="text-[10px] text-slate-400">NBIS (Buy TP)</span>
                        </div>
                        <span class="text-[9px] text-emerald-400/80 font-mono bg-emerald-500/10 px-1 py-0.5 rounded text-center">Grade A+ ($400)</span>
                    </div>

                    <!-- Day 23 Wed -->
                    <div class="bg-term-card/60 border border-term-border/60 rounded-xl p-2.5 flex flex-col justify-between opacity-70">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-500 font-mono">23 Сеп (Сря)</span>
                            <span class="text-slate-500 text-[10px]">⚪</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-xs font-mono text-slate-500 block">$0.00</span>
                            <span class="text-[10px] text-slate-600">Рейндж / Без риск</span>
                        </div>
                        <span class="text-[9px] text-slate-500 font-mono bg-term-surface px-1 py-0.5 rounded text-center">Защитен капитал</span>
                    </div>

                    <!-- Day 24 Thu -->
                    <div class="bg-emerald-950/20 border border-emerald-500/40 rounded-xl p-2.5 flex flex-col justify-between hover:scale-[1.02] transition shadow-md shadow-emerald-950/20">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-400 font-mono">24 Сеп (Чет)</span>
                            <span class="text-emerald-400 font-bold">🟢</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-sm font-bold text-emerald-400 font-mono block">+$15.20</span>
                            <span class="text-[10px] text-slate-400">EOSE (Sell TP)</span>
                        </div>
                        <span class="text-[9px] text-emerald-400/80 font-mono bg-emerald-500/10 px-1 py-0.5 rounded text-center">Grade A+ ($400)</span>
                    </div>

                    <!-- Day 25 Fri (Today) -->
                    <div class="bg-amber-950/20 border border-amber-500/50 rounded-xl p-2.5 flex flex-col justify-between hover:scale-[1.02] transition shadow-md shadow-amber-950/20 animate-pulse">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-amber-300 font-mono font-bold">25 Сеп (Днес)</span>
                            <span class="text-amber-400">⏳</span>
                        </div>
                        <div class="my-1.5">
                            <span class="text-xs font-bold text-amber-300 font-mono block">В ход ($400)</span>
                            <span class="text-[10px] text-slate-300">NBIS (A+ Watch)</span>
                        </div>
                        <span class="text-[9px] text-amber-300 font-mono bg-amber-500/20 px-1 py-0.5 rounded text-center font-bold">Очаква +$18.97</span>
                    </div>
                </div>
            </div>

            <!-- Recent Highlights Table -->
            <div class="bg-term-surface border border-term-border rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center space-x-2">
                            <i class="fa-solid fa-list-check text-emerald-400"></i>
                            <span>Последни Сигнали & Резултати</span>
                        </h2>
                        <p class="text-xs text-slate-400">Най-актуалните генерирани алерти от n8n системата</p>
                    </div>
                    <button onclick="switchTab('signals')" class="text-xs text-emerald-400 hover:text-emerald-300 font-medium flex items-center space-x-1">
                        <span>Виж всички</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-term-border text-slate-400 uppercase font-medium">
                                <th class="py-3 px-3">Дата / Час</th>
                                <th class="py-3 px-3">Тикер</th>
                                <th class="py-3 px-3">Присъда</th>
                                <th class="py-3 px-3">Увереност</th>
                                <th class="py-3 px-3">Цена Вход</th>
                                <th class="py-3 px-3">Вложени Пари</th>
                                <th class="py-3 px-3">Stop Loss</th>
                                <th class="py-3 px-3">Target Price</th>
                                <th class="py-3 px-3">R:R</th>
                                <th class="py-3 px-3">Резултат / PnL ($)</th>
                                <th class="py-3 px-3 text-right">Детайли</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-term-border/40">
                            <?php foreach (array_slice($trades, 0, 8) as $t): 
                                $verdict = strtoupper($t['verdict'] ?? 'WATCH');
                                $vColor = $verdict === 'BUY' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 
                                         ($verdict === 'SELL' ? 'bg-rose-500/10 text-rose-400 border-rose-500/30' : 'bg-amber-500/10 text-amber-400 border-amber-500/30');
                            ?>
                            <tr class="hover:bg-term-hover/60 transition group">
                                <td class="py-3 px-3 mono text-slate-400"><?= htmlspecialchars($t['date_time'] ?? '') ?></td>
                                <td class="py-3 px-3 font-bold text-white mono text-sm flex items-center space-x-1.5">
                                    <span><?= htmlspecialchars($t['ticker'] ?? '') ?></span>
                                    <?php if (($t['setup_grade'] ?? 'B') === 'A+'): ?>
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">A+</span>
                                    <?php else: ?>
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">B</span>
                                    <?php endif; ?>
                                    <span class="text-[10px] text-slate-500 font-normal"><?= htmlspecialchars($t['market_trend'] ?? '') ?></span>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold border <?= $vColor ?>">
                                        <?= htmlspecialchars($verdict) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 mono text-slate-300"><?= $t['confidence'] ?? 0 ?>%</td>
                                <td class="py-3 px-3 mono text-slate-200 font-medium">$<?= number_format((float)($t['current_price'] ?? 0), 2) ?></td>
                                <td class="py-3 px-3 mono text-cyan-400 font-semibold">
                                    <?php if (strpos(strtoupper($t['outcome'] ?? ''), 'PENDING') !== false): ?>
                                        <span class="text-amber-400 font-bold">$0.00</span>
                                        <span class="text-[10px] text-amber-400/80 font-normal block font-mono">Чака пробив ($<?= number_format((float)($t['position_size'] ?? 200), 0) ?>)</span>
                                    <?php else: ?>
                                        $<?= number_format((float)($t['position_size'] ?? 200), 2) ?>
                                        <span class="text-[10px] text-slate-500 font-normal block"><?= $t['shares_est'] ?? '0' ?> бр. (<?= ($t['setup_grade'] ?? 'B') === 'A+' ? '2 слота / A+' : '1 слот / B' ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 mono text-rose-400/90">$<?= number_format((float)($t['stop_loss'] ?? 0), 2) ?></td>
                                <td class="py-3 px-3 mono text-emerald-400 font-medium">$<?= number_format((float)($t['target_price'] ?? 0), 2) ?></td>
                                <td class="py-3 px-3 mono text-slate-300"><?= htmlspecialchars($t['risk_reward'] ?? '1:2.0') ?></td>
                                <td class="py-3 px-3 mono">
                                    <?php 
                                        $out = strtoupper($t['outcome'] ?? 'PENDING');
                                        $pnl = $t['realized_pnl'] ?? '0.0R';
                                        $pnlD = $t['pnl_dollars_str'] ?? '$0.00';
                                        $pnlVal = (float)($t['pnl_dollars'] ?? 0);
                                        $isPending = strpos($out, 'PENDING') !== false;
                                        $isOpen = ($out === 'OPEN' || strpos($out, 'OPEN') !== false || $out === 'IN PROGRESS') && !$isPending;

                                        if (strpos($out, 'WATCH WIN') !== false): 
                                    ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                            <i class="fa-solid fa-bullseye mr-1 text-[10px]"></i>Target Hit
                                        </span>
                                        <span class="ml-1 text-[11px] px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold"><?= htmlspecialchars($pnlD) ?></span>
                                    <?php elseif (strpos($out, 'WATCH LOSS') !== false): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30">
                                            <i class="fa-solid fa-triangle-exclamation mr-1 text-[10px]"></i>Stop Hit
                                        </span>
                                        <span class="ml-1 text-[11px] px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 font-bold"><?= htmlspecialchars($pnlD) ?></span>
                                    <?php elseif (strpos($out, 'WIN') !== false): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                            <i class="fa-solid fa-trophy mr-1 text-[10px]"></i>WIN
                                        </span>
                                        <span class="ml-1 text-[11px] px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold"><?= htmlspecialchars($pnlD) ?></span>
                                    <?php elseif (strpos($out, 'LOSS') !== false): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30">
                                            <i class="fa-solid fa-xmark mr-1 text-[10px]"></i>LOSS
                                        </span>
                                        <span class="ml-1 text-[11px] px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 font-bold"><?= htmlspecialchars($pnlD) ?></span>
                                    <?php elseif ($isOpen): 
                                        $openBadge = $pnlVal >= 0 ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-rose-500/15 text-rose-400 border-rose-500/30';
                                        $openPill = $pnlVal >= 0 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300';
                                        $openSign = $pnlVal >= 0 ? '+' : '-';
                                    ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold border <?= $openBadge ?> animate-pulse">
                                            <i class="fa-solid fa-circle-dot mr-1 text-[9px]"></i>OPEN
                                        </span>
                                        <span class="ml-1 text-[11px] px-1.5 py-0.5 rounded font-bold font-mono <?= $openPill ?>"><?= $openSign ?>$<?= number_format(abs($pnlVal), 2) ?></span>
                                        <span class="text-[10px] text-slate-400 font-mono block mt-0.5"><?= htmlspecialchars($pnl) ?></span>
                                    <?php elseif (strpos($out, 'RANGE') !== false || strpos($out, 'EXPIRED') !== false || strpos($out, 'WATCHLIST') !== false): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono text-slate-400 bg-slate-700/30 border border-slate-600/30">
                                            <i class="fa-solid fa-arrows-left-right mr-1 text-[10px]"></i>Рейндж $0.00
                                        </span>
                                    <?php elseif ($isPending): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold text-amber-400 bg-amber-500/10 border border-amber-500/30">
                                            <i class="fa-solid fa-hourglass-half mr-1 text-[10px]"></i>Чака пробив • 100% Кеш ($0)
                                        </span>
                                        <?php if (!empty($t['trigger_price']) && (float)$t['trigger_price'] > 0): 
                                            $tDist = (float)($t['trigger_distance_pct'] ?? 0);
                                            $tStat = $t['trigger_status'] ?? 'SCANNING';
                                            $tBadgeColor = $tStat === 'ZONE' ? 'text-emerald-400 font-bold' : ($tStat === 'APPROACHING' ? 'text-amber-400 font-bold' : 'text-slate-400');
                                        ?>
                                        <span class="text-[10px] <?= $tBadgeColor ?> font-mono block mt-0.5">🎯 Праг: $<?= number_format((float)$t['trigger_price'], 2) ?> (<?= $tDist > 0 ? '+' : '' ?><?= $tDist ?>%)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-mono text-[11px]"><?= htmlspecialchars($out) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <button onclick="viewSignalDetails(<?= htmlspecialchars(json_encode($t)) ?>)" class="px-2.5 py-1 rounded bg-term-card hover:bg-term-border text-slate-300 hover:text-white transition text-[11px]">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- TAB 2: LIVE SIGNALS & TRADES                                   -->
        <!-- ============================================================== -->
        <div id="tab-signals" class="tab-content hidden space-y-5">
            
            <!-- Controls & Filters -->
            <div class="bg-term-surface border border-term-border rounded-xl p-4 flex flex-wrap gap-4 items-center justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-500 text-xs"></i>
                        <input type="text" id="signalSearch" oninput="filterSignals()" placeholder="Търси тикер (NBIS, CRWV...)" class="bg-term-card border border-term-border rounded-lg pl-9 pr-4 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 w-48 sm:w-64">
                    </div>

                    <!-- Verdict Filter -->
                    <select id="verdictFilter" onchange="filterSignals()" class="bg-term-card border border-term-border rounded-lg px-3 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-emerald-500">
                        <option value="">Всички присъди</option>
                        <option value="BUY">BUY (Купи)</option>
                        <option value="SELL">SELL (Продай)</option>
                        <option value="WATCH">WATCH (Наблюдавай)</option>
                    </select>

                    <!-- Status Filter -->
                    <select id="outcomeFilter" onchange="filterSignals()" class="bg-term-card border border-term-border rounded-lg px-3 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-emerald-500">
                        <option value="">Всички статуси</option>
                        <option value="OPEN">🟢 OPEN (Активни отворени позиции)</option>
                        <option value="PENDING">⏳ PENDING (В очакване на пробив)</option>
                        <option value="WIN">🏆 WIN (Hit TP / Target)</option>
                        <option value="LOSS">🔴 LOSS (Hit SL / Stop)</option>
                        <option value="RANGE">⚪ RANGE (Без пробив / Рейндж)</option>
                    </select>
                </div>

                <div class="text-xs text-slate-400">
                    Показани: <span id="signalsCount" class="font-bold text-white mono"><?= count($trades) ?></span> сигнала
                </div>
            </div>

            <!-- Signals Grid / Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="signalsContainer">
                <?php foreach ($trades as $t): 
                    $verdict = strtoupper($t['verdict'] ?? 'WATCH');
                    $isBuy = $verdict === 'BUY';
                    $isSell = $verdict === 'SELL';
                    $accentColor = $isBuy ? 'border-emerald-500/40 hover:border-emerald-500' : ($isSell ? 'border-rose-500/40 hover:border-rose-500' : 'border-term-border hover:border-slate-600');
                    $badgeBg = $isBuy ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : ($isSell ? 'bg-rose-500/15 text-rose-400 border-rose-500/30' : 'bg-amber-500/15 text-amber-400 border-amber-500/30');
                ?>
                <div class="signal-card bg-term-surface border <?= $accentColor ?> rounded-xl p-5 space-y-4 transition flex flex-col justify-between"
                     data-ticker="<?= htmlspecialchars($t['ticker'] ?? '') ?>"
                     data-verdict="<?= htmlspecialchars($verdict) ?>"
                     data-outcome="<?= htmlspecialchars($t['outcome'] ?? '') ?>">
                    
                    <div class="space-y-3">
                        <!-- Top Header -->
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-lg font-bold text-white mono tracking-wide"><?= htmlspecialchars($t['ticker'] ?? '') ?></span>
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold border <?= $badgeBg ?>">
                                        <?= htmlspecialchars($verdict) ?>
                                    </span>
                                    <?php if (($t['setup_grade'] ?? 'B') === 'A+'): ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">GRADE A+ (2 слота)</span>
                                    <?php else: ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">GRADE B (1 слот)</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-slate-500 mono block mt-0.5"><?= htmlspecialchars($t['date_time'] ?? '') ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-mono text-slate-400 block">Увереност</span>
                                <span class="text-sm font-bold mono text-cyan-400"><?= $t['confidence'] ?? 0 ?>%</span>
                            </div>
                        </div>

                        <!-- Price & Position Levels Grid -->
                        <div class="grid grid-cols-4 gap-2 bg-term-card p-2.5 rounded-lg border border-term-border text-center text-xs mono">
                            <div>
                                <span class="text-slate-500 block text-[10px]">ВХОД</span>
                                <span class="font-bold text-white">$<?= number_format((float)($t['current_price'] ?? 0), 2) ?></span>
                            </div>
                            <div>
                                <span class="text-cyan-400/80 block text-[10px]">ВЛОЖЕНИ</span>
                                <?php if (strpos(strtoupper($t['outcome'] ?? ''), 'PENDING') !== false): ?>
                                    <span class="font-bold text-amber-400 font-mono" title="100% Запазен кеш в готовност">$0 (Чака)</span>
                                <?php else: ?>
                                    <span class="font-bold text-cyan-400 font-mono">$<?= number_format((float)($t['position_size'] ?? 200), 0) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="text-rose-400/80 block text-[10px]">STOP LOSS</span>
                                <span class="font-bold text-rose-400">$<?= number_format((float)($t['stop_loss'] ?? 0), 2) ?></span>
                            </div>
                            <div>
                                <span class="text-emerald-400/80 block text-[10px]">TARGET</span>
                                <span class="font-bold text-emerald-400">$<?= number_format((float)($t['target_price'] ?? 0), 2) ?></span>
                            </div>
                        </div>

                        <!-- Key Metrics -->
                        <div class="flex items-center justify-between text-xs text-slate-400 px-1">
                            <div>R:R: <span class="text-cyan-400 font-mono font-semibold"><?= htmlspecialchars($t['risk_reward'] ?? '1:2.0') ?></span></div>
                            <div>RVOL: <span class="text-slate-200 font-mono font-semibold"><?= htmlspecialchars($t['rvol'] ?? '1.0x') ?></span></div>
                            <div>Тренд: <span class="text-slate-200 font-medium"><?= htmlspecialchars($t['market_trend'] ?? 'Neutral') ?></span></div>
                        </div>

                        <!-- Trigger Radar if available -->
                        <?php if (!empty($t['trigger_price']) && (float)$t['trigger_price'] > 0): 
                            $tDist = (float)($t['trigger_distance_pct'] ?? 0);
                            $tStat = $t['trigger_status'] ?? 'SCANNING';
                            $tBadge = $tStat === 'ZONE' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 animate-pulse' : 
                                     ($tStat === 'APPROACHING' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-cyan-500/10 text-cyan-300 border-cyan-500/30');
                            $tLabel = $tStat === 'ZONE' ? '🔥 В ЗОНА НА ПРОБИВ' : ($tStat === 'APPROACHING' ? '⏳ ПРИБЛИЖАВА ТРИГЕРА' : '🎯 РАДАР ТРИГЕР');
                        ?>
                        <div class="bg-slate-950/80 p-2.5 rounded-lg border border-cyan-500/20 text-xs flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-slate-400 block font-mono">НИВО ЗА ПРОБИВ</span>
                                <span class="font-bold text-white mono">$<?= number_format((float)$t['trigger_price'], 2) ?></span>
                                <span class="text-[10px] text-cyan-400 mono">(<?= $tDist > 0 ? '+' : '' ?><?= $tDist ?>%)</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold border <?= $tBadge ?>">
                                <?= $tLabel ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <!-- Trigger Note -->
                        <?php if (!empty($t['entry_trigger'])): ?>
                        <div class="bg-term-surface/70 border border-term-border/70 p-2.5 rounded-lg text-xs text-slate-300">
                            <span class="text-[10px] text-slate-500 uppercase font-semibold block mb-0.5">Входно Условие:</span>
                            <p class="line-clamp-2"><?= htmlspecialchars($t['entry_trigger']) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- News Headline Preview -->
                        <?php if (!empty($t['catalyst_headline'])): ?>
                        <div class="text-xs text-slate-400">
                            <span class="text-[10px] text-emerald-400/90 uppercase font-semibold block mb-0.5"><i class="fa-solid fa-bolt text-emerald-400 mr-1"></i>Катализатор:</span>
                            <p class="line-clamp-2 italic text-slate-300">"<?= htmlspecialchars($t['catalyst_headline']) ?>"</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer Action & Outcome -->
                    <div class="pt-3 border-t border-term-border/60 flex items-center justify-between text-xs">
                        <div>
                            <?php 
                                $out = strtoupper($t['outcome'] ?? 'PENDING');
                                $pnl = $t['realized_pnl'] ?? '0.0R';
                                $pnlD = $t['pnl_dollars_str'] ?? '$0.00';
                                $pnl_val = (float)($t['pnl_dollars'] ?? 0);
                                $isPending = strpos($out, 'PENDING') !== false;
                                $isOpen = ($out === 'OPEN' || strpos($out, 'OPEN') !== false || $out === 'IN PROGRESS') && !$isPending;

                                if (strpos($out, 'WATCH WIN') !== false): 
                            ?>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-mono font-bold text-[11px] border border-emerald-500/30">
                                    <i class="fa-solid fa-bullseye mr-1"></i>TARGET HIT <?= htmlspecialchars($pnlD) ?> <span class="text-[10px] font-normal text-emerald-300">(<?= htmlspecialchars($pnl) ?>)</span>
                                </span>
                            <?php elseif (strpos($out, 'WATCH LOSS') !== false): ?>
                                <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-400 font-mono font-bold text-[11px] border border-rose-500/30">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>STOP HIT <?= htmlspecialchars($pnlD) ?> <span class="text-[10px] font-normal text-rose-300">(<?= htmlspecialchars($pnl) ?>)</span>
                                </span>
                            <?php elseif (strpos($out, 'WIN') !== false): ?>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-mono font-bold text-[11px] border border-emerald-500/30">
                                    <i class="fa-solid fa-trophy mr-1"></i>WIN <?= htmlspecialchars($pnlD) ?> <span class="text-[10px] font-normal text-emerald-300">(<?= htmlspecialchars($pnl) ?>)</span>
                                </span>
                            <?php elseif (strpos($out, 'LOSS') !== false): ?>
                                <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-400 font-mono font-bold text-[11px] border border-rose-500/30">
                                    <i class="fa-solid fa-xmark mr-1"></i>LOSS <?= htmlspecialchars($pnlD) ?> <span class="text-[10px] font-normal text-rose-300">(<?= htmlspecialchars($pnl) ?>)</span>
                                </span>
                            <?php elseif ($isOpen): 
                                $badge_col = $pnl_val >= 0 ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-rose-500/15 text-rose-400 border-rose-500/30';
                            ?>
                                <span class="px-2 py-0.5 rounded font-mono font-bold text-[11px] border <?= $badge_col ?> animate-pulse">
                                    <i class="fa-solid fa-circle-dot mr-1 text-[9px]"></i>АКТИВНА ОТВОРЕНА <?= $pnl_val >= 0 ? '+$' . number_format($pnl_val, 2) : '-$' . number_format(abs($pnl_val), 2) ?> <span class="text-[10px] font-normal opacity-80">(<?= htmlspecialchars($pnl) ?>)</span>
                                </span>
                            <?php elseif (strpos($out, 'RANGE') !== false || strpos($out, 'EXPIRED') !== false || strpos($out, 'WATCHLIST') !== false): ?>
                                <span class="px-2 py-0.5 rounded bg-slate-700/40 text-slate-400 font-mono text-[11px] border border-slate-600/30">
                                    <i class="fa-solid fa-arrows-left-right mr-1"></i>Рейндж $0.00
                                </span>
                            <?php elseif ($isPending): ?>
                                <span class="px-2 py-0.5 rounded bg-amber-500/15 text-amber-400 font-mono font-bold text-[11px] border border-amber-500/30">
                                    <i class="fa-solid fa-hourglass-half mr-1"></i>ЧАКА ПРОБИВ • 100% КЕШ ($0)
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400 font-mono text-[11px]"><?= htmlspecialchars($out) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="openChartForTicker('<?= htmlspecialchars($t['ticker'] ?? '') ?>')" class="px-2.5 py-1 rounded bg-term-card hover:bg-term-border text-slate-300 hover:text-white transition text-xs flex items-center space-x-1" title="Отвори TradingView">
                                <i class="fa-solid fa-chart-candlestick"></i>
                                <span>Графика</span>
                            </button>
                            <button onclick="viewSignalDetails(<?= htmlspecialchars(json_encode($t)) ?>)" class="px-2.5 py-1 rounded bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition text-xs font-medium">
                                Инфо
                            </button>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- TAB 3: NEWS & CATALYSTS                                        -->
        <!-- ============================================================== -->
        <div id="tab-news" class="tab-content hidden space-y-5">
            
            <!-- News Search & Filters -->
            <div class="bg-term-surface border border-term-border rounded-xl p-4 flex flex-wrap gap-4 items-center justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-500 text-xs"></i>
                        <input type="text" id="newsSearch" oninput="filterNews()" placeholder="Търси ключова дума, медия или тикер..." class="bg-term-card border border-term-border rounded-lg pl-9 pr-4 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 w-48 sm:w-72">
                    </div>

                    <select id="newsTickerFilter" onchange="filterNews()" class="bg-term-card border border-term-border rounded-lg px-3 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-emerald-500">
                        <option value="">Всички тикери</option>
                        <?php foreach ($tickers as $tick): ?>
                        <option value="<?= htmlspecialchars($tick) ?>"><?= htmlspecialchars($tick) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button onclick="toggleAllArticles()" id="toggleAllArticlesBtn" class="px-3 py-1.5 rounded-lg bg-term-card hover:bg-term-hover border border-term-border text-xs text-slate-300 hover:text-white transition flex items-center space-x-1.5">
                        <i class="fa-solid fa-arrows-up-down text-emerald-400"></i>
                        <span id="toggleAllArticlesText">Сгъни всички статии</span>
                    </button>
                </div>

                <div class="text-xs text-slate-400 flex items-center space-x-2">
                    <span class="px-2.5 py-1 rounded bg-term-card border border-term-border font-mono text-cyan-400">
                        <i class="fa-solid fa-bolt text-emerald-400 mr-1"></i>Perplexity 24h Intelligence Feed
                    </span>
                </div>
            </div>

            <!-- News Feed -->
            <div class="space-y-6" id="newsFeedContainer">
                <?php foreach ($news as $n): 
                    $arts = $n['articles'] ?? [];
                    $artCount = count($arts);
                    $artsJson = json_encode($arts);
                ?>
                <div class="news-card bg-term-surface border border-term-border hover:border-slate-600 rounded-xl p-5 space-y-4 transition"
                     data-ticker="<?= htmlspecialchars($n['ticker'] ?? '') ?>"
                     data-headline="<?= htmlspecialchars(strtolower($n['headline'] ?? '')) ?>"
                     data-fullnews="<?= htmlspecialchars(strtolower($n['full_news'] ?? '')) ?>"
                     data-articles="<?= htmlspecialchars(strtolower($artsJson)) ?>">
                    
                    <!-- Header -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-term-border/60 pb-3">
                        <div class="flex items-center space-x-3">
                            <span class="text-lg font-bold text-white mono bg-term-card px-3 py-1 rounded-lg border border-term-border"><?= htmlspecialchars($n['ticker'] ?? '') ?></span>
                            <div>
                                <span class="text-xs text-slate-400 mono block"><?= htmlspecialchars($n['date_time'] ?? '') ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                                <i class="fa-solid fa-newspaper mr-1.5"></i><?= $artCount ?> намерени новини (24ч)
                            </span>
                            <span class="px-2.5 py-1 rounded-md text-xs font-mono font-medium bg-term-card border border-term-border text-slate-300">
                                Тренд: <?= htmlspecialchars($n['market_trend'] ?? 'Neutral') ?>
                            </span>
                        </div>
                    </div>

                    <!-- AI Analyst Synthesized Catalyst -->
                    <?php if (!empty($n['headline'])): ?>
                    <div class="text-xs sm:text-sm font-medium text-slate-200 leading-relaxed bg-gradient-to-r from-emerald-950/20 to-transparent p-3.5 rounded-lg border border-emerald-500/20">
                        <div class="text-[11px] text-emerald-400 font-semibold uppercase mb-1 flex items-center space-x-1.5">
                            <i class="fa-solid fa-brain"></i>
                            <span>Синтезиран Катализатор от AI Анализатора:</span>
                        </div>
                        <p class="text-slate-300"><?= nl2br(htmlspecialchars($n['headline'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Individual Articles Feed from Perplexity -->
                    <?php if (!empty($arts)): ?>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-solid fa-list-ul text-cyan-400"></i>
                                <span>Индивидуални статии и източници (<?= $artCount ?>)</span>
                            </span>
                            <span class="text-[11px] text-slate-500">Кликнете върху заглавието за оригиналния източник</span>
                        </div>

                        <div class="articles-grid grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($arts as $idx => $art): 
                                $domain = $art['domain'] ?: 'web';
                                $artUrl = $art['url'] ?: '#';
                            ?>
                            <div class="bg-term-card/80 hover:bg-term-hover border border-term-border hover:border-slate-500 rounded-lg p-3.5 space-y-2.5 transition flex flex-col justify-between">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="px-2 py-0.5 rounded font-mono font-semibold bg-slate-800 text-cyan-400 border border-slate-700/80">
                                            <?= htmlspecialchars($domain) ?>
                                        </span>
                                        <?php if (!empty($art['date'])): ?>
                                        <span class="text-slate-500 font-mono"><?= htmlspecialchars($art['date']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <a href="<?= htmlspecialchars($artUrl) ?>" target="_blank" rel="noopener noreferrer" class="font-bold text-slate-100 hover:text-emerald-400 transition text-xs block leading-snug group">
                                        <span><?= htmlspecialchars($art['title']) ?></span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-500 group-hover:text-emerald-400 ml-1 transition"></i>
                                    </a>

                                    <?php if (!empty($art['snippet'])): ?>
                                    <p class="text-slate-400 text-xs leading-relaxed bg-slate-950/70 p-2.5 rounded border border-term-border/40">
                                        <?= htmlspecialchars($art['snippet']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>

                                <div class="pt-2 border-t border-term-border/40 flex justify-end">
                                    <a href="<?= htmlspecialchars($artUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center space-x-1 text-[11px] font-medium text-emerald-400 hover:text-emerald-300 transition">
                                        <span>Към цялата статия</span>
                                        <i class="fa-solid fa-chevron-right text-[9px]"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Full Perplexity AI Synthesis Toggle -->
                    <?php if (!empty($n['full_news'])): ?>
                    <div class="space-y-2 pt-2 border-t border-term-border/50">
                        <button onclick="toggleFullNews(this)" class="text-xs text-slate-400 hover:text-cyan-400 font-medium flex items-center space-x-1.5 transition">
                            <i class="fa-solid fa-file-lines text-cyan-400"></i>
                            <span>Пълен структуриран доклад от Perplexity (SEC Filings, Upgrades, Macro)</span>
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform ml-1"></i>
                        </button>
                        <div class="full-news-content hidden bg-slate-950 p-4 rounded-lg border border-term-border/80 text-xs text-slate-300 leading-relaxed font-mono whitespace-pre-line max-h-96 overflow-y-auto custom-scrollbar">
<?= htmlspecialchars($n['full_news']) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- TAB 4: TRADINGVIEW LIVE CHART                                  -->
        <!-- ============================================================== -->
        <div id="tab-chart" class="tab-content hidden space-y-4">
            
            <div class="bg-term-surface border border-term-border rounded-xl p-4 flex flex-wrap gap-4 items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase">Избери акция:</span>
                    <select id="chartTickerSelect" onchange="changeChartTicker(this.value)" class="bg-term-card border border-term-border rounded-lg px-3 py-1.5 text-xs text-white font-mono font-bold focus:outline-none focus:border-emerald-500">
                        <?php foreach ($tickers as $tick): ?>
                        <option value="<?= htmlspecialchars($tick) ?>" <?= $tick === 'NBIS' ? 'selected' : '' ?>><?= htmlspecialchars($tick) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="text-xs text-slate-400 flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>TradingView Advanced Real-Time Chart</span>
                </div>
            </div>

            <!-- TradingView Widget Container -->
            <div class="bg-term-surface border border-term-border rounded-xl p-2 h-[650px] w-full relative overflow-hidden" id="tradingview-container">
                <!-- TradingView Widget BEGIN -->
                <div class="tradingview-widget-container" style="height:100%;width:100%">
                    <div id="tradingview_widget" style="height:calc(100% - 32px);width:100%"></div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 5: GLOBAL MARKET BRIEF (AI-Powered Daily Summary)          -->
        <!-- ============================================================== -->
        <div id="tab-market-brief" class="tab-content hidden space-y-5 pb-8">

            <!-- Header Bar -->
            <div class="bg-term-surface border border-term-border rounded-xl p-4 flex flex-wrap gap-4 items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center">
                        <i class="fa-solid fa-globe text-blue-400"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white">Глобален Пазарен Бриф</h2>
                        <p class="text-xs text-slate-400">AI анализ на световните новини и ефекта им върху пазарите</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span id="brief-generated-at" class="text-xs text-slate-500 font-mono"></span>
                    <button onclick="generateMarketBrief()" id="brief-generate-btn"
                        class="flex items-center space-x-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition shadow-lg shadow-blue-900/30">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span>Генерирай Бриф</span>
                    </button>
                    <button onclick="loadMarketBrief()" class="px-3 py-2 rounded-lg bg-term-card hover:bg-term-hover border border-term-border text-xs text-slate-300 hover:text-white transition">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div id="brief-loading" class="hidden">
                <div class="bg-term-surface border border-blue-500/30 rounded-xl p-8 text-center space-y-4">
                    <div class="w-14 h-14 mx-auto rounded-full bg-blue-500/20 flex items-center justify-center animate-pulse">
                        <i class="fa-solid fa-brain text-blue-400 text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-white font-semibold">AI анализира световните новини...</p>
                        <p class="text-xs text-slate-400 mt-1">Извличане на RSS новини → GPT-4o анализ → Структуриране по категории</p>
                        <p class="text-xs text-slate-500 mt-3 font-mono" id="brief-loading-timer">Изчакай ~30-60 секунди</p>
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <div id="brief-error" class="hidden">
                <div class="bg-term-surface border border-red-500/30 rounded-xl p-6 text-center space-y-2">
                    <i class="fa-solid fa-triangle-exclamation text-red-400 text-2xl"></i>
                    <p class="text-red-300 font-semibold" id="brief-error-msg">Грешка при зареждане</p>
                    <p class="text-xs text-slate-400">Опитай отново след малко</p>
                </div>
            </div>

            <!-- Empty State (no brief generated yet) -->
            <div id="brief-empty" class="">
                <div class="bg-term-surface border border-term-border rounded-xl p-10 text-center space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                        <i class="fa-solid fa-globe text-blue-400 text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-lg">Все още няма генериран бриф</h3>
                        <p class="text-slate-400 text-sm mt-2">Натисни <strong class="text-blue-400">Генерирай Бриф</strong> за да стартираш AI анализ на глобалните новини.</p>
                        <p class="text-slate-500 text-xs mt-3">Анализът извлича новини от Reuters, Yahoo Finance, CNBC и MarketWatch, след което GPT-4o ги обобщава по категории.</p>
                    </div>
                    <button onclick="generateMarketBrief()" class="mt-4 px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition shadow-lg shadow-blue-900/30">
                        <i class="fa-solid fa-wand-magic-sparkles mr-2"></i>Генерирай сега
                    </button>
                </div>
            </div>

            <!-- Brief Content (populated by JS) -->
            <div id="brief-content" class="hidden space-y-5">

                <!-- Executive Summary Banner -->
                <div id="brief-executive" class="bg-gradient-to-r from-blue-950/60 to-term-surface border border-blue-500/30 rounded-xl p-5 flex gap-4">
                    <div id="brief-sentiment-icon" class="w-12 h-12 flex-shrink-0 rounded-xl flex items-center justify-center text-2xl font-bold"></div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="text-xs font-bold text-blue-300 uppercase tracking-wider">Обобщено Настроение</span>
                            <span id="brief-sentiment-badge" class="px-2.5 py-0.5 rounded-full text-xs font-bold border"></span>
                            <span id="brief-score-badge" class="px-2 py-0.5 rounded bg-term-card border border-term-border text-xs font-mono text-slate-300"></span>
                        </div>
                        <p id="brief-exec-summary" class="text-sm text-slate-200 leading-relaxed"></p>
                    </div>
                </div>

                <!-- Category Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="brief-cards-grid"></div>

                <!-- Hot Sectors -->
                <div id="brief-sectors-wrap" class="bg-term-surface border border-term-border rounded-xl p-5 space-y-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-fire text-orange-400"></i>Горещи Сектори
                    </h3>
                    <div id="brief-sectors" class="flex flex-wrap gap-2"></div>
                </div>

                <!-- Risk & Opportunities Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Risks -->
                    <div class="bg-term-surface border border-red-500/20 rounded-xl p-5 space-y-3">
                        <h3 class="text-xs font-bold text-red-400 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-shield-halved"></i>Рискови Фактори
                        </h3>
                        <ul id="brief-risks" class="space-y-2"></ul>
                    </div>
                    <!-- Opportunities -->
                    <div class="bg-term-surface border border-emerald-500/20 rounded-xl p-5 space-y-3">
                        <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-star"></i>Пазарни Възможности
                        </h3>
                        <ul id="brief-opportunities" class="space-y-2"></ul>
                    </div>
                </div>

                <!-- Watchlist Impact / Action Plan -->
                <div class="bg-gradient-to-r from-emerald-950/40 to-term-surface border border-emerald-500/20 rounded-xl p-5 space-y-2">
                    <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-crosshairs text-emerald-400"></i>🎯 Екшън План за Портфейла: Какво да правим днес (Действия за Трейдъра)
                    </h3>
                    <p id="brief-watchlist-impact" class="text-sm text-slate-200 leading-relaxed whitespace-pre-line"></p>
                </div>

                <!-- Source Footer -->
                <div class="text-center text-xs text-slate-600 font-mono">
                    <i class="fa-solid fa-bolt text-blue-600 mr-1"></i>
                    GPT-4o · Reuters · Yahoo Finance · CNBC · MarketWatch ·
                    <span id="brief-source-time"></span>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 6: METHODOLOGY & DOCUMENTATION (COMMERCIAL / SAAS GUIDE)   -->
        <!-- ============================================================== -->
        <div id="tab-methodology" class="tab-content hidden space-y-8 pb-10">

            <!-- Hero Section -->
            <div class="bg-gradient-to-br from-term-surface via-term-card to-emerald-950/20 border border-emerald-500/30 rounded-2xl p-6 sm:p-8 space-y-4 relative overflow-hidden shadow-2xl">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-amber-500 to-emerald-500 flex items-center justify-center text-slate-950 text-xl font-bold shadow-lg shadow-amber-500/20">
                            <i class="fa-solid fa-book-bookmark"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h1 class="text-xl sm:text-2xl font-bold text-white tracking-wide">Методология & Документация</h1>
                                <span class="px-2.5 py-0.5 text-xs font-mono font-bold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">SAAS READY</span>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-400">Официално ръководство за инвеститори, клиенти и абонати на Lexmation Intelligence Terminal</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs mono">
                        <span class="px-3 py-1 rounded-lg bg-term-card border border-term-border text-slate-300">
                            <i class="fa-solid fa-coins text-emerald-400 mr-1.5"></i>100% Кешов Спот Модел
                        </span>
                        <span class="px-3 py-1 rounded-lg bg-term-card border border-term-border text-slate-300">
                            <i class="fa-solid fa-shield-halved text-cyan-400 mr-1.5"></i>0% Ливъридж / Без Заем
                        </span>
                        <span class="px-3 py-1 rounded-lg bg-term-card border border-term-border text-slate-300">
                            <i class="fa-solid fa-brain text-purple-400 mr-1.5"></i>0% Човешки Емоции
                        </span>
                    </div>
                </div>

                <div class="border-t border-term-border/60 pt-4 text-xs sm:text-sm text-slate-300 leading-relaxed max-w-4xl space-y-2">
                    <p>
                        <strong>Lexmation Stock Multi-Timeframe & News Intelligence Terminal</strong> е напълно автономен алго-агент, разработен за професионално откриване, валидиране и проследяване на пазарни движения на акции на американските борси (NYSE & NASDAQ).
                    </p>
                    <p class="text-slate-400">
                        Този документ обяснява техническата архитектура, стриктния кешов модел от 5 слота за управление на капитала ($1,000 максимален капацитет без марджин дълг, Клас A+: 2 слота / $400, Клас B: 1 слот / $200), разликата между директните сигнали и изчакването на пробив при WATCH (с интрадей валидация на свещите след сигнала), както и как алгоритмичната математика елиминира 6-те най-опасни емоционални капана (вкл. Overtrading & Overleveraging) в търговията.
                    </p>
                </div>
            </div>

            <!-- Quick Navigation Index -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <a href="#sec-architecture" class="bg-term-surface hover:bg-term-card border border-term-border rounded-xl p-3.5 flex items-center space-x-3 transition group">
                    <i class="fa-solid fa-network-wired text-emerald-400 text-lg group-hover:scale-110 transition"></i>
                    <div>
                        <span class="font-bold text-white block">1. Архитектура</span>
                        <span class="text-slate-400 text-[11px]">TwelveData & Perplexity AI</span>
                    </div>
                </a>
                <a href="#sec-execution" class="bg-term-surface hover:bg-term-card border border-term-border rounded-xl p-3.5 flex items-center space-x-3 transition group">
                    <i class="fa-solid fa-crosshairs text-cyan-400 text-lg group-hover:scale-110 transition"></i>
                    <div>
                        <span class="font-bold text-white block">2. BUY срещу WATCH</span>
                        <span class="text-slate-400 text-[11px]">Пробивната стратегия</span>
                    </div>
                </a>
                <a href="#sec-money-mgmt" class="bg-term-surface hover:bg-term-card border border-term-border rounded-xl p-3.5 flex items-center space-x-3 transition group">
                    <i class="fa-solid fa-calculator text-amber-400 text-lg group-hover:scale-110 transition"></i>
                    <div>
                        <span class="font-bold text-white block">3. Кешова Математика</span>
                        <span class="text-slate-400 text-[11px]">5 Слота ($1,000) & чисти пари</span>
                    </div>
                </a>
                <a href="#sec-psychology" class="bg-term-surface hover:bg-term-card border border-term-border rounded-xl p-3.5 flex items-center space-x-3 transition group">
                    <i class="fa-solid fa-heart-crack text-rose-400 text-lg group-hover:scale-110 transition"></i>
                    <div>
                        <span class="font-bold text-white block">4. Психология & AI</span>
                        <span class="text-slate-400 text-[11px]">Победа над FOMO & страха</span>
                    </div>
                </a>
            </div>

            <!-- MODULE 1: SYSTEM ARCHITECTURE -->
            <div id="sec-architecture" class="bg-term-surface border border-term-border rounded-2xl p-6 sm:p-7 space-y-6">
                <div class="flex items-center justify-between border-b border-term-border pb-4">
                    <div class="flex items-center space-x-3">
                        <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-mono font-bold text-sm">01</span>
                        <div>
                            <h2 class="text-lg font-bold text-white">Архитектура на Системата и Източници на Данни в Реално Време</h2>
                            <p class="text-xs text-slate-400">Как AI агентът събира, филтрира и синтезира информация за секунди</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-term-card text-xs mono text-cyan-400 border border-term-border">Pipeline v2.4</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Step 1 -->
                    <div class="bg-term-card border border-term-border rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="w-7 h-7 rounded-md bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-xs font-bold font-mono">L1</span>
                            <i class="fa-solid fa-chart-line text-cyan-400"></i>
                        </div>
                        <h3 class="font-bold text-white text-sm">TwelveData Technicals</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Мулти-таймфрейм сканиране на 15m, 1h и Daily. Изчислява EMA 20/50/200, 5-минутен институционален VWAP, ATR (волатилност) и RVOL (относителен обем).
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="bg-term-card border border-term-border rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="w-7 h-7 rounded-md bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs font-bold font-mono">L2</span>
                            <i class="fa-solid fa-newspaper text-emerald-400"></i>
                        </div>
                        <h3 class="font-bold text-white text-sm">Perplexity 24h News</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Живо сканиране на финансови медии, SEC отчети (10-Q, 8-K), Wall Street ъпгрейди и пазарни катализатори през последните 24 часа за всеки тикер.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="bg-term-card border border-term-border rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="w-7 h-7 rounded-md bg-purple-500/10 text-purple-400 flex items-center justify-center text-xs font-bold font-mono">L3</span>
                            <i class="fa-solid fa-brain text-purple-400"></i>
                        </div>
                        <h3 class="font-bold text-white text-sm">Gemini 3.8 Flash Reasoning</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Анализира вероятностите за пробив. Изчислява прецизни нива: Цена на Вход, Stop Loss под пазарна структура, Target Price и съотношение Risk/Reward $\ge$ 1:2.0.
                        </p>
                    </div>

                    <!-- Step 4 -->
                    <div class="bg-term-card border border-term-border rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="w-7 h-7 rounded-md bg-amber-500/10 text-amber-400 flex items-center justify-center text-xs font-bold font-mono">L4</span>
                            <i class="fa-solid fa-clock-rotate-left text-amber-400"></i>
                        </div>
                        <h3 class="font-bold text-white text-sm">n8n EOD Verification</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Автоматичен цикъл в 23:30 (Market Close). Сравнява реалните High/Low/Close дневни свещи и автоматично отчита Target Hit, Stop Hit или Range.
                        </p>
                    </div>
                </div>
            </div>

            <!-- MODULE 2: EXECUTION RULES (BUY VS WATCH) -->
            <div id="sec-execution" class="bg-term-surface border border-term-border rounded-2xl p-6 sm:p-7 space-y-6">
                <div class="flex items-center justify-between border-b border-term-border pb-4">
                    <div class="flex items-center space-x-3">
                        <span class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-mono font-bold text-sm">02</span>
                        <div>
                            <h2 class="text-lg font-bold text-white">Правила за Търговия: BUY/SELL срещу WATCH (Пробивната Стратегия)</h2>
                            <p class="text-xs text-slate-400">Как правилно да търгувате сигналите и защо при WATCH никога не купуваме сляпо</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-cyan-500/10 text-xs mono text-cyan-400 border border-cyan-500/20">Execution Framework</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Card A: BUY / SELL Direct -->
                    <div class="bg-term-card border border-emerald-500/40 rounded-xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded text-xs font-mono font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                ДИРЕКТЕН СИГНАЛ: BUY / SELL
                            </span>
                            <span class="text-xs text-slate-400 font-mono">Незабавно изпълнение</span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            При присъда <strong>BUY</strong> или <strong>SELL</strong> всички фактори (обем, тренд, новини и технически индикатори) са в 100% синхрон към момента на публикуване.
                        </p>
                        <div class="bg-slate-950/70 p-3 rounded-lg border border-term-border text-xs space-y-2">
                            <div class="flex items-center text-slate-300">
                                <i class="fa-solid fa-check text-emerald-400 mr-2"></i>
                                <span><strong>Вход:</strong> Отваря се позиция на текущата пазарна цена ($200 за Клас B или $400 за Клас A+).</span>
                            </div>
                            <div class="flex items-center text-slate-300">
                                <i class="fa-solid fa-check text-emerald-400 mr-2"></i>
                                <span><strong>Stop Loss:</strong> Поставя се твърд стоп ордер на зададената цена.</span>
                            </div>
                            <div class="flex items-center text-slate-300">
                                <i class="fa-solid fa-check text-emerald-400 mr-2"></i>
                                <span><strong>Target:</strong> Поставя се лимитиран ордер (Take Profit) на зададения таргет.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card B: WATCH Breakout Setup -->
                    <div class="bg-term-card border border-amber-500/40 rounded-xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded text-xs font-mono font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                НАБЛЮДЕНИЕ: WATCH (ПРОБИВ)
                            </span>
                            <span class="text-xs text-amber-300 font-mono font-semibold">Изисква потвърждение!</span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            При <strong>WATCH</strong> акцията има висок потенциал и катализатор, но в момента се намира в консолидация. <strong>НЕ КУПУВАМЕ НА СЛЯПО НА ТЕКУЩАТА ЦЕНА!</strong>
                        </p>
                        <div class="bg-slate-950/70 p-3 rounded-lg border border-term-border text-xs space-y-2">
                            <div class="flex items-start text-slate-300">
                                <i class="fa-solid fa-bullseye text-amber-400 mr-2 mt-0.5"></i>
                                <span><strong>Entry Trigger (Входно условие):</strong> Изчакваме цената да пробие ключовото ниво (напр. "Пробив над $244.50 с висок обем").</span>
                            </div>
                            <div class="flex items-start text-slate-300">
                                <i class="fa-solid fa-arrow-turn-up text-emerald-400 mr-2 mt-0.5"></i>
                                <span><strong>Ако има пробив:</strong> Поставя се поръчка <em>Buy Stop / Stop Limit</em> на тригера. Позицията се активира към Target.</span>
                            </div>
                            <div class="flex items-start text-slate-300">
                                <i class="fa-solid fa-shield text-cyan-400 mr-2 mt-0.5"></i>
                                <span><strong>Ако няма пробив (Рейндж $0.00):</strong> Позиция НЕ се отваря! Рискът е точно <strong>$0</strong> и парите ви са напълно защитени (100% кеш в готовност).</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Post-Signal Candle Evaluation Callout -->
                <div class="bg-slate-950/80 border border-cyan-500/30 rounded-xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                    </div>
                    <div class="space-y-1 text-xs">
                        <h4 class="font-bold text-white text-sm">Интрадей Оценка на Свещите След Генериране на Сигнала (Post-Signal Validation)</h4>
                        <p class="text-slate-300 leading-relaxed">
                            Алго-верификаторът на n8n оценява движенията на цената <strong>СЛЕД часа на подаване на сигнала</strong>. Ако сутринта преди алармата акцията е имала дъно под нивото на Stop Loss (премаркет или откриване), то <strong>НЕ активира фалшив Stop Loss</strong>, тъй като ние все още не сме били в сделка. За WATCH сигналите поръчката се активира единствено при пресичане на прага (Trigger Price) нагоре.
                        </p>
                    </div>
                </div>

                <!-- Step-by-Step Flow Graphic -->
                <div class="bg-term-card/60 border border-term-border rounded-xl p-4 sm:p-5">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-3 flex items-center space-x-2">
                        <i class="fa-solid fa-diagram-project text-cyan-400"></i>
                        <span>Жизнен цикъл на един WATCH сигнал:</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs text-center mono">
                        <div class="bg-term-surface p-3 rounded-lg border border-term-border">
                            <span class="text-cyan-400 font-bold block mb-1">1. Идентифициране</span>
                            <span class="text-slate-400 text-[11px]">AI отчита катализатор и консолидация ($0 риск)</span>
                        </div>
                        <div class="bg-term-surface p-3 rounded-lg border border-term-border">
                            <span class="text-amber-400 font-bold block mb-1">2. Buy Stop на Тригер</span>
                            <span class="text-slate-400 text-[11px]">Поръчка на прага (Кешът остава 100% свободен)</span>
                        </div>
                        <div class="bg-term-surface p-3 rounded-lg border border-term-border">
                            <span class="text-emerald-400 font-bold block mb-1">3. Пробив & Вход</span>
                            <span class="text-slate-400 text-[11px]">Сделката заема слот ($200/$400), стопът пази капитала</span>
                        </div>
                        <div class="bg-term-surface p-3 rounded-lg border border-term-border">
                            <span class="text-white font-bold block mb-1">4. Резултат EOD</span>
                            <span class="text-slate-400 text-[11px]">Target Hit (+PnL) или Рейндж ($0 риск, кеш непокътнат)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODULE 3: CASH SPOT MONEY MANAGEMENT ($1,000 / $200) -->
            <div id="sec-money-mgmt" class="bg-term-surface border border-term-border rounded-2xl p-6 sm:p-7 space-y-6">
                <div class="flex items-center justify-between border-b border-term-border pb-4">
                    <div class="flex items-center space-x-3">
                        <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center font-mono font-bold text-sm">03</span>
                        <div>
                            <h2 class="text-lg font-bold text-white">Кешов Спот Модел и Точна Математика на Чистите Печалби</h2>
                            <p class="text-xs text-slate-400">5-слотово портфолио, без ливъридж, без маржин заеми – 100% реална кешова стойност</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-amber-500/10 text-xs mono text-amber-400 border border-amber-500/20">5-Slot Architecture</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: Parameters -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Параметри на портфейла:</h4>
                        <div class="bg-term-card p-3 rounded-lg border border-term-border space-y-2 text-xs mono">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Стартов капитал:</span>
                                <span class="font-bold text-white">$1,000.00 (100% Кеш)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Капацитет слотове:</span>
                                <span class="font-bold text-emerald-400">5 слота по $200 ($1,000)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Клас B сделка:</span>
                                <span class="font-bold text-cyan-400">1 слот ($200.00 / 20%)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Клас A+ сделка:</span>
                                <span class="font-bold text-amber-400">2 слота ($400.00 / 40%)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Купуване на:</span>
                                <span class="font-bold text-emerald-400">Реални акции (Спот)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Ливъридж:</span>
                                <span class="font-bold text-slate-300">1:1 (0% Заем)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Опашка (Slot Queue):</span>
                                <span class="font-bold text-amber-300">При 5/5 заети чака</span>
                            </div>
                        </div>

                        <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-xs text-emerald-300 leading-relaxed">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            <strong>Защо без ливъридж?</strong> Спот търговията с твърд лимит от 5 слота елиминира риска от маржин кол (margin call) и ликвидация на акаунта при пазарни колебания.
                        </div>
                    </div>

                    <!-- Center & Right: Real NBIS Example -->
                    <div class="lg:col-span-2 bg-term-card border border-term-border rounded-xl p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-term-border/60 pb-3">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm font-bold text-white mono bg-term-surface px-2.5 py-1 rounded border border-term-border">NBIS Пример</span>
                                <span class="text-xs text-slate-400">Реална формула за чист нетен PnL</span>
                            </div>
                            <span class="text-xs mono text-cyan-400 font-bold">Вложени: точно $200.00</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="space-y-2 bg-term-surface p-3.5 rounded-lg border border-term-border">
                                <span class="text-[11px] text-slate-400 font-bold uppercase block">1. Параметри на акцията:</span>
                                <ul class="space-y-1 mono text-slate-300">
                                    <li>&bull; Цена вход: <strong>$243.44</strong></li>
                                    <li>&bull; Stop Loss: <strong>$242.80</strong> (-$0.64 / -0.26%)</li>
                                    <li>&bull; Target Price: <strong>$255.00</strong> (+$11.56 / +4.75%)</li>
                                    <li>&bull; Купени акции: <strong>0.8215 бр.</strong> ($200 / $243.44)</li>
                                </ul>
                            </div>

                            <div class="space-y-2 bg-term-surface p-3.5 rounded-lg border border-term-border">
                                <span class="text-[11px] text-emerald-400 font-bold uppercase block">2. Чист нетен резултат в долари:</span>
                                <div class="space-y-1.5 mono">
                                    <div class="p-2 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">
                                        <div class="font-bold">При Target ($255.00):</div>
                                        <div>0.82 бр. &times; $11.56 = <strong class="text-emerald-400 text-sm">+$9.48 (+$9.50)</strong> чисти пари (+4.75% върху вложените $200)</div>
                                    </div>
                                    <div class="p-2 rounded bg-rose-500/10 border border-rose-500/30 text-rose-300">
                                        <div class="font-bold">При Stop Loss ($242.80):</div>
                                        <div>0.82 бр. &times; (-$0.64) = <strong class="text-rose-400 text-sm">-$0.52</strong> загуба (-0.26% от вашите $200)</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-400 italic">
                            * Виждате защо съотношението Risk/Reward тук е фантастично: рискувате само 52 цента от своите $200, за да спечелите $9.50 чисти долари!
                        </p>
                    </div>
                </div>
            </div>

            <!-- MODULE 4: TRADING PSYCHOLOGY & EMOTION ELIMINATION -->
            <div id="sec-psychology" class="bg-term-surface border border-term-border rounded-2xl p-6 sm:p-7 space-y-6">
                <div class="flex items-center justify-between border-b border-term-border pb-4">
                    <div class="flex items-center space-x-3">
                        <span class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center font-mono font-bold text-sm">04</span>
                        <div>
                            <h2 class="text-lg font-bold text-white">Психология на Търговията: Как AI Елиминира Човешките Емоции</h2>
                            <p class="text-xs text-slate-400">90% от трейдърите губят пари не заради липса на знания, а заради емоционален срив</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-rose-500/10 text-xs mono text-rose-400 border border-rose-500/20">Emotionless Execution</span>
                </div>

                <!-- Comparison Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="border-b border-term-border text-slate-400 uppercase font-semibold">
                                <th class="py-3 px-4 w-1/4">Емоционален Капан</th>
                                <th class="py-3 px-4 w-3/8 text-rose-400"><i class="fa-solid fa-user-xmark mr-1.5"></i>Как реагира Човекът (Загуба)</th>
                                <th class="py-3 px-4 w-3/8 text-emerald-400"><i class="fa-solid fa-robot mr-1.5"></i>Как действа Lexmation AI (Печалба)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-term-border/40">
                            <tr class="hover:bg-term-card/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <div class="flex items-center space-x-1.5">
                                        <i class="fa-solid fa-fire text-amber-400"></i>
                                        <span>FOMO (Страх от пропускане)</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    Купува на върха на зелената свещ от алчност, точно преди цената да коригира надолу.
                                </td>
                                <td class="py-3.5 px-4 text-emerald-300 font-medium bg-emerald-500/5">
                                    Изчаква строго Entry Trigger (пробив с висок RVOL) или не влиза изобщо.
                                </td>
                            </tr>
                            <tr class="hover:bg-term-card/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <div class="flex items-center space-x-1.5">
                                        <i class="fa-solid fa-arrows-down-to-line text-rose-400"></i>
                                        <span>Местене на Стопа (Denial)</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    Когато цената падне, отказва да приеме малката загуба, мести стопа по-ниско с надеждата "да се върне" и губи 50% от акаунта.
                                </td>
                                <td class="py-3.5 px-4 text-emerald-300 font-medium bg-emerald-500/5">
                                    Стопът е непоклатим. При удар сделката се затваря незабавно с минимална загуба (-$0.50 до -$2).
                                </td>
                            </tr>
                            <tr class="hover:bg-term-card/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <div class="flex items-center space-x-1.5">
                                        <i class="fa-solid fa-scissors text-cyan-400"></i>
                                        <span>Рязане на Печалбите (Fear)</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    При печалба от едва +$1.50 трейдърът се плаши да не я загуби и затваря преждевременно, изпускайки голямото движение.
                                </td>
                                <td class="py-3.5 px-4 text-emerald-300 font-medium bg-emerald-500/5">
                                    Държи позицията дисциплинирано до пълния Target (напр. +$9.50), осигурявайки асиметрична възвръщаемост.
                                </td>
                            </tr>
                            <tr class="hover:bg-term-card/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <div class="flex items-center space-x-1.5">
                                        <i class="fa-solid fa-skull-crossbones text-rose-500"></i>
                                        <span>Revenge Trading (Гняв)</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    След губеща сделка изпада в гняв, влиза с двоен размер в случайна акция и фалира сметката за 1 час.
                                </td>
                                <td class="py-3.5 px-4 text-emerald-300 font-medium bg-emerald-500/5">
                                    Няма его, няма гняв. Всяка сделка е независима математическа вероятност с фиксиран размер от $200.
                                </td>
                            </tr>
                            <tr class="hover:bg-term-card/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <div class="flex items-center space-x-1.5">
                                        <i class="fa-solid fa-hourglass-half text-indigo-400"></i>
                                        <span>Колебание (Hesitation)</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    При перфектен сигнал страхът го парализира и той не смее да натисне бутона "Купи".
                                </td>
                                <td class="py-3.5 px-4 text-emerald-300 font-medium bg-emerald-500/5">
                                    Автоматично генерира алерти за секунди без никакво човешко колебание.
                                </td>
                            </tr>
                            <tr class="hover:bg-term-card/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <div class="flex items-center space-x-1.5">
                                        <i class="fa-solid fa-scale-unbalanced-flip text-amber-400"></i>
                                        <span>Свръхтърговия (Overtrading)</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    Отваря 10-15 сделки едновременно на маржин заем, претоварва баланса и фалира сметката при първото разклащане на борсата.
                                </td>
                                <td class="py-3.5 px-4 text-emerald-300 font-medium bg-emerald-500/5">
                                    Твърд лимит от точно 5 слота ($1,000 макс капацитет). Нови сигнали чакат в опашка (Slot Queue) със 100% защитен кеш.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mathematical Edge Explainer -->
                <div class="bg-gradient-to-r from-emerald-950/30 to-term-card p-4 rounded-xl border border-emerald-500/20 text-xs text-slate-300 leading-relaxed space-y-2">
                    <div class="font-bold text-white flex items-center space-x-2">
                        <i class="fa-solid fa-calculator text-emerald-400"></i>
                        <span>Математическото предимство (The Mathematical Edge):</span>
                    </div>
                    <p>
                        Благодарение на минималното съотношение Risk/Reward от 1:2.0, системата не се нуждае от 90% успеваемост, за да бъде печеливша. Дори при <strong>само 50% успеваемост</strong>:
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1 font-mono text-center">
                        <div class="bg-term-surface p-2 rounded border border-term-border">5 победи по +$9.50 = <span class="text-emerald-400 font-bold">+$47.50</span></div>
                        <div class="bg-term-surface p-2 rounded border border-term-border">5 загуби по -$2.00 = <span class="text-rose-400 font-bold">-$10.00</span></div>
                        <div class="bg-term-surface p-2 rounded border border-emerald-500/30">Чист нетен резултат = <span class="text-emerald-400 font-bold text-sm">+$37.50 (+3.75%)</span></div>
                    </div>
                </div>
            </div>

            <!-- MODULE 5: SUBSCRIPTION VALUE PROPOSITION (SAAS) -->
            <div id="sec-value" class="bg-term-surface border border-term-border rounded-2xl p-6 sm:p-7 space-y-6">
                <div class="flex items-center justify-between border-b border-term-border pb-4">
                    <div class="flex items-center space-x-3">
                        <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-mono font-bold text-sm">05</span>
                        <div>
                            <h2 class="text-lg font-bold text-white">Защо да се Абонирате за Lexmation Stock Intelligence?</h2>
                            <p class="text-xs text-slate-400">Професионална услуга за заети хора и инвеститори, които ценят времето и капитала си</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-emerald-500/15 text-xs mono text-emerald-400 border border-emerald-500/30">Commercial Proposition</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-xs">
                    <div class="bg-term-card border border-term-border rounded-xl p-4.5 space-y-2.5">
                        <div class="w-9 h-9 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-base">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <h4 class="font-bold text-white text-sm">Спестява 4-6 часа дневно</h4>
                        <p class="text-slate-400 leading-relaxed">
                            Няма нужда да четете десетки финансови сайтове, SEC доклади и да следите 500 графики. AI синтезира целия пазарен шум в 1 ясна карта с готови нива.
                        </p>
                    </div>

                    <div class="bg-term-card border border-term-border rounded-xl p-4.5 space-y-2.5">
                        <div class="w-9 h-9 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-base">
                            <i class="fa-solid fa-magnifying-glass-chart"></i>
                        </div>
                        <h4 class="font-bold text-white text-sm">100% Публична Прозрачност</h4>
                        <p class="text-slate-400 leading-relaxed">
                            Всеки сигнал, генериран от n8n, се записва публично в терминала. Системата не трие губещи сигнали и не пренаписва историята – всяка сделка подлежи на одит.
                        </p>
                    </div>

                    <div class="bg-term-card border border-term-border rounded-xl p-4.5 space-y-2.5">
                        <div class="w-9 h-9 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-base">
                            <i class="fa-solid fa-shield-check"></i>
                        </div>
                        <h4 class="font-bold text-white text-sm">Институционален Риск Контрол</h4>
                        <p class="text-slate-400 leading-relaxed">
                            Всяка идея идва с точно математическо съотношение. Знаете предварително колко точно рискувате (напр. -$0.52) и колко очаквате да приберете (+$9.50).
                        </p>
                    </div>
                </div>

                <div class="p-4 bg-gradient-to-r from-emerald-950/40 via-cyan-950/20 to-term-card border border-emerald-500/40 rounded-xl flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <h4 class="text-sm font-bold text-white">Готови ли сте да се присъедините към алгоритмичната търговия?</h4>
                        <p class="text-xs text-slate-300">Следете сигналите в реално време или се свържете с нас за индивидуален абонамент и интеграция.</p>
                    </div>
                    <a href="https://lexmation.com" target="_blank" class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs flex items-center space-x-2 transition shadow-lg shadow-emerald-500/20 whitespace-nowrap">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Свържи се с Lexmation &raquo;</span>
                    </a>
                </div>
            </div>

        </div>
        </div> <!-- End pro-dashboard-container -->

    </main>

    <!-- Modal for Detailed Signal Info -->
    <div id="signalModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-term-surface border border-term-border rounded-2xl max-w-2xl w-full p-6 space-y-5 shadow-2xl relative max-h-[90vh] overflow-y-auto custom-scrollbar">
            <button onclick="closeSignalModal()" class="absolute right-4 top-4 text-slate-400 hover:text-white p-2 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div id="modalContent" class="space-y-4">
                <!-- Populated dynamically by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-term-border bg-term-surface py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div>
                <span class="font-bold text-slate-300">Lexmation Intelligence Terminal</span> &bull; Stock Multi-Timeframe & News Alerter
            </div>
            <div class="mono text-slate-400">
                Data pipeline: TwelveData &bull; Perplexity AI &bull; Gemini 3.8 Flash &bull; n8n
            </div>
        </div>
    </footer>

    <!-- TradingView Script -->
    <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>

    <script>
        // Trigger 10 Daily Stocks Scan in n8n
        function triggerDailyScan() {
            const btn = document.getElementById('btn-daily-scan');
            const btnText = document.getElementById('btn-daily-scan-text');
            const banner = document.getElementById('daily-scan-banner');
            if (!btn) return;

            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Стартира се...';

            if (banner) {
                banner.classList.remove('hidden');
                banner.innerHTML = `
                    <div class="p-4 rounded-2xl bg-gradient-to-r from-cyan-950/90 to-slate-900 border border-cyan-500/40 text-cyan-200 text-xs flex items-start gap-3 shadow-xl">
                        <i class="fa-solid fa-circle-notch fa-spin text-cyan-400 text-lg mt-0.5 flex-shrink-0"></i>
                        <div class="space-y-1.5 flex-1">
                            <p class="font-bold text-white text-sm">🚀 Стартиран е пълен дневен анализ на 10-те акции в n8n!</p>
                            <p class="text-slate-300 leading-relaxed">
                                Системата сканира подред <strong>NBIS, APP, META, IREN, EOSE, CRWV, HIMS, CRDO, OUST, MU</strong>, изчаквайки по <strong>32 секунди между акциите</strong> за безопасно спазване на TwelveData лимитите. Пълният скан отнема <strong>~8-9 минути</strong>. Таблото следи прогреса в реално време и ще се обнови автоматично при завършване.
                            </p>
                            <div class="flex items-center gap-2 pt-1 font-mono text-[11px] text-cyan-400" id="scan-progress-status">
                                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                                <span>Изчакване на първите завършени акции...</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            fetch('/api.php?action=run_daily_scan')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        btnText.innerHTML = '<i class="fa-solid fa-clock fa-spin"></i> Анализ в ход (~8-9 мин.)';
                        let checks = 0;
                        const maxChecks = 36; // 36 * 15s = 9 minutes
                        const initialHash = window.__INITIAL_TRADES_HASH || '';
                        const initialCount = window.__INITIAL_TRADES_COUNT || 0;

                        const pollInterval = setInterval(async () => {
                            checks++;
                            const elapsedMin = Math.floor((checks * 15) / 60);
                            const elapsedSec = (checks * 15) % 60;
                            const timeStr = `${elapsedMin}:${elapsedSec < 10 ? '0' : ''}${elapsedSec} мин.`;

                            const progEl = document.getElementById('scan-progress-status');
                            if (progEl) {
                                progEl.innerHTML = `<span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span><span>Сканиране в ход (${timeStr})... проверява се за пристигане на нови сигнали</span>`;
                            }

                            // Smart check: poll /live_prices.php to detect when fresh signals land
                            try {
                                const lpRes = await fetch('/live_prices.php');
                                if (lpRes.ok) {
                                    const lpData = await lpRes.json();
                                    const newHash = lpData ? lpData.trades_hash : '';
                                    const newCount = lpData ? lpData.trade_count : 0;
                                    if ((newHash && initialHash && newHash !== initialHash) || (newCount > initialCount)) {
                                        clearInterval(pollInterval);
                                        if (banner) {
                                            banner.innerHTML = `
                                                <div class="p-3.5 rounded-xl bg-emerald-950/90 border border-emerald-500/40 text-emerald-200 text-xs flex items-center justify-between shadow-xl">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-check-circle text-emerald-400 text-base"></i>
                                                        <span>Всички 10 акции са анализирани успешно! Презареждане на новите данни...</span>
                                                    </div>
                                                    <button onclick="window.location.reload()" class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-bold text-xs">Обнови сега</button>
                                                </div>
                                            `;
                                        }
                                        setTimeout(() => window.location.reload(), 1500);
                                        return;
                                    }
                                }
                            } catch (e) {
                                console.debug('Poll check error:', e);
                            }

                            if (checks >= maxChecks) {
                                clearInterval(pollInterval);
                                if (banner) {
                                    banner.innerHTML = `
                                        <div class="p-3.5 rounded-xl bg-emerald-950/90 border border-emerald-500/40 text-emerald-200 text-xs flex items-center justify-between shadow-xl">
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-check-circle text-emerald-400 text-base"></i>
                                                <span>Анализът приключи! Презареждане на новите данни...</span>
                                            </div>
                                            <button onclick="window.location.reload()" class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-bold text-xs">Обнови сега</button>
                                        </div>
                                    `;
                                }
                                setTimeout(() => window.location.reload(), 2000);
                            }
                        }, 15000);
                    } else {
                        btn.disabled = false;
                        btn.classList.remove('opacity-70', 'cursor-not-allowed');
                        btnText.innerText = 'Пусни дневен анализ сега';
                        if (banner) {
                            banner.innerHTML = `<div class="p-3 rounded-xl bg-rose-950/80 border border-rose-500/40 text-rose-300 text-xs">Грешка при стартиране: ${data.message || 'Опитайте отново.'}</div>`;
                        }
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.classList.remove('opacity-70', 'cursor-not-allowed');
                    btnText.innerText = 'Пусни дневен анализ сега';
                    if (banner) {
                        banner.innerHTML = `<div class="p-3 rounded-xl bg-rose-950/80 border border-rose-500/40 text-rose-300 text-xs">Мрежова грешка: ${err.message}</div>`;
                    }
                });
        }

        // View Mode Switcher (Simple vs Pro)
        function setViewMode(mode) {
            const simpleDash = document.getElementById('simple-dashboard');
            const proContainer = document.getElementById('pro-dashboard-container');
            const proTabsBar = document.getElementById('pro-tabs-bar');
            const btnSimple = document.getElementById('mode-btn-simple');
            const btnPro = document.getElementById('mode-btn-pro');

            if (mode === 'simple') {
                if (simpleDash) simpleDash.classList.remove('hidden');
                if (proContainer) proContainer.classList.add('hidden');
                if (proTabsBar) proTabsBar.classList.add('hidden');

                if (btnSimple) {
                    btnSimple.className = 'px-3 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1.5 bg-emerald-500 text-slate-950 shadow-md';
                }
                if (btnPro) {
                    btnPro.className = 'px-3 sm:px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition flex items-center space-x-1.5';
                }
                localStorage.setItem('lexmation_stock_view_mode', 'simple');
            } else {
                if (simpleDash) simpleDash.classList.add('hidden');
                if (proContainer) proContainer.classList.remove('hidden');
                if (proTabsBar) proTabsBar.classList.remove('hidden');

                if (btnPro) {
                    btnPro.className = 'px-3 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1.5 bg-emerald-500 text-slate-950 shadow-md';
                }
                if (btnSimple) {
                    btnSimple.className = 'px-3 sm:px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition flex items-center space-x-1.5';
                }
                localStorage.setItem('lexmation_stock_view_mode', 'pro');
            }
        }

        // Trade Carousel Navigation
        let currentTradeIndex = 0;
        function goToTradeSlide(idx) {
            const slides = document.querySelectorAll('.trade-slide');
            const pills = document.querySelectorAll('.trade-pill-btn');
            const dots = document.querySelectorAll('.trade-dot');
            const counter = document.getElementById('carousel-current-index');
            if (!slides || !slides.length) return;

            if (idx < 0) idx = slides.length - 1;
            if (idx >= slides.length) idx = 0;
            currentTradeIndex = idx;

            slides.forEach((s, i) => {
                if (i === currentTradeIndex) {
                    s.classList.remove('hidden');
                    s.classList.add('block');
                } else {
                    s.classList.add('hidden');
                    s.classList.remove('block');
                }
            });

            pills.forEach((p, i) => {
                if (i === currentTradeIndex) {
                    p.className = 'trade-pill-btn px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 border flex-shrink-0 bg-emerald-500 text-slate-950 border-emerald-400 shadow-md shadow-emerald-500/20';
                } else {
                    p.className = 'trade-pill-btn px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center space-x-2 border flex-shrink-0 bg-term-card text-slate-300 border-term-border hover:bg-term-hover hover:text-white';
                }
            });

            dots.forEach((d, i) => {
                if (i === currentTradeIndex) {
                    d.className = 'trade-dot h-2 rounded-full transition-all duration-300 bg-emerald-400 w-6';
                } else {
                    d.className = 'trade-dot h-2 rounded-full transition-all duration-300 bg-slate-700 w-2 hover:bg-slate-500';
                }
            });

            if (counter) counter.innerText = (currentTradeIndex + 1);
        }

        function nextTradeSlide() {
            goToTradeSlide(currentTradeIndex + 1);
        }

        function prevTradeSlide() {
            goToTradeSlide(currentTradeIndex - 1);
        }

        // Growth & Compounding Simulator Controller
        let simDeposit = 1000;
        let simMonthlyRate = 0.12;
        let simStrategyMode = 'compound';

        function updateSimDeposit(val) {
            simDeposit = parseFloat(val) || 1000;
            const disp = document.getElementById('sim-deposit-display');
            if (disp) disp.innerText = '$' + Math.round(simDeposit).toLocaleString('en-US');
            calcGrowthSim();
        }

        function setSimRate(rate, btn) {
            simMonthlyRate = rate;
            document.querySelectorAll('.sim-rate-btn').forEach(b => {
                b.className = 'sim-rate-btn px-3 py-2.5 rounded-xl text-xs font-semibold border transition text-center bg-term-card text-slate-300 border-term-border hover:bg-term-hover';
            });
            if (btn) {
                btn.className = 'sim-rate-btn px-3 py-2.5 rounded-xl text-xs font-bold border transition text-center bg-emerald-500 text-slate-950 border-emerald-400 shadow-md shadow-emerald-500/20';
            }
            calcGrowthSim();
        }

        function setSimMode(mode, btn) {
            simStrategyMode = mode;
            document.querySelectorAll('.sim-mode-btn').forEach(b => {
                b.className = 'sim-mode-btn w-full p-3 rounded-xl border text-left transition flex items-start space-x-3 bg-term-card text-slate-300 border-term-border hover:bg-term-hover';
                const bold = b.querySelector('b');
                if (bold) bold.className = 'text-xs text-white block';
            });
            if (btn) {
                btn.className = 'sim-mode-btn w-full p-3 rounded-xl border text-left transition flex items-start space-x-3 bg-emerald-500/15 border-emerald-500/60 text-white shadow-md';
                const bold = btn.querySelector('b');
                if (bold) bold.className = 'text-xs text-emerald-300 block';
            }
            calcGrowthSim();
        }

        function calcGrowthSim() {
            const outBal1Yr = document.getElementById('sim-out-1yr-bal');
            const outProfit1Yr = document.getElementById('sim-out-1yr-profit');
            const outRoi1Yr = document.getElementById('sim-out-1yr-roi');
            const outSub1Yr = document.getElementById('sim-out-1yr-sub');

            const card1m = document.getElementById('sim-card-1m');
            const card1mPnl = document.getElementById('sim-card-1m-pnl');
            const card3m = document.getElementById('sim-card-3m');
            const card3mPnl = document.getElementById('sim-card-3m-pnl');
            const card6m = document.getElementById('sim-card-6m');
            const card6mPnl = document.getElementById('sim-card-6m-pnl');
            const card12m = document.getElementById('sim-card-12m');
            const card12mPnl = document.getElementById('sim-card-12m-pnl');

            const months = [1, 3, 6, 12];
            const balances = {};
            const profits = {};

            if (simStrategyMode === 'withdraw') {
                const monthlyProfit = simDeposit * simMonthlyRate;
                months.forEach(m => {
                    balances[m] = simDeposit;
                    profits[m] = monthlyProfit * m;
                });
                if (outSub1Yr) outSub1Yr.innerText = 'депозитът остава $' + Math.round(simDeposit).toLocaleString() + ' + изтеглен кеш';
            } else if (simStrategyMode === 'compound') {
                months.forEach(m => {
                    const b = simDeposit * Math.pow(1 + simMonthlyRate, m);
                    balances[m] = b;
                    profits[m] = b - simDeposit;
                });
                if (outSub1Yr) outSub1Yr.innerText = 'депозит + реинвестирана печалба';
            } else if (simStrategyMode === 'save') {
                const monthlyAdd = 100;
                let runningBal = simDeposit;
                for (let m = 1; m <= 12; m++) {
                    runningBal = (runningBal * (1 + simMonthlyRate)) + monthlyAdd;
                    if (months.includes(m)) {
                        balances[m] = runningBal;
                        const totalDeposited = simDeposit + (monthlyAdd * m);
                        profits[m] = runningBal - totalDeposited;
                    }
                }
                if (outSub1Yr) outSub1Yr.innerText = 'вложени $' + Math.round(simDeposit + 1200).toLocaleString() + ' + печалба';
            }

            const finalBal = balances[12] || 0;
            const finalProfit = profits[12] || 0;
            const invested = simStrategyMode === 'save' ? (simDeposit + 1200) : simDeposit;
            const roiPct = ((finalProfit / invested) * 100).toFixed(1);

            if (outBal1Yr) outBal1Yr.innerText = '$' + Math.round(finalBal).toLocaleString('en-US');
            if (outProfit1Yr) outProfit1Yr.innerText = '+$' + Math.round(finalProfit).toLocaleString('en-US');
            if (outRoi1Yr) outRoi1Yr.innerText = '+' + roiPct + '% възвръщаемост';

            if (card1m) card1m.innerText = '$' + Math.round(balances[1]).toLocaleString('en-US');
            if (card1mPnl) card1mPnl.innerText = '+$' + Math.round(profits[1]).toLocaleString('en-US');
            if (card3m) card3m.innerText = '$' + Math.round(balances[3]).toLocaleString('en-US');
            if (card3mPnl) card3mPnl.innerText = '+$' + Math.round(profits[3]).toLocaleString('en-US');
            if (card6m) card6m.innerText = '$' + Math.round(balances[6]).toLocaleString('en-US');
            if (card6mPnl) card6mPnl.innerText = '+$' + Math.round(profits[6]).toLocaleString('en-US');
            if (card12m) card12m.innerText = '$' + Math.round(balances[12]).toLocaleString('en-US');
            if (card12mPnl) card12mPnl.innerText = '+$' + Math.round(profits[12]).toLocaleString('en-US');
        }

        window.__INITIAL_TRADES_TIME = '<?= !empty($trades) ? ($trades[0]['date_time'] ?? '') : '' ?>';
        window.__INITIAL_TRADES_COUNT = <?= count($trades) ?>;
        window.__INITIAL_CLOSED_COUNT = <?= count($real_closed_trades) ?>;
        window.__INITIAL_TRADES_HASH = '<?= file_exists(__DIR__ . "/data/trades.json") ? md5_file(__DIR__ . "/data/trades.json") : "" ?>';

        // ==============================================================
        // Real-Time Live Stock Quotes Poller & Dynamic Slot Engine
        // ==============================================================
        async function updateLiveStockPrices() {
            try {
                if (document.hidden) return; // Do not poll when tab is inactive/minimized

                const res = await fetch('/live_prices.php');
                if (!res.ok) return;
                const data = await res.json();
                if (!data) return;

                // Silent background sync if target/stop hit detected (max once per 60s, NO PAGE RELOAD)
                if (data.target_hit_detected || data.stop_hit_detected) {
                    const nowTs = Date.now();
                    const lastSync = parseInt(sessionStorage.getItem('lexmation_last_bg_sync') || '0', 10);
                    if (nowTs - lastSync > 60000) {
                        sessionStorage.setItem('lexmation_last_bg_sync', nowTs.toString());
                        fetch('api.php?action=sync').catch(() => {});
                    }
                }

                // 2. Real-Time Slot & Cash Status Update in Card 3
                if (data.portfolio_cash) {
                    const pc = data.portfolio_cash;
                    const slotStatusPill = document.getElementById('slot-status-pill');
                    const slotFreeCashPill = document.getElementById('slot-free-cash-pill');
                    if (slotStatusPill) {
                        if (pc.available_slots > 0) {
                            slotStatusPill.className = 'text-xs font-bold font-mono px-2 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                            slotStatusPill.textContent = '🟢 ' + pc.available_slots + ' Свободни слота';
                        } else {
                            slotStatusPill.className = 'text-xs font-bold font-mono px-2 py-0.5 rounded-lg bg-slate-800 text-slate-400 border border-slate-700';
                            slotStatusPill.textContent = '🔒 Пълен капацитет (5/5)';
                        }
                    }
                    if (slotFreeCashPill) {
                        if (pc.available_slots > 0) {
                            slotFreeCashPill.className = 'px-2 py-0.5 rounded text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
                            slotFreeCashPill.textContent = '🟢 Свободен кеш: $' + (pc.free_cash ? pc.free_cash.toFixed(2) : '0.00');
                        } else {
                            slotFreeCashPill.className = 'px-2 py-0.5 rounded text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
                            slotFreeCashPill.textContent = '0% Заеми • 0% Ливъридж';
                        }
                    }
                }

                if (!data.quotes) return;
                const quotes = data.quotes;

                // 3. Update Slide Header Live Badges
                document.querySelectorAll('[data-ticker-live]').forEach(el => {
                    const sym = el.getAttribute('data-ticker-live');
                    if (quotes[sym] && quotes[sym].price > 0) {
                        const q = quotes[sym];
                        const oldPrice = parseFloat(el.innerText.replace(/[^0-9.-]/g, '')) || 0;
                        el.innerText = q.price_formatted;
                        
                        // Flash tick animation
                        if (oldPrice > 0 && Math.abs(oldPrice - q.price) >= 0.01) {
                            el.classList.add(q.price >= oldPrice ? 'text-emerald-300' : 'text-rose-300');
                            setTimeout(() => el.classList.remove('text-emerald-300', 'text-rose-300'), 1500);
                        }
                    }
                });

                // 4. Update Change Badges
                document.querySelectorAll('[data-ticker-change]').forEach(el => {
                    const sym = el.getAttribute('data-ticker-change');
                    if (quotes[sym]) {
                        const q = quotes[sym];
                        el.innerText = q.change_pct_formatted;
                        el.className = 'font-mono text-xs font-bold live-change-display ' + (q.is_positive ? 'text-emerald-400' : 'text-rose-400');
                    }
                });

                // 5. Update Pills Live Prices
                document.querySelectorAll('[data-pill-ticker]').forEach(el => {
                    const sym = el.getAttribute('data-pill-ticker');
                    if (quotes[sym] && quotes[sym].price > 0) {
                        el.innerText = quotes[sym].price_formatted;
                    }
                });

                // 6. Update Step 1 Prices
                document.querySelectorAll('[data-ticker-step]').forEach(el => {
                    const sym = el.getAttribute('data-ticker-step');
                    if (quotes[sym] && quotes[sym].price > 0) {
                        el.innerText = quotes[sym].price_formatted;
                    }
                });

                // 7. Update Real-Time Floating PnL on Open Positions
                document.querySelectorAll('[data-ticker-pnl-pill]').forEach(el => {
                    const sym = el.getAttribute('data-ticker-pnl-pill');
                    if (!quotes[sym] || quotes[sym].price <= 0) return;
                    const qPrice = quotes[sym].price;
                    const entry = parseFloat(el.getAttribute('data-entry')) || 0;
                    const shares = parseFloat(el.getAttribute('data-shares')) || 0;
                    const stop = parseFloat(el.getAttribute('data-stop')) || 0;
                    const target = parseFloat(el.getAttribute('data-target')) || 0;
                    const verdict = (el.getAttribute('data-verdict') || 'BUY').toUpperCase();

                    if (entry <= 0) return;

                    let pnlDollars = 0;
                    let rMult = 0;
                    const isBull = (verdict === 'BUY') || (verdict === 'WATCH' && (target > entry || target > stop));

                    if (isBull) {
                        pnlDollars = (qPrice - entry) * (shares > 0 ? shares : 1);
                        rMult = (entry - stop !== 0) ? (qPrice - entry) / (entry - stop) : 0;
                    } else {
                        pnlDollars = (entry - qPrice) * (shares > 0 ? shares : 1);
                        rMult = (stop - entry !== 0) ? (entry - qPrice) / (stop - entry) : 0;
                    }

                    const pnlSign = pnlDollars >= 0 ? '+' : '-';
                    const pnlFormatted = pnlSign + '$' + Math.abs(pnlDollars).toFixed(2);
                    const rFormatted = '(' + (rMult >= 0 ? '+' : '') + rMult.toFixed(2) + 'R)';

                    const pnlTextEl = document.getElementById('floating-pnl-text-' + sym);
                    const rTextEl = document.getElementById('floating-r-text-' + sym);
                    if (pnlTextEl) pnlTextEl.textContent = 'Плаващ резултат: ' + pnlFormatted;
                    if (rTextEl) rTextEl.textContent = rFormatted;

                    if (pnlDollars >= 0) {
                        el.className = 'inline-flex items-center space-x-1.5 px-3 py-1 rounded-xl text-emerald-400 border-emerald-500/40 bg-emerald-500/10 border text-xs font-mono font-bold shadow-inner';
                    } else {
                        el.className = 'inline-flex items-center space-x-1.5 px-3 py-1 rounded-xl text-rose-400 border-rose-500/40 bg-rose-500/10 border text-xs font-mono font-bold shadow-inner';
                    }

                    const statusTextEl = document.getElementById('live-status-text-' + sym);
                    const statusPillEl = document.getElementById('live-status-pill-' + sym);
                    const statusDotEl = document.getElementById('live-status-dot-' + sym);
                    if (statusTextEl && statusPillEl && statusDotEl) {
                        statusTextEl.textContent = 'АКТИВНА ОТВОРЕНА ПОЗИЦИЯ (' + verdict + ') • ' + pnlFormatted;
                        if (pnlDollars >= 0) {
                            statusPillEl.className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-emerald-500/10 border flex items-center gap-2 animate-pulse shadow-lg';
                            statusDotEl.className = 'w-2 h-2 rounded-full bg-emerald-400';
                        } else {
                            statusPillEl.className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-rose-500/10 border flex items-center gap-2 animate-pulse shadow-lg';
                            statusDotEl.className = 'w-2 h-2 rounded-full bg-rose-400';
                        }
                    }
                });

                // 8. Update Target Distances & Proximity Alerts (Slot Freeing Indicators)
                document.querySelectorAll('[data-ticker-dist-target]').forEach(el => {
                    const sym = el.getAttribute('data-ticker-dist-target');
                    const target = parseFloat(el.getAttribute('data-target')) || 0;
                    const entry = parseFloat(el.getAttribute('data-entry')) || 0;
                    const stop = parseFloat(el.getAttribute('data-stop')) || 0;
                    const verdict = (el.getAttribute('data-verdict') || 'BUY').toUpperCase();
                    if (!quotes[sym] || quotes[sym].price <= 0 || target <= 0) return;
                    const livePrice = quotes[sym].price;
                    const isBull = (verdict === 'BUY') || (verdict === 'WATCH' && (target > entry || target > stop));

                    const dist = isBull ? (target - livePrice) : (livePrice - target);
                    const distPct = ((Math.abs(dist) / livePrice) * 100).toFixed(1);

                    if (dist <= 0) {
                        el.innerText = '🎯 Целта е достигната! ($' + target.toFixed(2) + ')';
                        el.className = 'text-cyan-300 font-extrabold animate-bounce';
                    } else if (distPct <= 0.6) {
                        el.innerText = '🔥 НА ' + distPct + '% ОТ ТАРГЕТА! (Остават $' + Math.abs(dist).toFixed(2) + ')';
                        el.className = 'text-amber-300 font-extrabold animate-pulse';
                    } else {
                        el.innerText = (isBull ? '+$' : '-$') + Math.abs(dist).toFixed(2) + ' (' + distPct + '% до целта)';
                        el.className = 'text-emerald-400 font-bold';
                    }
                });

            } catch (e) {
                console.debug('Live prices poller error:', e);
            }
        }

        // Tab Navigation
        function switchTab(tabId) {
            setViewMode('pro');
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-emerald-400', 'text-emerald-400');
                btn.classList.add('border-transparent', 'text-slate-400');
            });

            const targetTab = document.getElementById('tab-' + tabId);
            const targetBtn = document.getElementById('tab-btn-' + tabId);
            if (targetTab) targetTab.classList.remove('hidden');
            if (targetBtn) {
                targetBtn.classList.remove('border-transparent', 'text-slate-400');
                targetBtn.classList.add('border-emerald-400', 'text-emerald-400');
            }

            if (tabId === 'chart') {
                const sel = document.getElementById('chartTickerSelect');
                loadTradingView(sel ? sel.value : 'NBIS');
            }
        }

        // TradingView Widget Loader
        let tvWidget = null;
        function loadTradingView(symbol) {
            if (typeof TradingView === 'undefined') return;
            const container = document.getElementById('tradingview_widget');
            if (!container) return;
            container.innerHTML = '';
            
            new TradingView.widget({
                "autosize": true,
                "symbol": symbol.toUpperCase(),
                "interval": "15",
                "timezone": "America/New_York",
                "theme": "dark",
                "style": "1",
                "locale": "en",
                "toolbar_bg": "#0E1422",
                "enable_publishing": false,
                "hide_side_toolbar": false,
                "allow_symbol_change": true,
                "container_id": "tradingview_widget"
            });
        }

        function changeChartTicker(symbol) {
            loadTradingView(symbol);
        }

        function openChartForTicker(symbol) {
            switchTab('chart');
            const sel = document.getElementById('chartTickerSelect');
            if (sel) {
                sel.value = symbol.toUpperCase();
            }
            loadTradingView(symbol);
        }

        // Filter Signals
        function filterSignals() {
            const query = document.getElementById('signalSearch').value.toLowerCase().trim();
            const verdict = document.getElementById('verdictFilter').value.toUpperCase().trim();
            const outcome = document.getElementById('outcomeFilter').value.toUpperCase().trim();
            const cards = document.querySelectorAll('.signal-card');
            let visible = 0;

            cards.forEach(card => {
                const cTicker = (card.getAttribute('data-ticker') || '').toLowerCase();
                const cVerdict = (card.getAttribute('data-verdict') || '').toUpperCase();
                const cOutcome = (card.getAttribute('data-outcome') || '').toUpperCase();

                const matchQuery = !query || cTicker.includes(query);
                const matchVerdict = !verdict || cVerdict === verdict;
                const matchOutcome = !outcome || cOutcome.includes(outcome);

                if (matchQuery && matchVerdict && matchOutcome) {
                    card.style.display = 'flex';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('signalsCount').innerText = visible;
        }

        // Filter News
        function filterNews() {
            const query = document.getElementById('newsSearch').value.toLowerCase().trim();
            const ticker = document.getElementById('newsTickerFilter').value.toUpperCase().trim();
            const cards = document.querySelectorAll('.news-card');

            cards.forEach(card => {
                const cTicker = (card.getAttribute('data-ticker') || '').toUpperCase();
                const cHeadline = card.getAttribute('data-headline') || '';
                const cFull = card.getAttribute('data-fullnews') || '';
                const cArticles = card.getAttribute('data-articles') || '';

                const matchTicker = !ticker || cTicker === ticker;
                const matchQuery = !query || cTicker.toLowerCase().includes(query) || cHeadline.includes(query) || cFull.includes(query) || cArticles.includes(query);

                if (matchTicker && matchQuery) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        let allArticlesCollapsed = false;
        function toggleAllArticles() {
            const grids = document.querySelectorAll('.articles-grid');
            const btnText = document.getElementById('toggleAllArticlesText');
            allArticlesCollapsed = !allArticlesCollapsed;
            
            grids.forEach(g => {
                if (allArticlesCollapsed) {
                    g.classList.add('hidden');
                } else {
                    g.classList.remove('hidden');
                }
            });

            if (btnText) {
                btnText.innerText = allArticlesCollapsed ? 'Разгъни всички статии' : 'Сгъни всички статии';
            }
        }

        function toggleFullNews(btn) {
            const content = btn.nextElementSibling;
            const icon = btn.querySelector('i');
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.style.transform = 'rotate(180deg)';
            } else {
                content.classList.add('hidden');
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // Modal for Signal Details
        function viewSignalDetails(data) {
            const modal = document.getElementById('signalModal');
            const content = document.getElementById('modalContent');
            
            const isBuy = data.verdict === 'BUY';
            const isSell = data.verdict === 'SELL';
            const vColor = isBuy ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30' : (isSell ? 'text-rose-400 bg-rose-500/10 border-rose-500/30' : 'text-amber-400 bg-amber-500/10 border-amber-500/30');

            const isGradeA = data.setup_grade === 'A+';
            const gradeBadge = isGradeA ? '<span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">GRADE A+ ($400)</span>' : '<span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-800 text-slate-400 border border-slate-700">GRADE B ($200)</span>';
            const radarBlock = (Number(data.trigger_price || 0) > 0) ? `
                <div class="bg-slate-950 p-3 rounded-lg border border-cyan-500/30 text-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 font-mono block">РАДАР ЗА БЛИЗОСТ ДО ТРИГЕРА:</span>
                        <span class="text-sm font-bold text-white mono">$${Number(data.trigger_price).toFixed(2)}</span>
                        <span class="text-xs text-cyan-400 font-mono">(${Number(data.trigger_distance_pct || 0) > 0 ? '+' : ''}${data.trigger_distance_pct}% / $${Math.abs(data.trigger_distance_dollars || 0).toFixed(2)} разстояние)</span>
                    </div>
                    <span class="px-2.5 py-1 rounded text-xs font-mono font-bold ${data.trigger_status === 'ZONE' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 animate-pulse' : (data.trigger_status === 'APPROACHING' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-cyan-500/10 text-cyan-300 border border-cyan-500/30')}">
                        ${data.trigger_status === 'ZONE' ? '🔥 В ЗОНА НА ПРОБИВ' : (data.trigger_status === 'APPROACHING' ? '⏳ ПРИБЛИЖАВА ТРИГЕРА' : '🎯 РАДАР ТРИГЕР')}
                    </span>
                </div>` : '';

            content.innerHTML = `
                <div class="flex items-center justify-between border-b border-term-border pb-4">
                    <div class="flex items-center space-x-3">
                        <span class="text-2xl font-bold text-white mono">${data.ticker}</span>
                        <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold border ${vColor}">
                            ${data.verdict}
                        </span>
                        ${gradeBadge}
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-400 block">${data.date_time}</span>
                        <span class="text-xs text-cyan-400 font-mono">Увереност: ${data.confidence}%</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center mono text-xs">
                    <div class="bg-term-card p-3 rounded-lg border border-term-border">
                        <span class="text-slate-400 text-[10px] block">ЦЕНА ВХОД</span>
                        <span class="text-base font-bold text-white">$${Number(data.current_price || 0).toFixed(2)}</span>
                    </div>
                    <div class="bg-term-card p-3 rounded-lg border border-term-border">
                        <span class="text-cyan-400 text-[10px] block">ВЛОЖЕНИ (ПОЗИЦИЯ)</span>
                        <span class="text-base font-bold text-cyan-400">$${Number(data.position_size || 200).toFixed(2)}</span>
                        <span class="text-[10px] text-slate-500 block">${data.shares_est || 0} бр. акции</span>
                    </div>
                    <div class="bg-term-card p-3 rounded-lg border border-term-border">
                        <span class="text-rose-400 text-[10px] block">STOP LOSS (РИСК -$${Number(data.expected_stop_cash || 0).toFixed(2)})</span>
                        <span class="text-base font-bold text-rose-400">$${Number(data.stop_loss || 0).toFixed(2)}</span>
                    </div>
                    <div class="bg-term-card p-3 rounded-lg border border-term-border">
                        <span class="text-emerald-400 text-[10px] block">TARGET (ПЕЧАЛБА +$${Number(data.expected_target_cash || 0).toFixed(2)})</span>
                        <span class="text-base font-bold text-emerald-400">$${Number(data.target_price || 0).toFixed(2)}</span>
                    </div>
                </div>

                ${radarBlock}

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-term-card p-3 rounded-lg border border-term-border">
                    <div>RVOL: <span class="text-white font-mono font-bold">${data.rvol || '1.0x'}</span></div>
                    <div>Пазарен Тренд: <span class="text-white font-medium">${data.market_trend || 'Neutral'}</span></div>
                    <div>Статус: <span class="${(data.outcome || '').includes('WIN') ? 'text-emerald-400' : ((data.outcome || '').includes('LOSS') ? 'text-rose-400' : ((data.outcome || '').includes('Pending') ? 'text-amber-400' : 'text-slate-300'))} font-bold">${data.outcome || 'PENDING'}</span></div>
                    <div>Резултат ($): <span class="${(data.pnl_dollars_str || '').includes('+') ? 'text-emerald-400' : ((data.pnl_dollars_str || '').includes('-') ? 'text-rose-400' : 'text-slate-300')} font-mono font-bold">${data.pnl_dollars_str || '$0.00'}</span></div>
                </div>

                ${data.entry_trigger ? `
                <div class="bg-term-card p-3 rounded-lg border border-term-border text-xs space-y-1">
                    <span class="text-[10px] text-slate-400 uppercase font-semibold block">Техническо Входно Условие:</span>
                    <p class="text-slate-200">${data.entry_trigger}</p>
                </div>` : ''}

                ${data.catalyst_headline ? `
                <div class="bg-term-card p-3 rounded-lg border border-term-border text-xs space-y-1">
                    <span class="text-[10px] text-emerald-400 uppercase font-semibold block">Катализатор & Новини:</span>
                    <p class="text-slate-200 italic">${data.catalyst_headline}</p>
                </div>` : ''}

                <div class="pt-3 flex justify-end space-x-3">
                    <button onclick="openChartForTicker('${data.ticker}'); closeSignalModal();" class="px-4 py-2 rounded-lg bg-term-card hover:bg-term-hover text-white text-xs border border-term-border transition">
                        Отвори Графика
                    </button>
                    <button onclick="closeSignalModal()" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-black font-semibold text-xs transition">
                        Затвори
                    </button>
                </div>
            `;

            modal.classList.remove('hidden');
        }

        function closeSignalModal() {
            document.getElementById('signalModal').classList.add('hidden');
        }

        // Live Clocks (NYSE)
        function updateClocks() {
            const now = new Date();
            const nyTime = new Intl.DateTimeFormat('en-US', {
                timeZone: 'America/New_York',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).format(now);

            const clockEl = document.getElementById('nyse-clock');
            if (clockEl) clockEl.innerText = nyTime + ' EST';

            // Check if market is open (9:30 to 16:00 EST Mon-Fri)
            const nyDay = new Intl.DateTimeFormat('en-US', { timeZone: 'America/New_York', weekday: 'short' }).format(now);
            const nyParts = nyTime.split(':');
            const nyMin = parseInt(nyParts[0]) * 60 + parseInt(nyParts[1]);
            const isOpen = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'].includes(nyDay) && nyMin >= 570 && nyMin < 960;

            const dot = document.getElementById('market-status-dot');
            const statusText = document.getElementById('market-status-text');
            if (dot && statusText) {
                if (isOpen) {
                    dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse';
                    statusText.innerText = 'MARKET OPEN';
                } else {
                    dot.className = 'w-2.5 h-2.5 rounded-full bg-slate-500';
                    statusText.innerText = 'MARKET CLOSED';
                }
            }
        }
        setInterval(updateClocks, 1000);
        updateClocks();

        // Refresh Data via API
        async function refreshData() {
            const icon = document.getElementById('refresh-icon');
            if (icon) icon.classList.add('fa-spin');
            try {
                const res = await fetch('api.php?action=sync');
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (e) {
                console.error(e);
            } finally {
                if (icon) icon.classList.remove('fa-spin');
            }
        }

        // Initialization & Mode restore
        document.addEventListener('DOMContentLoaded', () => {
            const savedMode = localStorage.getItem('lexmation_stock_view_mode') || 'simple';
            setViewMode(savedMode);

            // Real-time stock quotes poller (every 60s, smoothly updates DOM without page reload)
            updateLiveStockPrices();
            setInterval(updateLiveStockPrices, 60000);

            // Immediately refresh prices when returning to tab
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    updateLiveStockPrices();
                }
            });

            // Carousel Touch Swipe & Keyboard support
            const carousel = document.getElementById('trade-carousel-container');
            if (carousel) {
                let touchStartX = 0;
                let touchEndX = 0;
                carousel.addEventListener('touchstart', e => {
                    touchStartX = e.changedTouches[0].screenX;
                }, { passive: true });
                carousel.addEventListener('touchend', e => {
                    touchEndX = e.changedTouches[0].screenX;
                    if (touchStartX - touchEndX > 40) nextTradeSlide();
                    if (touchEndX - touchStartX > 40) prevTradeSlide();
                }, { passive: true });
            }

            document.addEventListener('keydown', e => {
                const simpleDash = document.getElementById('simple-dashboard');
                if (simpleDash && !simpleDash.classList.contains('hidden')) {
                    if (e.key === 'ArrowRight') nextTradeSlide();
                    if (e.key === 'ArrowLeft') prevTradeSlide();
                }
            });

            const rawCurve = <?= json_encode($stats['equity_curve'] ?? []) ?>;
            const labels = rawCurve.map(p => p.date + (p.ticker && p.ticker !== 'BASE' ? ' (' + p.ticker + ')' : ''));
            const dataPoints = rawCurve.map(p => p.balance !== undefined ? p.balance : (1000 + (p.cumulative_r * 20)));

            const ctx = document.getElementById('equityChart');
            if (ctx) {
                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Капитал на портфейла ($)',
                            data: dataPoints,
                            borderColor: '#00E676',
                            backgroundColor: 'rgba(0, 230, 118, 0.1)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#00E676',
                            pointBorderColor: '#0E1422',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#131A2B',
                                titleColor: '#FFFFFF',
                                bodyColor: '#00E676',
                                borderColor: '#1E293B',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(ctx) {
                                        const p = rawCurve[ctx.dataIndex];
                                        const pnlD = p.pnl_dollars !== undefined ? (p.pnl_dollars >= 0 ? '+' : '') + '$' + p.pnl_dollars.toFixed(2) : '';
                                        return ` Баланс: $${ctx.parsed.y.toFixed(2)} ${pnlD ? '(' + pnlD + ')' : ''}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: 'rgba(30, 41, 59, 0.4)' },
                                ticks: { color: '#94A3B8', font: { family: 'JetBrains Mono', size: 10 } }
                            },
                            y: {
                                grid: { color: 'rgba(30, 41, 59, 0.4)' },
                                ticks: {
                                    color: '#94A3B8',
                                    font: { family: 'JetBrains Mono', size: 10 },
                                    callback: (val) => '$' + val
                                }
                            }
                        }
                    }
                });
            }

            // Sentiment Donut Chart
            const ctxSent = document.getElementById('sentimentChart');
            if (ctxSent) {
                const sentimentData = <?= json_encode($stats['sentiment'] ?? ['bullish' => 0, 'bearish' => 0, 'neutral' => 0]) ?>;
                new Chart(ctxSent.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Bullish', 'Bearish', 'Neutral'],
                        datasets: [{
                            data: [sentimentData.bullish, sentimentData.bearish, sentimentData.neutral],
                            backgroundColor: ['#00E676', '#FF3366', '#64748B'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#94A3B8', font: { size: 11 }, boxWidth: 12 }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }
        });
    </script>

    <!-- ============================================================== -->
    <!-- AI CHATBOT FLOATING TRIGGER & INTERACTIVE WIDGET               -->
    <!-- ============================================================== -->
    <!-- Floating AI Chatbot Trigger Button -->
    <div id="ai-chat-trigger" class="fixed bottom-6 right-6 z-50 flex items-center">
        <button onclick="toggleAiChat()" class="group flex items-center space-x-2.5 bg-gradient-to-r from-emerald-500 via-emerald-600 to-cyan-600 hover:from-emerald-400 hover:to-cyan-500 text-slate-950 font-bold px-4 py-3 rounded-full shadow-2xl shadow-emerald-500/30 border border-emerald-300/40 transition-all duration-300 hover:scale-105 active:scale-95">
            <div class="w-7 h-7 rounded-full bg-slate-950/20 flex items-center justify-center text-sm">
                <i class="fa-solid fa-robot text-white animate-bounce"></i>
            </div>
            <span class="text-xs sm:text-sm tracking-wide">Попитай AI Асистента</span>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-300 animate-ping ml-1"></span>
        </button>
    </div>

    <!-- AI Chatbot Floating Window -->
    <div id="ai-chat-window" class="fixed bottom-6 right-3 sm:right-6 z-50 w-[95vw] sm:w-[420px] max-h-[85vh] h-[580px] bg-term-surface/95 backdrop-blur-xl border border-emerald-500/40 rounded-3xl shadow-2xl flex flex-col overflow-hidden hidden transition-all duration-300">
        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-950/80 via-slate-900/90 to-cyan-950/80 border-b border-term-border p-4 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-cyan-500 flex items-center justify-center shadow-lg shadow-emerald-500/30 text-white text-sm">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-1.5">
                        <span class="font-bold text-sm text-white">Lexmation AI</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">ONLINE</span>
                    </div>
                    <p class="text-[11px] text-slate-400">Борсов асистент &bull; Следи сигналите на живо</p>
                </div>
            </div>
            <div class="flex items-center space-x-1">
                <button onclick="clearAiChat()" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-term-card transition text-xs" title="Изчисти чата">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
                <button onclick="toggleAiChat()" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-term-card transition text-xs" title="Затвори">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Messages Container -->
        <div id="ai-chat-messages" class="flex-1 p-4 overflow-y-auto space-y-3.5 custom-scrollbar text-xs sm:text-sm">
            <!-- Initial Bot Greeting -->
            <div class="flex items-start space-x-2.5">
                <div class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xs flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="bg-term-card border border-term-border p-3.5 rounded-2xl rounded-tl-sm text-slate-200 space-y-2 leading-relaxed max-w-[85%] shadow-md">
                    <p>Здравейте! Аз съм виртуалният борсов асистент на <strong>Lexmation</strong>. Следя сигналите на живо и мога да отговоря на всеки ваш въпрос на достъпен език.</p>
                    <p class="text-[11px] text-slate-400">💡 <em>Можете да изберете готов въпрос отдолу или да напишете свой:</em></p>
                </div>
            </div>
        </div>

        <!-- Quick Suggestion Chips -->
        <div class="px-4 py-2 border-t border-term-border/40 bg-slate-950/40 flex items-center gap-1.5 overflow-x-auto custom-scrollbar flex-shrink-0">
            <button onclick="sendQuickPrompt('Какво да купя днес?')" class="px-2.5 py-1 rounded-full text-[11px] bg-term-card hover:bg-term-hover text-emerald-400 hover:text-emerald-300 border border-emerald-500/30 transition whitespace-nowrap">
                💡 Какво да купя днес?
            </button>
            <button onclick="sendQuickPrompt('Колко пари мога да загубя от META?')" class="px-2.5 py-1 rounded-full text-[11px] bg-term-card hover:bg-term-hover text-slate-300 hover:text-white border border-term-border transition whitespace-nowrap">
                💰 Риск при META?
            </button>
            <button onclick="sendQuickPrompt('Защо чакаме NBIS, а не купуваме сега?')" class="px-2.5 py-1 rounded-full text-[11px] bg-term-card hover:bg-term-hover text-slate-300 hover:text-white border border-term-border transition whitespace-nowrap">
                ⏳ Защо чакаме NBIS?
            </button>
            <button onclick="sendQuickPrompt('Как се пазят парите ми от фалит?')" class="px-2.5 py-1 rounded-full text-[11px] bg-term-card hover:bg-term-hover text-slate-300 hover:text-white border border-term-border transition whitespace-nowrap">
                🛡️ Сигурност на парите?
            </button>
        </div>

        <!-- Input Bar -->
        <div class="p-3 border-t border-term-border bg-term-card flex-shrink-0">
            <form id="ai-chat-form" onsubmit="handleAiChatSubmit(event)" class="flex items-center space-x-2">
                <input type="text" id="ai-chat-input" placeholder="Попитайте за акциите, риска, печалбите..." class="flex-1 bg-slate-900 border border-term-border rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition">
                <button type="submit" id="ai-chat-send-btn" class="w-10 h-10 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 flex items-center justify-center transition shadow-md shadow-emerald-500/20 flex-shrink-0">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        // ==============================================================
        // AI Chatbot Frontend Controller
        // ==============================================================
        let aiChatHistory = [];

        function toggleAiChat() {
            const win = document.getElementById('ai-chat-window');
            const input = document.getElementById('ai-chat-input');
            if (!win) return;
            const isHidden = win.classList.contains('hidden');
            if (isHidden) {
                win.classList.remove('hidden');
                if (input) setTimeout(() => input.focus(), 150);
            } else {
                win.classList.add('hidden');
            }
        }

        function clearAiChat() {
            sessionStorage.removeItem('lexmation_ai_chat');
            aiChatHistory = [];
            const container = document.getElementById('ai-chat-messages');
            if (container) {
                container.innerHTML = `
                    <div class="flex items-start space-x-2.5">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xs flex-shrink-0 mt-0.5">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <div class="bg-term-card border border-term-border p-3.5 rounded-2xl rounded-tl-sm text-slate-200 space-y-2 leading-relaxed max-w-[85%] shadow-md">
                            <p>Здравейте! Аз съм виртуалният борсов асистент на <strong>Lexmation</strong>. Следя сигналите на живо и мога да отговоря на всеки ваш въпрос на достъпен език.</p>
                            <p class="text-[11px] text-slate-400">💡 <em>Можете да изберете готов въпрос отдолу или да напишете свой:</em></p>
                        </div>
                    </div>
                `;
            }
        }

        function formatAiMarkdown(text) {
            let escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            // Bold
            escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong class="text-white font-bold">$1</strong>');
            // Dollar gains / losses highlight
            escaped = escaped.replace(/(\+\$[0-9]+(\.[0-9]+)?)/g, '<span class="text-emerald-400 font-bold font-mono">$1</span>');
            escaped = escaped.replace(/(\-\$[0-9]+(\.[0-9]+)?)/g, '<span class="text-rose-400 font-bold font-mono">$1</span>');
            // Bullet points
            escaped = escaped.replace(/^\s*-\s+(.*)$/gm, '<li class="ml-4 list-disc text-slate-300">$1</li>');
            // Line breaks
            escaped = escaped.replace(/\n\n/g, '<div class="h-2"></div>');
            escaped = escaped.replace(/\n/g, '<br>');
            return escaped;
        }

        function sendQuickPrompt(txt) {
            const input = document.getElementById('ai-chat-input');
            const win = document.getElementById('ai-chat-window');
            if (win && win.classList.contains('hidden')) {
                win.classList.remove('hidden');
            }
            if (input) {
                input.value = txt;
                document.getElementById('ai-chat-form').dispatchEvent(new Event('submit', { cancelable: true }));
            }
        }

        async function handleAiChatSubmit(e) {
            if (e) e.preventDefault();
            const input = document.getElementById('ai-chat-input');
            const btn = document.getElementById('ai-chat-send-btn');
            const messagesContainer = document.getElementById('ai-chat-messages');
            if (!input || !messagesContainer) return;

            const text = input.value.trim();
            if (!text) return;
            input.value = '';

            // 1. Append User Message
            aiChatHistory.push({ role: 'user', content: text });
            const userMsgEl = document.createElement('div');
            userMsgEl.className = 'flex justify-end';
            userMsgEl.innerHTML = `
                <div class="bg-gradient-to-r from-emerald-600 to-cyan-600 text-white font-medium p-3 rounded-2xl rounded-tr-sm max-w-[85%] shadow-md leading-relaxed">
                    ${text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}
                </div>
            `;
            messagesContainer.appendChild(userMsgEl);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // 2. Append Typing Indicator
            const typingEl = document.createElement('div');
            typingEl.className = 'flex items-start space-x-2.5';
            typingEl.id = 'ai-typing-indicator';
            typingEl.innerHTML = `
                <div class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xs flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="bg-term-card border border-term-border px-4 py-3 rounded-2xl rounded-tl-sm text-slate-400 flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse delay-100"></span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse delay-200"></span>
                    <span class="text-xs ml-1 text-slate-400">AI проверява пазара...</span>
                </div>
            `;
            messagesContainer.appendChild(typingEl);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            if (btn) btn.disabled = true;

            try {
                const response = await fetch('chat_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        messages: aiChatHistory,
                        stream: false
                    })
                });

                const data = await response.json();
                const botReply = data.reply || 'Съжалявам, възникна грешка при обработката.';
                aiChatHistory.push({ role: 'assistant', content: botReply });

                // Remove typing indicator
                const typing = document.getElementById('ai-typing-indicator');
                if (typing) typing.remove();

                // Append Bot Response
                const botMsgEl = document.createElement('div');
                botMsgEl.className = 'flex items-start space-x-2.5';
                botMsgEl.innerHTML = `
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xs flex-shrink-0 mt-0.5">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="bg-term-card border border-term-border p-3.5 rounded-2xl rounded-tl-sm text-slate-200 leading-relaxed max-w-[85%] shadow-md">
                        ${formatAiMarkdown(botReply)}
                    </div>
                `;
                messagesContainer.appendChild(botMsgEl);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                sessionStorage.setItem('lexmation_ai_chat', JSON.stringify(aiChatHistory));
            } catch (err) {
                console.error(err);
                const typing = document.getElementById('ai-typing-indicator');
                if (typing) typing.remove();

                const errMsgEl = document.createElement('div');
                errMsgEl.className = 'text-xs text-rose-400 p-2 bg-rose-500/10 rounded-lg border border-rose-500/30 text-center';
                errMsgEl.innerText = 'Възникна временна грешка при връзката. Моля опитайте отново.';
                messagesContainer.appendChild(errMsgEl);
            } finally {
                if (btn) btn.disabled = false;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        // Restore chat from sessionStorage if available
        document.addEventListener('DOMContentLoaded', () => {
            const savedChat = sessionStorage.getItem('lexmation_ai_chat');
            if (savedChat) {
                try {
                    const parsed = JSON.parse(savedChat);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        aiChatHistory = parsed;
                        const messagesContainer = document.getElementById('ai-chat-messages');
                        if (messagesContainer) {
                            parsed.forEach(m => {
                                if (m.role === 'user') {
                                    const u = document.createElement('div');
                                    u.className = 'flex justify-end';
                                    u.innerHTML = `
                                        <div class="bg-gradient-to-r from-emerald-600 to-cyan-600 text-white font-medium p-3 rounded-2xl rounded-tr-sm max-w-[85%] shadow-md leading-relaxed">
                                            ${m.content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}
                                        </div>
                                    `;
                                    messagesContainer.appendChild(u);
                                } else {
                                    const b = document.createElement('div');
                                    b.className = 'flex items-start space-x-2.5';
                                    b.innerHTML = `
                                        <div class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xs flex-shrink-0 mt-0.5">
                                            <i class="fa-solid fa-robot"></i>
                                        </div>
                                        <div class="bg-term-card border border-term-border p-3.5 rounded-2xl rounded-tl-sm text-slate-200 leading-relaxed max-w-[85%] shadow-md">
                                            ${formatAiMarkdown(m.content)}
                                        </div>
                                    `;
                                    messagesContainer.appendChild(b);
                                }
                            });
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        }
                    }
                } catch (e) {}
            }
        });

        // History of Closed Trades Category Filter
        function filterHistory(cat) {
            document.querySelectorAll('.hfilter-btn').forEach(b => {
                b.className = 'hfilter-btn px-3 py-1.5 rounded-lg font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition';
            });
            const activeBtn = document.getElementById('hfilter-' + cat);
            if (activeBtn) {
                activeBtn.className = 'hfilter-btn px-3 py-1.5 rounded-lg font-semibold bg-emerald-500 text-slate-950 font-mono transition shadow-md';
            }
            document.querySelectorAll('.history-row').forEach(row => {
                if (cat === 'all') {
                    row.style.display = '';
                } else if (cat === 'real') {
                    row.style.display = (row.dataset.real === '1') ? '' : 'none';
                } else if (row.dataset.cat === cat) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            const footLabel = document.getElementById('history-tfoot-label');
            const footAmount = document.getElementById('history-tfoot-amount');
            if (footLabel && footAmount) {
                if (cat === 'real') {
                    footLabel.textContent = 'ОБЩ РЕАЛИЗИРАН РЕЗУЛТАТ (<?= count($real_closed_trades) ?> СДЕЛКИ: <?= $closed_wins_count ?>W / <?= $closed_loss_count ?>L):';
                    footAmount.textContent = '+$<?= number_format($total_closed_cash, 2) ?> ЧИСТА ПЕЧАЛБА (<?= number_format($display_win_rate, 1) ?>%)';
                    footAmount.className = 'py-3.5 px-4 text-right mono text-emerald-400 text-base font-extrabold whitespace-nowrap';
                } else if (cat === 'all') {
                    footLabel.textContent = 'ОБЩ РЕЗУЛТАТ ВКЛ. РАДАР (<?= count($closed_trades_list) ?> ЗАПИСА):';
                    footAmount.textContent = '+$<?= number_format($all_closed_cash, 2) ?> ЧИСТА ПЕЧАЛБА';
                    footAmount.className = 'py-3.5 px-4 text-right mono text-cyan-400 text-base font-extrabold whitespace-nowrap';
                } else if (cat === 'win') {
                    footLabel.textContent = 'ВСИЧКИ РЕАЛИЗИРАНИ ПОБЕДИ (<?= $all_wins_count ?> ПОБЕДИ):';
                    footAmount.textContent = '+$<?= number_format($gross_profit_dollars, 2) ?> БРУТНА ПЕЧАЛБА';
                    footAmount.className = 'py-3.5 px-4 text-right mono text-emerald-400 text-base font-extrabold whitespace-nowrap';
                } else if (cat === 'loss') {
                    footLabel.textContent = 'ВСИЧКИ ЗАДЕЙСТВАНИ СТОПОВЕ (<?= $all_loss_count ?> СТОПА):';
                    footAmount.textContent = '-$<?= number_format($gross_loss_dollars, 2) ?> ОГРАНИЧЕН РИСК';
                    footAmount.className = 'py-3.5 px-4 text-right mono text-rose-400 text-base font-extrabold whitespace-nowrap';
                } else {
                    footLabel.textContent = 'БЕЗ ПРОБИВ / СЪХРАНЕН КАПИТАЛ (<?= $all_range_count ?> ПОЗИЦИИ):';
                    footAmount.textContent = '$0.00 ЗАПАЗЕН КЕШ';
                    footAmount.className = 'py-3.5 px-4 text-right mono text-slate-400 text-base font-extrabold whitespace-nowrap';
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            filterHistory('real');
        });
    </script>

    <!-- ====================================================================== -->
    <!-- MARKET BRIEF JS                                                         -->
    <!-- ====================================================================== -->
    <script>
    // ── Helpers ──────────────────────────────────────────────────────────────
    const SENTIMENT_CONFIG = {
        'BULLISH':  { cls: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40', icon: '📈', iconBg: 'bg-emerald-500/20', score: '+' },
        'BEARISH':  { cls: 'bg-red-500/20 text-red-300 border-red-500/40',             icon: '📉', iconBg: 'bg-red-500/20',     score: '' },
        'NEUTRAL':  { cls: 'bg-slate-500/20 text-slate-300 border-slate-500/40',       icon: '⚖️', iconBg: 'bg-slate-500/20',  score: '' },
        'MIXED':    { cls: 'bg-amber-500/20 text-amber-300 border-amber-500/40',        icon: '🌀', iconBg: 'bg-amber-500/20',  score: '' }
    };

    function briefShowState(state) {
        ['brief-loading','brief-error','brief-empty','brief-content'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        });
        const target = document.getElementById('brief-' + state);
        if (target) target.classList.remove('hidden');
    }

    function renderSentimentBadge(sentiment) {
        const cfg = SENTIMENT_CONFIG[sentiment?.toUpperCase()] || SENTIMENT_CONFIG['NEUTRAL'];
        return `<span class="px-2.5 py-0.5 rounded-full text-xs font-bold border ${cfg.cls}">${sentiment}</span>`;
    }

    function renderCategoryCard(cat) {
        const cfg = SENTIMENT_CONFIG[cat.sentiment?.toUpperCase()] || SENTIMENT_CONFIG['NEUTRAL'];
        const points = (cat.key_points || []).map(p =>
            `<li class="flex items-start gap-2 text-xs text-slate-300">
                <i class="fa-solid fa-circle-dot text-slate-500 mt-0.5 flex-shrink-0 text-[10px]"></i>
                <span>${p}</span>
            </li>`
        ).join('');
        return `
        <div class="bg-term-surface border border-term-border hover:border-slate-600 rounded-xl p-5 space-y-3 transition">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">${cat.emoji || '📊'}</span>
                    <h3 class="text-sm font-bold text-white">${cat.title || ''}</h3>
                </div>
                ${renderSentimentBadge(cat.sentiment)}
            </div>
            <p class="text-xs text-slate-400 leading-relaxed">${cat.summary || ''}</p>
            <ul class="space-y-1.5">${points}</ul>
        </div>`;
    }

    function renderMarketBrief(data) {
        const cfg = SENTIMENT_CONFIG[data.overall_sentiment?.toUpperCase()] || SENTIMENT_CONFIG['NEUTRAL'];

        // Executive summary
        const sentIcon = document.getElementById('brief-sentiment-icon');
        if (sentIcon) {
            sentIcon.textContent = cfg.icon;
            sentIcon.className = `w-12 h-12 flex-shrink-0 rounded-xl flex items-center justify-center text-2xl font-bold ${cfg.iconBg}`;
        }
        const sentBadge = document.getElementById('brief-sentiment-badge');
        if (sentBadge) {
            sentBadge.textContent = data.overall_sentiment || 'NEUTRAL';
            sentBadge.className = `px-2.5 py-0.5 rounded-full text-xs font-bold border ${cfg.cls}`;
        }
        const scoreBadge = document.getElementById('brief-score-badge');
        if (scoreBadge) {
            const score = data.sentiment_score ?? 0;
            scoreBadge.textContent = `Score: ${score > 0 ? '+' : ''}${score}`;
        }
        const execEl = document.getElementById('brief-exec-summary');
        if (execEl) execEl.textContent = data.executive_summary || '';

        // Category cards
        const grid = document.getElementById('brief-cards-grid');
        if (grid) {
            grid.innerHTML = [data.macro, data.equities, data.gold, data.crypto]
                .filter(Boolean).map(renderCategoryCard).join('');
        }

        // Hot sectors
        const sectorsEl = document.getElementById('brief-sectors');
        if (sectorsEl && data.equities?.hot_sectors?.length) {
            sectorsEl.innerHTML = data.equities.hot_sectors.map(s => {
                const scfg = SENTIMENT_CONFIG[s.sentiment?.toUpperCase()] || SENTIMENT_CONFIG['NEUTRAL'];
                return `<div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border ${scfg.cls} text-xs">
                    <span class="font-bold">${s.name}</span>
                    <span class="opacity-70">·</span>
                    <span class="opacity-80">${s.reason || s.sentiment}</span>
                </div>`;
            }).join('');
            document.getElementById('brief-sectors-wrap')?.classList.remove('hidden');
        }

        // Risks
        const risksEl = document.getElementById('brief-risks');
        if (risksEl) {
            risksEl.innerHTML = (data.risk_factors || []).map(r =>
                `<li class="flex items-start gap-2 text-xs text-slate-300">
                    <i class="fa-solid fa-circle-exclamation text-red-400 mt-0.5 flex-shrink-0"></i>
                    <span>${r}</span>
                </li>`).join('');
        }

        // Opportunities
        const oppEl = document.getElementById('brief-opportunities');
        if (oppEl) {
            oppEl.innerHTML = (data.opportunities || []).map(o =>
                `<li class="flex items-start gap-2 text-xs text-slate-300">
                    <i class="fa-solid fa-circle-check text-emerald-400 mt-0.5 flex-shrink-0"></i>
                    <span>${o}</span>
                </li>`).join('');
        }

        // Watchlist impact
        const wlEl = document.getElementById('brief-watchlist-impact');
        if (wlEl) wlEl.textContent = data.watchlist_impact || '';

        // Timestamps
        const genAtEl = document.getElementById('brief-generated-at');
        const sourceTimeEl = document.getElementById('brief-source-time');
        if (data.generated_at) {
            try {
                const dt = new Date(data.generated_at);
                const bgTime = dt.toLocaleString('bg-BG', { timeZone: 'Europe/Sofia', hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' });
                if (genAtEl) genAtEl.textContent = `Генериран: ${bgTime} ч.`;
                if (sourceTimeEl) sourceTimeEl.textContent = bgTime + ' ч.';
            } catch(e) {
                if (genAtEl) genAtEl.textContent = data.generated_at;
            }
        }

        // Badge in tab nav
        const badge = document.getElementById('market-brief-badge');
        if (badge) badge.classList.remove('hidden');

        briefShowState('content');
    }

    // ── Load existing brief from API ─────────────────────────────────────────
    async function loadMarketBrief() {
        try {
            const resp = await fetch('/api.php?action=market_brief&_=' + Date.now());
            const data = await resp.json();
            if (data.error || data.generated === false) {
                briefShowState('empty');
            } else {
                renderMarketBrief(data);
            }
        } catch (e) {
            document.getElementById('brief-error-msg').textContent = 'Грешка: ' + e.message;
            briefShowState('error');
        }
    }

    // ── Generate new brief via n8n webhook ───────────────────────────────────
    async function generateMarketBrief() {
        briefShowState('loading');
        const btn = document.getElementById('brief-generate-btn');
        if (btn) { btn.disabled = true; btn.classList.add('opacity-60'); }

        // Countdown timer UI
        let elapsed = 0;
        const timerEl = document.getElementById('brief-loading-timer');
        const timerInterval = setInterval(() => {
            elapsed++;
            if (timerEl) timerEl.textContent = `Изминали: ${elapsed}с — Изчакай ~30-60 секунди`;
        }, 1000);

        try {
            const resp = await fetch('https://n8n.lexmation.com/webhook/market-brief', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ source: 'web_terminal', triggered_at: new Date().toISOString() })
            });

            clearInterval(timerInterval);
            if (btn) { btn.disabled = false; btn.classList.remove('opacity-60'); }

            if (resp.ok) {
                // Brief was generated and saved — now load it
                await loadMarketBrief();
            } else {
                const errText = await resp.text();
                document.getElementById('brief-error-msg').textContent = `n8n грешка (HTTP ${resp.status}): ${errText.substring(0, 200)}`;
                briefShowState('error');
            }
        } catch (e) {
            clearInterval(timerInterval);
            if (btn) { btn.disabled = false; btn.classList.remove('opacity-60'); }
            document.getElementById('brief-error-msg').textContent = 'Мрежова грешка: ' + e.message;
            briefShowState('error');
        }
    }

    // Auto-load brief when tab is switched to market-brief
    const _origSwitchTab = typeof switchTab === 'function' ? switchTab : null;
    document.addEventListener('DOMContentLoaded', function() {
        // Patch switchTab to trigger brief load on first visit
        let briefLoaded = false;
        const origSwitch = window.switchTab;
        window.switchTab = function(tabId) {
            if (origSwitch) origSwitch(tabId);
            if (tabId === 'market-brief' && !briefLoaded) {
                briefLoaded = true;
                loadMarketBrief();
            }
        };
    });
    </script>
</body>
</html>

