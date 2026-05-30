<?php
// api/availability.php  — Staff manages provider availability
require_once 'config.php';
session_start();

// All routes here require staff
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    respond(['error' => 'Staff only'], 403);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ─────────────────────────────────────────────────────────
// GET all availability for a provider
// GET /api/availability.php?action=list&provider_id=1
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $provider_id = intval($_GET['provider_id'] ?? 0);
    if (!$provider_id) respond(['error' => 'provider_id required'], 400);

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM availability
         WHERE provider_id = ? AND avail_date >= CURDATE()
         ORDER BY avail_date, start_time'
    );
    $stmt->execute([$provider_id]);
    respond($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────
// POST add one or more slots for a provider
// POST /api/availability.php?action=add
// Body: { provider_id, slots: [ { date, start_time, end_time }, ... ] }
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'add') {
    $body        = getBody();
    $provider_id = intval($body['provider_id'] ?? 0);
    $slots       = $body['slots'] ?? [];

    if (!$provider_id || empty($slots)) {
        respond(['error' => 'provider_id and slots array required'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT IGNORE INTO availability (provider_id, avail_date, start_time, end_time)
         VALUES (?, ?, ?, ?)'
    );

    $added = 0;
    foreach ($slots as $slot) {
        $date  = $slot['date']       ?? '';
        $start = $slot['start_time'] ?? '';
        $end   = $slot['end_time']   ?? '';
        if (!$date || !$start || !$end) continue;
        $stmt->execute([$provider_id, $date, $start, $end]);
        $added += $stmt->rowCount();
    }

    respond(['success' => true, 'added' => $added]);
}

// ─────────────────────────────────────────────────────────
// POST generate recurring slots for a provider
// POST /api/availability.php?action=generate
// Body: {
//   provider_id, from_date, to_date,
//   weekdays: [1,2,3,4,5],   // 1=Mon … 7=Sun
//   time_slots: [ { start_time, end_time }, ... ]
// }
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'generate') {
    $body        = getBody();
    $provider_id = intval($body['provider_id'] ?? 0);
    $from        = $body['from_date']   ?? '';
    $to          = $body['to_date']     ?? '';
    $weekdays    = $body['weekdays']    ?? [1,2,3,4,5];
    $time_slots  = $body['time_slots']  ?? [];

    if (!$provider_id || !$from || !$to || empty($time_slots)) {
        respond(['error' => 'Missing required fields'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT IGNORE INTO availability (provider_id, avail_date, start_time, end_time)
         VALUES (?, ?, ?, ?)'
    );

    $current = new DateTime($from);
    $end     = new DateTime($to);
    $added   = 0;

    while ($current <= $end) {
        $dow = (int)$current->format('N'); // 1=Mon … 7=Sun
        if (in_array($dow, $weekdays)) {
            foreach ($time_slots as $ts) {
                $stmt->execute([
                    $provider_id,
                    $current->format('Y-m-d'),
                    $ts['start_time'],
                    $ts['end_time']
                ]);
                $added += $stmt->rowCount();
            }
        }
        $current->modify('+1 day');
    }

    respond(['success' => true, 'added' => $added]);
}

// ─────────────────────────────────────────────────────────
// DELETE a slot (only if not booked)
// DELETE /api/availability.php?action=delete&id=5
// ─────────────────────────────────────────────────────────
if ($method === 'DELETE' && $action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) respond(['error' => 'id required'], 400);

    $db   = getDB();
    $stmt = $db->prepare('SELECT is_booked FROM availability WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) respond(['error' => 'Slot not found'], 404);
    if ($row['is_booked']) respond(['error' => 'Cannot delete a booked slot'], 409);

    $db->prepare('DELETE FROM availability WHERE id = ?')->execute([$id]);
    respond(['success' => true]);
}

respond(['error' => 'Invalid action'], 400);
