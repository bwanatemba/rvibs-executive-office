<?php
$page_title = 'Institutional Readiness';
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $institution = $_POST['institution'];
        $legal = $_POST['legal_route'] ?? null;
        $evidence = $_POST['supporting_evidence'] ?? null;
        $status = $_POST['status'] ?? 'in_progress';
        $notes = $_POST['notes'] ?? null;
        if ($action === 'create') {
            $stmt = $db->prepare('INSERT INTO funding_readiness (institution, legal_route, supporting_evidence, status, notes, updated_by) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$institution, $legal, $evidence, $status, $notes, $user['id']]);
            $id = $db->lastInsertId();
            log_action('create', 'readiness', $id, null, $_POST);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare('UPDATE funding_readiness SET institution=?, legal_route=?, supporting_evidence=?, status=?, notes=?, updated_by=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$institution, $legal, $evidence, $status, $notes, $user['id'], $id]);
            log_action('update', 'readiness', $id);
        }
        toast_redirect('Readiness record saved', 'funding-readiness.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM funding_readiness WHERE id=?')->execute([$id]);
        log_action('delete', 'readiness', $id);
        toast_redirect('Record deleted', 'funding-readiness.php');
    }
}

$readiness = $db->query('SELECT * FROM funding_readiness ORDER BY institution')->fetchAll();
$csrf = csrf_token();
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Institutional Readiness</h2>
        <p class="section-sub">Establish eligibility, legal applicant routes and supporting evidence for each institution</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalReadiness')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Readiness Record
    </button>
</div>

<div class="grid grid-3">
    <?php if (empty($readiness)): ?>
        <div class="card"><div class="card-body">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <h3>No readiness records</h3>
                <p>Define the legal applicant routes and supporting evidence for each institution (RVIBS, ATS, ATS Kindergarten).</p>
            </div>
        </div></div>
    <?php else: ?>
        <?php foreach ($readiness as $r): ?>
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="fw-600 text-sm" style="font-family:var(--font-heading)"><?= e($r['institution']) ?></h3>
                        <span class="badge <?= status_badge($r['status']) ?>"><?= status_label($r['status']) ?></span>
                    </div>
                    <div class="text-xs text-2 mb-2"><strong>Legal Route:</strong> <?= e($r['legal_route'] ?? 'Not specified') ?></div>
                    <div class="text-xs text-2 mb-2"><strong>Supporting Evidence:</strong></div>
                    <p class="text-xs text-2 mb-3"><?= e($r['supporting_evidence'] ?? '—') ?></p>
                    <?php if ($r['notes']): ?><div class="text-xs text-3 mb-3"><strong>Notes:</strong> <?= e($r['notes']) ?></div><?php endif; ?>
                    <div class="flex gap-2">
                        <button class="btn btn-outline btn-sm" onclick="editReadiness(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">Edit</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn-ghost btn-sm">Delete</button>
                        </form>
                    </div>
                    <div class="text-xs text-3 mt-2">Updated <?= e(fmt_date($r['updated_at'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="modalReadiness">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title" id="readinessTitle">Add Readiness Record</h2>
            <button class="modal-close" onclick="closeModal('modalReadiness')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="readinessAction" value="create">
            <input type="hidden" name="id" id="readinessId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Institution <span class="req">*</span></label>
                    <input type="text" name="institution" class="form-control" required placeholder="e.g. RVIBS, ATS, ATS Kindergarten">
                </div>
                <div class="form-group">
                    <label class="form-label">Legal Applicant Route</label>
                    <input type="text" name="legal_route" class="form-control" placeholder="e.g. Registered trust, subsidiary entity">
                </div>
                <div class="form-group">
                    <label class="form-label">Supporting Evidence</label>
                    <textarea name="supporting_evidence" class="form-control" rows="3" placeholder="Registration certificates, governance documents, audited accounts, etc."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="not_ready">Not Ready</option>
                        <option value="in_progress" selected>In Progress</option>
                        <option value="ready">Ready</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalReadiness')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function editReadiness(data) {
    document.getElementById('readinessTitle').textContent = 'Edit Readiness';
    document.getElementById('readinessAction').value = 'update';
    document.getElementById('readinessId').value = data.id;
    const form = document.querySelector('#modalReadiness form');
    form.querySelector('[name="institution"]').value = data.institution || '';
    form.querySelector('[name="legal_route"]').value = data.legal_route || '';
    form.querySelector('[name="supporting_evidence"]').value = data.supporting_evidence || '';
    form.querySelector('[name="status"]').value = data.status || 'in_progress';
    form.querySelector('[name="notes"]').value = data.notes || '';
    openModal('modalReadiness');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
