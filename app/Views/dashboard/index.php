<section class="welcome-strip">
    <div>
        <span class="eyebrow"><i data-lucide="sparkles" aria-hidden="true"></i> სამუშაო სივრცე</span>
        <h2>გამარჯობა, <?= e(explode(' ', current_user()['name'])[0] ?? current_user()['name']) ?></h2>
        <p>აქედან მართავ შენს გამცემებს, მიმღებებსა და ინვოისებს.</p>
    </div>
    <a class="button primary" href="<?= url('invoices/create') ?>"><i data-lucide="plus" aria-hidden="true"></i> ინვოისის შექმნა</a>
</section>

<section class="stats-grid">
    <article class="stat-card mint">
        <span class="stat-symbol mint"><i data-lucide="files" aria-hidden="true"></i></span>
        <div><small>სულ ინვოისი</small><strong><?= (int) ($stats['total_invoices'] ?? 0) ?></strong></div>
    </article>
    <article class="stat-card blue">
        <span class="stat-symbol blue"><i data-lucide="trending-up" aria-hidden="true"></i></span>
        <div><small>ამ თვეში</small><strong><?= (int) ($stats['month_invoices'] ?? 0) ?></strong></div>
    </article>
    <article class="stat-card amber">
        <span class="stat-symbol amber"><i data-lucide="circle-dollar-sign" aria-hidden="true"></i></span>
        <div><small>ამ თვის ჯამი (GEL)</small><strong><?= number_format((float) ($stats['month_total_gel'] ?? 0), 2, '.', ' ') ?></strong></div>
    </article>
    <article class="stat-card violet">
        <span class="stat-symbol violet"><i data-lucide="contact-round" aria-hidden="true"></i></span>
        <div><small>უნიკალური მიმღები</small><strong><?= (int) ($stats['clients_count'] ?? 0) ?></strong></div>
    </article>
</section>

<div class="content-grid dashboard-grid">
    <section class="card table-card">
        <div class="card-header">
            <div><h3>ბოლო ინვოისები</h3><p>ბოლოს შექმნილი ჩანაწერები</p></div>
            <a class="text-link" href="<?= url('invoices') ?>">ყველას ნახვა <i data-lucide="arrow-right" aria-hidden="true"></i></a>
        </div>
        <?php if (!$recent): ?>
            <div class="mini-empty"><p>ინვოისები ჯერ არ გაქვს.</p><a href="<?= url('invoices/create') ?>">შექმენი პირველი</a></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="responsive-table">
                    <thead><tr><th>ნომერი</th><th>მიმღები</th><?php if (is_admin()): ?><th>მომხმარებელი</th><?php endif; ?><th>ჯამი</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td data-label="ინვოისი"><span class="table-cell-content"><a class="strong-link" href="<?= url('invoices/' . $row['id']) ?>"><?= e($row['invoice_number']) ?></a><small><?= e(date('d.m.Y', strtotime($row['issue_date']))) ?></small></span></td>
                            <td data-label="მიმღები"><?= e($row['client_name'] ?: '—') ?></td>
                            <?php if (is_admin()): ?><td data-label="მომხმარებელი"><?= e($row['owner_name']) ?></td><?php endif; ?>
                            <td class="amount" data-label="ჯამი"><?= money($row['total'], $row['currency']) ?></td>
                            <td class="actions-cell" data-label="მოქმედება"><div class="table-actions"><a class="icon-button" href="<?= url('invoices/' . $row['id']) ?>" aria-label="ინვოისის ნახვა"><i data-lucide="arrow-up-right" aria-hidden="true"></i></a></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="card issuer-summary">
        <div class="card-header">
            <div><h3>ჩემი გამცემები</h3><p>აქტიური პროფილები</p></div>
            <a class="icon-button" href="<?= url('issuers/create') ?>" aria-label="გამცემის დამატება"><i data-lucide="plus" aria-hidden="true"></i></a>
        </div>
        <?php if (!$issuers): ?>
            <div class="mini-empty"><p>გამცემი ჯერ არ დაგიმატებია.</p><a href="<?= url('issuers/create') ?>">დამატება</a></div>
        <?php else: ?>
            <div class="issuer-list">
                <?php foreach (array_slice($issuers, 0, 4) as $issuer): ?>
                    <a href="<?= url('issuers/' . $issuer['id'] . '/edit') ?>">
                        <span class="entity-badge"><?= e(mb_strtoupper(mb_substr($issuer['name'], 0, 1))) ?></span>
                        <span><strong><?= e($issuer['name']) ?></strong><small><?= e($issuer['identification_number']) ?><?= $issuer['is_vat_payer'] ? ' · დღგ' : '' ?></small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>
