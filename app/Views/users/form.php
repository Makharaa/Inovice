<?php
$editing = is_array($editUser);
$formValue = static fn (string $key, mixed $default = '') => old($key, $editing ? ($editUser[$key] ?? $default) : $default);
?>
<div class="form-layout">
    <form class="card form-card" method="post" action="<?= $editing ? url('users/' . $editUser['id']) : url('users') ?>">
        <?= csrf_field() ?>
        <div class="card-header"><div><h3><?= $editing ? 'ანგარიშის მონაცემები' : 'ახალი ანგარიში' ?></h3><p>მომხმარებელი საკუთარ გამცემებსა და მიმღებებს დამოუკიდებლად მართავს.</p></div></div>
        <div class="form-grid">
            <div class="field"><label>სახელი და გვარი *</label><input name="name" value="<?= e($formValue('name')) ?>" required></div>
            <div class="field"><label>ელფოსტა *</label><input type="email" name="email" value="<?= e($formValue('email')) ?>" required></div>
            <div class="field">
                <label>როლი *</label>
                <select name="role">
                    <option value="user"<?= selected($formValue('role', 'user'), 'user') ?>>მომხმარებელი</option>
                    <option value="admin"<?= selected($formValue('role'), 'admin') ?>>ადმინისტრატორი</option>
                </select>
            </div>
            <?php if (!$editing): ?>
                <div class="field"><label>საწყისი პაროლი *</label><input type="password" name="password" minlength="8" required></div>
            <?php endif; ?>
            <label class="check-field full"><input type="checkbox" name="is_active" value="1"<?= checked((bool) $formValue('is_active', 1)) ?>><span><strong>აქტიური ანგარიში</strong><small>მომხმარებელს სისტემაში შესვლა შეუძლია</small></span></label>
        </div>
        <div class="form-actions">
            <a class="button ghost" href="<?= url('users') ?>">გაუქმება</a>
            <button class="button primary" type="submit"><i data-lucide="circle-check" aria-hidden="true"></i> შენახვა</button>
        </div>
    </form>

    <?php if ($editing): ?>
        <form class="card form-card side-form" method="post" action="<?= url('users/' . $editUser['id'] . '/password') ?>">
            <?= csrf_field() ?>
            <div class="card-header"><div><h3>პაროლის შეცვლა</h3><p>ახალი პაროლი მინიმუმ 8 სიმბოლო.</p></div></div>
            <div class="field"><label>ახალი პაროლი</label><input type="password" name="password" minlength="8" required></div>
            <div class="field"><label>გაიმეორეთ პაროლი</label><input type="password" name="password_confirmation" minlength="8" required></div>
            <button class="button secondary wide" type="submit"><i data-lucide="key-round" aria-hidden="true"></i> პაროლის განახლება</button>
        </form>
    <?php else: ?>
        <aside class="card helper-card">
            <span class="helper-symbol"><i data-lucide="info" aria-hidden="true"></i></span>
            <h3>მონაცემების გამიჯვნა</h3>
            <p>ჩვეულებრივი მომხმარებელი ხედავს მხოლოდ თავის გამცემებს, მიმღებებსა და ინვოისებს.</p>
        </aside>
    <?php endif; ?>
</div>
