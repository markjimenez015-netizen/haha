<?php
// api/appointments.php
require_once 'config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ─────────────────────────────────────────────────────────
// GET providers list (public — no auth needed for dropdown)
// GET /api/appointments.php?action=providers
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'providers') {
    $db   = getDB();
    $stmt = $db->query('SELECT id, name, role, specialty, avatar_initials FROM providers WHERE is_active = 1 ORDER BY role, name');
    respond($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────
// GET available dates for a provider
// GET /api/appointments.php?action=dates&provider_id=1
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'dates') {
    $provider_id = intval($_GET['provider_id'] ?? 0);
    if (!$provider_id) respond(['error' => 'provider_id required'], 400);

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT DISTINCT avail_date
         FROM availability
         WHERE provider_id = ? AND is_booked = 0 AND avail_date >= CURDATE()
         ORDER BY avail_date
         LIMIT 30'
    );
    $stmt->execute([$provider_id]);
    $dates = array_column($stmt->fetchAll(), 'avail_date');
    respond($dates);
}

// ─────────────────────────────────────────────────────────
// GET available time slots for a provider on a date
// GET /api/appointments.php?action=slots&provider_id=1&date=2026-06-01
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'slots') {
    $provider_id = intval($_GET['provider_id'] ?? 0);
    $date        = $_GET['date'] ?? '';
    if (!$provider_id || !$date) respond(['error' => 'provider_id and date required'], 400);

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT id, start_time, end_time
         FROM availability
         WHERE provider_id = ? AND avail_date = ? AND is_booked = 0
         ORDER BY start_time'
    );
    $stmt->execute([$provider_id, $date]);
    $slots = $stmt->fetchAll();

    // Format times nicely: 09:00:00 → 9:00 AM
    foreach ($slots as &$slot) {
        $slot['label'] = date('g:i A', strtotime($slot['start_time']))
                       . ' – '
                       . date('g:i A', strtotime($slot['end_time']));
    }
    respond($slots);
}

// ─────────────────────────────────────────────────────────
// POST book an appointment (patient must be logged in)
// POST /api/appointments.php?action=book
// Body: { provider_id, availability_id, reason }
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'book') {
    session_start();
    if (empty($_SESSION['user'])) respond(['error' => 'Please log in first.'], 401);
    $patient = $_SESSION['user'];

    $body            = getBody();
    $provider_id     = intval($body['provider_id'] ?? 0);
    $availability_id = intval($body['availability_id'] ?? 0);
    $reason          = trim($body['reason'] ?? '');

    if (!$provider_id || !$availability_id) {
        respond(['error' => 'provider_id and availability_id are required.'], 400);
    }

    $db = getDB();

    // Lock the slot in a transaction
    $db->beginTransaction();
    try {
        // Fetch slot (with lock)
        $stmt = $db->prepare(
            'SELECT * FROM availability WHERE id = ? AND is_booked = 0 FOR UPDATE'
        );
        $stmt->execute([$availability_id]);
        $slot = $stmt->fetch();

        if (!$slot) {
            $db->rollBack();
            respond(['error' => 'This slot is no longer available. Please choose another.'], 409);
        }

        // Check patient doesn't already have an appointment on the same date
        $dup = $db->prepare(
            'SELECT id FROM appointments
             WHERE patient_id = ? AND appt_date = ? AND status != "cancelled"'
        );
        $dup->execute([$patient['id'], $slot['avail_date']]);
        if ($dup->fetch()) {
            $db->rollBack();
            respond(['error' => 'You already have an appointment on this date.'], 409);
        }

        // Mark slot as booked
        $db->prepare('UPDATE availability SET is_booked = 1 WHERE id = ?')->execute([$availability_id]);

        // Create appointment
        $ins = $db->prepare(
            'INSERT INTO appointments (patient_id, provider_id, availability_id, appt_date, appt_time, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, "pending")'
        );
        $ins->execute([
            $patient['id'],
            $provider_id,
            $availability_id,
            $slot['avail_date'],
            $slot['start_time'],
            $reason
        ]);
        $appt_id = $db->lastInsertId();

        // Notify patient
        $db->prepare(
            'INSERT INTO notifications (user_id, title, body) VALUES (?, ?, ?)'
        )->execute([
            $patient['id'],
            'Appointment Booked',
            'Your appointment has been booked. Awaiting confirmation.'
        ]);

        $db->commit();
        respond(['success' => true, 'appointment_id' => $appt_id]);

    } catch (Exception $e) {
        $db->rollBack();
        respond(['error' => 'Booking failed: ' . $e->getMessage()], 500);
    }
}

// ─────────────────────────────────────────────────────────
// GET patient's own appointments
// GET /api/appointments.php?action=mine
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'mine') {
    session_start();
    if (empty($_SESSION['user'])) respond(['error' => 'Unauthorized'], 401);
    $patient_id = $_SESSION['user']['id'];

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT a.*, p.name AS provider_name, p.role AS provider_role, p.specialty
         FROM appointments a
         JOIN providers p ON p.id = a.provider_id
         WHERE a.patient_id = ?
         ORDER BY a.appt_date DESC, a.appt_time DESC'
    );
    $stmt->execute([$patient_id]);
    respond($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────
// POST cancel an appointment
// POST /api/appointments.php?action=cancel
// Body: { appointment_id }
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'cancel') {
    session_start();
    if (empty($_SESSION['user'])) respond(['error' => 'Unauthorized'], 401);
    $patient_id = $_SESSION['user']['id'];

    $body    = getBody();
    $appt_id = intval($body['appointment_id'] ?? 0);
    if (!$appt_id) respond(['error' => 'appointment_id required'], 400);

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM appointments WHERE id = ? AND patient_id = ?'
    );
    $stmt->execute([$appt_id, $patient_id]);
    $appt = $stmt->fetch();

    if (!$appt) respond(['error' => 'Appointment not found.'], 404);

    // Release the slot
    $db->prepare('UPDATE availability SET is_booked = 0 WHERE id = ?')->execute([$appt['availability_id']]);
    $db->prepare('UPDATE appointments SET status = "cancelled" WHERE id = ?')->execute([$appt_id]);

    respond(['success' => true]);
}

// ─────────────────────────────────────────────────────────
// STAFF: GET all appointments
// GET /api/appointments.php?action=all  (staff only)
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'all') {
    session_start();
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
        respond(['error' => 'Staff only'], 403);
    }

    $db   = getDB();
    $stmt = $db->query(
        'SELECT a.*, u.name AS patient_name, u.student_id, p.name AS provider_name, p.specialty
         FROM appointments a
         JOIN users u ON u.id = a.patient_id
         JOIN providers p ON p.id = a.provider_id
         ORDER BY a.appt_date DESC, a.appt_time DESC'
    );
    respond($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────
// STAFF: Update appointment status
// POST /api/appointments.php?action=update_status
// Body: { appointment_id, status }
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'update_status') {
    session_start();
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
        respond(['error' => 'Staff only'], 403);
    }

    $body    = getBody();
    $appt_id = intval($body['appointment_id'] ?? 0);
    $status  = $body['status'] ?? '';
    $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];

    if (!$appt_id || !in_array($status, $allowed)) {
        respond(['error' => 'Invalid params'], 400);
    }

    $db = getDB();
    $db->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$status, $appt_id]);
    respond(['success' => true]);
}

respond(['error' => 'Invalid action'], 400);
