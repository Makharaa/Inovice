<header class="invoice-header">
    <div class="invoice-title">
        <span class="invoice-eyebrow"><?= e(invoice_label($labels, 'invoice')) ?></span>
        <h1><?= e($invoice['invoice_number']) ?></h1>
        <span class="invoice-language"><?= e(strtoupper($invoice['language'])) ?> · <?= e($currency) ?></span>
    </div>

    <dl class="invoice-dates">
        <div>
            <dt><?= e(invoice_label($labels, 'issue_date')) ?></dt>
            <dd><?= e(date('d.m.Y', strtotime($invoice['issue_date']))) ?></dd>
        </div>
        <?php if ($invoice['due_date']): ?>
            <div>
                <dt><?= e(invoice_label($labels, 'due_date')) ?></dt>
                <dd><?= e(date('d.m.Y', strtotime($invoice['due_date']))) ?></dd>
            </div>
        <?php endif; ?>
    </dl>
</header>

