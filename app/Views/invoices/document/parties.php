<section class="parties">
    <article class="party party-issuer">
        <span class="party-label"><?= e(invoice_label($labels, 'issuer')) ?></span>
        <h2><?= e($issuerData['name'] ?? '') ?></h2>
        <dl>
            <div><dt><?= e(invoice_label($labels, 'identification_number')) ?></dt><dd><?= e($issuerData['identification_number'] ?? '') ?></dd></div>
            <?php if (!empty($issuerData['address'])): ?><div><dt><?= e(invoice_label($labels, 'address')) ?></dt><dd><?= e($issuerData['address']) ?></dd></div><?php endif; ?>
            <?php if (!empty($issuerData['email'])): ?><div><dt><?= e(invoice_label($labels, 'email')) ?></dt><dd><?= e($issuerData['email']) ?></dd></div><?php endif; ?>
            <?php if (!empty($issuerData['phone'])): ?><div><dt><?= e(invoice_label($labels, 'phone')) ?></dt><dd><?= e($issuerData['phone']) ?></dd></div><?php endif; ?>
        </dl>
        <?php if (!empty($issuerData['is_vat_payer'])): ?><span class="vat-pill"><?= e(invoice_label($labels, 'vat_payer')) ?></span><?php endif; ?>
    </article>

    <article class="party party-client">
        <span class="party-label"><?= e(invoice_label($labels, 'client')) ?></span>
        <h2><?= e($clientData['name'] ?? '') ?></h2>
        <dl>
            <div><dt><?= e(invoice_label($labels, 'identification_number')) ?></dt><dd><?= e($clientData['identification_number'] ?? '') ?></dd></div>
            <?php if (!empty($clientData['address'])): ?><div><dt><?= e(invoice_label($labels, 'address')) ?></dt><dd><?= e($clientData['address']) ?></dd></div><?php endif; ?>
            <?php if (!empty($clientData['email'])): ?><div><dt><?= e(invoice_label($labels, 'email')) ?></dt><dd><?= e($clientData['email']) ?></dd></div><?php endif; ?>
            <?php if (!empty($clientData['phone'])): ?><div><dt><?= e(invoice_label($labels, 'phone')) ?></dt><dd><?= e($clientData['phone']) ?></dd></div><?php endif; ?>
        </dl>
    </article>
</section>

