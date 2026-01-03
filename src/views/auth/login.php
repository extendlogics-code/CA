<?php
$flashError = $flashError ?? null;
$flashSuccess = $flashSuccess ?? null;
$active_tab = $active_tab ?? 'login';
$login_form = $login_form ?? [];
$register_form = $register_form ?? [];
$register_errors = $register_errors ?? [];
$show_registration_link = $show_registration_link ?? true;
?>

<style>
:root {
    --ink: #0f172a;
    --slate: #1e293b;
    --stone: #cbd5f5;
    --accent: #0ea5e9;
    --accent-soft: rgba(14, 165, 233, 0.15);
    --gold: #fbbf24;
    --success: #10b981;
    --error: #ef4444;
}

body {
    margin: 0;
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: radial-gradient(circle at top left, rgba(15, 23, 42, 0.08), transparent 50%),
                linear-gradient(135deg, #f8fbff 0%, #edf2ff 100%);
    color: var(--ink);
}

.ca-auth-shell {
    min-height: 100vh;
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: clamp(1rem, 4vw, 2.5rem);
    padding: clamp(1rem, 3vw, 2.5rem);
    background-image: url('data:image/svg+xml,%3Csvg width="220" height="220" viewBox="0 0 220 220" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" stroke="%230f172a" stroke-opacity="0.05" stroke-width="0.6"%3E%3Cpath d="M0 110h220M110 0v220M55 0v220M0 55h220M165 0v220M0 165h220"/%3E%3Ctext x="20" y="40" font-size="11" fill="%230f172a" fill-opacity="0.08"%3E%CE%A3 ledgers%3C/text%3E%3Ctext x="120" y="170" font-size="11" fill="%230f172a" fill-opacity="0.08"%3EGST 3B%3C/text%3E%3C/g%3E%3C/svg%3E');
}

.ca-auth-info {
    flex: 1 1 420px;
    border-radius: 28px;
    padding: clamp(1.5rem, 4vw, 3rem);
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 65%);
    color: #f8fafc;
    position: relative;
    overflow: hidden;
}

.ca-auth-info::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 20%, rgba(14, 165, 233, 0.35), transparent 45%),
                radial-gradient(circle at 80% 10%, rgba(251, 191, 36, 0.3), transparent 55%);
    pointer-events: none;
}

.ca-auth-info > * {
    position: relative;
    z-index: 1;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.15);
    font-weight: 600;
    letter-spacing: 0.02em;
}

.hero-title {
    font-size: clamp(2rem, 3vw, 2.8rem);
    margin: 1.4rem 0 0.75rem;
    line-height: 1.2;
}

.hero-title span {
    color: var(--gold);
}

.hero-copy {
    color: rgba(248, 250, 252, 0.8);
    max-width: 480px;
    font-size: 1.05rem;
}

