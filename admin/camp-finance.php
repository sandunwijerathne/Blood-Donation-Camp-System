<?php
/**
 * Camp Budget & Donations Page
 *
 * Two halves of the same book for one camp:
 *
 *   Donations  - what wellwishers brought in. Mostly food, soft drinks
 *                and water bottles for the donors, sometimes cash.
 *   Expenses   - what the camp cost to run.
 *
 * Goods are never folded into the cash balance. A hundred donated
 * water bottles are real value, but they are not money the treasurer
 * can spend, so they are reported on their own line.
 */

$pageTitle = 'Camp Budget & Donations';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$camps = $db->query(
    "SELECT id, title, camp_date, location, status, budget_amount
     FROM blood_camps
     ORDER BY camp_date DESC"
)->fetchAll();

// Preselect: ?camp_id=, else the nearest upcoming camp, else the latest.
$selectedCampId = (int) ($_GET['camp_id'] ?? 0);

if ($selectedCampId <= 0) {
    $next = getNextUpcomingCamp();
    $selectedCampId = $next ? (int) $next['id'] : (int) ($camps[0]['id'] ?? 0);
}

$currency           = getSetting('currency_symbol', 'Rs.');
$contribCategories  = campContributionCategories();
$expenseCategories  = campExpenseCategories();
$paymentMethods     = campPaymentMethods();
?>

