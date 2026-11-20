<div class="page-actions">
    <form class="search-form" method="get">
        <input type="search" name="search" value="<?= e($search) ?>" placeholder="დასახელება, ნომერი ან ელფოსტა">
        <button class="button secondary" type="submit"><i data-lucide="search" aria-hidden="true"></i> ძებნა</button>
    </form>
    <a class="button primary" href="<?= url('clients/create') ?>"><i data-lucide="user-round-plus" aria-hidden="true"></i> მიმღების დამატება</a>
</div>

<section class="card table-card">
    <?php if (!$clients): ?>
        <div class="empty-state compact"><span class="empty-icon"><i data-lucide="contact-round" aria-hidden="true"></i></span><h2>მიმღები ვერ მოიძებნა</h2><p>ერთხელ დაამატეთ კლიენტის მონაცემები და შემდეგ ჩამონათვალიდან აირჩევთ.</p><a class="button primary" href="<?= url('clients/create') ?>"><i data-lucide="user-round-plus" aria-hidden="true"></i> მიმღების დამატება</a></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>დასახელება</th><th>ტიპი</th><th>საიდენტიფიკაციო ნომერი</th><th>კონტაქტი</th><th class="actions-cell">მოქმედება</th></tr></thead>
                <tbody>
                <?php foreach ($clients as $row): ?>
                    <tr>
                        <td data-label="დასახელება"><div class="person-cell"><span class="entity-badge"><?= e(mb_strtoupper(mb_substr($row['name'], 0, 1))) ?></span><span><strong><?= e($row['name']) ?></strong><small><?= e($row['address'] ?: 'მისამართი არ არის მითითებული') ?></small></span></div></td>
                        <td data-label="ტიპი"><span class="badge neutral"><?= ['company' => 'კომპანია', 'individual_entrepreneur' => 'ინდ. მეწარმე', 'individual' => 'ფიზიკური პირი'][$row['entity_type']] ?></span></td>
                        <td data-label="საიდ. ნომერი"><?= e($row['identification_number']) ?></td>
                        <td data-label="კონტაქტი"><span class="table-cell-content"><?= e($row['phone'] ?: '—') ?><small><?= e($row['email'] ?: '') ?></small></span></td>
                        <td class="actions-cell" data-label="მოქმედება"><div class="table-actions">
                            <a class="icon-button" href="<?= url('clients/' . $row['id'] . '/edit') ?>" title="რედაქტირება" aria-label="რედაქტირება"><i data-lucide="square-pen" aria-hidden="true"></i></a>
                            <form method="post" action="<?= url('clients/' . $row['id'] . '/delete') ?>" data-confirm="ნამდვილად გსურთ მიმღების წაშლა? ძველი ინვოისები უცვლელად დარჩება."><?= csrf_field() ?><button class="icon-button danger" type="submit" aria-label="წაშლა"><i data-lucide="trash-2" aria-hidden="true"></i></button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/app/Views/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
