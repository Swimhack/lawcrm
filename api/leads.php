<?php
// Requires auth — enforced by router.php

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $search = trim($_GET['search'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');
        $areaFilter = trim($_GET['practice_area'] ?? '');

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($statusFilter !== '') {
            $where[] = 'status = ?';
            $params[] = $statusFilter;
        }
        if ($areaFilter !== '') {
            $where[] = 'practice_area = ?';
            $params[] = $areaFilter;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Fetch page
        $sql = "SELECT * FROM leads $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $leads = $stmt->fetchAll();

        jsonResponse([
            'leads' => $leads,
            'total' => intval($total),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) jsonError('Invalid JSON');

        $token = $data['csrf_token'] ?? '';
        if (!verifyCsrf($token)) jsonError('Invalid CSRF token', 403);

        $errors = validateLead($data);
        if ($errors) jsonError(implode(', ', $errors));

        $sql = 'INSERT INTO leads (name, email, phone, practice_area, status, score) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            sanitize($data['name']),
            sanitize($data['email']),
            sanitize($data['phone'] ?? ''),
            $data['practice_area'],
            $data['status'],
            intval($data['score']),
        ]);

        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([$id]);
        jsonResponse($stmt->fetch(), 201);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['id'])) jsonError('Invalid request');

        $token = $data['csrf_token'] ?? '';
        if (!verifyCsrf($token)) jsonError('Invalid CSRF token', 403);

        $errors = validateLead($data);
        if ($errors) jsonError(implode(', ', $errors));

        $sql = 'UPDATE leads SET name = ?, email = ?, phone = ?, practice_area = ?, status = ?, score = ? WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            sanitize($data['name']),
            sanitize($data['email']),
            sanitize($data['phone'] ?? ''),
            $data['practice_area'],
            $data['status'],
            intval($data['score']),
            intval($data['id']),
        ]);

        $stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([intval($data['id'])]);
        $lead = $stmt->fetch();
        if (!$lead) jsonError('Lead not found', 404);
        jsonResponse($lead);
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['id'])) jsonError('Invalid request');

        $token = $data['csrf_token'] ?? '';
        if (!verifyCsrf($token)) jsonError('Invalid CSRF token', 403);

        $stmt = $pdo->prepare('DELETE FROM leads WHERE id = ?');
        $stmt->execute([intval($data['id'])]);

        if ($stmt->rowCount() === 0) jsonError('Lead not found', 404);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Method not allowed', 405);
}
