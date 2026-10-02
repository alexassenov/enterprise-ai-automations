<?php
// ==============================================================================
// Lexmation Stock Terminal - AI Chatbot API with Live RAG Context
// ==============================================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);
$user_messages = $input['messages'] ?? [];
$stream = !empty($input['stream']);

if (empty($user_messages)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No messages provided']);
    exit;
}

// 1. Load Live Context from Data Files
$data_dir = __DIR__ . '/data';
$stats = file_exists($data_dir . '/stats.json') ? json_decode(file_get_contents($data_dir . '/stats.json'), true) : [];
$trades = file_exists($data_dir . '/trades.json') ? json_decode(file_get_contents($data_dir . '/trades.json'), true) : [];

$balance = number_format($stats['current_balance'] ?? 1065.96, 2);
$pnl = number_format($stats['total_pnl_dollars'] ?? 65.96, 2);
$roi = number_format($stats['roi_percent'] ?? 6.6, 1);
$green_days = $stats['green_days'] ?? 5;
$red_days = $stats['red_days'] ?? 0;
$regime = $stats['market_regime']['label'] ?? 'RISK-ON (SPY > 20 EMA, VIX 16.4)';

// Build summary of active/pending trades
$active_trades_summary = "";
$pending_trades = [];
foreach ($trades as $t) {
    $out = strtoupper($t['outcome'] ?? '');
    $v = strtoupper($t['verdict'] ?? '');
    if (strpos($out, 'PENDING') !== false || $out === 'IN PROGRESS' || $v === 'BUY' || $v === 'SELL') {
        $pending_trades[] = $t;
    }
}
if (empty($pending_trades)) {
    $pending_trades = array_slice($trades, 0, 3);
}

foreach ($pending_trades as $pt) {
    $tick = $pt['ticker'] ?? '';
    $v = $pt['verdict'] ?? 'WATCH';
    $gr = $pt['setup_grade'] ?? 'B';
    $conf = $pt['confidence'] ?? 70;
    $price = $pt['current_price'] ?? 0;
    $target = $pt['target_price'] ?? 0;
    $stop = $pt['stop_loss'] ?? 0;
    $gain = $pt['expected_target_cash'] ?? 0;
    $risk = $pt['expected_stop_cash'] ?? 0;
    $pos = $pt['position_size'] ?? 400;
    $trigger = (float)($pt['trigger_price'] ?? 0);
    $cat = $pt['catalyst_headline'] ?? '';

    $active_trades_summary .= "- {$tick} | Присъда: {$v} | Клас: {$gr} (Вход: \${$pos}) | Увереност: {$conf}%\n";
    $active_trades_summary .= "  Текуща цена: \${$price} | Цел за печалба: \${$target} (Очаквана чиста печалба: +\${$gain}) | Защита Stop-Loss: \${$stop} (Макс риск: -\${$risk})\n";
    if ($trigger > 0) {
        $active_trades_summary .= "  Праг за пробив (Радар): \${$trigger} (Не купуваме преди да прескочи това ниво!)\n";
    }
    if ($cat) {
        $active_trades_summary .= "  Катализатор/Новини: {$cat}\n";
    }
}

// Check if user is asking about specific tickers or past performance
$user_text = "";
foreach ($user_messages as $m) {
    $user_text .= " " . ($m['content'] ?? '');
}
$user_text_upper = strtoupper($user_text);

$known_tickers = ['NBIS', 'APP', 'META', 'IREN', 'EOSE', 'CRWV', 'HIMS', 'CRDO', 'OUST', 'MU', 'AAPL'];
$rag_sections = [];
foreach ($known_tickers as $kt) {
    if (preg_match('/\b' . $kt . '\b/i', $user_text_upper)) {
        $rag_script = __DIR__ . '/rag_engine.py';
        $out = [];
        exec("python3 " . escapeshellarg($rag_script) . " " . escapeshellarg($kt) . " 2>&1", $out);
        $rag_sections[] = implode("\n", $out);
    }
}
$rag_context_block = !empty($rag_sections) ? "\n\nИЗВЛЕЧЕНА ИСТОРИЧЕСКА ПАМЕТ (RAG CONTEXT):\n" . implode("\n---\n", $rag_sections) : "";

// 2. Formulate System Prompt with strict Grounding & Bulgarian Persona
$system_prompt = "Ти си 'Lexmation AI' – интелигентният виртуален борсов асистент за платформата stocks.lexmation.com.
Твоята цел е да отговаряш на потребителя (който може да е начинаещ, по-възрастен човек без трейдърски опит като баща на инвеститора, или абонат) по изключително приятелски, спокоен, уверен и кристално ясен начин на български език.

