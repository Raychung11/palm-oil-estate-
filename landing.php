<?php
/**
 * landing.php
 * Public marketing landing page for Estate BOS.
 *
 * Standalone and public — loads only app config + helpers (no session, no DB),
 * so it is safe to expose without authentication.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/inc/functions.php';

$modules = [
    ['bi-geo-alt',       'Estate Setup',        'Estates, divisions, blocks and plots with acreage, palm age, GPS and status.'],
    ['bi-basket',        'Harvest & FFB',       'Daily harvest entry, team assignment, field photos and supervisor approval.'],
    ['bi-person-badge',  'Workers & Attendance','Profiles, documents, attendance register and permit/contract expiry alerts.'],
    ['bi-list-check',    'Field Tasks',         'Plan, assign, start, complete and approve field operations with proof photos.'],
    ['bi-droplet-half',  'Fertilizer & Chemical','Product masters, stock control, block applications and spraying records.'],
    ['bi-box-seam',      'Inventory & Store',   'Item master, stock movements, valuation and low-stock alerts.'],
    ['bi-truck-front',   'Vehicles & Fuel',     'Machinery maintenance, service reminders and fuel control with leak alerts.'],
    ['bi-truck',         'Mill Delivery',       'Weighbridge tickets, net weight, mill price/OER and harvest reconciliation.'],
    ['bi-graph-up',      'Costing Dashboard',   'Cost per acre & tonne, profitability by block and division, Excel/PDF export.'],
    ['bi-robot',         'AI Assistant',        'Ask questions of your data and generate monthly management summaries.'],
];

$benefits = [
    ['bi-clipboard-check', 'Accurate daily data',     'Every harvest, task and movement captured at source — no more paper trails.'],
    ['bi-speedometer2',    'Worker productivity',     'Track output per worker and block to reward performance and spot gaps.'],
    ['bi-cash-coin',       'Cost visibility',         'See exactly where money goes, down to cost per tonne for each block.'],
    ['bi-fuel-pump',       'Fuel leakage control',    'Per-vehicle consumption with automatic abnormal-usage alerts.'],
    ['bi-shield-check',    'Compliance ready',        'Records and documents structured for MSPO / RSPO readiness.'],
    ['bi-lightbulb',       'AI decision support',     'Rule-based insights today, optional LLM assistant tomorrow.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> — AI-Powered Palm Oil Estate Operating System</title>
    <meta name="description" content="A centralized operating system to manage palm oil estate operations: harvest, workers, assets, inventory, fuel, mill delivery, costing and AI decision support.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --green:#1b5e20; --green-2:#2e7d32; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color:#1f2933; }
        .navbar-brand { font-weight:700; color:var(--green) !important; }
        .brand-mark { width:34px; height:34px; border-radius:8px; background:var(--green); color:#fff;
            display:inline-flex; align-items:center; justify-content:center; margin-right:.5rem; }
        .hero { background:linear-gradient(135deg, #1b5e20, #2e7d32 60%, #43a047); color:#fff; }
        .hero h1 { font-weight:800; font-size:clamp(2rem,5vw,3.4rem); line-height:1.1; }
        .hero .lead { opacity:.92; }
        .hero-card { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2);
            border-radius:14px; backdrop-filter: blur(4px); }
        .section { padding:4.5rem 0; }
        .eyebrow { letter-spacing:.08em; text-transform:uppercase; font-size:.78rem; font-weight:700; color:var(--green-2); }
        .feature-card { border:1px solid #e8ecef; border-radius:14px; height:100%; transition:.15s; background:#fff; }
        .feature-card:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(16,24,40,.08); border-color:#cfe3d0; }
        .feature-icon { width:46px; height:46px; border-radius:10px; background:#e8f5e9; color:var(--green);
            display:inline-flex; align-items:center; justify-content:center; font-size:1.35rem; }
        .stat h3 { font-weight:800; color:var(--green); font-size:2rem; margin:0; }
        .bg-soft { background:#f4f7f5; }
        .price-card { border:1px solid #e8ecef; border-radius:16px; height:100%; }
        .price-card.featured { border-color:var(--green); box-shadow:0 12px 36px rgba(27,94,32,.16); }
        .cta { background:var(--green); color:#fff; border-radius:18px; }
        footer { background:#102216; color:#cfd8d2; }
        a.btn-light-green { background:#fff; color:var(--green); font-weight:600; }
        .check { color:var(--green-2); }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#top">
            <span class="brand-mark"><i class="bi bi-tree-fill"></i></span> <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#modules">Modules</a></li>
                <li class="nav-item"><a class="nav-link" href="#why">Why Estate BOS</a></li>
                <li class="nav-item"><a class="nav-link" href="#packages">Packages</a></li>
                <li class="nav-item"><a class="btn btn-success btn-sm px-3" href="<?= e(url('login.php')) ?>">Sign in</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero -->
<header class="hero section" id="top">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-light text-success mb-3">AI-Powered Estate BOS</span>
                <h1>Run your palm oil estate as one connected system.</h1>
                <p class="lead mt-3">From harvest and workers to fuel, mill delivery and costing — <?= e(APP_NAME) ?>
                   centralizes estate operations and turns daily data into management decisions.</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="<?= e(url('login.php')) ?>" class="btn btn-light-green btn-lg px-4"><i class="bi bi-box-arrow-in-right me-2"></i>Get Started</a>
                    <a href="#modules" class="btn btn-outline-light btn-lg px-4">Explore Modules</a>
                </div>
                <p class="small mt-3 mb-0" style="opacity:.8;">Native PHP &middot; MySQL &middot; runs on standard shared hosting.</p>
            </div>
            <div class="col-lg-6">
                <div class="hero-card p-4">
                    <div class="row g-3 text-center">
                        <div class="col-6"><div class="p-3"><div class="fs-3 fw-bold">10</div><div class="small">Integrated modules</div></div></div>
                        <div class="col-6"><div class="p-3"><div class="fs-3 fw-bold">1,000+</div><div class="small">Acres managed</div></div></div>
                        <div class="col-6"><div class="p-3"><div class="fs-3 fw-bold">RM/t</div><div class="small">Cost per tonne</div></div></div>
                        <div class="col-6"><div class="p-3"><div class="fs-3 fw-bold">MSPO</div><div class="small">Compliance ready</div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Modules -->
<section class="section" id="modules">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow">One platform</div>
            <h2 class="fw-bold">Everything your estate runs on</h2>
            <p class="text-muted">Ten modules that share the same data — record once, see it everywhere.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($modules as [$icon, $title, $desc]): ?>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card p-4">
                    <span class="feature-icon mb-3"><i class="bi <?= e($icon) ?>"></i></span>
                    <h5 class="fw-bold"><?= e($title) ?></h5>
                    <p class="text-muted mb-0 small"><?= e($desc) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Why -->
<section class="section bg-soft" id="why">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow">The value</div>
            <h2 class="fw-bold">Built for long-term estate performance</h2>
            <p class="text-muted">Not a one-time CRUD tool — a long-term operating platform.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($benefits as [$icon, $title, $desc]): ?>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3">
                    <i class="bi <?= e($icon) ?> fs-3 check"></i>
                    <div>
                        <h6 class="fw-bold mb-1"><?= e($title) ?></h6>
                        <p class="text-muted small mb-0"><?= e($desc) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Packages -->
<section class="section" id="packages">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow">Packages</div>
            <h2 class="fw-bold">Start lean, scale to a full BOS</h2>
            <p class="text-muted">Phased delivery so value lands early and grows with your estate.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="price-card p-4">
                    <h5 class="fw-bold">Phase 1 — MVP</h5>
                    <p class="text-muted small">Foundation, estate setup, harvest, workers and reporting.</p>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>Role-based access &amp; audit log</li>
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>Harvest &amp; worker management</li>
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>Daily &amp; monthly reports</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="price-card featured p-4">
                    <span class="badge bg-success mb-2">Most popular</span>
                    <h5 class="fw-bold">Full BOS</h5>
                    <p class="text-muted small">All operational modules plus the costing dashboard.</p>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>Fertilizer, chemical, inventory</li>
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>Assets, fuel &amp; mill delivery</li>
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>Costing &amp; profitability</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="price-card p-4">
                    <h5 class="fw-bold">AI + GIS + Compliance</h5>
                    <p class="text-muted small">Decision support, mapping and audit-ready compliance.</p>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>AI estate assistant &amp; insights</li>
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>GIS / block mapping</li>
                        <li class="mb-2"><i class="bi bi-check-lg check me-2"></i>MSPO / RSPO documentation</li>
                    </ul>
                </div>
            </div>
        </div>
        <p class="text-center text-muted small mt-4">Tailored to estate size and module scope. Contact us for a quote.</p>
    </div>
</section>

<!-- CTA -->
<section class="section">
    <div class="container">
        <div class="cta p-5 text-center">
            <h2 class="fw-bold mb-2">Ready to operate your estate as one system?</h2>
            <p class="mb-4" style="opacity:.9;">Sign in to the dashboard and start capturing live estate data today.</p>
            <a href="<?= e(url('login.php')) ?>" class="btn btn-light-green btn-lg px-4"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in to Estate BOS</a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="py-4">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="d-flex align-items-center"><span class="brand-mark"><i class="bi bi-tree-fill"></i></span>
            <strong><?= e(APP_NAME) ?></strong></span>
        <span class="small">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — <?= e(APP_TAGLINE) ?></span>
        <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-light btn-sm">Sign in</a>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
