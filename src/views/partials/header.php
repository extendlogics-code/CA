<?php
$user = current_user();
$title = $title ?? 'Advisory Console';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>
        header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            padding: 1rem clamp(1rem, 3vw, 2.5rem);
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 70%);
            color: #f8fafc;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.35);
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .header-logo {
            height: 40px;
            width: auto;
        }

        .header-brand h1 {
            margin: 0;
            color: #f8fafc;
        }

        .site-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 0.75rem;
            flex-wrap: nowrap;
            overflow-x: auto;
        }

        .nav-list li {
            flex: 0 0 auto;
        }

        .nav-list a {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 0.35rem;
            width: 100%;
            padding: 0.7rem 1.35rem;
            border-radius: 999px;
            border: none;
            position: relative;
            background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 50%, #a855f7 100%);
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 18px 35px rgba(79, 70, 229, 0.35);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .nav-list a::before {
            content: '';
            position: absolute;
            inset: 2px;
            border-radius: 999px;
            border: 1px dashed rgba(255, 255, 255, 0.35);
            opacity: 0.75;
            pointer-events: none;
        }

        .nav-list a::after {
            content: '';
            position: absolute;
            top: -40%;
            left: -10%;
            width: 80%;
            height: 120%;
            background: rgba(255, 255, 255, 0.25);
            filter: blur(30px);
            transform: rotate(20deg);
            pointer-events: none;
        }

        .nav-list a:hover::after {
            background: rgba(15, 23, 42, 0.15);
        }

        .nav-list a:hover {
            transform: translateY(-1px);
            box-shadow: 0 22px 40px rgba(79, 70, 229, 0.45);
            color: #0f172a;
        }

        .nav-list a:hover::before {
            border-color: rgba(15, 23, 42, 0.35);
        }

        .nav-toggle {
            display: none;
        }

        .logout-button {
            border: none;
            background: linear-gradient(135deg, #ef4444 0%, #be123c 70%);
            color: #fff5f5;
            border-radius: 999px;
            padding: 0.65rem 1.4rem;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            box-shadow: 0 16px 32px rgba(239, 68, 68, 0.35);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .logout-button::before {
            content: '';
            position: absolute;
            inset: 2px;
            border-radius: 999px;
            border: 1px dashed rgba(255, 255, 255, 0.4);
            opacity: 0.8;
            pointer-events: none;
        }

        .logout-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 22px 40px rgba(190, 18, 60, 0.45);
        }
        /* Responsive helpers */
        main { padding: clamp(0.75rem, 2vw, 1.5rem); }
        .table-wrapper { overflow-x: auto; }
        .table { min-width: 640px; width: 100%; }
        img, video { max-width: 100%; height: auto; }
        .card { margin-bottom: 1rem; }
        /* Mobile tweaks */
        @media (max-width: 768px) {
            .site-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            .nav-toggle {
                display: inline-flex;
                flex-direction: column;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 0.35rem;
                gap: 0.3rem;
            }
        .nav-toggle span {
            display: block;
            width: 22px;
            height: 2px;
            background: #f8fafc;
            border-radius: 999px;
        }
            .nav-list {
                width: 100%;
                display: none;
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            .nav-list.open {
                display: flex;
            }
            .nav-list li {
                width: auto;
                flex: 0 0 auto;
            }
            .nav-list a {
                width: 100%;
                justify-content: center;
            }
        }
        /* 13-inch laptop tweaks (~1366px width) */
        @media (max-width: 1366px) {
            header {
                padding: 0.85rem clamp(0.85rem, 2.5vw, 1.75rem);
            }
            .header-logo {
                height: 32px;
            }
            .nav-list {
                gap: 0.5rem;
            }
            .nav-list a {
                padding: 0.55rem 1rem;
                font-size: 0.95rem;
            }
            .logout-button {
                padding: 0.55rem 1rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="header-brand">
        <img src="/assets/images/logo.png" alt="CA Service Hub logo" class="header-logo">
        <h1>CA Service Hub</h1>
    </div>
    <nav class="site-nav">
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <ul class="nav-list">
            <?php if ($user): ?>
                <?php if (user_has_role(['ceo'])): ?>
                    <li><a href="<?= e(url_for('dashboard')) ?>">Dashboard</a></li>
                    <li><a href="<?= e(url_for('tasks')) ?>">Tasks</a></li>
                    <li><a href="<?= e(url_for('clients')) ?>">Clients</a></li>
                    <li><a href="<?= e(url_for('staff')) ?>">Staff</a></li>
                    <li><a href="<?= e(url_for('checklist')) ?>">Checklist</a></li>
                    <li><a href="<?= e(url_for('templates')) ?>">Templates</a></li>
                    <li><a href="<?= e(url_for('statuses')) ?>">Statuses</a></li>
                    <li><a href="<?= e(url_for('hierarchy')) ?>">Hierarchy</a></li>
                    <li><a href="<?= e(url_for('audits')) ?>" <?= (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === url_for('audits')) ? 'class="active"' : '' ?>>Audits</a></li>
                    <!-- <li><a href="<?= e(url_for('access')) ?>">Access</a></li> -->
                <?php else: ?>
                    <li><a href="<?= e(url_for('dashboard')) ?>">Dashboard</a></li>
                    <li><a href="<?= e(url_for('tasks')) ?>">Tasks</a></li>
                    <?php if (!user_has_role(['employee'])): ?>
                        <li><a href="<?= e(url_for('clients')) ?>">Clients</a></li>
                    <?php endif; ?>
                    <li><a href="<?= e(url_for('checklist')) ?>">Checklist</a></li>
                    <li><a href="<?= e(url_for('hierarchy')) ?>">Hierarchy</a></li>

                <?php endif; ?>
                <li>
                    <form action="<?= e(url_for('logout')) ?>" method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="logout-button">Logout (<?= e($user['name']) ?>)</button>
                    </form>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.nav-toggle');
    var menu = document.querySelector('.nav-list');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', function () {
        var isOpen = menu.classList.toggle('open');
        toggle.classList.toggle('active', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
});
</script>
