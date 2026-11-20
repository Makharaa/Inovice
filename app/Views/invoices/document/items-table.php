<section class="items-section">
    <table>
        <thead>
            <tr>
                <th class="number-col"><?= e(invoice_label($labels, 'item_no')) ?></th>
                <th><?= e(invoice_label($labels, 'description')) ?></th>
                <th><?= e(invoice_label($labels, 'unit')) ?></th>
                <th class="numeric"><?= e(invoice_label($labels, 'quantity')) ?></th>
                <th class="numeric"><?= e(invoice_label($labels, 'unit_price')) ?></th>
                <?php if ((float) $invoice['discount_total'] > 0): ?><th class="numeric"><?= e(invoice_label($labels, 'discount')) ?></th><?php endif; ?>
                <th class="numeric"><?= e(invoice_label($labels, 'line_total')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pageItems as $item): ?>
                <tr>
                    <td class="number-col"><?= (int) ($item['_document_number'] ?? 0) ?></td>
                    <td class="description-cell"><?= nl2br(e($item['description'])) ?></td>
                    <td><?= e($labels['units'][$item['unit']] ?? $item['unit']) ?></td>
                    <td class="numeric"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 3, '.', ''), '0'), '.')) ?></td>
                    <td class="numeric"><?= e(number_format((float) $item['unit_price'], 2, '.', ' ')) ?></td>
                    <?php if ((float) $invoice['discount_total'] > 0): ?><td class="numeric"><?= e(number_format((float) $item['discount_percent'], 2)) ?>%</td><?php endif; ?>
                    <td class="numeric strong"><?= e($formatAmount($item['line_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

