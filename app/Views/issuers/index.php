<div class="page-actions align-end">
    <p class="page-description">დაამატეთ ყველა კომპანია, ინდ. მეწარმე ან ფიზიკური პირი, რომლის სახელითაც ინვოისს ქმნით.</p>
    <a class="button primary" href="<?= url('issuers/create') ?>"><i data-lucide="plus" aria-hidden="true"></i> გამცემის დამატება</a>
</div>

<?php if (!$issuers): ?>
    <section class="empty-state card"><span class="empty-icon"><i data-lucide="building-2" aria-hidden="true"></i></span><h2>გამცემი ჯერ არ გაქვთ</h2><p>ერთხელ შეავსეთ მონაცემები და შემდეგ ინვოისში უბრალოდ აირჩიეთ.</p><a class="button primary" href="<?= url('issuers/create') ?>"><i data-lucide="plus" aria-hidden="true"></i> პირველი გამცემის დამატება</a></section>
<?php else: ?>
    <section class="entity-grid">
        <?php foreach ($issuers as $row): ?>
            <article class="entity-card card <?= !$row['is_active'] ? 'muted' : '' ?>">
                <div class="entity-card-top">
                    <span class="entity-badge large"><?= e(mb_strtoupper(mb_substr($row['name'], 0, 1))) ?></span>
                    <div class="entity-actions">
                        <a class="icon-button" href="<?= url('issuers/' . $row['id'] . '/edit') ?>" aria-label="რედაქტირება"><i data-lucide="square-pen" aria-hidden="true"></i></a>
                        <form method="post" action="<?= url('issuers/' . $row['id'] . '/delete') ?>" data-confirm="ნამდვილად გსურთ გამცემის წაშლა?"><?= csrf_field() ?><button class="icon-button danger" type="submit" aria-label="წაშლა"><i data-lucide="trash-2" aria-hidden="true"></i></button></form>
                    </div>
                </div>
                <div class="entity-card-title">
                    <h3><?= e($row['name']) ?></h3>
                    <p><?= e($row['identification_number']) ?></p>
                </div>
                <div class="badge-row">
                    <?php if ($row['is_default']): ?><span class="badge positive">ნაგულისხმევი</span><?php endif; ?>
                    <?php if ($row['is_vat_payer']): ?><span class="badge neutral">დღგ-ის გადამხდელი</span><?php endif; ?>
                    <?php if (!$row['is_active']): ?><span class="badge negative">გათიშული</span><?php endif; ?>
                </div>
                <dl class="entity-meta">
                    <div><dt>ინვოისის პრეფიქსი</dt><dd><?= e($row['invoice_prefix']) ?></dd></div>
                    <div><dt>ნაგულისხმევი ენა</dt><dd><?= ['ka' => 'ქართული', 'en' => 'English', 'ru' => 'Русский'][$row['default_language']] ?></dd></div>
                    <div><dt>საბანკო ანგარიში</dt><dd><?= e($row['bank_account'] ?: '—') ?></dd></div>
                </dl>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
