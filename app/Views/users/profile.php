<div class="form-layout">
    <form class="card form-card" method="post" action="<?= url('profile') ?>">
        <?= csrf_field() ?>
        <div class="card-header"><div><h3>პირადი მონაცემები</h3><p>ეს მონაცემები გამოიყენება მხოლოდ სისტემაში.</p></div></div>
        <div class="form-grid">
            <div class="field"><label>სახელი და გვარი *</label><input name="name" value="<?= e(old('name', $profile['name'])) ?>" required></div>
            <div class="field"><label>ელფოსტა *</label><input type="email" name="email" value="<?= e(old('email', $profile['email'])) ?>" required></div>
            <div class="field"><label>როლი</label><input value="<?= $profile['role'] === 'admin' ? 'ადმინისტრატორი' : 'მომხმარებელი' ?>" disabled></div>
            <div class="field"><label>ბოლო შესვლა</label><input value="<?= $profile['last_login_at'] ? e(date('d.m.Y H:i', strtotime($profile['last_login_at']))) : '—' ?>" disabled></div>
        </div>
        <div class="form-actions"><button class="button primary" type="submit"><i data-lucide="circle-check" aria-hidden="true"></i> ცვლილებების შენახვა</button></div>
    </form>
    <form class="card form-card side-form" method="post" action="<?= url('profile/password') ?>">
        <?= csrf_field() ?>
        <div class="card-header"><div><h3>პაროლის შეცვლა</h3><p>გამოიყენეთ მინიმუმ 8 სიმბოლო.</p></div></div>
        <div class="field"><label>ახალი პაროლი</label><input type="password" name="password" minlength="8" required></div>
        <div class="field"><label>გაიმეორეთ პაროლი</label><input type="password" name="password_confirmation" minlength="8" required></div>
        <button class="button secondary wide" type="submit"><i data-lucide="key-round" aria-hidden="true"></i> პაროლის განახლება</button>
    </form>
</div>
