<?php
// ==============================================================================
// Kalimera AI Real Estate CRM — REST API Backend
// ==============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataPath = __DIR__ . '/data/leads.json';

function getLeadsData($path) {
    if (!file_exists($path)) {
        file_put_contents($path, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function saveLeadsData($path, $data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function computeStats($leads) {
    $counts = [
        'new_discovered' => 0,
        'ai_audited'     => 0,
        'outreach_sent'  => 0,
        'follow_up'      => 0,
        'replied'        => 0,
        'deal_closed'    => 0,
        'total'          => count($leads)
    ];

    $regions = [];
    $totalPipelineVal = 0;

    foreach ($leads as $l) {
        $st = $l['status'] ?? 'new_discovered';
        if (isset($counts[$st])) {
            $counts[$st]++;
        } else {
            $counts[$st] = 1;
        }

        $reg = $l['region'] ?? 'Other';
        $regions[$reg] = ($regions[$reg] ?? 0) + 1;

        if (isset($l['estimated_revenue'])) {
            preg_match('/[0-9,]+/', str_replace(',', '', $l['estimated_revenue']), $m);
            if (!empty($m[0])) {
                $totalPipelineVal += (float)$m[0];
            }
        }
    }

    return [
        'counts' => $counts,
        'regions' => $regions,
        'total_pipeline_val' => $totalPipelineVal
    ];
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

// 1. GET ALL LEADS & STATS
if ($action === '' || $action === 'get_leads') {
    $leads = getLeadsData($dataPath);
    $stats = computeStats($leads);
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'leads' => $leads
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. UPDATE STATUS
if ($action === 'update_status') {
    $id = $input['id'] ?? '';
    $newStatus = $input['status'] ?? '';

    if (!$id || !$newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing lead ID or status']);
        exit;
    }

    $leads = getLeadsData($dataPath);
    $updated = false;

    foreach ($leads as &$l) {
        if ($l['id'] === $id) {
            $l['status'] = $newStatus;
            $l['updated_at'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }

    if ($updated) {
        saveLeadsData($dataPath, $leads);
        echo json_encode(['success' => true, 'id' => $id, 'new_status' => $newStatus, 'stats' => computeStats($leads)]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lead not found']);
    }
    exit;
}

// 3. ADD NEW LEAD (Used by n8n workflow or manual add)
if ($action === 'add_lead') {
    $propertyName = trim($input['property_name'] ?? '');
    if (!$propertyName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Property name is required']);
        exit;
    }

    $leads = getLeadsData($dataPath);

    // Deduplication check: Do not add if phone or exact property name exists
    $incomingPhone = preg_replace('/[^0-9+]/', '', $input['phone'] ?? '');
    foreach ($leads as $existing) {
        $existPhone = preg_replace('/[^0-9+]/', '', $existing['phone'] ?? '');
        $samePhone = (!empty($incomingPhone) && !empty($existPhone) && $incomingPhone === $existPhone);
        $sameName = (mb_strtolower(trim($existing['property_name'] ?? '')) === mb_strtolower($propertyName));
        if ($samePhone || $sameName) {
            echo json_encode([
                'success' => true,
                'already_exists' => true,
                'message' => 'Property already exists in CRM. Skipped duplicate.',
                'lead' => $existing,
                'stats' => computeStats($leads)
            ]);
            exit;
        }
    }

    $newLead = [
        'id' => 'kal-lead-' . (count($leads) + 101),
        'property_name' => $propertyName,
        'owner_name' => trim($input['owner_name'] ?? 'Property Owner'),
        'phone' => trim($input['phone'] ?? ''),
        'email' => trim($input['email'] ?? ''),
        'region' => trim($input['region'] ?? 'Greece General'),
        'website' => trim($input['website'] ?? ''),
        'status' => $input['status'] ?? 'new_discovered',
        'grade' => $input['grade'] ?? 'A',
        'estimated_revenue' => $input['estimated_revenue'] ?? '€35,000/season',
        'audit_findings' => $input['audit_findings'] ?? [
            'calendar_sync' => 'Under assessment',
            'video_marketing' => 'Not found',
            'direct_booking' => 'Standard OTA dependence'
        ],
        'ai_draft' => $input['ai_draft'] ?? [
            'subject' => "Direct booking automation for {$propertyName}",
            'body' => "Hi,\n\nI was reviewing {$propertyName} and noticed an opportunity to automate your guest inquiries and capture more direct bookings without OTA commissions.\n\nLet me know if you would like a brief preview from Kalimera.",
            'whatsapp_msg' => "Γεια σας! Είδα το εξαιρετικό {$propertyName}. Έχουμε ένα αυτόματο σύστημα direct κρατήσεων και promo video. Θα θέλατε να σας στείλω ένα σύντομο demo;"
        ],
        'notes' => trim($input['notes'] ?? 'Discovered via automated real estate pipeline.'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Prepend to top of list
    array_unshift($leads, $newLead);
    saveLeadsData($dataPath, $leads);

    echo json_encode(['success' => true, 'lead' => $newLead, 'stats' => computeStats($leads)]);
    exit;
}

// 4. UPDATE NOTES / DETAILS
if ($action === 'update_notes') {
    $id = $input['id'] ?? '';
    $notes = $input['notes'] ?? '';

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing lead ID']);
        exit;
    }

    $leads = getLeadsData($dataPath);
    $found = false;

    foreach ($leads as &$l) {
        if ($l['id'] === $id) {
            $l['notes'] = $notes;
            if (isset($input['email'])) {
                $l['email'] = trim($input['email']);
            }
            if (isset($input['email_draft_subject'])) {
                $l['ai_draft']['subject'] = $input['email_draft_subject'];
            }
            if (isset($input['email_draft_body'])) {
                $l['ai_draft']['body'] = $input['email_draft_body'];
            }
            if (isset($input['whatsapp_msg'])) {
                $l['ai_draft']['whatsapp_msg'] = $input['whatsapp_msg'];
            }
            $l['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }

    if ($found) {
        saveLeadsData($dataPath, $leads);
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lead not found']);
    }
    exit;
}

// 5. DELETE LEAD
if ($action === 'delete_lead') {
    $id = $input['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing ID']);
        exit;
    }

    $leads = getLeadsData($dataPath);
    $newLeads = array_values(array_filter($leads, function($item) use ($id) {
        return $item['id'] !== $id;
    }));

    saveLeadsData($dataPath, $newLeads);
    echo json_encode(['success' => true, 'deleted_id' => $id, 'stats' => computeStats($newLeads)]);
    exit;
}

// 6. SEND OUTREACH EMAIL via n8n alexassenov@gmail.com
if ($action === 'send_email') {
    $id = $input['id'] ?? '';
    $to = trim($input['to'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    if (!$to || !$subject || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Recipient email, subject, and message are required.']);
        exit;
    }

    // Call n8n webhook
    $webhookUrl = 'http://127.0.0.1:5678/webhook/kalimera-send-email';
    $postData = json_encode([
        'id' => $id,
        'to' => $to,
        'subject' => $subject,
        'message' => $message
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $leads = getLeadsData($dataPath);
        $statusUpdated = false;
        if ($id) {
            foreach ($leads as &$l) {
                if ($l['id'] === $id) {
                    $l['status'] = 'outreach_sent';
                    if (empty($l['email'])) {
                        $l['email'] = $to;
                    }
                    $l['updated_at'] = date('Y-m-d H:i:s');
                    $timestamp = date('d/m/Y H:i');
                    $l['notes'] = trim(($l['notes'] ?? '') . "\n[{$timestamp}] Sent outreach email from alexassenov@gmail.com to {$to}");
                    $statusUpdated = true;
                    break;
                }
            }
            if ($statusUpdated) {
                saveLeadsData($dataPath, $leads);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Имейлът беше успешно изпратен от alexassenov@gmail.com до {$to}!",
            'new_status' => 'outreach_sent',
            'stats' => computeStats($leads)
        ]);
    } else {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => "Грешка при изпращане през n8n (HTTP {$httpCode}): " . ($curlErr ?: $response)
        ]);
    }
    exit;
}

// 7. SEND OUTREACH WHATSAPP via Evolution API
if ($action === 'send_whatsapp') {
    $id = $input['id'] ?? '';
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
    $message = trim($input['message'] ?? '');

    if (!$phone || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Телефонният номер и текстът на съобщението са задължителни.']);
        exit;
    }

    $evoUrl = 'http://127.0.0.1:8085/send';
    $postData = json_encode([
        'number' => $phone,
        'text' => $message
    ]);

    $ch = curl_init($evoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $resData = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($resData['success'])) {
        $leads = getLeadsData($dataPath);
        $statusUpdated = false;
        if ($id) {
            foreach ($leads as &$l) {
                if ($l['id'] === $id) {
                    $l['status'] = 'outreach_sent';
                    $l['updated_at'] = date('Y-m-d H:i:s');
                    $timestamp = date('d/m/Y H:i');
                    $l['notes'] = trim(($l['notes'] ?? '') . "\n[{$timestamp}] Sent WhatsApp message to +{$phone}");
                    $statusUpdated = true;
                    break;
                }
            }
            if ($statusUpdated) {
                saveLeadsData($dataPath, $leads);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "WhatsApp съобщението беше успешно изпратено до +{$phone}!",
            'new_status' => 'outreach_sent',
            'stats' => computeStats($leads),
            'response' => $resData
        ]);
    } else {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => "Грешка при изпращане през WhatsApp Gateway: " . ($resData['error'] ?? ($curlErr ?: $response))
        ]);
    }
    exit;
}

// 8. WEBHOOK UPDATE LEAD BY PHONE (e.g. from Inbound WhatsApp)
if ($action === 'webhook_update') {
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
    $status = $input['status'] ?? 'replied';
    $lastMsg = trim($input['last_message'] ?? '');

    $leads = getLeadsData($dataPath);
    $matched = false;

    if ($phone) {
        foreach ($leads as &$l) {
            $lPhone = preg_replace('/[^0-9]/', '', $l['phone'] ?? '');
            // match if phone ends with last 8-9 digits
            if ($lPhone && (substr($lPhone, -8) === substr($phone, -8) || $lPhone === $phone)) {
                $l['status'] = $status;
                $l['updated_at'] = date('Y-m-d H:i:s');
                $timestamp = date('d/m/Y H:i');
                $noteMsg = $lastMsg ? " [Msg: \"{$lastMsg}\"]" : "";
                $l['notes'] = trim(($l['notes'] ?? '') . "\n[{$timestamp}] Received WhatsApp reply:{$noteMsg}");
                $matched = true;
                break;
            }
        }
        if ($matched) {
            saveLeadsData($dataPath, $leads);
        }
    }

    echo json_encode([
        'success' => true,
        'matched' => $matched,
        'phone' => $phone,
        'status' => $status
    ]);
    exit;
}

// 9. CONVERSATION HISTORY & MEMORY API (For AI WhatsApp Chatbot)
$convFile = __DIR__ . '/conversations.json';

if ($action === 'get_conversation') {
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? $_GET['phone'] ?? '');
    $limit = intval($input['limit'] ?? $_GET['limit'] ?? 10);
    $history = [];
    if ($phone && file_exists($convFile)) {
        $all = json_decode(file_get_contents($convFile), true) ?: [];
        $history = $all[$phone] ?? [];
        if ($limit > 0 && count($history) > $limit) {
            $history = array_slice($history, -$limit);
        }
    }
    echo json_encode([
        'success' => true,
        'phone' => $phone,
        'has_history' => !empty($history),
        'count' => count($history),
        'history' => $history
    ]);
    exit;
}

if ($action === 'append_conversation') {
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
    $role = $input['role'] ?? 'user';
    $message = trim($input['message'] ?? '');

    if (!$phone || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Phone and message are required']);
        exit;
    }

    $all = file_exists($convFile) ? (json_decode(file_get_contents($convFile), true) ?: []) : [];
    if (!isset($all[$phone])) $all[$phone] = [];

    $all[$phone][] = [
        'role' => $role,
        'content' => $message,
        'time' => date('Y-m-d H:i:s')
    ];

    // Keep last 30 messages per phone
    if (count($all[$phone]) > 30) {
        $all[$phone] = array_slice($all[$phone], -30);
    }

    file_put_contents($convFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'total_messages' => count($all[$phone])]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action parameter']);
