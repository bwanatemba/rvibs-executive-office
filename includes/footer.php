        </main>
    </div>
</div>
<script src="assets/js/app.js?v=<?= time() ?>"></script>
<?php render_session_toast(); ?>
<?php if (!empty($page_js)): ?>
<script><?= $page_js ?></script>
<?php endif; ?>
</body>
</html>
