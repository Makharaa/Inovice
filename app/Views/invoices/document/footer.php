<footer class="invoice-footer">
    <span><?= e($invoice['invoice_number']) ?></span>
    <span><?= e($issuerData['name'] ?? '') ?></span>
    <span><?= e(invoice_label($labels, 'page')) ?> <?= $pageNumber ?> / <?= $pageCount ?></span>
    <span><?= e(date('d.m.Y', strtotime($invoice['issue_date']))) ?></span>
</footer>

