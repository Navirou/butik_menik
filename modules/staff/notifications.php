<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_STAFF]);
require_once __DIR__ . '/../../includes/notifications-handler.php';

$user = currentUser(); $role = 'staff'; $pageTitle = 'Notifikasi'; $activeMenu = 'notifications'; $notifCount = $unreadCount;
require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header d-flex justify-content-between align-items-start gap-3">
  <div><h1>Notifikasi</h1><p><?= $unreadCount ?> notifikasi belum dibaca</p></div>
  <?php if ($unreadCount > 0): ?><a href="?action=mark_read" class="btn-ivory" style="font-size:.85rem"><i class="bi bi-check2-all me-1"></i>Tandai Semua Dibaca</a><?php endif; ?>
</div>

<?php if (isset($_GET['marked'])): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i>Semua notifikasi ditandai dibaca.</div><?php endif; ?>

<div class="d-flex gap-2 mb-4">
  <?php foreach (['all'=>'Semua','unread'=>'Belum Dibaca'] as $k=>$l): ?>
  <a href="?filter=<?= $k ?>" class="bm-badge text-decoration-none" style="font-size:.82rem;padding:.5em 1em;<?= $notifFilter===$k?'background:var(--navy);color:var(--ivory)':'background:#f3f4f6;color:var(--text-muted)' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<div class="bm-card">
  <?php if (empty($notifications)): ?>
  <div class="bm-card-body text-center py-5 text-muted">
    <i class="bi bi-bell-slash" style="font-size:2.5rem;display:block;margin-bottom:.5rem"></i>
    <p>Tidak ada notifikasi.</p>
  </div>
  <?php else: ?>
  <?php foreach ($notifications as $n):
    [$ico,$bg,$ic] = $notifIcons[$n['type']] ?? ['bi-bell-fill','#f3f4f6','#6b7280'];
  ?>
  <div class="d-flex gap-3 px-4 py-3 <?= $n['is_read']?'':'bg-ivory' ?>" style="border-bottom:1px solid var(--border)">
    <div style="width:40px;height:40px;border-radius:10px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="bi <?= $ico ?>" style="color:<?= $ic ?>;font-size:1rem"></i>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:<?= $n['is_read']?'500':'700' ?>;font-size:.9rem;color:var(--text-main)"><?= htmlspecialchars($n['title']) ?></div>
      <div style="font-size:.82rem;color:var(--text-muted);margin:.15rem 0"><?= htmlspecialchars($n['body']) ?></div>
      <div style="font-size:.75rem;color:#adb5bd"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
    </div>
    <?php if (!$n['is_read']): ?><a href="?action=mark_read&id=<?= $n['id'] ?>" style="flex-shrink:0;color:var(--navy);font-size:.75rem;font-weight:600;white-space:nowrap;text-decoration:none;align-self:center">Baca</a><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
