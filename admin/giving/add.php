<?php
/**
 * Giving - Record Manual Donation
 */

$page_title = 'Record Donation';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GivingService.php';

$pdo = getDbConnection();
$givingService = new GivingService($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $data = [
        'user_id' => $_POST['user_id'] ?: null,
        'fund_id' => $_POST['fund_id'] ?: null,
        'amount' => (float)$_POST['amount'],
        'payment_method' => $_POST['payment_method'],
        'donor_email' => trim($_POST['donor_email']),
        'donor_name' => trim($_POST['donor_name']) ?: null,
        'gift_aid' => isset($_POST['gift_aid']) ? 1 : 0,
        'frequency' => 'one-time',
        'status' => 'completed',
        'donated_at' => $_POST['donated_at'] ?: date('Y-m-d H:i:s'),
        'notes' => trim($_POST['notes']) ?: null,
    ];

    $result = $givingService->recordDonation($data);
    if ($result['success']) {
        header('Location: /admin/giving?recorded=1');
        exit;
    } else {
        $error = $result['error'];
    }
}

$funds = $givingService->getFunds();
?>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<div class="admin-actions-bar">
    <a href="/admin/giving" class="btn btn-outline">&larr; Back to Giving</a>
</div>

<div class="form-container">
    <div class="admin-card">
        <div class="admin-card-header"><h3>Record Donation</h3></div>
        <form method="post">
            <?= csrf_field(); ?>

            <div class="form-row-2">
                <div class="form-group">
                    <label>Amount (£) *</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label>Fund</label>
                    <select name="fund_id">
                        <option value="">General Fund</option>
                        <?php foreach ($funds as $f): ?>
                            <option value="<?= $f['id']; ?>"><?= htmlspecialchars($f['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="card">Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="datetime-local" name="donated_at" value="<?= date('Y-m-d\TH:i'); ?>">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label>Donor Name</label>
                    <input type="text" name="donor_name">
                </div>
                <div class="form-group">
                    <label>Donor Email *</label>
                    <input type="email" name="donor_email" required>
                </div>
            </div>

            <div class="form-group">
                <label>Link to Person (optional)</label>
                <input type="text" id="person-search" placeholder="Search by name or email..." autocomplete="off">
                <input type="hidden" name="user_id" id="user-id">
                <div id="search-results" class="search-results"></div>
                <div id="selected-person" class="selected-person"></div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="gift_aid" value="1">
                    <span>Gift Aid eligible</span>
                </label>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Record Donation</button>
            </div>
        </form>
    </div>
</div>

<style>
.form-container { max-width: 600px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-actions { margin-top: 1.5rem; }
.checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.checkbox-label input { width: auto; }
.form-group { position: relative; margin-bottom: 1rem; }
.search-results { position: absolute; z-index: 10; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius); max-height: 200px; overflow-y: auto; width: 100%; display: none; }
.search-results.active { display: block; }
.search-result-item { padding: 0.5rem 0.75rem; cursor: pointer; }
.search-result-item:hover { background: var(--color-surface-hover); }
.selected-person { margin-top: 0.5rem; padding: 0.5rem; background: var(--color-primary); color: white; border-radius: var(--radius); display: none; }
.selected-person.active { display: flex; justify-content: space-between; align-items: center; }
</style>

<script <?= csp_nonce(); ?>>
const searchInput = document.getElementById('person-search');
const resultsDiv = document.getElementById('search-results');
const selectedDiv = document.getElementById('selected-person');
const userIdInput = document.getElementById('user-id');
const nameInput = document.querySelector('[name="donor_name"]');
const emailInput = document.querySelector('[name="donor_email"]');
let searchTimeout;

searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) { resultsDiv.classList.remove('active'); return; }

    searchTimeout = setTimeout(async () => {
        const res = await fetch('/admin/api/people.php?action=search&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (data.success && data.data.length) {
            resultsDiv.innerHTML = data.data.map(p => `
                <div class="search-result-item" data-id="${p.id}" data-name="${p.first_name} ${p.last_name}" data-email="${p.email}">
                    ${p.first_name} ${p.last_name} <span class="text-muted">${p.email}</span>
                </div>
            `).join('');
            resultsDiv.classList.add('active');
        } else {
            resultsDiv.classList.remove('active');
        }
    }, 300);
});

resultsDiv?.addEventListener('click', function(e) {
    const item = e.target.closest('.search-result-item');
    if (item) {
        userIdInput.value = item.dataset.id;
        nameInput.value = item.dataset.name;
        emailInput.value = item.dataset.email;
        selectedDiv.innerHTML = item.dataset.name + ' <button type="button" onclick="clearPerson()">&times;</button>';
        selectedDiv.classList.add('active');
        searchInput.value = '';
        resultsDiv.classList.remove('active');
    }
});

function clearPerson() {
    userIdInput.value = '';
    selectedDiv.classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
