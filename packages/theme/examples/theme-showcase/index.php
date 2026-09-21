<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Theme\ThemeManager;

$themes = new ThemeManager([
    'themes_path' => __DIR__ . '/themes',
    'default' => 'default',
    'mode' => 'system',
    'density' => 'comfortable',
    'themes' => [
        'default' => ['modes' => ['light', 'dark']],
        'showcase' => ['modes' => ['light', 'dark']],
        'win11' => ['modes' => ['light', 'dark']],
        'vscode' => ['modes' => ['light', 'dark']],
        'minimal' => ['modes' => ['light', 'dark']],
        'ubuntu' => ['modes' => ['light', 'dark']],
        'macos' => ['modes' => ['light', 'dark']],
        'android' => ['modes' => ['light', 'dark']],
    ],
    'user' => [
        'enabled' => true,
        'theme' => true,
        'mode' => true,
        'density' => true,
    ],
    'components' => [
        'admin' => true,
    ],
    'toggle' => [
        'enabled' => true,
        'position' => 'bottom-right',
    ],
]);

$documents = [
    ['OBR-2026-09121', 'Office Supplies', 'Provincial Budget Office', 'For Review', 'warning'],
    ['OBR-2026-09120', 'ICT Equipment', 'PGSO', 'Approved', 'success'],
    ['OBR-2026-09119', 'Training Expenses', 'HRMO', 'Returned', 'danger'],
    ['OBR-2026-09118', 'Fuel Allocation', 'PGENRO', 'Received', 'info'],
    ['OBR-2026-09117', 'Maintenance', 'Engineering', 'Draft', 'neutral'],
];

