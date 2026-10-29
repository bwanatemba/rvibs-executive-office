<?php
$page_title = 'Partners';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $data = [$_POST['name'], $_POST['type'] ?? 'partner', $_POST['contact_person'] ?? null,
            $_POST['email'] ?? null, $_POST['phone'] ?? null, $_POST['relationship_summary'] ?? null,
            $_POST['stewardship_notes'] ?? null, $_POST['renewal_opportunities'] ?? null];
        if ($action === 'create') {
            $db->prepare('INSERT INTO partners (name, type, contact_person, email, phone, relationship_summary, stewardship_notes, renewal_opportunities) VALUES (?,?,?,?,?,?,?,?)')->execute($data);
            $id = $db->lastInsertId();
            log_action('create', 'partner', $id);
        } else {
            $id = (int)$_POST['id'];
            $data[] = $id;
            $db->prepare('UPDATE partners SET name=?, type=?, contact_person=?, email=?, phone=?, relationship_summary=?, stewardship_notes=?, renewal_opportunities=? WHERE id=?')->execute($data);
            log_action('update', 'partner', $id);
        }
        $_SESSION['partner_save_success'] = $action === 'create'
            ? 'New partner saved successfully.'
            : 'Partner details updated successfully.';
        redirect('partners.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM partners WHERE id=?')->execute([$id]);
        log_action('delete', 'partner', $id);
        toast_redirect('Partner deleted', 'partners.php');
    }
}

