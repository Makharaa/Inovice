<section class="invoice-summary">
    <div class="summary-notes">
        <?php if (!empty($issuerData['bank_name']) || !empty($issuerData['bank_account'])): ?>
            <div class="bank-details">
                <h3><?= e(invoice_label($labels, 'bank_details')) ?></h3>
                <div class="detail-lines">
                    <?php if (!empty($issuerData['bank_name'])): ?><p><strong><?= e(invoice_label($labels, 'bank')) ?>:</strong> <?= e($issuerData['bank_name']) ?></p><?php endif; ?>
                    <?php if (!empty($issuerData['bank_code'])): ?><p><strong><?= e(invoice_label($labels, 'bank_code')) ?>:</strong> <?= e($issuerData['bank_code']) ?></p><?php endif; ?>
                    <?php if (!empty($issuerData['bank_account'])): ?><p><strong><?= e(invoice_label($labels, 'bank_account')) ?>:</strong> <?= e($issuerData['bank_account']) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($invoice['notes'])): ?>
            <div class="notes">
                <h3><?= e(invoice_label($labels, 'notes')) ?></h3>
                <p><?= nl2br(e($invoice['notes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <dl class="summary-totals">
        <div><dt><?= e(invoice_label($labels, 'subtotal')) ?></dt><dd><?= e($formatAmount($invoice['subtotal'])) ?></dd></div>
        <?php if ((float) $invoice['discount_total'] > 0): ?><div><dt><?= e(invoice_label($labels, 'discount_total')) ?></dt><dd>- <?= e($formatAmount($invoice['discount_total'])) ?></dd></div><?php endif; ?>
        <?php if ((float) $invoice['vat_rate'] > 0): ?><div><dt><?= e(invoice_label($labels, 'vat')) ?><?= $invoice['prices_include_vat'] ? ' *' : '' ?></dt><dd><?= e($formatAmount($invoice['vat_total'])) ?></dd></div><?php endif; ?>
        <div class="total-due"><dt><?= e(invoice_label($labels, 'grand_total')) ?></dt><dd><?= e($formatAmount($invoice['total'])) ?></dd></div>
        <?php if ((float) $invoice['vat_rate'] > 0 && $invoice['prices_include_vat']): ?><small>* <?= e(invoice_label($labels, 'prices_include_vat')) ?></small><?php endif; ?>
    </dl>
</section>

