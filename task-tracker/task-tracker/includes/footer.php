<?php
/**
 * Page footer. Optional variable: $withCharts = true  -> loads Chart.js
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
$layout     = $layout ?? 'app';
$withCharts = $withCharts ?? false;
?>
<?php if ($layout === 'app'): ?>
        </main>
        <footer class="app-footer">
            &copy; <?= date('Y') ?> TaskFlow &middot; Built with PHP, MySQL &amp; Bootstrap 5
        </footer>
    </div><!-- /.app-main -->
</div><!-- /.app-shell -->
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($withCharts): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
