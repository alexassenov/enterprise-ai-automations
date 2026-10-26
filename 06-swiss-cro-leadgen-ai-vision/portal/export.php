<?php
require_once __DIR__ . '/config.php';
check_auth();

$stmt = $pdo->query("SELECT * FROM leads ORDER BY pagespeed_score ASC, rating DESC");
$leads = $stmt->fetchAll();

$filename = "joschua_leads_sanitaer_zuerich_" . date('Y-m-d_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'ID',
    'Firma',
    'Website URL',
    'Domain',
    'Telefon',
    'E-Mail',
    'Adresse',
    'Rating',
    'Bewertungen',
    'Quelle',
    'Mobile PageSpeed (0-100)',
    'FCP',
    'LCP',
    'Technische Fehler',
    'AI UX / CRO Schwachstellen (DE)',
    'AI UX / CRO Flaws (EN)',
    'Outreach Pitch (Deutsch)',
    'Outreach Pitch (English)',
    'Screenshot Mobile Link',
    'Screenshot Desktop Link',
    'Status',
    'Erstellt am'
], ';');

foreach ($leads as $l) {
    fputcsv($output, [
        $l['id'],
        $l['business_name'],
        $l['website_url'],
        $l['domain'],
        $l['phone_number'],
        $l['email'],
        $l['address'],
        $l['rating'],
        $l['reviews_count'],
        $l['source'],
        $l['pagespeed_score'],
        $l['fcp'],
        $l['lcp'],
        str_replace("\n", " | ", $l['technical_issues'] ?? ''),
        str_replace("\n", " | ", $l['ai_ux_flaws'] ?? ''),
        str_replace("\n", " | ", $l['ai_ux_flaws_en'] ?? ''),
        $l['generated_pitch'],
        $l['generated_pitch_en'] ?? '',
        $l['screenshot_url'],
        $l['desktop_screenshot_url'] ?? '',
        $l['status'],
        $l['created_at']
    ], ';');
}

fclose($output);
exit;
