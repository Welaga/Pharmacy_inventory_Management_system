<?php if (isLoggedIn()): ?>
  </div><!-- /#content -->
</div><!-- /#main -->
<?php endif ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js (for dashboard) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
// Sidebar toggle (mobile)
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar       = document.getElementById('sidebar');
if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
        if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
            sidebar.classList.remove('open');
        }
    });
}

// Auto-dismiss flash alerts after 5s
document.querySelectorAll('.flash-alert').forEach(el => {
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert?.close();
    }, 5000);
});

// Confirm delete prompts
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});

// CSRF token helper for fetch()
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;
function apiFetch(url, opts = {}) {
    opts.headers = { ...(opts.headers || {}), 'X-CSRF-Token': CSRF_TOKEN, 'Content-Type': 'application/json' };
    return fetch(url, opts).then(r => r.json());
}
</script>
</body>
</html>
