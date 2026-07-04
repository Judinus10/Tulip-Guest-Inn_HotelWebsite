<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

$defaultPassword = 'Admin@123456789';
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = get_db_connection();
        $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, updated_at = :updated_at WHERE is_active = 1');
        $stmt->execute([
            ':password_hash' => $hash,
            ':updated_at' => $now,
        ]);

        $revoke = $pdo->prepare('UPDATE admin_sessions SET revoked_at = :revoked_at WHERE revoked_at IS NULL');
        $revoke->execute([':revoked_at' => $now]);

        $pdo->commit();

        $success = true;
        $message = 'All active admin passwords were reset successfully. Delete this file now.';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Hidden admin password reset error: ' . $e->getMessage());
        $message = 'Reset failed. Check PHP error log.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hidden Admin Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; padding: 40px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 14px 40px rgba(15,23,42,.08); }
        h1 { margin: 0 0 12px; font-size: 22px; }
        p { color: #475569; line-height: 1.5; }
        code { background: #f1f5f9; padding: 3px 6px; border-radius: 6px; }
        button { border: 0; border-radius: 10px; padding: 12px 16px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .msg { margin: 16px 0; padding: 12px; border-radius: 10px; font-weight: 700; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .danger { background: #fff7ed; border: 1px solid #fed7aa; padding: 12px; border-radius: 10px; color: #9a3412; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Hidden Admin Password Reset</h1>
        <p>This file resets every active admin password to:</p>
        <p><code><?php echo htmlspecialchars($defaultPassword, ENT_QUOTES, 'UTF-8'); ?></code></p>
        <div class="danger">Use this only if admin login is locked. Delete this file immediately after reset.</div>

        <?php if ($message !== ''): ?>
            <div class="msg <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="post" onsubmit="return confirm('Reset all active admin passwords now? Delete this file after use.');">
            <button type="submit">Reset All Active Admin Passwords</button>
        </form>
    </div>
</body>
</html>
