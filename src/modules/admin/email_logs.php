<?php
// modules/admin/email_logs.php
// Wird von modules/admin/index.php geladen.
// $pdo, formatEuroCurrency, $currentSection, $id, $action, $actionStatus, $actionMessage sind bereits verfügbar.

// HTML-Ausgabe für E-Mail-Logs
$stmt = $pdo->query("SELECT * FROM email_logs ORDER BY sent_at DESC");
$emailLogs = $stmt->fetchAll();
?>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>An E-Mail</th>
            <th>Betreff</th>
            <th>Status</th>
            <th>Versendet am</th>
            <th>Fehlermeldung</th>
            <th>Bestell-ID</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($emailLogs as $log): ?>
            <tr>
                <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                <td><?php echo htmlspecialchars($log['to_email']); ?></td>
                <td><?php echo htmlspecialchars($log['subject']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($log['status'])); ?></td>
                <td><?php echo (new DateTime($log['sent_at']))->format('d.m.Y H:i'); ?></td>
                <td><?php echo htmlspecialchars($log['error_message'] ?: 'N/A'); ?></td>
                <td><?php echo ($log['order_id'] ? '<a href="?section=orders&action=edit&id=' . htmlspecialchars($log['order_id']) . '">' . htmlspecialchars($log['order_id']) . '</a>' : 'N/A'); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>