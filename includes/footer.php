        </main>
    </div>
</div>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php render_session_toast(); ?>
<?php if (!empty($page_js)): ?>
<script><?= $page_js ?></script>
<?php endif; ?>
</body>
</html>
