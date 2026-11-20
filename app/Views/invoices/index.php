<div class="page-actions">
    <form class="filter-bar" method="get">
        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="ნომერი ან მიმღები">
        <select name="status">
            <option value="">ყველა სტატუსი</option>
            <option value="draft"<?= selected($filters['status'], 'draft') ?>>მონახაზი</option>
            <option value="issued"<?= selected($filters['status'], 'issued') ?>>გაცემული</option>
        </select>
        <?php if (is_admin()): ?>
            <select name="user_id">
                <option value="">ყველა მომხმარებელი</option>
                <?php foreach ($userOptions as $option): ?><option value="<?= (int) $option['id'] ?>"<?= selected($filters['user_id'], $option['id']) ?>><?= e($option['name']) ?></option><?php endforeach; ?>
            </select>
        <?php endif; ?>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" title="თარიღიდან">
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" title="თარიღამდე">
        <button class="button secondary" type="submit"><i data-lucide="list-filter" aria-hidden="true"></i> ფილტრი</button>
        <a class="button ghost small-button" href="<?= url('invoices') ?>"><i data-lucide="rotate-ccw" aria-hidden="true"></i> გასუფთავება</a>
    </form>
    <a class="button primary" href="<?= url('invoices/create') ?>"><i data-lucide="plus" aria-hidden="true"></i> ახალი ინვოისი</a>
</div>

<section class="card table-card">
    <?php if (!$invoices): ?>
        <div class="empty-state compact"><span class="empty-icon"><i data-lucide="file-search" aria-hidden="true"></i></span><h2>ინვოისი ვერ მოიძებნა</h2><p>შექმენით პირველი ინვოისი ან შეცვალეთ ფილტრი.</p><a class="button primary" href="<?= url('invoices/create') ?>"><i data-lucide="plus" aria-hidden="true"></i> ინვოისის შექმნა</a></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>ინვოისი</th><th>მიმღები</th><?php if (is_admin()): ?><th>მომხმარებელი</th><?php endif; ?><th>სტატუსი</th><th>ჯამი</th><th class="actions-cell">მოქმედება</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $row): ?>
                    <tr>
                        <td data-label="ინვოისი"><span class="table-cell-content"><a class="strong-link" href="<?= url('invoices/' . $row['id']) ?>"><?= e($row['invoice_number']) ?></a><small><?= e(date('d.m.Y', strtotime($row['issue_date']))) ?> · <?= strtoupper(e($row['language'])) ?></small></span></td>
                        <td data-label="მიმღები"><?= e($row['client_name'] ?: '—') ?></td>
                        <?php if (is_admin()): ?><td data-label="მომხმარებელი"><?= e($row['owner_name']) ?></td><?php endif; ?>
                        <td data-label="სტატუსი"><span class="badge <?= $row['status'] === 'issued' ? 'positive' : 'warning' ?>"><?= $row['status'] === 'issued' ? 'გაცემული' : 'მონახაზი' ?></span></td>
                        <td class="amount" data-label="ჯამი"><?= money($row['total'], $row['currency']) ?></td>
                        <td class="actions-cell" data-label="მოქმედება"><div class="table-actions">
                            <a class="icon-button" href="<?= url('invoices/' . $row['id']) ?>" title="ნახვა" aria-label="ნახვა"><i data-lucide="eye" aria-hidden="true"></i></a>
                            <?php if ((int) $row['user_id'] === \App\Core\Auth::id()): ?><a class="icon-button" href="<?= url('invoices/' . $row['id'] . '/edit') ?>" title="რედაქტირება" aria-label="რედაქტირება"><i data-lucide="square-pen" aria-hidden="true"></i></a><?php endif; ?>
                            <a class="icon-button" href="<?= url('invoices/' . $row['id'] . '/pdf') ?>" target="_blank" title="PDF" aria-label="PDF ჩამოტვირთვა"><i data-lucide="download" aria-hidden="true"></i></a>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/app/Views/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
