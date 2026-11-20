<div class="page-actions">
    <form class="search-form" method="get">
        <input type="search" name="search" value="<?= e($search) ?>" placeholder="სახელი ან ელფოსტა">
        <button class="button secondary" type="submit"><i data-lucide="search" aria-hidden="true"></i> ძებნა</button>
    </form>
    <a class="button primary" href="<?= url('users/create') ?>"><i data-lucide="user-round-plus" aria-hidden="true"></i> მომხმარებლის დამატება</a>
</div>

<section class="card table-card">
    <?php if (!$users): ?>
        <div class="empty-state compact"><span class="empty-icon"><i data-lucide="users-round" aria-hidden="true"></i></span><h2>მომხმარებელი ვერ მოიძებნა</h2></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>მომხმარებელი</th><th>როლი</th><th>სტატუსი</th><th>ბოლო შესვლა</th><th class="actions-cell">მოქმედება</th></tr></thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td data-label="მომხმარებელი">
                            <div class="person-cell"><span class="avatar small"><?= e(mb_strtoupper(mb_substr($row['name'], 0, 1))) ?></span><span><strong><?= e($row['name']) ?></strong><small><?= e($row['email']) ?></small></span></div>
                        </td>
                        <td data-label="როლი"><span class="badge neutral"><?= $row['role'] === 'admin' ? 'ადმინისტრატორი' : 'მომხმარებელი' ?></span></td>
                        <td data-label="სტატუსი"><span class="badge <?= $row['is_active'] ? 'positive' : 'negative' ?>"><?= $row['is_active'] ? 'აქტიური' : 'გათიშული' ?></span></td>
                        <td data-label="ბოლო შესვლა"><span class="table-cell-content"><?= $row['last_login_at'] ? e(date('d.m.Y H:i', strtotime($row['last_login_at']))) : '—' ?><small><?= e($row['last_login_ip'] ?? '') ?></small></span></td>
                        <td class="actions-cell" data-label="მოქმედება"><div class="table-actions">
                            <a class="icon-button" href="<?= url('users/' . $row['id'] . '/edit') ?>" title="რედაქტირება" aria-label="რედაქტირება"><i data-lucide="square-pen" aria-hidden="true"></i></a>
                            <?php if ((int) $row['id'] !== \App\Core\Auth::id()): ?>
                                <form method="post" action="<?= url('users/' . $row['id'] . '/toggle') ?>"><?= csrf_field() ?><button class="icon-button" type="submit" title="სტატუსის შეცვლა" aria-label="სტატუსის შეცვლა"><i data-lucide="<?= $row['is_active'] ? 'user-round-x' : 'user-round-check' ?>" aria-hidden="true"></i></button></form>
                                <form method="post" action="<?= url('users/' . $row['id'] . '/delete') ?>" data-confirm="ნამდვილად გსურთ მომხმარებლის წაშლა?"><?= csrf_field() ?><button class="icon-button danger" type="submit" title="წაშლა" aria-label="წაშლა"><i data-lucide="trash-2" aria-hidden="true"></i></button></form>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