$partners = $db->query('SELECT * FROM partners ORDER BY name')->fetchAll();
$csrf = csrf_token();
$typeLabels = ['grant_body' => 'Grant Body', 'donor' => 'Donor', 'partner' => 'Partner', 'institution' => 'Institution'];
$saveSuccess = $_SESSION['partner_save_success'] ?? '';
unset($_SESSION['partner_save_success']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Partners</h2>
        <p class="section-sub">Partner relationships, stewardship and renewal opportunities</p>
    </div>
    <button class="btn btn-primary" onclick="addPartner()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Partner
    </button>
</div>

<div class="grid grid-3">
    <?php if (empty($partners)): ?>
        <div class="card"><div class="card-body">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <h3>No partners yet</h3>
                <p>Track partner relationships, stewardship activities and renewal opportunities.</p>
            </div>
        </div></div>
    <?php else: ?>
        <?php foreach ($partners as $p): ?>
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="fw-600 text-sm" style="font-family:var(--font-heading)"><?= e($p['name']) ?></h3>
                        <span class="badge badge-primary"><?= e($typeLabels[$p['type']] ?? 'Partner') ?></span>
                    </div>
                    <?php if ($p['contact_person']): ?><div class="text-xs text-2 mb-1"><strong>Contact:</strong> <?= e($p['contact_person']) ?></div><?php endif; ?>
                    <?php if ($p['email']): ?><div class="text-xs text-2 mb-1"><strong>Email:</strong> <a href="mailto:<?= e($p['email']) ?>" class="cell-link"><?= e($p['email']) ?></a></div><?php endif; ?>
                    <?php if ($p['phone']): ?><div class="text-xs text-2 mb-1"><strong>Phone:</strong> <?= e($p['phone']) ?></div><?php endif; ?>
                    <?php if ($p['relationship_summary']): ?><div class="text-xs text-2 mb-2"><strong>Relationship:</strong> <?= e($p['relationship_summary']) ?></div><?php endif; ?>
                    <?php if ($p['stewardship_notes']): ?><div class="text-xs text-2 mb-2"><strong>Stewardship:</strong> <?= e($p['stewardship_notes']) ?></div><?php endif; ?>
                    <?php if ($p['renewal_opportunities']): ?><div class="alert alert-info" style="padding:0.4rem 0.625rem; font-size:0.75rem;"><strong>Renewal:</strong> <?= e($p['renewal_opportunities']) ?></div><?php endif; ?>
                    <div class="flex gap-2 mt-2">
                        <button class="btn btn-outline btn-sm" onclick="editPartner(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">Edit</button>
                        <button type="button" class="btn-ghost btn-sm" data-partner-id="<?= (int)$p['id'] ?>" data-partner-name="<?= e($p['name']) ?>" onclick="confirmDeletePartner(this)">Delete</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="modalPartner">
    <div class="modal modal-lg">
        <div class="modal-header"><h2 class="modal-title" id="partnerTitle">Add Partner</h2><button class="modal-close" onclick="closeModal('modalPartner')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="partnerAction" value="create">
            <input type="hidden" name="id" id="partnerId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Name <span class="req">*</span></label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control">
                        <option value="grant_body">Grant Body</option><option value="donor">Donor</option>
                        <option value="partner">Partner</option><option value="institution">Institution</option>
                    </select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                <div class="form-group"><label class="form-label">Relationship Summary</label><textarea name="relationship_summary" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Stewardship Notes</label><textarea name="stewardship_notes" class="form-control" rows="2" placeholder="How do we maintain this relationship?"></textarea></div>
                <div class="form-group"><label class="form-label">Renewal Opportunities</label><textarea name="renewal_opportunities" class="form-control" rows="2" placeholder="What could we renew or expand?"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalPartner')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalDeletePartner">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deletePartnerTitle" aria-describedby="deletePartnerMessage">
        <div class="modal-header">
            <h2 class="modal-title" id="deletePartnerTitle">Delete partner?</h2>
            <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('modalDeletePartner')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deletePartnerId">
            <div class="modal-body">
                <p id="deletePartnerMessage">Are you sure you want to delete <strong id="deletePartnerName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="deletePartnerCancel" onclick="closeModal('modalDeletePartner')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Partner</button>
            </div>
        </form>
    </div>
</div>

<?php if ($saveSuccess): ?>
<div class="modal-overlay" id="modalPartnerSuccess">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="partnerSuccessTitle" aria-describedby="partnerSuccessMessage">
        <div class="modal-header">
            <h2 class="modal-title" id="partnerSuccessTitle">Partner saved</h2>
            <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('modalPartnerSuccess')">&times;</button>
        </div>
        <div class="modal-body"><p id="partnerSuccessMessage" role="status"><?= e($saveSuccess) ?></p></div>
        <div class="modal-footer"><button type="button" class="btn btn-primary" id="partnerSuccessDone" onclick="closeModal('modalPartnerSuccess')">Done</button></div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if ($saveSuccess): ?>
document.addEventListener('DOMContentLoaded', function () {
    openModal('modalPartnerSuccess');
    document.getElementById('partnerSuccessDone').focus();
});
<?php endif; ?>
function addPartner() {
    document.querySelector('#modalPartner form').reset();
    document.getElementById('partnerTitle').textContent = 'Add Partner';
    document.getElementById('partnerAction').value = 'create';
    document.getElementById('partnerId').value = '';
    openModal('modalPartner');
}
function confirmDeletePartner(button) {
    document.getElementById('deletePartnerId').value = button.dataset.partnerId;
    document.getElementById('deletePartnerName').textContent = button.dataset.partnerName;
    openModal('modalDeletePartner');
    setTimeout(function () { document.getElementById('deletePartnerCancel').focus(); }, 150);
}
function editPartner(data) {
    document.getElementById('partnerTitle').textContent = 'Edit Partner';
    document.getElementById('partnerAction').value = 'update';
    document.getElementById('partnerId').value = data.id;
    const form = document.querySelector('#modalPartner form');
    form.querySelector('[name="name"]').value = data.name || '';
    form.querySelector('[name="type"]').value = data.type || 'partner';
    form.querySelector('[name="contact_person"]').value = data.contact_person || '';
    form.querySelector('[name="email"]').value = data.email || '';
    form.querySelector('[name="phone"]').value = data.phone || '';
    form.querySelector('[name="relationship_summary"]').value = data.relationship_summary || '';
    form.querySelector('[name="stewardship_notes"]').value = data.stewardship_notes || '';
    form.querySelector('[name="renewal_opportunities"]').value = data.renewal_opportunities || '';
    openModal('modalPartner');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
