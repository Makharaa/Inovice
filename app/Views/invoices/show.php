<?php
$isOwner = (int) $invoice['user_id'] === \App\Core\Auth::id();
$isLocked = \App\Models\Invoice::isLocked($invoice);
?>
<div class="document-page-actions">
    <a class="button ghost" href="<?= url('invoices') ?>"><i data-lucide="arrow-left" aria-hidden="true"></i> ინვოისები</a>
    <div>
        <?php if ($isOwner): ?>
            <?php if (!$isLocked): ?>
                <a class="button secondary" href="<?= url('invoices/' . $invoice['id'] . '/edit') ?>"><i data-lucide="square-pen" aria-hidden="true"></i> რედაქტირება</a>
            <?php endif; ?>
            <form class="inline-form" method="post" action="<?= url('invoices/' . $invoice['id'] . '/duplicate') ?>"><?= csrf_field() ?><button class="button secondary" type="submit"><i data-lucide="copy" aria-hidden="true"></i> ასლის შექმნა</button></form>
        <?php endif; ?>
        <a class="button secondary" href="<?= url('invoices/' . $invoice['id'] . '/print') ?>" target="_blank"><i data-lucide="printer" aria-hidden="true"></i> ბეჭდვა</a>
        <a class="button primary" href="<?= url('invoices/' . $invoice['id'] . '/pdf') ?>" target="_blank"><i data-lucide="download" aria-hidden="true"></i> PDF ჩამოტვირთვა</a>
    </div>
</div>

<section class="invoice-overview card">
    <div>
        <span class="badge <?= $invoice['status'] === 'issued' ? 'positive' : 'warning' ?>"><?= $invoice['status'] === 'issued' ? 'გაცემული' : 'მონახაზი' ?></span>
        <h2><?= e($invoice['invoice_number']) ?></h2>
        <p><?= e($invoice['client_snapshot']['name'] ?? '') ?> · <?= e(date('d.m.Y', strtotime($invoice['issue_date']))) ?></p>
    </div>
    <div class="invoice-overview-total"><small>სულ გადასახდელი</small><strong><?= money($invoice['total'], $invoice['currency']) ?></strong></div>
</section>

<section class="preview-card card">
    <div class="preview-header">
        <div><h3>ინვოისის წინასწარი ნახვა</h3><p><?= ['ka' => 'ქართული', 'en' => 'English', 'ru' => 'Русский'][$invoice['language']] ?> · <?= ['standard' => 'კლასიკური', 'modern' => 'თანამედროვე', 'compact' => 'კომპაქტური (Landscape)'][$invoice['template_key']] ?></p></div>
        <span><i data-lucide="file-type-2" aria-hidden="true"></i> PDF ფორმატი · A4</span>
    </div>
    <iframe class="invoice-preview-frame" src="<?= url('invoices/' . $invoice['id'] . '/print?embed=1') ?>" title="ინვოისის წინასწარი ნახვა"></iframe>
</section>

<?php if ($isOwner && $isLocked): ?>
    <section class="danger-zone">
        <div><h3>გაცემული ინვოისი</h3><p>გაცემული ინვოისის შეცვლა ან წაშლა შეუძლებელია. შეცდომის გასასწორებლად შექმენით ასლი და გასცით ახალი ინვოისი.</p></div>
    </section>
<?php elseif ($isOwner): ?>
    <section class="danger-zone">
        <div><h3>ინვოისის წაშლა</h3><p>ჩანაწერი ისტორიიდან დაიმალება, მაგრამ ინვოისის ნომერი ხელახლა აღარ გამოიყენება.</p></div>
        <form method="post" action="<?= url('invoices/' . $invoice['id'] . '/delete') ?>" data-confirm="ნამდვილად გსურთ ინვოისის წაშლა?"><?= csrf_field() ?><button class="button danger-button" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i> ინვოისის წაშლა</button></form>
    </section>
<?php endif; ?>
