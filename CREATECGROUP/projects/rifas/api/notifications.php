<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

try {
    require_admin();
    $pdo = db();
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['_csrf'] ?? null)) {
        throw new RuntimeException('Sesión expirada.');
    }

    if ($action === 'mark_read') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND read_at IS NULL');
            $stmt->execute([$id]);
        }
    }

    if ($action === 'mark_all_read') {
        $pdo->exec('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL');
    }

    $unreadCount = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL')->fetchColumn();
    $rows = $pdo->query('
        SELECT id, type, title, body, url, read_at, created_at
        FROM notifications
        ORDER BY created_at DESC
        LIMIT 20
    ')->fetchAll();

    echo json_encode(['ok' => true, 'count' => $unreadCount, 'items' => $rows, 'csrf' => csrf_token()]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