$themeOptions = $themes->available();
$diagnostics = $themes->explain();

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en" <?= $themes->attributes() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefab Theme Showcase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= $themes->styles() ?>
    <style>
        html { scroll-behavior: smooth; }
        [id] { scroll-margin-top: calc(var(--pf-topbar-height) + 1rem); }
        .demo-section { margin-bottom: 2rem; }
        .demo-section-title { margin: 0 0 .25rem; font-size: 1.15rem; font-weight: 700; }
        .demo-section-copy { margin: 0 0 1rem; color: var(--pf-text-muted); font-size: .9rem; }
        .demo-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap: 1rem; }
        .demo-swatch { min-height: 76px; padding: .75rem; border: 1px solid var(--pf-border); border-radius: var(--pf-radius); }
        .demo-code { margin: 0; padding: .85rem; overflow: auto; background: var(--pf-surface-alt); border: 1px solid var(--pf-border); border-radius: var(--pf-radius); color: var(--pf-text); font-size: .8rem; }
        .demo-anchor { color: inherit; text-decoration: none; }
        .demo-anchor:hover { color: var(--pf-primary); }
        .demo-inline { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .demo-muted { color: var(--pf-text-muted); }
        .demo-sidebar-brand { font-weight: 750; letter-spacing: -.02em; }
        .demo-sidebar-caption { color: var(--pf-text-muted); font-size: .75rem; }
        .demo-topbar-title { font-weight: 650; white-space: nowrap; }
        .demo-state code { color: var(--pf-primary); }
    </style>
</head>
<body>
<div class="pf-shell">
    <aside class="pf-sidebar" id="demoSidebar">
        <div class="pf-sidebar-header">
            <div>
                <div class="demo-sidebar-brand">Prefab Theme</div>
                <div class="demo-sidebar-caption">Component Showcase</div>
            </div>
            <button class="btn btn-sm btn-outline-secondary ms-auto d-lg-none" type="button" data-prefab-sidebar-close aria-label="Close sidebar">×</button>
        </div>

        <div class="pf-sidebar-body">
            <nav class="pf-nav">
                <div class="pf-nav-section">Overview</div>
                <a class="pf-nav-link active" href="#overview">Overview</a>
                <a class="pf-nav-link" href="#appearance">Appearance <span class="pf-nav-counter">3</span></a>
                <a class="pf-nav-link" href="#stats">Stats</a>

                <div class="pf-nav-section">Application UI</div>
                <a class="pf-nav-link" href="#toolbar">Toolbar</a>
                <a class="pf-nav-link" href="#panels">Panels</a>
                <a class="pf-nav-link" href="#record">Record</a>
                <a class="pf-nav-link" href="#data">Data panel <span class="pf-nav-counter">5</span></a>
                <a class="pf-nav-link" href="#timeline">Timeline</a>
                <a class="pf-nav-link" href="#settings">Settings</a>
                <a class="pf-nav-link" href="#empty">Empty state</a>

                <div class="pf-nav-section">Bootstrap</div>
                <a class="pf-nav-link" href="#buttons">Buttons & status</a>
                <a class="pf-nav-link" href="#forms">Forms</a>
                <a class="pf-nav-link" href="#bootstrap">Components</a>

                <div class="pf-nav-section">Developer</div>
                <a class="pf-nav-link" href="#tokens">Tokens</a>
                <a class="pf-nav-link" href="#diagnostics">Diagnostics</a>
            </nav>
        </div>
    </aside>

    <div class="pf-workspace">
        <header class="pf-topbar">
            <div class="pf-topbar-start">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-prefab-sidebar-toggle data-prefab-sidebar-target="#demoSidebar" aria-expanded="false">Menu</button>
                <span class="demo-topbar-title">Prefab Theme Showcase</span>
            </div>

            <div class="pf-topbar-end">
                <span class="demo-state d-none d-md-inline small">
                    <code id="currentTheme">default</code> /
                    <code id="currentMode">system</code> /
                    <code id="currentDensity">comfortable</code>
                </span>
                <button class="btn btn-sm btn-outline-secondary" type="button" pf:theme-mode="toggle">Toggle mode</button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">Appearance</button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 14rem">
                        <div class="small text-body-secondary px-2 pb-1">Theme</div>
                        <?php foreach ($themeOptions as $themeId => $entry): ?>
                            <button class="dropdown-item" type="button" pf:theme="<?= e($themeId) ?>"><?= e($entry['theme']->name) ?></button>
                        <?php endforeach; ?>
                        <div class="dropdown-divider"></div>
                        <div class="small text-body-secondary px-2 pb-1">Mode</div>
                        <button class="dropdown-item" type="button" pf:theme-mode="light">Light</button>
                        <button class="dropdown-item" type="button" pf:theme-mode="dark">Dark</button>
                        <button class="dropdown-item" type="button" pf:theme-mode="system">System</button>
                    </div>
                </div>
            </div>
        </header>

        <main class="pf-main">
            <div class="pf-page">
                <section class="pf-page-header" id="overview">
                    <div>
                        <h1 class="pf-page-title">Theme & Component Showcase</h1>
                        <div class="pf-page-subtitle">One page showing the current Prefab Theme primitives, admin objects, Bootstrap integration and appearance actions.</div>
                    </div>
                    <div class="pf-page-actions">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#demoOffcanvas">Open offcanvas</button>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#demoModal">Open modal</button>
                    </div>
                </section>

                <section class="demo-section" id="appearance">
                    <h2 class="demo-section-title">Appearance actions</h2>
                    <p class="demo-section-copy">Theme owns only <code>pf:theme</code>, <code>pf:theme-mode</code> and <code>pf:theme-density</code>. Changes persist automatically.</p>

                    <div class="pf-panel">
                        <div class="pf-panel-header"><strong>Directives on ordinary controls</strong></div>
                        <div class="demo-grid">
                            <div>
                                <div class="small fw-semibold mb-2">Theme</div>
                                <div class="demo-inline">
                                    <?php foreach ($themeOptions as $themeId => $entry): ?>
                                        <button class="btn btn-outline-primary" type="button" pf:theme="<?= e($themeId) ?>"><?= e($entry['theme']->name) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <div class="small fw-semibold mb-2">Mode</div>
                                <div class="demo-inline">
                                    <button class="btn btn-outline-secondary" type="button" pf:theme-mode="light">Light</button>
                                    <button class="btn btn-outline-secondary" type="button" pf:theme-mode="dark">Dark</button>
                                    <button class="btn btn-outline-secondary" type="button" pf:theme-mode="system">System</button>
                                    <button class="btn btn-outline-secondary" type="button" pf:theme-mode="toggle">Toggle</button>
                                </div>
                            </div>
                            <div>
                                <div class="small fw-semibold mb-2">Density</div>
                                <div class="demo-inline">
                                    <button class="btn btn-outline-secondary" type="button" pf:theme-density="comfortable">Comfortable</button>
                                    <button class="btn btn-outline-secondary" type="button" pf:theme-density="compact">Compact</button>
                                    <button class="btn btn-outline-secondary" type="button" pf:theme-density="toggle">Toggle</button>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label">Theme select</label>
                                <select class="form-select" pf:theme>
                                    <?php foreach ($themeOptions as $themeId => $entry): ?>
                                        <option value="<?= e($themeId) ?>"><?= e($entry['theme']->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mode select</label>
                                <select class="form-select" pf:theme-mode>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                    <option value="system">System</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Density select</label>
                                <select class="form-select" pf:theme-density>
                                    <option value="comfortable">Comfortable</option>
                                    <option value="compact">Compact</option>
                                </select>
                            </div>
                        </div>

                        <div class="pf-panel-footer">
                            <span class="small demo-muted">The floating ◐ button is also enabled by configuration.</span>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="stats">
                    <h2 class="demo-section-title">Stats</h2>
                    <p class="demo-section-copy"><code>pf-stats</code> adapts automatically to the available width.</p>
                    <div class="pf-stats">
                        <div class="pf-stat">
                            <div class="pf-stat-label">Incoming</div>
                            <div class="pf-stat-value">128</div>
                            <div class="pf-stat-meta">18 received today</div>
                        </div>
                        <div class="pf-stat">
                            <div class="pf-stat-label">For Review</div>
                            <div class="pf-stat-value">24</div>
                            <div class="pf-stat-meta">6 need attention</div>
                        </div>
                        <div class="pf-stat">
                            <div class="pf-stat-label">Approved</div>
                            <div class="pf-stat-value">91</div>
                            <div class="pf-stat-meta">71% of current batch</div>
                        </div>
                        <div class="pf-stat">
                            <div class="pf-stat-label">Returned</div>
                            <div class="pf-stat-value">13</div>
                            <div class="pf-stat-meta">10% return rate</div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="toolbar">
                    <h2 class="demo-section-title">Toolbar</h2>
                    <p class="demo-section-copy">Responsive start/end groups for search, filters and page actions.</p>
                    <div class="pf-toolbar">
                        <div class="pf-toolbar-start">
                            <input class="form-control" type="search" placeholder="Search documents..." style="max-width: 22rem">
                            <select class="form-select" style="max-width: 12rem">
                                <option>All statuses</option>
                                <option>For Review</option>
                                <option>Approved</option>
                            </select>
                        </div>
                        <div class="pf-toolbar-end">
                            <button class="btn btn-outline-secondary">Filter</button>
                            <button class="btn btn-outline-secondary">Export</button>
                            <button class="btn btn-primary">Add Document</button>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="panels">
                    <h2 class="demo-section-title">Panels</h2>
                    <p class="demo-section-copy">General application containers with optional header and footer areas.</p>
                    <div class="demo-grid">
                        <div class="pf-panel">
                            <div class="pf-panel-header">
                                <strong>Standard panel</strong>
                                <span class="badge text-bg-primary ms-auto">New</span>
                            </div>
                            <p class="mb-2">Panels are for app-level content that does not map directly to a Bootstrap component.</p>
                            <p class="mb-0 demo-muted">Bootstrap controls can be composed normally inside them.</p>
                            <div class="pf-panel-footer">
                                <button class="btn btn-sm btn-primary">Save</button>
                                <button class="btn btn-sm btn-outline-secondary">Cancel</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header fw-semibold">Bootstrap card</div>
                            <div class="card-body">
                                <h5 class="card-title">Still normal Bootstrap</h5>
                                <p class="card-text">Prefab Theme styles Bootstrap through semantic tokens instead of replacing its markup.</p>
                                <a href="#bootstrap" class="btn btn-outline-primary">See Bootstrap objects</a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="record">
                    <h2 class="demo-section-title">Record header & details</h2>
                    <p class="demo-section-copy">Useful for document, user, transaction and other record pages.</p>

                    <div class="pf-record-header">
                        <div class="pf-record-main">
                            <div class="pf-record-id">OBR-2026-09121</div>
                            <h3 class="pf-record-title">Office Supplies Procurement</h3>
                            <div class="mt-2"><span class="pf-status pf-status-warning">For Review</span></div>
                        </div>
                        <div class="pf-record-actions">
                            <button class="btn btn-outline-secondary">Print</button>
                            <button class="btn btn-outline-danger">Return</button>
                            <button class="btn btn-primary">Approve</button>
                        </div>
                    </div>

                    <div class="pf-panel">
                        <div class="pf-details">
                            <div class="pf-detail">
                                <div class="pf-detail-label">Origin</div>
                                <div class="pf-detail-value">Provincial Budget Office</div>
                            </div>
                            <div class="pf-detail">
                                <div class="pf-detail-label">Fund</div>
                                <div class="pf-detail-value">General Fund</div>
                            </div>
                            <div class="pf-detail">
                                <div class="pf-detail-label">Amount</div>
                                <div class="pf-detail-value">₱185,420.00</div>
                            </div>
                            <div class="pf-detail">
                                <div class="pf-detail-label">Created</div>
                                <div class="pf-detail-value">September 21, 2026</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="data">
                    <h2 class="demo-section-title">Data panel</h2>
                    <p class="demo-section-copy">Scrollable table container plus a dedicated footer.</p>
                    <div class="pf-data-panel">
                        <div class="pf-table-wrap">
                            <table class="table table-hover align-middle">
                                <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Description</th>
                                    <th>Office</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($documents as [$number, $description, $office, $status, $variant]): ?>
                                    <tr>
                                        <td><a href="#record" class="fw-semibold text-decoration-none"><?= e($number) ?></a></td>
                                        <td><?= e($description) ?></td>
                                        <td><?= e($office) ?></td>
                                        <td><span class="pf-status pf-status-<?= e($variant) ?>"><?= e($status) ?></span></td>
                                        <td class="text-end"><button class="btn btn-sm btn-outline-secondary">View</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pf-data-footer">
                            <span>Showing 1–5 of 128 records</span>
                            <nav aria-label="Data pages">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item disabled"><span class="page-link">Previous</span></li>
                                    <li class="page-item active"><span class="page-link">1</span></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">Next</a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="timeline">
                    <h2 class="demo-section-title">Timeline</h2>
                    <p class="demo-section-copy">A simple history/routing primitive.</p>
                    <div class="pf-panel">
                        <div class="pf-timeline">
                            <div class="pf-timeline-item">
                                <div class="pf-timeline-title">Document created</div>
                                <div class="pf-timeline-meta">Budget Office · 09:12</div>
                            </div>
                            <div class="pf-timeline-item">
                                <div class="pf-timeline-title">Forwarded to Fund Control</div>
                                <div class="pf-timeline-meta">Maria Santos · 09:26</div>
                            </div>
                            <div class="pf-timeline-item">
                                <div class="pf-timeline-title">Reviewed</div>
                                <div class="pf-timeline-meta">Fund Control · 10:03</div>
                            </div>
                            <div class="pf-timeline-item">
                                <div class="pf-timeline-title">Pending approval</div>
                                <div class="pf-timeline-meta">Current step · 10:11</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="settings">
                    <h2 class="demo-section-title">Settings</h2>
                    <p class="demo-section-copy">Grouped configuration rows that remain responsive on small screens.</p>
                    <div class="pf-settings">
                        <div class="pf-settings-section">
                            <div class="pf-settings-title">Appearance</div>
                            <div class="pf-setting-row">
                                <div class="pf-setting-main">
                                    <div class="pf-setting-label">Theme</div>
                                    <div class="pf-setting-help">Choose an enabled application theme.</div>
                                </div>
                                <select class="form-select" style="max-width: 13rem" pf:theme>
                                    <?php foreach ($themeOptions as $themeId => $entry): ?>
                                        <option value="<?= e($themeId) ?>"><?= e($entry['theme']->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="pf-setting-row">
                                <div class="pf-setting-main">
                                    <div class="pf-setting-label">Color mode</div>
                                    <div class="pf-setting-help">Light, dark or follow the operating system.</div>
                                </div>
                                <select class="form-select" style="max-width: 13rem" pf:theme-mode>
                                    <option value="system">System</option>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                            </div>
                            <div class="pf-setting-row">
                                <div class="pf-setting-main">
                                    <div class="pf-setting-label">Density</div>
                                    <div class="pf-setting-help">Compact mode reduces page and control spacing.</div>
                                </div>
                                <select class="form-select" style="max-width: 13rem" pf:theme-density>
                                    <option value="comfortable">Comfortable</option>
                                    <option value="compact">Compact</option>
                                </select>
                            </div>
                        </div>

                        <div class="pf-settings-section">
                            <div class="pf-settings-title">Application</div>
                            <div class="pf-setting-row">
                                <div class="pf-setting-main">
                                    <div class="pf-setting-label">Email notifications</div>
                                    <div class="pf-setting-help">This row demonstrates composition with a normal Bootstrap switch.</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" checked>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="empty">
                    <h2 class="demo-section-title">Empty state</h2>
                    <div class="pf-panel">
                        <div class="pf-empty">
                            <div class="pf-empty-title">No archived documents</div>
                            <div class="pf-empty-text">When records become available, they will appear here. Empty states can contain ordinary Bootstrap actions.</div>
                            <div class="pf-empty-actions">
                                <button class="btn btn-primary">Create document</button>
                                <button class="btn btn-outline-secondary">Learn more</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="buttons">
                    <h2 class="demo-section-title">Buttons, badges & Prefab status</h2>
                    <p class="demo-section-copy">Bootstrap owns standard buttons/badges; Prefab adds semantic application status pills.</p>
                    <div class="pf-panel">
                        <div class="demo-inline mb-3">
                            <button class="btn btn-primary">Primary</button>
                            <button class="btn btn-secondary">Secondary</button>
                            <button class="btn btn-success">Success</button>
                            <button class="btn btn-danger">Danger</button>
                            <button class="btn btn-warning">Warning</button>
                            <button class="btn btn-info">Info</button>
                            <button class="btn btn-outline-primary">Outline</button>
                            <button class="btn btn-link">Link</button>
                        </div>
                        <div class="demo-inline mb-3">
                            <span class="badge text-bg-primary">Primary</span>
                            <span class="badge text-bg-success">Success</span>
                            <span class="badge text-bg-warning">Warning</span>
                            <span class="badge text-bg-danger">Danger</span>
                        </div>
                        <div class="demo-inline">
                            <span class="pf-status pf-status-success">Success</span>
                            <span class="pf-status pf-status-warning">Warning</span>
                            <span class="pf-status pf-status-danger">Danger</span>
                            <span class="pf-status pf-status-info">Info</span>
                            <span class="pf-status pf-status-neutral">Neutral</span>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="forms">
                    <h2 class="demo-section-title">Forms</h2>
                    <p class="demo-section-copy">Normal Bootstrap form markup follows the active Prefab semantic tokens.</p>
                    <div class="pf-panel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Text input</label>
                                <input class="form-control" value="Prefab Theme">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Select</label>
                                <select class="form-select">
                                    <option>Option one</option>
                                    <option>Option two</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Input group</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input class="form-control" value="185420.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Range</label>
                                <input class="form-range" type="range" min="0" max="100" value="65">
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input class="form-control" id="floatingEmail" placeholder="name@example.com" value="demo@example.com">
                                    <label for="floatingEmail">Floating label</label>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-center gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkDemo" checked>
                                    <label class="form-check-label" for="checkDemo">Checkbox</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switchDemo" checked>
                                    <label class="form-check-label" for="switchDemo">Switch</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="bootstrap">
                    <h2 class="demo-section-title">Bootstrap component integration</h2>
                    <p class="demo-section-copy">These remain standard Bootstrap objects; Theme only supplies the visual token layer.</p>

                    <div class="demo-grid mb-3">
                        <div>
                            <div class="alert alert-primary">Primary alert</div>
                            <div class="alert alert-success mb-0">Success alert</div>
                        </div>
                        <div class="pf-panel">
                            <div class="fw-semibold mb-2">Progress</div>
                            <div class="progress mb-3" role="progressbar" aria-label="Example" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width:72%">72%</div>
                            </div>
                            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                        </div>
                    </div>

                    <div class="demo-grid mb-3">
                        <div class="list-group">
                            <a href="#" class="list-group-item list-group-item-action active">Active list item</a>
                            <a href="#" class="list-group-item list-group-item-action">Second item</a>
                            <a href="#" class="list-group-item list-group-item-action">Third item</a>
                        </div>

                        <div class="accordion" id="demoAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">Accordion item</button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#demoAccordion">
                                    <div class="accordion-body">Normal Bootstrap accordion content.</div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">Second item</button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#demoAccordion">
                                    <div class="accordion-body">Another collapsible section.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pf-panel">
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOne" type="button">Tab one</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTwo" type="button">Tab two</button></li>
                        </ul>
                        <div class="tab-content mb-3">
                            <div class="tab-pane fade show active" id="tabOne">Tabbed content integrates normally.</div>
                            <div class="tab-pane fade" id="tabTwo">Second tab content.</div>
                        </div>
                        <div class="demo-inline">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Dropdown</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#">Action</a></li>
                                    <li><a class="dropdown-item" href="#">Another action</a></li>
                                </ul>
                            </div>
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDemo">Collapse</button>
                            <button class="btn btn-outline-secondary" type="button" id="showToast">Toast</button>
                        </div>
                        <div class="collapse mt-3" id="collapseDemo">
                            <div class="card card-body">Collapsible Bootstrap content.</div>
                        </div>
                    </div>
                </section>

                <section class="demo-section" id="tokens">
                    <h2 class="demo-section-title">Semantic tokens</h2>
                    <p class="demo-section-copy">A visual sample of the variables themes override.</p>
                    <div class="demo-grid">
                        <div class="demo-swatch" style="background:var(--pf-bg)">--pf-bg</div>
                        <div class="demo-swatch" style="background:var(--pf-surface)">--pf-surface</div>
                        <div class="demo-swatch" style="background:var(--pf-surface-alt)">--pf-surface-alt</div>
                        <div class="demo-swatch" style="background:var(--pf-primary);color:var(--pf-primary-contrast)">--pf-primary</div>
                        <div class="demo-swatch" style="background:var(--pf-success);color:#fff">--pf-success</div>
                        <div class="demo-swatch" style="background:var(--pf-danger);color:#fff">--pf-danger</div>
                    </div>
                </section>

                <section class="demo-section" id="diagnostics">
                    <h2 class="demo-section-title">Theme diagnostics</h2>
                    <p class="demo-section-copy">The server-side <code>explain()</code> result for this showcase configuration.</p>
                    <pre class="demo-code"><?= e(json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </section>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="demoModal" tabindex="-1" aria-labelledby="demoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="demoModalLabel">Bootstrap modal</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">This modal uses normal Bootstrap markup and follows the active Prefab theme.</div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="demoOffcanvas" aria-labelledby="demoOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="demoOffcanvasLabel">Bootstrap offcanvas</h5>
        <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        Offcanvas, modals, dropdowns, cards and forms remain Bootstrap components.
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3">
    <div class="toast" id="demoToast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Prefab Theme</strong>
            <small>now</small>
            <button class="btn-close ms-2 mb-1" type="button" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">Bootstrap toast is working with the active theme.</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<?= $themes->scripts() ?>
<script>
(() => {
    const renderState = () => {
        if (!window.PrefabTheme) return;
        const state = PrefabTheme.get();
        document.getElementById('currentTheme').textContent = state.theme;
        document.getElementById('currentMode').textContent = state.mode;
        document.getElementById('currentDensity').textContent = state.density;
        document.querySelectorAll('select[pf\\:theme]').forEach(el => el.value = state.theme);
        document.querySelectorAll('select[pf\\:theme-mode]').forEach(el => el.value = state.mode);
        document.querySelectorAll('select[pf\\:theme-density]').forEach(el => el.value = state.density);
    };

    document.addEventListener('prefab:themechange', renderState);
    renderState();

    document.getElementById('showToast')?.addEventListener('click', () => {
        bootstrap.Toast.getOrCreateInstance(document.getElementById('demoToast')).show();
    });

    const links = [...document.querySelectorAll('.pf-nav-link[href^="#"]')];
    const sections = links
        .map(link => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);

    const observer = new IntersectionObserver(entries => {
        const visible = entries
            .filter(entry => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (!visible) return;

        links.forEach(link => {
            link.classList.toggle(
                'active',
                link.getAttribute('href') === '#' + visible.target.id
            );
        });
    }, { rootMargin: '-20% 0px -65% 0px', threshold: [0, .25, .5] });

    sections.forEach(section => observer.observe(section));
})();
</script>
</body>
</html>
