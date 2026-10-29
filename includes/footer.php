        </main>
    </div>
</div>
<div class="modal-overlay" id="modalSaveNotification">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="saveNotificationTitle" aria-describedby="saveNotificationMessage">
        <div class="modal-header">
            <h2 class="modal-title" id="saveNotificationTitle">Success</h2>
            <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('modalSaveNotification')">&times;</button>
        </div>
        <div class="modal-body"><p id="saveNotificationMessage" role="status"></p></div>
        <div class="modal-footer"><button type="button" class="btn btn-primary" id="saveNotificationDone" onclick="closeModal('modalSaveNotification')">Done</button></div>
    </div>
</div>
<div class="modal-overlay" id="modalConfirmDelete">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirmDeleteTitle" aria-describedby="confirmDeleteMessage">
        <div class="modal-header">
            <h2 class="modal-title" id="confirmDeleteTitle">Confirm deletion</h2>
            <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('modalConfirmDelete')">&times;</button>
        </div>
        <div class="modal-body"><p id="confirmDeleteMessage"></p></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" id="confirmDeleteCancel" onclick="closeModal('modalConfirmDelete')">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteSubmit">Delete</button>
        </div>
    </div>
</div>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php render_session_toast(); ?>
<?php if (!empty($page_js)): ?>
<script><?= $page_js ?></script>
<?php endif; ?>
</body>
</html>
