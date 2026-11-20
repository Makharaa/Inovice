<?php
$editing = is_array($invoice);
$defaultIssuer = null;
foreach ($issuers as $candidate) {
    if ($candidate['is_default']) {
        $defaultIssuer = $candidate;
        break;
    }
}
$defaultIssuer ??= $issuers[0] ?? [];
$value = static fn (string $key, mixed $default = '') => old($key, $editing ? ($invoice[$key] ?? $default) : $default);
$issuerId = $value('issuer_id', $defaultIssuer['id'] ?? '');
$selectedIssuer = $defaultIssuer;
foreach ($issuers as $candidate) {
    if ((int) $candidate['id'] === (int) $issuerId) {
        $selectedIssuer = $candidate;
        break;
    }
}
$initialItems = old('items', $editing ? $invoice['items'] : [[
    'description' => '', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => '', 'discount_percent' => 0,
]]);
$pageScripts = ['invoice-editor.js'];
?>
<form class="invoice-editor" id="invoice-form" method="post" action="<?= $editing ? url('invoices/' . $invoice['id']) : url('invoices') ?>">
    <?= csrf_field() ?>
    <section class="card form-card">
        <div class="card-header"><div><h3>ინვოისის პარამეტრები</h3><p><?= $editing ? 'ნომერი: ' . e($invoice['invoice_number']) : 'ნომერი შეიქმნება ავტომატურად შენახვისას' ?></p></div><span class="badge neutral"><?= $editing ? 'რედაქტირება' : 'ახალი' ?></span></div>
        <div class="form-grid four">
            <div class="field span-2">
                <label>გამცემი *</label>
                <select name="issuer_id" id="issuer-select" required>
                    <?php foreach ($issuers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>"
                                data-vat="<?= (int) $row['is_vat_payer'] ?>"
                                data-language="<?= e($row['default_language']) ?>"
                                data-template="<?= e($row['default_template']) ?>"<?= selected($issuerId, $row['id']) ?>>
                            <?= e($row['name']) ?> · <?= e($row['identification_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field span-2">
                <label>მიმღები *</label>
                <select name="client_id" required>
                    <option value="">აირჩიეთ მიმღები</option>
                    <?php foreach ($clients as $row): ?><option value="<?= (int) $row['id'] ?>"<?= selected($value('client_id'), $row['id']) ?>><?= e($row['name']) ?> · <?= e($row['identification_number']) ?></option><?php endforeach; ?>
                </select>
                <small><a href="<?= url('clients/create') ?>">ახალი მიმღების დამატება</a></small>
            </div>
            <div class="field"><label>ინვოისის თარიღი *</label><input type="date" name="issue_date" value="<?= e($value('issue_date', date('Y-m-d'))) ?>" required></div>
            <div class="field"><label>გადახდის ვადა</label><input type="date" name="due_date" value="<?= e($value('due_date')) ?>"></div>
            <div class="field">
                <label>ენა</label>
                <select name="language" id="language-select">
                    <option value="ka"<?= selected($value('language', $selectedIssuer['default_language'] ?? 'ka'), 'ka') ?>>ქართული</option>
                    <option value="en"<?= selected($value('language', $selectedIssuer['default_language'] ?? 'ka'), 'en') ?>>English</option>
                    <option value="ru"<?= selected($value('language', $selectedIssuer['default_language'] ?? 'ka'), 'ru') ?>>Русский</option>
                </select>
            </div>
            <div class="field">
                <label>ვალუტა</label>
                <select name="currency" id="currency-select">
                    <option value="GEL"<?= selected($value('currency', 'GEL'), 'GEL') ?>>GEL — ₾</option>
                    <option value="USD"<?= selected($value('currency'), 'USD') ?>>USD — $</option>
                    <option value="EUR"<?= selected($value('currency'), 'EUR') ?>>EUR — €</option>
                </select>
            </div>
            <div class="field span-2">
                <label>ინვოისის დიზაინი</label>
                <div class="template-options">
                    <?php foreach ([
                        'standard' => ['კლასიკური', 'საქმიანი, მკაფიო'],
                        'modern' => ['თანამედროვე', 'ფერადი ბლოკები'],
                        'compact' => ['კომპაქტური', 'Landscape, ბევრი პოზიცია'],
                    ] as $key => [$label, $description]): ?>
                        <label>
                            <input type="radio" name="template_key" value="<?= $key ?>"<?= checked($value('template_key', $selectedIssuer['default_template'] ?? 'standard') === $key) ?>>
                            <span><strong><?= e($label) ?></strong><small><?= e($description) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="check-field span-2 vat-price-option"<?= empty($selectedIssuer['is_vat_payer']) ? ' hidden' : '' ?>>
                <input type="checkbox" name="prices_include_vat" value="1"<?= checked((bool) $value('prices_include_vat')) ?>>
                <span><strong>მითითებული ფასები შეიცავს დღგ-ს</strong><small>გამოურთველ შემთხვევაში 18% დღგ ფასს ზემოდან დაემატება</small></span>
            </label>
        </div>
    </section>

    <section class="card invoice-items-card">
        <div class="card-header"><div><h3>პროდუქტები და მომსახურებები</h3><p>დაამატეთ ინვოისის ყველა პოზიცია</p></div><button class="button secondary" type="button" id="add-item"><i data-lucide="plus" aria-hidden="true"></i> პოზიციის დამატება</button></div>
        <div class="invoice-items" id="invoice-items">
            <div class="item-head"><span>აღწერა</span><span>ერთეული</span><span>რაოდენობა</span><span>ფასი</span><span>ფასდ. %</span><span>ჯამი</span><span></span></div>
            <?php foreach (array_values($initialItems) as $index => $item): ?>
                <div class="invoice-item" data-item>
                    <textarea name="items[<?= $index ?>][description]" rows="2" placeholder="პროდუქტი ან მომსახურება" required><?= e($item['description'] ?? '') ?></textarea>
                    <select name="items[<?= $index ?>][unit]">
                        <?php foreach (invoice_units() as $unitKey => $unitLabel): ?><option value="<?= $unitKey ?>"<?= selected($item['unit'] ?? 'pcs', $unitKey) ?>><?= $unitLabel ?></option><?php endforeach; ?>
                    </select>
                    <input type="number" name="items[<?= $index ?>][quantity]" value="<?= e($item['quantity'] ?? 1) ?>" min="0.001" step="0.001" data-quantity required>
                    <input type="number" name="items[<?= $index ?>][unit_price]" value="<?= e($item['unit_price'] ?? '') ?>" min="0" step="0.0001" data-price required>
                    <input type="number" name="items[<?= $index ?>][discount_percent]" value="<?= e($item['discount_percent'] ?? 0) ?>" min="0" max="100" step="0.01" data-discount>
                    <strong class="line-total" data-line-total>0.00 GEL</strong>
                    <button class="remove-item" type="button" data-remove-item aria-label="პოზიციის წაშლა">×</button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="invoice-bottom-grid">
        <div class="card form-card">
            <div class="field"><label>დამატებითი შენიშვნა</label><textarea name="notes" rows="5" placeholder="გადახდის პირობა ან სხვა ინფორმაცია"><?= e($value('notes')) ?></textarea></div>
        </div>
        <aside class="card totals-card">
            <dl>
                <div><dt>ქვეჯამი</dt><dd data-subtotal>0.00 GEL</dd></div>
                <div><dt>ფასდაკლება</dt><dd data-discount-total>0.00 GEL</dd></div>
                <div class="vat-total-row"><dt>დღგ 18%</dt><dd data-vat-total>0.00 GEL</dd></div>
                <div class="grand-total"><dt>სულ გადასახდელი</dt><dd data-grand-total>0.00 GEL</dd></div>
            </dl>
        </aside>
    </section>

    <div class="editor-actions">
        <a class="button ghost" href="<?= url('invoices') ?>">გაუქმება</a>
        <button class="button secondary" type="submit" name="status" value="draft"><i data-lucide="save" aria-hidden="true"></i> მონახაზად შენახვა</button>
        <button class="button primary" type="submit" name="status" value="issued" data-confirm-submit="ინვოისის გაცემის შემდეგ მისი შეცვლა ან წაშლა შეუძლებელი იქნება. გავცეთ ინვოისი?"><i data-lucide="circle-check" aria-hidden="true"></i> ინვოისის გაცემა</button>
    </div>
</form>

<template id="invoice-item-template">
    <div class="invoice-item" data-item>
        <textarea name="items[__INDEX__][description]" rows="2" placeholder="პროდუქტი ან მომსახურება" required></textarea>
        <select name="items[__INDEX__][unit]">
            <?php foreach (invoice_units() as $unitKey => $unitLabel): ?><option value="<?= e($unitKey) ?>"><?= e($unitLabel) ?></option><?php endforeach; ?>
        </select>
        <input type="number" name="items[__INDEX__][quantity]" value="1" min="0.001" step="0.001" data-quantity required>
        <input type="number" name="items[__INDEX__][unit_price]" value="" min="0" step="0.0001" data-price required>
        <input type="number" name="items[__INDEX__][discount_percent]" value="0" min="0" max="100" step="0.01" data-discount>
        <strong class="line-total" data-line-total>0.00 GEL</strong>
        <button class="remove-item" type="button" data-remove-item aria-label="პოზიციის წაშლა">×</button>
    </div>
</template>
