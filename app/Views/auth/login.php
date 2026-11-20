<main class="login-shell">
    <section class="login-intro">
        <div class="login-brand"><span class="brand-mark"><i data-lucide="receipt-text" aria-hidden="true"></i></span><strong>Makhara Invoice</strong></div>
        <div class="intro-copy">
            <span class="eyebrow"><i data-lucide="sparkles" aria-hidden="true"></i> მარტივი ინვოისები</span>
            <h1>შექმენი, შეინახე და ჩამოტვირთე.</h1>
            <p>შენი კომპანიები, კლიენტები და ყველა ინვოისი ერთ დაცულ სამუშაო სივრცეში.</p>
        </div>
        <div class="intro-foot"><i data-lucide="languages" aria-hidden="true"></i> ქართული · English · Русский</div>
    </section>
    <section class="login-panel">
        <form method="post" action="<?= url('login') ?>" class="login-form">
            <?= csrf_field() ?>
            <div class="login-form-title">
                <span class="mobile-brand-mark"><i data-lucide="receipt-text" aria-hidden="true"></i></span>
                <span class="eyebrow">კეთილი იყოს შენი დაბრუნება</span>
                <h2>სისტემაში შესვლა</h2>
                <p>შეიყვანე შენი ანგარიშის მონაცემები</p>
            </div>
            <?php if ($errorMessage): ?>
                <div class="alert danger"><span>!</span><div><?= e($errorMessage) ?></div></div>
            <?php endif; ?>
            <div class="field">
                <label for="email"><i data-lucide="mail" aria-hidden="true"></i> ელფოსტა</label>
                <input id="email" type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required autofocus>
            </div>
            <div class="field">
                <label for="password"><i data-lucide="lock-keyhole" aria-hidden="true"></i> პაროლი</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="button primary wide large" type="submit">შესვლა <i data-lucide="arrow-right" aria-hidden="true"></i></button>
        </form>
    </section>
</main>
