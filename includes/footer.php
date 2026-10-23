</main>
<?php if (empty($isAdmin)): ?>
<footer class="ll-footer mt-5">
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="fw-semibold mb-1"><i class="bi bi-cpu me-1"></i>LabLend</div>
                <p class="small mb-0">Borrow college lab equipment without the paperwork: browse what's available, request it online and track your due dates.</p>
                <p class="small footer-note mt-2 mb-0">All students and records shown are fictional demo data for a DBMS mini project.</p>
            </div>
            <div class="col-6 col-md-3">
                <div class="fw-semibold mb-1">Explore</div>
                <ul class="list-unstyled small mb-0">
                    <li><a href="<?= e($root) ?>equipment.php">Equipment</a></li>
                    <li><a href="<?= e($root) ?>labs.php">Labs</a></li>
                    <li><a href="<?= e($root) ?>register.php">Student registration</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3">
                <div class="fw-semibold mb-1">Rules</div>
                <ul class="list-unstyled small mb-0">
                    <li>Up to <?= MAX_ACTIVE_ITEMS ?> items at a time</li>
                    <li>Up to <?= MAX_DAYS ?> days per loan</li>
                    <li>Late fine: <?= money(FINE_PER_DAY) ?> per item per day</li>
                    <li><a href="<?= e($root) ?>admin/login.php">Lab in-charge login</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e($root) ?>assets/js/script.js"></script>
</body>
</html>
