<?php
$editing = is_array($client);
$value = static fn (string $key, mixed $default = '') => old($key, $editing ? ($client[$key] ?? $default) : $default);
?>
<div class="form-layout">
    <form class="card form-card" method="post" action="<?= $editing ? url('clients/' . $client['id']) : url('clients') ?>">
        <?= csrf_field() ?>
        <div class="card-header"><div><h3><?= $editing ? 'მიმღების მონაცემები' : 'ახალი მიმღები' ?></h3><p>შენახვის შემდეგ მიმღები ხელმისაწვდომი იქნება ყველა ახალ ინვოისში.</p></div></div>
        <div class="form-grid">
            <div class="field">
                <label>მიმღების ტიპი *</label>
                <select name="entity_type" required>
                    <option value="company"<?= selected($value('entity_type', 'company'), 'company') ?>>კომპანია</option>
                    <option value="individual_entrepreneur"<?= selected($value('entity_type'), 'individual_entrepreneur') ?>>ინდ. მეწარმე</option>
                    <option value="individual"<?= selected($value('entity_type'), 'individual') ?>>ფიზიკური პირი</option>
                </select>
            </div>
            <div class="field"><label>დასახელება / სახელი და გვარი *</label><input name="name" value="<?= e($value('name')) ?>" required></div>
            <div class="field"><label>საიდენტიფიკაციო / პირადი ნომერი *</label><input name="identification_number" value="<?= e($value('identification_number')) ?>" required></div>
            <div class="field"><label>ტელეფონი</label><input name="phone" value="<?= e($value('phone')) ?>"></div>
            <div class="field full"><label>მისამართი</label><input name="address" value="<?= e($value('address')) ?>"></div>
            <div class="field full"><label>ელფოსტა</label><input type="email" name="email" value="<?= e($value('email')) ?>"></div>
        </div>
        <div class="form-actions"><a class="button ghost" href="<?= url('clients') ?>">გაუქმება</a><button class="button primary" type="submit"><i data-lucide="circle-check" aria-hidden="true"></i> მიმღების შენახვა</button></div>
    </form>
    <aside class="card helper-card">
        <span class="helper-symbol"><i data-lucide="contact-round" aria-hidden="true"></i></span>
        <h3>ერთხელ შევსება საკმარისია</h3>
        <p>მომავალში იგივე მიმღებისთვის ინვოისის შექმნისას მონაცემების თავიდან შეყვანა აღარ დაგჭირდებათ.</p>
    </aside>
</div>
