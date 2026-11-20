<header class="continuation-header">
    <div>
        <span><?= e(invoice_label($labels, 'invoice')) ?> · <?= e(invoice_label($labels, 'continued')) ?></span>
        <strong><?= e($invoice['invoice_number']) ?></strong>
    </div>
    <div class="continuation-client">
        <span><?= e(invoice_label($labels, 'client')) ?></span>
        <strong><?= e($clientData['name'] ?? '') ?></strong>
    </div>
    <div class="continuation-page"><?= e(invoice_label($labels, 'page')) ?> <?= $pageNumber ?> / <?= $pageCount ?></div>
</header>