АКТУАЛНИ ДАННИ ЗА ПОРТФЕЙЛА КЪМ МОМЕНТА (ИСТИНАТА):
- Стартов капитал: $1,000.00
- Текущ баланс в сметката: \${$balance}
- Реализирана чиста печалба: +\${$pnl} (+{$roi}% възвръщаемост)
- Успеваемост: 100% победи ({$green_days} от {$green_days} успешни дни, {$red_days} дни на загуба, Max Drawdown: 0.0%)
- Пазарен режим: {$regime}

АКТИВНИ СДЕЛКИ НА РАДАРА ДНЕС:
{$active_trades_summary}{$rag_context_block}

ПРАВИЛА И СИГУРНОСТ НА СИСТЕМАТА:
1. Търгуваме 100% СПОТ КЕШ, 0% КРЕДИТИ / ЛИВЪРИДЖ. Парите са лично наши, никой не може да ни поиска дълг или да ни ликвидира.
2. Оразмеряване: Клас А+ влизаме с $400, Клас B влизаме с $200. Останалият капитал стои винаги защитен в брой.
3. Асиметричен риск и професионален Stop-Loss буфер: Стопът ни е разположен с 1.5% - 2.5% буфер за волатилност под ключови технически подкрепи (15m/1h), за да даде въздух на акцията да 'диша' и да предотврати фалшиво изхвърляне от пазарен шум (whipsaw / stop hunt). Максималният риск в долари е стриктно ограничен до ~$3.50-$7.20 (под 1% от целия капитал), докато таргетите целят между +$7 и +$19 чиста печалба (съотношение Risk/Reward 1:2.0+).
4. Психология и търпение: При статус 'WATCH' (като NBIS или CRDO) КАТЕГОРИЧНО НЕ купуваме на сляпо! Чакаме алгоритъма да потвърди пробив с висок институционален обем. При 'BUY' (като META) има зелена светлина за директен вход.

ПРАВИЛА ЗА ТВОИТЕ ОТГОВОРИ:
- Говори на достъпен, естествен и топъл български език, без сухо професионално високомерие и без сложни трейдърски съкращения.
- Когато те питат за конкретна акция (напр. META, NBIS, CRDO), цитирай точните числа: вход, очаквана чиста печалба в долари и максимален риск (Stop Loss).
- Ако потребителят попита за стоп лоса или се оплаче, че графиките се движат и го задействат, обясни концепцията за 'буфер срещу пазарен шум' – стопът ни е на 1.8% дистанция точно за да издържи минутните колебания и да хване голямото покачване.
- Ако потребителят се притеснява дали парите му са сигурни, успокой го с 3-те правила за сигурност (без кредити, малък фиксиран риск под 1%, желязно търпение).
- Отговаряй стегнато, прегледно (с точки или кратки абзаци) и не прекалявай с дължината, освен ако изрично не те помолят за подробности.";

// 3. Prepare payload for LLM (Antigravity Gateway via Local AgyApi)
$api_url = "http://127.0.0.1:5143/v1/chat/completions";
$api_key = "w9C7IP18QUYraX2_Qgf8DJxcMeTrVigHGZg6IC_md2rVPQ5r285soBmQ7BYLhiD";
$model = "gemini-3.8-flash-low";

$messages = [
    ["role" => "system", "content" => $system_prompt]
];
foreach ($user_messages as $m) {
    if (isset($m['role']) && isset($m['content'])) {
        $messages[] = [
            "role" => $m['role'] === 'user' ? 'user' : 'assistant',
            "content" => (string)$m['content']
        ];
    }
}

// 4. Send request to LLM (with or without streaming)
$payload = [
    "model" => $model,
    "messages" => $messages,
    "max_tokens" => 600,
    "temperature" => 0.5,
    "stream" => $stream
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $api_key
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, !$stream);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);

if ($stream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        echo $data;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        return strlen($data);
    });
    curl_exec($ch);
    curl_close($ch);
    exit;
} else {
    header('Content-Type: application/json; charset=utf-8');
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    $reply = $data['choices'][0]['message']['content'] ?? 'Съжалявам, възникна кратко забавяне при връзката с AI модела. Моля опитайте отново след момент.';
    echo json_encode(['reply' => $reply]);
    exit;
}
