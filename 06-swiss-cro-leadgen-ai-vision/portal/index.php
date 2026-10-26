<?php
require_once __DIR__ . '/config.php';
check_auth();

// Fetch all leads
$stmt = $pdo->query("SELECT * FROM leads ORDER BY pagespeed_score ASC, rating DESC");
$leads = $stmt->fetchAll();

// Statistics
$total_leads = count($leads);
$high_potential = 0;
$missing_call = 0;
$total_score = 0;

foreach ($leads as $l) {
    if ($l['pagespeed_score'] < 60) {
        $high_potential++;
    }
    if (strpos($l['technical_issues'] ?? '', 'Fehlender Click-to-Call') !== false || strpos($l['ai_ux_flaws'] ?? '', 'Kein sofort sichtbarer Notfall-Anrufbutton') !== false) {
        $missing_call++;
    }
    $total_score += $l['pagespeed_score'];
}
$avg_score = $total_leads > 0 ? round($total_score / $total_leads) : 0;
?>
<!DOCTYPE html>
<html lang="de" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joschua Web Agency | Lead Intelligence & CRO Audit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 flex flex-col antialiased">

    <!-- Top Navigation -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-base font-bold text-white tracking-tight leading-none flex items-center gap-2">
                        Joschua Web Agency
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Client Portal</span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-0.5">B2B Lead Intelligence • Sanitär & Heizung Zürich</p>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <a href="export.php"
                   class="inline-flex items-center px-3.5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm shadow-emerald-600/30 transition-all">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Excel / CSV Export
                </a>
                <a href="login.php?logout=1"
                   class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
                    Abmelden
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Gefundene Leads</span>
                    <span class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline">
                    <span class="text-3xl font-extrabold text-white"><?= $total_leads ?></span>
                    <span class="ml-2 text-xs text-slate-400">Unternehmen in Zürich</span>
                </div>
            </div>

            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-400">Hohes Relaunch-Potenzial</span>
                    <span class="p-2 rounded-lg bg-amber-500/10 text-amber-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline">
                    <span class="text-3xl font-extrabold text-amber-400"><?= $high_potential ?></span>
                    <span class="ml-2 text-xs text-slate-400">PageSpeed &lt; 60/100</span>
                </div>
            </div>

            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-red-400">Fehlender Call-Button</span>
                    <span class="p-2 rounded-lg bg-red-500/10 text-red-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline">
                    <span class="text-3xl font-extrabold text-red-400"><?= $missing_call ?></span>
                    <span class="ml-2 text-xs text-slate-400">Verlieren Notfall-Anrufe</span>
                </div>
            </div>

            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ø Mobile Score</span>
                    <span class="p-2 rounded-lg bg-blue-500/10 text-blue-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline">
                    <span class="text-3xl font-extrabold text-white"><?= $avg_score ?></span>
                    <span class="ml-1 text-slate-400 text-sm">/100</span>
                    <span class="ml-2 text-xs text-slate-400">Performance Index</span>
                </div>
            </div>
        </div>

        <!-- Filters & Search Toolbar -->
        <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="relative w-full md:w-96">
                <input type="text" id="searchInput" placeholder="Firma, Domain oder Telefon suchen..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 text-sm text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <select id="scoreFilter" class="px-3.5 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 text-xs font-medium text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Alle PageSpeed Scores</option>
                    <option value="critical">Kritisch (&lt; 50 Punkte)</option>
                    <option value="moderate">Ausbaufähig (50 - 79)</option>
                    <option value="good">Gut (80+ Punkte)</option>
                </select>

                <select id="statusFilter" class="px-3.5 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 text-xs font-medium text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Alle Status</option>
                    <option value="new">Neu</option>
                    <option value="reviewed">Geprüft</option>
                    <option value="contacted">Kontaktiert</option>
                </select>
            </div>
        </div>

        <!-- Leads Table -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-sm text-slate-300 divide-y divide-slate-800" id="leadsTable">
                    <thead class="bg-slate-900/90 text-xs uppercase font-semibold tracking-wider text-slate-400">
                        <tr>
                            <th scope="col" class="px-6 py-4">Unternehmen / Domain</th>
                            <th scope="col" class="px-6 py-4">Kontakt & Standort</th>
                            <th scope="col" class="px-6 py-4">Google Rating</th>
                            <th scope="col" class="px-6 py-4 text-center">Mobile PageSpeed</th>
                            <th scope="col" class="px-6 py-4">Status</th>
                            <th scope="col" class="px-6 py-4 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60" id="tableBody">
                        <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                    Keine Leads in der Datenbank gefunden. Der Scan-Prozess wird initialisiert...
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $lead): ?>
                                <tr class="lead-row hover:bg-slate-800/40 transition-colors"
                                    data-id="<?= $lead['id'] ?>"
                                    data-score="<?= $lead['pagespeed_score'] ?>"
                                    data-status="<?= $lead['status'] ?>"
                                    data-search="<?= strtolower(htmlspecialchars($lead['business_name'] . ' ' . $lead['domain'] . ' ' . $lead['phone_number'])) ?>">
                                    
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-white text-sm flex items-center gap-2">
                                            <?= htmlspecialchars($lead['business_name']) ?>
                                            <?php if (strpos($lead['source'] ?? '', 'Google Ads') !== false): ?>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-violet-500/20 text-violet-300 border border-violet-500/30">Google Ads</span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="<?= htmlspecialchars($lead['website_url']) ?>" target="_blank" rel="noopener"
                                           class="text-xs text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1 mt-0.5">
                                            <?= htmlspecialchars($lead['domain']) ?>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </td>

                                    <td class="px-6 py-4 text-xs space-y-1">
                                        <?php if ($lead['phone_number']): ?>
                                            <div class="flex items-center text-slate-200 font-medium">
                                                <svg class="w-3.5 h-3.5 mr-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                <a href="tel:<?= htmlspecialchars($lead['phone_number']) ?>" class="hover:text-indigo-400">
                                                    <?= htmlspecialchars($lead['phone_number']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-slate-400 truncate max-w-xs">
                                            <?= htmlspecialchars($lead['address'] ?: '—') ?>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php if (!empty($lead['rating']) && $lead['rating'] > 0): ?>
                                            <div class="flex items-center space-x-1">
                                                <span class="text-amber-400 font-bold text-sm">★ <?= number_format($lead['rating'], 1) ?></span>
                                                <span class="text-xs text-slate-400">(<?= $lead['reviews_count'] ?>)</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <?php
                                        $s = $lead['pagespeed_score'];
                                        $color = $s >= 80 ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : ($s >= 50 ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20');
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border <?= $color ?>">
                                            <?= $s ?> / 100
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <select onchange="updateLeadStatus(<?= $lead['id'] ?>, this.value)"
                                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-800 border border-slate-700 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer">
                                            <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?>>Neu</option>
                                            <option value="reviewed" <?= $lead['status'] === 'reviewed' ? 'selected' : '' ?>>Geprüft</option>
                                            <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?>>Kontaktiert</option>
                                        </select>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <button onclick="openAuditModal(<?= htmlspecialchars(json_encode($lead), ENT_QUOTES, 'UTF-8') ?>)"
                                                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/30 text-xs font-semibold transition-all">
                                            Audit & Pitch →
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal for Full AI Visual Audit & Outreach Pitch -->
    <div id="auditModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="relative bg-slate-900 border border-slate-800 rounded-3xl max-w-5xl w-full max-h-[94vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between bg-slate-900/90">
                <div>
                    <h3 id="modalTitle" class="text-lg font-bold text-white flex items-center gap-2"></h3>
                    <a id="modalWebsiteLink" href="#" target="_blank" class="text-xs text-indigo-400 hover:underline flex items-center gap-1 mt-0.5"></a>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Language Toggle (DE / EN) -->
                    <div class="flex items-center bg-slate-800/80 p-1 rounded-xl border border-slate-700/60 shadow-sm">
                        <button type="button" id="langDe" onclick="switchLanguage('de')" class="px-3 py-1 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow">
                            🇩🇪 DE
                        </button>
                        <button type="button" id="langEn" onclick="switchLanguage('en')" class="px-3 py-1 rounded-lg text-xs font-bold transition-all text-slate-400 hover:text-white">
                            🇬🇧 EN
                        </button>
                    </div>
                    <button onclick="closeAuditModal()" class="text-slate-400 hover:text-white p-2 rounded-xl hover:bg-slate-800 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Modal Body (Two-Column Layout) -->
            <div class="p-6 overflow-y-auto custom-scrollbar flex-1 grid grid-cols-1 md:grid-cols-12 gap-6">
                
                <!-- Left: Full Landing Page Screenshot Viewer (Mobile & Desktop) -->
                <div class="md:col-span-6 flex flex-col">
                    <div class="flex items-center justify-between w-full mb-3">
                        <div class="inline-flex rounded-lg bg-slate-800 p-1 border border-slate-700/80">
                            <button id="tabMobile" onclick="switchScreenshotTab('mobile')" class="px-3 py-1 rounded-md text-xs font-semibold bg-indigo-600 text-white shadow transition-all">
                                📱 Mobile (Full)
                            </button>
                            <button id="tabDesktop" onclick="switchScreenshotTab('desktop')" class="px-3 py-1 rounded-md text-xs font-semibold text-slate-400 hover:text-white transition-all">
                                💻 Desktop (Full)
                            </button>
                        </div>
                        <a id="modalScreenshotLink" href="#" target="_blank" class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1 font-semibold bg-indigo-500/10 px-2.5 py-1 rounded-lg border border-indigo-500/20">
                            🔍 Vollbild öffnen ↗
                        </a>
                    </div>
                    
                    <div class="relative w-full bg-slate-950 border-2 border-slate-800 rounded-2xl p-2 shadow-2xl h-[560px] overflow-y-auto custom-scrollbar flex justify-center">
                        <img id="modalScreenshot" src="" alt="Full Landing Page Screenshot" class="w-full h-auto object-contain object-top rounded-lg shadow-inner">
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-400 mt-2 px-1">
                        <span>↕ Im Fenster scrollen für die ganze Seite</span>
                        <span id="screenshotDimensions" class="text-slate-500">Mobile 390px View</span>
                    </div>
                </div>

                <!-- Right: Audit Findings & Cold Pitch -->
                <div class="md:col-span-6 space-y-5">
                    
                    <!-- Metrics row -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3 text-center">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Performance</span>
                            <span id="modalScore" class="text-lg font-bold text-white"></span>
                        </div>
                        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3 text-center">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">FCP (Speed)</span>
                            <span id="modalFcp" class="text-lg font-bold text-white"></span>
                        </div>
                        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3 text-center">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">LCP (Load)</span>
                            <span id="modalLcp" class="text-lg font-bold text-white"></span>
                        </div>
                    </div>

                    <!-- Technical & UX Flaws -->
                    <div class="space-y-3">
                        <div>
                            <h4 id="modalFlawsTitle" class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-1 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Erkannte UX & CRO Schwachstellen
                            </h4>
                            <div id="modalFlaws" class="text-xs text-slate-300 bg-slate-800/40 border border-slate-800 rounded-xl p-3 whitespace-pre-line leading-relaxed"></div>
                        </div>

                        <div>
                            <h4 id="modalTechTitle" class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                Technische Diagnose
                            </h4>
                            <div id="modalTech" class="text-xs text-slate-300 bg-slate-800/40 border border-slate-800 rounded-xl p-3 whitespace-pre-line leading-relaxed"></div>
                        </div>
                    </div>

                    <!-- Personalized Cold Pitch / Email Box -->
                    <div class="bg-gradient-to-br from-indigo-950/70 to-slate-900 border border-indigo-500/40 rounded-2xl p-4 shadow-xl">
                        <div class="flex items-center justify-between mb-2.5">
                            <span id="modalPitchTitle" class="text-xs font-bold uppercase tracking-wider text-indigo-300 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Personalisierte Outreach E-Mail (Joschua Template)
                            </span>
                            <button id="copyBtn" onclick="copyPitch()"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow transition-all">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                E-Mail kopieren
                            </button>
                        </div>
                        <pre id="modalPitch" class="text-xs text-slate-200 leading-relaxed font-sans whitespace-pre-wrap bg-slate-950/70 p-4 rounded-xl border border-indigo-500/20 custom-scrollbar max-h-64 overflow-y-auto select-all"></pre>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <!-- JavaScript Filter & Modal Logic -->
    <script>
        const searchInput = document.getElementById('searchInput');
        const scoreFilter = document.getElementById('scoreFilter');
        const statusFilter = document.getElementById('statusFilter');
        const rows = document.querySelectorAll('.lead-row');

        function filterLeads() {
            const query = searchInput.value.toLowerCase().trim();
            const scoreVal = scoreFilter.value;
            const statusVal = statusFilter.value;

            rows.forEach(row => {
                const searchData = row.dataset.search;
                const score = parseInt(row.dataset.score);
                const status = row.dataset.status;

                let matchesSearch = !query || searchData.includes(query);
                let matchesStatus = (statusVal === 'all') || (status === statusVal);
                
                let matchesScore = true;
                if (scoreVal === 'critical') matchesScore = score < 50;
                else if (scoreVal === 'moderate') matchesScore = score >= 50 && score < 80;
                else if (scoreVal === 'good') matchesScore = score >= 80;

                if (matchesSearch && matchesStatus && matchesScore) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterLeads);
        scoreFilter.addEventListener('change', filterLeads);
        statusFilter.addEventListener('change', filterLeads);

        // Modal Logic
        let currentPitch = '';
        let currentLeadData = null;
        let userLangPref = 'de';

        function switchLanguage(lang) {
            userLangPref = lang;
            const btnDe = document.getElementById('langDe');
            const btnEn = document.getElementById('langEn');
            const flawsElem = document.getElementById('modalFlaws');
            const pitchElem = document.getElementById('modalPitch');
            const flawsTitle = document.getElementById('modalFlawsTitle');
            const pitchTitle = document.getElementById('modalPitchTitle');
            
            if (lang === 'en') {
                btnEn.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow';
                btnDe.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all text-slate-400 hover:text-white';
                if (flawsTitle) flawsTitle.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> Identified UX & CRO Opportunities';
                if (pitchTitle) pitchTitle.innerHTML = '<svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> Personalized Outreach Email (English Review)';
                
                flawsElem.textContent = (currentLeadData && currentLeadData.ai_ux_flaws_en) ? currentLeadData.ai_ux_flaws_en : (currentLeadData?.ai_ux_flaws || 'No significant flaws detected.');
                currentPitch = (currentLeadData && currentLeadData.generated_pitch_en) ? currentLeadData.generated_pitch_en : (currentLeadData?.generated_pitch || '');
                pitchElem.textContent = currentPitch;
            } else {
                btnDe.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow';
                btnEn.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all text-slate-400 hover:text-white';
                if (flawsTitle) flawsTitle.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> Erkannte UX & CRO Schwachstellen';
                if (pitchTitle) pitchTitle.innerHTML = '<svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> Personalisierte Outreach E-Mail (Joschua Template)';
                
                flawsElem.textContent = (currentLeadData && currentLeadData.ai_ux_flaws) ? currentLeadData.ai_ux_flaws : 'Keine signifikanten Mängel festgestellt.';
                currentPitch = (currentLeadData && currentLeadData.generated_pitch) ? currentLeadData.generated_pitch : '';
                pitchElem.textContent = currentPitch;
            }
        }

        function openAuditModal(lead) {
            currentLeadData = lead;
            document.getElementById('modalTitle').textContent = lead.business_name;
            const link = document.getElementById('modalWebsiteLink');
            link.href = lead.website_url;
            link.textContent = lead.website_url;

            switchScreenshotTab('mobile');

            document.getElementById('modalScore').textContent = lead.pagespeed_score + ' / 100';
            document.getElementById('modalFcp').textContent = lead.fcp || 'N/A';
            document.getElementById('modalLcp').textContent = lead.lcp || 'N/A';
            document.getElementById('modalTech').textContent = lead.technical_issues || 'Keine technischen Fehler gemeldet.';
            
            switchLanguage(userLangPref);

            const copyBtn = document.getElementById('copyBtn');
            copyBtn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Pitch kopieren';
            copyBtn.className = 'inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow transition-all';

            document.getElementById('auditModal').classList.remove('hidden');
        }

        function switchScreenshotTab(tab) {
            if (!currentLeadData) return;
            const tabM = document.getElementById('tabMobile');
            const tabD = document.getElementById('tabDesktop');
            const img = document.getElementById('modalScreenshot');
            const link = document.getElementById('modalScreenshotLink');
            const dim = document.getElementById('screenshotDimensions');

            const timestamp = currentLeadData.updated_at ? new Date(currentLeadData.updated_at).getTime() : Date.now();
            let targetUrl = currentLeadData.screenshot_url;

            if (tab === 'desktop' && currentLeadData.desktop_screenshot_url) {
                targetUrl = currentLeadData.desktop_screenshot_url;
                tabD.className = 'px-3 py-1 rounded-md text-xs font-semibold bg-indigo-600 text-white shadow transition-all';
                tabM.className = 'px-3 py-1 rounded-md text-xs font-semibold text-slate-400 hover:text-white transition-all';
                if (dim) dim.textContent = 'Desktop 1280px View';
            } else {
                tabM.className = 'px-3 py-1 rounded-md text-xs font-semibold bg-indigo-600 text-white shadow transition-all';
                tabD.className = 'px-3 py-1 rounded-md text-xs font-semibold text-slate-400 hover:text-white transition-all';
                if (dim) dim.textContent = 'Mobile 390px View';
            }

            const freshUrl = targetUrl + (targetUrl.includes('?') ? '&' : '?') + 'v=' + timestamp;
            img.src = freshUrl;
            if (link) link.href = freshUrl;
        }

        function closeAuditModal() {
            document.getElementById('auditModal').classList.add('hidden');
        }

        function copyPitch() {
            if (!currentPitch) return;
            navigator.clipboard.writeText(currentPitch).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Kopiert!';
                btn.className = 'inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold shadow transition-all';
                setTimeout(() => {
                    btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Pitch kopieren';
                    btn.className = 'inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow transition-all';
                }, 2000);
            });
        }

        function updateLeadStatus(id, status) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', status);

            fetch('update_status.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    const row = document.querySelector(`.lead-row[data-id="${id}"]`);
                    if (row) row.dataset.status = status;
                }
            });
        }

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAuditModal();
        });
    </script>
</body>
</html>
