<?php
$editing = is_array($issuer);
$value = static fn (string $key, mixed $default = '') => old($key, $editing ? ($issuer[$key] ?? $default) : $default);
?>
<form class="card form-card wide-form" method="post" action="<?= $editing ? url('issuers/' . $issuer['id']) : url('issuers') ?>">
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="section-heading"><span>01</span><div><h3>ძირითადი მონაცემები</h3><p>ინვოისში ნაჩვენები გამცემის ინფორმაცია</p></div></div>
        <div class="form-grid three">
            <div class="field">
                <label>გამცემის ტიპი *</label>
                <select name="entity_type" required>
                    <option value="company"<?= selected($value('entity_type', 'company'), 'company') ?>>კომპანია</option>
                    <option value="individual_entrepreneur"<?= selected($value('entity_type'), 'individual_entrepreneur') ?>>ინდ. მეწარმე</option>
                    <option value="individual"<?= selected($value('entity_type'), 'individual') ?>>ფიზიკური პირი</option>
                </select>
            </div>
            <div class="field span-2"><label>დასახელება / სახელი და გვარი *</label><input name="name" value="<?= e($value('name')) ?>" required></div>
            <div class="field"><label>საიდენტიფიკაციო / პირადი ნომერი *</label><input name="identification_number" value="<?= e($value('identification_number')) ?>" required></div>
            <div class="field span-2"><label>მისამართი</label><input name="address" value="<?= e($value('address')) ?>"></div>
            <div class="field"><label>ტელეფონი</label><input name="phone" value="<?= e($value('phone')) ?>"></div>
            <div class="field"><label>ელფოსტა</label><input type="email" name="email" value="<?= e($value('email')) ?>"></div>
            <label class="check-field"><input type="checkbox" name="is_vat_payer" value="1"<?= checked((bool) $value('is_vat_payer')) ?>><span><strong>დღგ-ის გადამხდელი</strong><small>ინვოისში გამოჩნდება 18% დღგ</small></span></label>
        </div>
    </div>

    <div class="form-section">
        <div class="section-heading"><span>02</span><div><h3>საბანკო რეკვიზიტები</h3><p>არასავალდებულო ველები</p></div></div>
        <div class="form-grid three">
            <div class="field"><label>ბანკის დასახელება</label><input name="bank_name" value="<?= e($value('bank_name')) ?>"></div>
            <div class="field"><label>ბანკის კოდი / SWIFT</label><input name="bank_code" value="<?= e($value('bank_code')) ?>"></div>
            <div class="field"><label>ანგარიშის ნომერი / IBAN</label><input name="bank_account" value="<?= e($value('bank_account')) ?>"></div>
        </div>
    </div>

    <div class="form-section">
        <div class="section-heading"><span>03</span><div><h3>ინვოისის პარამეტრები</h3><p>ნაგულისხმევი არჩევანი ახალი ინვოისისთვის</p></div></div>
        <div class="form-grid three">
            <div class="field"><label>ნომრის პრეფიქსი</label><input name="invoice_prefix" value="<?= e($value('invoice_prefix', 'INV')) ?>" maxlength="20"><small>მაგალითად: INV-2026-0001</small></div>
            <div class="field">
                <label>ნაგულისხმევი ენა</label>
                <select name="default_language">
                    <option value="ka"<?= selected($value('default_language', 'ka'), 'ka') ?>>ქართული</option>
                    <option value="en"<?= selected($value('default_language'), 'en') ?>>English</option>
                    <option value="ru"<?= selected($value('default_language'), 'ru') ?>>Русский</option>
                </select>
            </div>
            <div class="field">
                <label>ნაგულისხმევი დიზაინი</label>
                <select name="default_template">
                    <option value="standard"<?= selected($value('default_template', 'standard'), 'standard') ?>>კლასიკური</option>
                    <option value="modern"<?= selected($value('default_template'), 'modern') ?>>თანამედროვე</option>
                    <option value="compact"<?= selected($value('default_template'), 'compact') ?>>კომპაქტური (Landscape)</option>
                </select>
            </div>
            <label class="check-field"><input type="checkbox" name="is_default" value="1"<?= checked((bool) $value('is_default')) ?>><span><strong>ნაგულისხმევ გამცემად დაყენება</strong><small>ახალ ინვოისში ავტომატურად აირჩევა</small></span></label>
            <?php if ($editing): ?>
                <label class="check-field"><input type="checkbox" name="is_active" value="1"<?= checked((bool) $value('is_active', 1)) ?>><span><strong>აქტიური გამცემი</strong><small>არჩევანში გამოჩნდება</small></span></label>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions sticky-actions"><a class="button ghost" href="<?= url('issuers') ?>">გაუქმება</a><button class="button primary" type="submit">გამცემის შენახვა</button></div>
</form>