<!-- ── Camp Selector ──────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-7">
                <label class="form-label fw-semibold">
                    <i class="fas fa-campground me-1 text-primary"></i> Select Camp
                </label>
                <select class="form-select form-select-lg" id="campSelect">
                    <?php if (empty($camps)): ?>
                        <option value="0">No camps created yet</option>
                    <?php else: ?>
                        <?php foreach ($camps as $camp): ?>
                            <option value="<?= (int) $camp['id'] ?>"
                                    data-date="<?= sanitize($camp['camp_date']) ?>"
                                    data-budget="<?= $camp['budget_amount'] !== null ? sanitize($camp['budget_amount']) : '' ?>"
                                    <?= (int) $camp['id'] === $selectedCampId ? 'selected' : '' ?>>
                                <?= sanitize($camp['title']) ?>
                                — <?= formatDate($camp['camp_date']) ?>
                                (<?= sanitize($camp['status']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-5">
                <div class="d-flex gap-2 justify-content-md-end">
                    <a href="<?= BASE_URL ?>/admin/camp-register.php?camp_id=<?= $selectedCampId ?>"
                       class="btn btn-outline-secondary" id="linkRegister">
                        <i class="fas fa-clipboard-list me-1"></i> Register
                    </a>
                    <div class="btn-group">
                        <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown"
                                <?= empty($camps) ? 'disabled' : '' ?>>
                            <i class="fas fa-file-export me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-export="xlsx" data-section="summary">
                                <i class="fas fa-file-excel me-2 text-success"></i> Full report (.xlsx)
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" data-export="csv" data-section="contributions">
                                <i class="fas fa-file-csv me-2 text-primary"></i> Donations (CSV)
                            </a></li>
                            <li><a class="dropdown-item" href="#" data-export="csv" data-section="expenses">
                                <i class="fas fa-file-csv me-2 text-primary"></i> Expenses (CSV)
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($camps)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state py-5 text-center">
                <i class="fas fa-campground d-block mb-3" style="font-size:2.5rem;"></i>
                <h5>No blood camps yet</h5>
                <p class="text-secondary mb-3">Create a camp first, then you can track its donations and expenses.</p>
                <a href="<?= BASE_URL ?>/admin/camps.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Create a Camp
                </a>
            </div>
        </div>
    </div>
<?php else: ?>

<!-- ── Money Summary ──────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2">
        <div class="stat-card bg-total">
            <i class="fas fa-wallet stat-icon"></i>
            <div class="stat-value" id="sumBudget">0</div>
            <div class="stat-label">Budget</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card bg-o-pos">
            <i class="fas fa-hand-holding-dollar stat-icon"></i>
            <div class="stat-value" id="sumCash">0</div>
            <div class="stat-label">Cash Donated</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card bg-messages">
            <i class="fas fa-bottle-water stat-icon"></i>
            <div class="stat-value" id="sumInKind">0</div>
            <div class="stat-label">Goods Value</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card bg-a-pos">
            <i class="fas fa-receipt stat-icon"></i>
            <div class="stat-value" id="sumPaid">0</div>
            <div class="stat-label">Spent</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card bg-eligible">
            <i class="fas fa-hourglass-half stat-icon"></i>
            <div class="stat-value" id="sumPlanned">0</div>
            <div class="stat-label">Still to Pay</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-card bg-b-pos">
            <i class="fas fa-scale-balanced stat-icon"></i>
            <div class="stat-value" id="sumBalance">0</div>
            <div class="stat-label">Balance</div>
        </div>
    </div>
</div>

<!-- ── Budget Planner ─────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                    <i class="fas fa-bullseye me-1 text-primary"></i> Planned Budget for this Camp
                </label>
                <div class="input-group">
                    <span class="input-group-text"><?= sanitize($currency) ?></span>
                    <input type="number" class="form-control" id="budgetInput" step="0.01" min="0"
                           placeholder="e.g. 25000" autocomplete="off">
                    <button class="btn btn-primary" id="btnSaveBudget">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
                <div class="form-text">Leave blank to clear the budget.</div>
            </div>
            <div class="col-md-8">
                <div class="d-flex justify-content-between mb-1">
                    <span class="fw-semibold" id="budgetBarLabel">No budget set</span>
                    <span class="text-secondary small" id="budgetBarNumbers">—</span>
                </div>
                <div class="progress" style="height:14px;">
                    <div class="progress-bar bg-danger" id="budgetBarPaid" role="progressbar" style="width:0%"></div>
                    <div class="progress-bar bg-warning" id="budgetBarPlanned" role="progressbar" style="width:0%"></div>
                </div>
                <div class="d-flex gap-3 mt-2 small text-secondary">
                    <span><i class="fas fa-square text-danger me-1"></i> Paid</span>
                    <span><i class="fas fa-square text-warning me-1"></i> Committed, not yet paid</span>
                    <span id="budgetOverWarning" class="text-danger fw-semibold d-none">
                        <i class="fas fa-triangle-exclamation me-1"></i> Over budget
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Tabs ───────────────────────────────────────────── -->
<ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDonations" type="button">
            <i class="fas fa-hand-holding-heart me-1"></i> Donations Received
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabExpenses" type="button">
            <i class="fas fa-receipt me-1"></i> Expenses
        </button>
    </li>
</ul>

<div class="tab-content">

<!-- ═══════════ DONATIONS TAB ═══════════ -->
<div class="tab-pane fade show active" id="tabDonations">

    <!-- What came in, by category -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-boxes-stacked me-2 text-primary"></i> What Came In</span>
            <span class="text-secondary small" id="contribContributors">0 contributors</span>
        </div>
        <div class="card-body">
            <div class="row g-2" id="contribBreakdown">
                <div class="col-12 text-secondary small">Nothing recorded yet.</div>
            </div>
        </div>
    </div>

    <div class="action-bar">
        <div>
            <button class="btn btn-primary" id="btnAddContribution">
                <i class="fas fa-plus me-1"></i> Record a Donation
            </button>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="filterContribCategory" style="min-width:150px;">
                <option value="">All categories</option>
                <?php foreach ($contribCategories as $cat): ?>
                    <option value="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" id="filterContribStatus" style="min-width:140px;">
                <option value="">All</option>
                <option value="Received">Received</option>
                <option value="Pledged">Pledged</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="contribTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Donated By</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ EXPENSES TAB ═══════════ -->
<div class="tab-pane fade" id="tabExpenses">

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2 text-primary"></i> Spending by Category
                </div>
                <div class="card-body">
                    <div style="position:relative;height:260px;" id="expenseChartWrap">
                        <canvas id="expenseChart"></canvas>
                    </div>
                    <div class="empty-state py-3 d-none" id="expenseChartEmpty">
                        <i class="fas fa-chart-pie d-block"></i>
                        <p class="mb-0 mt-2">No expenses recorded yet</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-list-ul me-2 text-primary"></i> Category Totals
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="expenseBreakdown">
                            <tbody>
                                <tr><td class="text-secondary">Nothing recorded yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="action-bar">
        <div>
            <button class="btn btn-primary" id="btnAddExpense">
                <i class="fas fa-plus me-1"></i> Add Expense
            </button>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="filterExpenseCategory" style="min-width:150px;">
                <option value="">All categories</option>
                <?php foreach ($expenseCategories as $cat): ?>
                    <option value="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" id="filterExpenseStatus" style="min-width:140px;">
                <option value="">All</option>
                <option value="Paid">Paid</option>
                <option value="Planned">Planned</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="expenseTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Paid To</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div><!-- /.tab-content -->

<!-- ── Contribution Modal ─────────────────────────────── -->
<div class="modal fade" id="contribModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contribModalTitle">
                    <i class="fas fa-hand-holding-heart me-2 text-primary"></i> Record a Donation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="contribForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="contribId" value="0">
                <input type="hidden" name="camp_id" id="contribCampId" value="<?= $selectedCampId ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Donated By <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contributor_name" id="contribName"
                                   required placeholder="Name of the person, shop or organisation">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">T.P. Number</label>
                            <input type="text" class="form-control" name="mobile" id="contribMobile"
                                   inputmode="numeric" placeholder="0771234567 (optional)">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">What Was Donated <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" id="contribCategory">
                                <?php foreach ($contribCategories as $cat): ?>
                                    <option value="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8 contrib-goods">
                            <label class="form-label">Item <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="item_name" id="contribItem"
                                   placeholder="e.g. Water bottles (500ml), Milk rice packets">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-3 contrib-goods">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="contribQty"
                                   step="0.01" min="0" placeholder="100">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-3 contrib-goods">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" id="contribUnit"
                                   list="unitOptions" placeholder="bottles">
                            <datalist id="unitOptions">
                                <option value="bottles"></option>
                                <option value="packets"></option>
                                <option value="boxes"></option>
                                <option value="cases"></option>
                                <option value="trays"></option>
                                <option value="kg"></option>
                                <option value="litres"></option>
                                <option value="pieces"></option>
                            </datalist>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" id="contribAmountLabel">Estimated Value</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= sanitize($currency) ?></span>
                                <input type="number" class="form-control" name="amount" id="contribAmount"
                                       step="0.01" min="0" placeholder="0.00">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-text" id="contribAmountHint">Roughly what it was worth — optional.</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="contribStatus">
                                <option value="Received">Received</option>
                                <option value="Pledged">Pledged (promised)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="received_date" id="contribDate">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="contribRemarks" rows="2"
                                      placeholder="Anything worth noting — who to thank, when it arrived..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveContribution">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Expense Modal ──────────────────────────────────── -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expenseModalTitle">
                    <i class="fas fa-receipt me-2 text-primary"></i> Add Expense
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="expenseForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="expenseId" value="0">
                <input type="hidden" name="camp_id" id="expenseCampId" value="<?= $selectedCampId ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="description" id="expenseDescription"
                                   required placeholder="e.g. Lunch packets for volunteers">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="expenseCategory">
                                <?php foreach ($expenseCategories as $cat): ?>
                                    <option value="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= sanitize($currency) ?></span>
                                <input type="number" class="form-control" name="amount" id="expenseAmount"
                                       step="0.01" min="0" required placeholder="0.00">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Paid To</label>
                            <input type="text" class="form-control" name="paid_to" id="expensePaidTo"
                                   placeholder="Shop or person">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="expense_date" id="expenseDate">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" id="expenseMethod">
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= sanitize($method) ?>"><?= sanitize($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="expenseStatus">
                                <option value="Paid">Paid</option>
                                <option value="Planned">Planned (not yet paid)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Receipt No</label>
                            <input type="text" class="form-control" name="receipt_no" id="expenseReceipt"
                                   placeholder="Optional">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="expenseRemarks" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveExpense">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Icons are defined once in functions.php so the chips, the table and
// any future report all use the same one per category.
$categoryIcons = [];
foreach ($contribCategories as $cat) {
    $categoryIcons[$cat] = contributionCategoryIcon($cat);
}
?>
<script>
$(document).ready(function () {

    const CURRENCY = <?= json_encode($currency) ?>;
    const CAT_ICONS = <?= json_encode($categoryIcons) ?>;
    let campId = <?= (int) $selectedCampId ?>;
    let expenseChart = null;

    // ── Helpers ──────────────────────────────────────
    function esc(value) {
        return $('<span>').text(value === null || value === undefined ? '' : value).html();
    }

    // Cents are only shown when there are any, so a page of round
    // rupee figures stays readable.
    function fmtNum(value) {
        const n = parseFloat(value) || 0;
        const decimals = Math.abs(n % 1) > 0.004 ? 2 : 0;
        return n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: 2 });
    }

    function fmtMoney(value) {
        return CURRENCY + ' ' + fmtNum(value);
    }

    function fmtDate(value) {
        if (!value) return '—';
        const d = new Date(value);
        if (isNaN(d)) return '—';
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function statusBadge(status) {
        const map = {
            'Received': 'completed',
            'Pledged': 'pending',
            'Paid': 'completed',
            'Planned': 'pending'
        };
        return '<span class="badge-status ' + (map[status] || 'pending') + '">' + esc(status) + '</span>';
    }

    // ── Summary cards + budget bar ───────────────────
    function renderSummary(s) {
        if (!s) return;

        $('#sumBudget').text(fmtMoney(s.budget));
        $('#sumCash').text(fmtMoney(s.cash_received));
        $('#sumInKind').text(fmtMoney(s.inkind_value));
        $('#sumPaid').text(fmtMoney(s.expenses_paid));
        $('#sumPlanned').text(fmtMoney(s.expenses_planned));
        $('#sumBalance').text(fmtMoney(s.balance));

        $('#contribContributors').text(
            s.contributors + (s.contributors === 1 ? ' contributor' : ' contributors')
            + ' · ' + s.inkind_items + (s.inkind_items === 1 ? ' item' : ' items')
        );

        const budget = parseFloat(s.budget) || 0;
        const paid = parseFloat(s.expenses_paid) || 0;
        const planned = parseFloat(s.expenses_planned) || 0;
        const cost = paid + planned;

        // With no budget set the bar still shows the paid/unpaid split
        // of what has been spent, so the panel is never dead space.
        const scale = budget > 0 ? Math.max(budget, cost) : cost;

        if (scale <= 0) {
            $('#budgetBarPaid, #budgetBarPlanned').css('width', '0%');
            $('#budgetBarLabel').text(budget > 0 ? 'Nothing spent yet' : 'No budget set');
            $('#budgetBarNumbers').text(budget > 0 ? fmtMoney(budget) + ' available' : '—');
            $('#budgetOverWarning').addClass('d-none');
            return;
        }

        $('#budgetBarPaid').css('width', (paid / scale * 100) + '%');
        $('#budgetBarPlanned').css('width', (planned / scale * 100) + '%');

        if (budget > 0) {
            $('#budgetBarLabel').text(Math.round(cost / budget * 100) + '% of budget committed');
            $('#budgetBarNumbers').text(fmtMoney(cost) + ' of ' + fmtMoney(budget));
            $('#budgetOverWarning').toggleClass('d-none', cost <= budget);
        } else {
            $('#budgetBarLabel').text('No budget set');
            $('#budgetBarNumbers').text(fmtMoney(cost) + ' spent so far');
            $('#budgetOverWarning').addClass('d-none');
        }
    }

    // ── Contributions: what came in, by category ─────
    function renderContribBreakdown(rows) {
        const $wrap = $('#contribBreakdown').empty();

        if (!rows || !rows.length) {
            $wrap.append('<div class="col-12 text-secondary small">Nothing recorded yet. '
                + 'Use <strong>Record a Donation</strong> to add the food, drinks and water people bring.</div>');
            return;
        }

        rows.forEach(function (row) {
            const icon = CAT_ICONS[row.category] || 'fa-box';
            const qty = parseFloat(row.quantity) || 0;
            const value = parseFloat(row.value) || 0;

            $wrap.append(
                '<div class="col-6 col-md-4 col-xl-3">'
                + '<div class="border rounded p-2 h-100">'
                + '<div class="d-flex align-items-center gap-2 mb-1">'
                + '<i class="fas ' + icon + ' text-primary"></i>'
                + '<span class="fw-semibold">' + esc(row.category) + '</span>'
                + '</div>'
                + '<div class="small text-secondary">'
                + row.entries + (row.entries == 1 ? ' entry' : ' entries')
                + (qty > 0 ? ' · ' + fmtNum(qty) + ' units' : '')
                + '</div>'
                + '<div class="small fw-semibold">' + (value > 0 ? fmtMoney(value) : 'value not estimated') + '</div>'
                + '</div></div>'
            );
        });
    }

    const contribTable = $('#contribTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ajax/contribution-list.php',
            type: 'POST',
            data: function (d) {
                d.csrf_token = window.CSRF_TOKEN;
                d.camp_id = campId;
                d.category = $('#filterContribCategory').val();
                d.status = $('#filterContribStatus').val();
            },
            dataSrc: function (json) {
                renderSummary(json.summary);
                renderContribBreakdown(json.by_category);
                return json.data;
            }
        },
        columns: [
            {
                data: 'contributor_name',
                render: function (data, type, row) {
                    let html = '<span class="fw-semibold">' + esc(data) + '</span>';
                    if (row.mobile) {
                        html += '<br><span class="text-secondary small">' + esc(row.mobile) + '</span>';
                    }
                    return html;
                }
            },
            {
                data: 'category',
                render: function (data) {
                    const icon = CAT_ICONS[data] || 'fa-box';
                    return '<i class="fas ' + icon + ' me-1 text-primary"></i>' + esc(data);
                }
            },
            {
                data: 'item_name',
                render: function (data, type, row) {
                    let html = esc(data || '—');
                    if (row.remarks) {
                        html += '<br><span class="text-secondary small">' + esc(row.remarks) + '</span>';
                    }
                    return html;
                }
            },
            {
                data: 'quantity',
                render: function (data, type, row) {
                    if (data === null || data === undefined || data === '') return '—';
                    return fmtNum(data) + (row.unit ? ' ' + esc(row.unit) : '');
                }
            },
            {
                data: 'amount',
                render: function (data, type, row) {
                    if (data === null || data === undefined || data === '') return '<span class="text-secondary">—</span>';
                    const label = row.category === 'Cash' ? '' : ' <span class="text-secondary small">(est.)</span>';
                    return fmtMoney(data) + label;
                }
            },
            { data: 'status', render: statusBadge },
            { data: 'received_date', render: fmtDate },
            {
                data: 'id',
                orderable: false,
                render: function (data) {
                    return '<div class="d-flex gap-1">'
                        + '<button class="btn btn-icon btn-outline-primary btn-edit-contrib" data-id="' + data + '" title="Edit">'
                        + '<i class="fas fa-edit"></i></button>'
                        + '<button class="btn btn-icon btn-outline-danger btn-delete-contrib" data-id="' + data + '" title="Delete">'
                        + '<i class="fas fa-trash-alt"></i></button>'
                        + '</div>';
                }
            }
        ],
        order: [[0, 'asc']]
    });

    $('#filterContribCategory, #filterContribStatus').on('change', function () {
        contribTable.ajax.reload();
    });

    // ── Expenses: chart + category totals ────────────
    const CHART_COLORS = [
        '#6366f1', '#ef4444', '#22c55e', '#f59e0b', '#06b6d4',
        '#a855f7', '#ec4899', '#14b8a6', '#f97316', '#64748b'
    ];

    function renderExpenseBreakdown(rows) {
        const $body = $('#expenseBreakdown tbody').empty();
        const labels = [];
        const values = [];
        const colors = [];

        if (!rows || !rows.length) {
            $body.append('<tr><td class="text-secondary">Nothing recorded yet.</td></tr>');
            $('#expenseChartWrap').addClass('d-none');
            $('#expenseChartEmpty').removeClass('d-none');
            if (expenseChart) { expenseChart.destroy(); expenseChart = null; }
            return;
        }

        $('#expenseChartWrap').removeClass('d-none');
        $('#expenseChartEmpty').addClass('d-none');

        const grandTotal = rows.reduce((sum, r) => sum + (parseFloat(r.total) || 0), 0);

        rows.forEach(function (row, index) {
            const total = parseFloat(row.total) || 0;
            const color = CHART_COLORS[index % CHART_COLORS.length];
            const share = grandTotal > 0 ? Math.round(total / grandTotal * 100) : 0;

            labels.push(row.category);
            values.push(total);
            colors.push(color);

            $body.append(
                '<tr>'
                + '<td style="width:1%"><span class="d-inline-block rounded-circle" '
                + 'style="width:10px;height:10px;background:' + color + '"></span></td>'
                + '<td>' + esc(row.category) + '</td>'
                + '<td class="text-secondary small">' + row.entries + (row.entries == 1 ? ' entry' : ' entries') + '</td>'
                + '<td class="text-end fw-semibold">' + fmtMoney(total) + '</td>'
                + '<td class="text-end text-secondary small">' + share + '%</td>'
                + '</tr>'
            );
        });

        $body.append(
            '<tr class="border-top">'
            + '<td></td><td class="fw-semibold">Total</td>'
            + '<td class="text-secondary small">paid + planned</td>'
            + '<td class="text-end fw-bold">' + fmtMoney(grandTotal) + '</td><td></td>'
            + '</tr>'
        );

        const ctx = document.getElementById('expenseChart');

        if (expenseChart) {
            expenseChart.data.labels = labels;
            expenseChart.data.datasets[0].data = values;
            expenseChart.data.datasets[0].backgroundColor = colors;
            expenseChart.update();
            return;
        }

        expenseChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': ' + fmtMoney(context.parsed);
                            }
                        }
                    }
                }
            }
        });
    }

    const expenseTable = $('#expenseTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ajax/expense-list.php',
            type: 'POST',
            data: function (d) {
                d.csrf_token = window.CSRF_TOKEN;
                d.camp_id = campId;
                d.category = $('#filterExpenseCategory').val();
                d.status = $('#filterExpenseStatus').val();
            },
            dataSrc: function (json) {
                renderSummary(json.summary);
                renderExpenseBreakdown(json.by_category);
                return json.data;
            }
        },
        columns: [
            { data: 'expense_date', render: fmtDate },
            { data: 'category', render: esc },
            {
                data: 'description',
                render: function (data, type, row) {
                    let html = '<span class="fw-semibold">' + esc(data) + '</span>';
                    if (row.receipt_no) {
                        html += '<br><span class="text-secondary small">Receipt ' + esc(row.receipt_no) + '</span>';
                    }
                    return html;
                }
            },
            {
                data: 'paid_to',
                render: function (data, type, row) {
                    let html = esc(data || '—');
                    if (row.payment_method) {
                        html += '<br><span class="text-secondary small">' + esc(row.payment_method) + '</span>';
                    }
                    return html;
                }
            },
            {
                data: 'amount',
                render: function (data) {
                    return '<span class="fw-semibold">' + fmtMoney(data) + '</span>';
                }
            },
            { data: 'status', render: statusBadge },
            {
                data: 'id',
                orderable: false,
                render: function (data) {
                    return '<div class="d-flex gap-1">'
                        + '<button class="btn btn-icon btn-outline-primary btn-edit-expense" data-id="' + data + '" title="Edit">'
                        + '<i class="fas fa-edit"></i></button>'
                        + '<button class="btn btn-icon btn-outline-danger btn-delete-expense" data-id="' + data + '" title="Delete">'
                        + '<i class="fas fa-trash-alt"></i></button>'
                        + '</div>';
                }
            }
        ],
        order: [[0, 'desc']]
    });

    $('#filterExpenseCategory, #filterExpenseStatus').on('change', function () {
        expenseTable.ajax.reload();
    });

    // DataTables lays a table out against the width it had when drawn.
    // The expenses table starts inside a hidden tab, so it needs a
    // redraw the first time that tab is opened or the columns are off.
    $('button[data-bs-target="#tabExpenses"]').on('shown.bs.tab', function () {
        expenseTable.columns.adjust();
    });

    // ── Contribution form: cash vs goods ─────────────
    // Cash needs an exact amount and no item lines; goods need an item
    // and only an optional estimate, so the form changes shape.
    function applyContributionMode() {
        const isCash = $('#contribCategory').val() === 'Cash';

        $('.contrib-goods').toggleClass('d-none', isCash);
        $('#contribAmountLabel').html(isCash
            ? 'Amount <span class="text-danger">*</span>'
            : 'Estimated Value');
        $('#contribAmountHint').text(isCash
            ? 'The cash handed over.'
            : 'Roughly what it was worth — optional.');
    }

    $('#contribCategory').on('change', applyContributionMode);

    $('#btnAddContribution').on('click', function () {
        resetForm($('#contribForm'));
        $('#contribId').val(0);
        $('#contribCampId').val(campId);
        $('#contribStatus').val('Received');
        $('#contribDate').val($('#campSelect').find(':selected').data('date') || '');
        $('#contribModalTitle').html('<i class="fas fa-hand-holding-heart me-2 text-primary"></i> Record a Donation');
        applyContributionMode();
        new bootstrap.Modal(document.getElementById('contribModal')).show();
    });

    $(document).on('click', '.btn-edit-contrib', function () {
        const row = contribTable.row($(this).closest('tr')).data();
        if (!row) return;

        resetForm($('#contribForm'));
        $('#contribId').val(row.id);
        $('#contribCampId').val(campId);
        $('#contribName').val(row.contributor_name);
        $('#contribMobile').val(row.mobile || '');
        $('#contribCategory').val(row.category);
        $('#contribItem').val(row.item_name || '');
        $('#contribQty').val(row.quantity || '');
        $('#contribUnit').val(row.unit || '');
        $('#contribAmount').val(row.amount || '');
        $('#contribStatus').val(row.status);
        $('#contribDate').val(row.received_date || '');
        $('#contribRemarks').val(row.remarks || '');
        $('#contribModalTitle').html('<i class="fas fa-hand-holding-heart me-2 text-primary"></i> Edit Donation');
        applyContributionMode();
        new bootstrap.Modal(document.getElementById('contribModal')).show();
    });

    $('#contribForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnSaveContribution');
        setButtonLoading($btn);

        $.post('<?= BASE_URL ?>/ajax/contribution-save.php', $(this).serialize(), function (res) {
            setButtonLoading($btn, false);

            if (res.success) {
                showToast(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('contribModal')).hide();
                contribTable.ajax.reload(null, false);
                renderSummary(res.data.summary);
            } else {
                if (res.data && res.data.errors) {
                    showValidationErrors($('#contribForm'), res.data.errors);
                }
                showToast(res.message, 'error');
            }
        }, 'json');
    });

    $(document).on('click', '.btn-delete-contrib', function () {
        const id = $(this).data('id');

        confirmAction('Delete this donation record?', 'This action cannot be undone.', 'Yes, delete', 'danger')
        .then(function (result) {
            if (!result.isConfirmed) return;

            $.post('<?= BASE_URL ?>/ajax/contribution-delete.php', {
                csrf_token: window.CSRF_TOKEN, id: id
            }, function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    contribTable.ajax.reload(null, false);
                    renderSummary(res.data.summary);
                } else {
                    showToast(res.message, 'error');
                }
            }, 'json');
        });
    });

    // ── Expense form ─────────────────────────────────
    $('#btnAddExpense').on('click', function () {
        resetForm($('#expenseForm'));
        $('#expenseId').val(0);
        $('#expenseCampId').val(campId);
        $('#expenseStatus').val('Paid');
        $('#expenseDate').val($('#campSelect').find(':selected').data('date') || '');
        $('#expenseModalTitle').html('<i class="fas fa-receipt me-2 text-primary"></i> Add Expense');
        new bootstrap.Modal(document.getElementById('expenseModal')).show();
    });

    $(document).on('click', '.btn-edit-expense', function () {
        const row = expenseTable.row($(this).closest('tr')).data();
        if (!row) return;

        resetForm($('#expenseForm'));
        $('#expenseId').val(row.id);
        $('#expenseCampId').val(campId);
        $('#expenseCategory').val(row.category);
        $('#expenseDescription').val(row.description);
        $('#expensePaidTo').val(row.paid_to || '');
        $('#expenseAmount').val(row.amount);
        $('#expenseMethod').val(row.payment_method);
        $('#expenseStatus').val(row.status);
        $('#expenseDate').val(row.expense_date || '');
        $('#expenseReceipt').val(row.receipt_no || '');
        $('#expenseRemarks').val(row.remarks || '');
        $('#expenseModalTitle').html('<i class="fas fa-receipt me-2 text-primary"></i> Edit Expense');
        new bootstrap.Modal(document.getElementById('expenseModal')).show();
    });

    $('#expenseForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnSaveExpense');
        setButtonLoading($btn);

        $.post('<?= BASE_URL ?>/ajax/expense-save.php', $(this).serialize(), function (res) {
            setButtonLoading($btn, false);

            if (res.success) {
                showToast(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
                expenseTable.ajax.reload(null, false);
                renderSummary(res.data.summary);
            } else {
                if (res.data && res.data.errors) {
                    showValidationErrors($('#expenseForm'), res.data.errors);
                }
                showToast(res.message, 'error');
            }
        }, 'json');
    });

    $(document).on('click', '.btn-delete-expense', function () {
        const id = $(this).data('id');

        confirmAction('Delete this expense?', 'This action cannot be undone.', 'Yes, delete', 'danger')
        .then(function (result) {
            if (!result.isConfirmed) return;

            $.post('<?= BASE_URL ?>/ajax/expense-delete.php', {
                csrf_token: window.CSRF_TOKEN, id: id
            }, function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    expenseTable.ajax.reload(null, false);
                    renderSummary(res.data.summary);
                } else {
                    showToast(res.message, 'error');
                }
            }, 'json');
        });
    });

    // ── Budget ───────────────────────────────────────
    function loadBudgetFromSelection() {
        $('#budgetInput').val($('#campSelect').find(':selected').data('budget') || '');
    }

    $('#btnSaveBudget').on('click', function () {
        const $btn = $(this);
        setButtonLoading($btn);

        $.post('<?= BASE_URL ?>/ajax/camp-budget-save.php', {
            csrf_token: window.CSRF_TOKEN,
            camp_id: campId,
            budget_amount: $('#budgetInput').val()
        }, function (res) {
            setButtonLoading($btn, false);

            if (res.success) {
                showToast(res.message, 'success');
                // Keep the option in step so switching camps and back
                // does not show the old figure.
                $('#campSelect').find(':selected').attr('data-budget', $('#budgetInput').val())
                    .data('budget', $('#budgetInput').val());
                renderSummary(res.data.summary);
            } else {
                showToast(res.message, 'error');
            }
        }, 'json');
    });

    // ── Camp switching ───────────────────────────────
    $('#campSelect').on('change', function () {
        campId = parseInt($(this).val(), 10) || 0;

        $('#contribCampId, #expenseCampId').val(campId);
        $('#linkRegister').attr('href', '<?= BASE_URL ?>/admin/camp-register.php?camp_id=' + campId);
        loadBudgetFromSelection();

        contribTable.ajax.reload();
        expenseTable.ajax.reload();

        // Keep the address bar pointing at the camp being looked at,
        // so a refresh or a shared link lands in the same place.
        const url = new URL(window.location.href);
        url.searchParams.set('camp_id', campId);
        window.history.replaceState({}, '', url);
    });

    // ── Export ───────────────────────────────────────
    $('[data-export]').on('click', function (e) {
        e.preventDefault();

        if (!campId) {
            showToast('Choose a camp first.', 'error');
            return;
        }

        const params = new URLSearchParams({
            camp_id: campId,
            format: $(this).data('export'),
            section: $(this).data('section')
        });

        window.location.href = '<?= BASE_URL ?>/ajax/camp-finance-export.php?' + params.toString();
    });

    loadBudgetFromSelection();
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