.hero-metrics {
    margin-top: 1.8rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.metric-card {
    padding: 1rem 1.1rem;
    border-radius: 18px;
    background: rgba(15, 23, 42, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.metric-card strong {
    font-size: 1.85rem;
    display: block;
    margin-bottom: 0.2rem;
}

.metric-card small {
    color: rgba(248, 250, 252, 0.65);
}

.ca-auth-panel {
    flex: 1 1 360px;
    max-width: 460px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 65%);
    border-radius: 28px;
    padding: clamp(1.5rem, 4vw, 3rem);
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.15);
    display: flex;
    flex-direction: column;
    color: #f8fafc;
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-logo {
    height: 56px;
    margin-bottom: 1rem;
}

.auth-title {
    margin: 0;
    font-size: 1.8rem;
    color: #f8fafc;
}

.auth-subtitle {
    margin: 0.35rem 0 0;
    color: rgba(248, 250, 252, 0.75);
    font-size: 0.95rem;
}

.auth-tabs {
    display: flex;
    background: #f1f5f9;
    border-radius: 999px;
    padding: 0.3rem;
    margin-bottom: 1.75rem;
}

.auth-tab {
    flex: 1;
    text-align: center;
    padding: 0.65rem;
    border-radius: 999px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}

.auth-tab.active {
    background: #ffffff;
    color: var(--accent);
    box-shadow: 0 12px 24px rgba(14, 165, 233, 0.2);
}

.auth-form { display: none; }
.auth-form.active { display: block; }

.form-group { margin-bottom: 1.1rem; }
.form-label { display: block; margin-bottom: 0.4rem; font-weight: 600; color: #475569; }
.ca-auth-panel .form-label { color: rgba(248, 250, 252, 0.85); }

.form-input,
.ca-auth-panel select {
    width: 100%;
    padding: 0.85rem 1rem;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus,
.ca-auth-panel select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    background: #ffffff;
}

.btn {
    width: 100%;
    padding: 0.95rem;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #0ea5e9 100%);
    color: #ffffff;
    box-shadow: 0 15px 35px rgba(14, 165, 233, 0.25);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 20px 40px rgba(14, 165, 233, 0.35);
}

.alert {
    padding: 0.9rem 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    font-weight: 500;
}

.alert-error { background: #fee2e2; color: var(--error); border: 1px solid #fecaca; }
.alert-success { background: #dcfce7; color: var(--success); border: 1px solid #bbf7d0; }

.form-error {
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: var(--error);
}

@media (max-width: 960px) {
    .ca-auth-shell { flex-direction: column; }
    .ca-auth-panel { max-width: none; }
}
</style>

<div class="ca-auth-shell">
    <section class="ca-auth-info">
        <span class="hero-badge">Chartered Accountants Workspace</span>
        <h1 class="hero-title">Bring every <span>ledger</span> and workflow under one secure roof.</h1>
        <p class="hero-copy">
            CA Service Hub keeps tax filings, audit schedules, and CFO trackers synchronized so your team focuses on advisory work instead of chasing spreadsheets.
        </p>
    </section>

    <section class="ca-auth-panel">
        <div class="auth-header">
            <img src="/assets/images/logo.png" alt="Firm logo" class="auth-logo">
            <p class="auth-title">Welcome back</p>
            <p class="auth-subtitle">Authenticate with your CA workspace credentials</p>
        </div>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error"><?= e($flashError) ?></div>
        <?php endif; ?>

        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>

        <!-- <div class="auth-tabs">
            <div class="auth-tab <?= $active_tab === 'register' ? '' : 'active' ?>" data-form="login" onclick="showForm('login')">Sign in</div>
            <?php if ($show_registration_link): ?>
                <div class="auth-tab <?= $active_tab === 'register' ? 'active' : '' ?>" data-form="register" onclick="showForm('register')">Register</div>
            <?php endif; ?>
        </div> -->

        <form id="login-form" class="auth-form <?= $active_tab === 'register' ? '' : 'active' ?>" method="POST" action="/login">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label for="email" class="form-label">Login ID</label>
                <input type="email" id="email" name="email" class="form-input" required placeholder="you@firm.com" value="<?= e($login_form['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary">Sign in</button>
            
        </form>

        <form id="mfa-form" class="auth-form <?= $active_tab === 'mfa' ? 'active' : '' ?>" method="POST" action="/login">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="mfa_verify">
            <div class="form-group">
                <label for="mfa_code" class="form-label">Verification code</label>
                <input type="text" id="mfa_code" name="code" class="form-input" required placeholder="123456">
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>

        <?php if ($show_registration_link): ?>
        <form id="register-form" class="auth-form <?= $active_tab === 'register' ? 'active' : '' ?>" method="POST" action="/login">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="register">

            <div class="form-group">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-input" required placeholder="John Doe" value="<?= e($register_form['full_name'] ?? '') ?>">
                <?php if (!empty($register_errors['full_name'])): ?><p class="form-error"><?= e($register_errors['full_name']) ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="reg_email" class="form-label">Email</label>
                <input type="email" id="reg_email" name="email" class="form-input" required placeholder="you@firm.com" value="<?= e($register_form['email'] ?? '') ?>">
                <?php if (!empty($register_errors['email'])): ?><p class="form-error"><?= e($register_errors['email']) ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-input" required>
                    <option value="" disabled <?= empty($register_form['role']) ? 'selected' : '' ?>>Select role</option>
                    <option value="superadmin" <?= ($register_form['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                    <option value="employee" <?= ($register_form['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Team Member</option>
                    <option value="customer" <?= ($register_form['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer</option>
                </select>
                <?php if (!empty($register_errors['role'])): ?><p class="form-error"><?= e($register_errors['role']) ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="reg_password" class="form-label">Password</label>
                <input type="password" id="reg_password" name="password" class="form-input" required placeholder="••••••••">
                <?php if (!empty($register_errors['password'])): ?><p class="form-error"><?= e($register_errors['password']) ?></p><?php endif; ?>
                <p id="reg_pw_hint" style="margin-top:.25rem;font-size:.8rem;color:#64748b;"></p>
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required placeholder="••••••••">
                <?php if (!empty($register_errors['password_confirmation'])): ?><p class="form-error"><?= e($register_errors['password_confirmation']) ?></p><?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Create account</button>
        </form>
        <?php endif; ?>
    </section>
</div>

<script>
function showForm(formName) {
    document.querySelectorAll('.auth-tab').forEach(function (tab) {
        tab.classList.toggle('active', tab.dataset.form === formName);
    });

    document.getElementById('login-form').classList.toggle('active', formName === 'login');
    var registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.classList.toggle('active', formName === 'register');
    }
}

function pwStrength(text){
    var len = text.length >= 12;
    var up = /[A-Z]/.test(text);
    var lo = /[a-z]/.test(text);
    var di = /\d/.test(text);
    var sy = /[^A-Za-z0-9]/.test(text);
    var score = (len?1:0)+(up?1:0)+(lo?1:0)+(di?1:0)+(sy?1:0);
    if(score === 5) return 'Strong password';
    if(score >= 3) return 'Medium — add ' + (!len?'length (≥12), ':'') + (!up?'an uppercase, ':'') + (!lo?'a lowercase, ':'') + (!di?'a number, ':'') + (!sy?'a symbol, ':'');
    return 'Weak — use ≥12 chars with upper, lower, number, and symbol';
}

document.addEventListener('DOMContentLoaded', function () {
    showForm('<?= $active_tab === 'register' ? 'register' : 'login' ?>');
    var reg = document.getElementById('reg_password');
    var hint = document.getElementById('reg_pw_hint');
    if (reg && hint) {
        var update = function(){ hint.textContent = pwStrength(reg.value); };
        reg.addEventListener('input', update);
        update();
    }
});
</script>
