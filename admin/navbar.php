<aside class="admin-sidebar" aria-label="Navigasi admin">
    <div class="admin-sidebar-top">
        <a href="dashboard.php" class="admin-sidebar-brand" aria-label="WASIBU Beranda">
            <img src="../assets/images/logo/wasibu.png" class="admin-navbar-logo" alt="WASIBU">
        </a>
        <button
            type="button"
            class="admin-sidebar-toggle"
            data-admin-sidebar-toggle
            aria-label="Ciutkan sidebar"
            aria-expanded="true"
            aria-controls="admin-sidebar-menu"
            title="Ciutkan sidebar"
        >
            <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
        </button>
    </div>

    <nav id="admin-sidebar-menu" class="admin-sidebar-menu" aria-label="Menu utama admin">
        <a href="dashboard.php" data-admin-route="dashboard" data-tooltip="Beranda" title="Beranda">
            <i class="bi bi-speedometer2" aria-hidden="true"></i>
            <span data-sidebar-label>Beranda</span>
        </a>
        <a href="products.php" data-admin-route="products" data-tooltip="Menu WASIBU" title="Menu WASIBU">
            <i class="bi bi-grid" aria-hidden="true"></i>
            <span data-sidebar-label>Menu WASIBU</span>
        </a>
        <a href="orders.php" data-admin-route="orders" data-tooltip="Pesanan" title="Pesanan">
            <i class="bi bi-bag-check" aria-hidden="true"></i>
            <span data-sidebar-label>Pesanan</span>
        </a>
        <a href="reports.php" data-admin-route="reports" data-tooltip="Laporan" title="Laporan">
            <i class="bi bi-graph-up" aria-hidden="true"></i>
            <span data-sidebar-label>Laporan</span>
        </a>
        <a href="finance-report.php" data-admin-route="finance-report" data-tooltip="Laporan Keuangan" title="Laporan Keuangan">
            <i class="bi bi-cash-coin" aria-hidden="true"></i>
            <span data-sidebar-label>Laporan Keuangan</span>
        </a>
        <a href="categories.php" data-admin-route="categories" data-tooltip="Kategori" title="Kategori">
            <i class="bi bi-tags" aria-hidden="true"></i>
            <span data-sidebar-label>Kategori</span>
        </a>
        <a href="promos.php" data-admin-route="promos" data-tooltip="Promo" title="Promo">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            <span data-sidebar-label>Promo</span>
        </a>
    </nav>

    <div class="admin-sidebar-footer">
        <button type="button" class="admin-sidebar-logout" data-admin-logout-open data-tooltip="Logout" title="Logout">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span data-sidebar-label>Logout</span>
        </button>
    </div>
</aside>

<dialog class="admin-logout-dialog" aria-labelledby="admin-logout-title">
    <div class="admin-logout-card">
        <h2 id="admin-logout-title">Keluar dari WASIBU?</h2>
        <p>Apakah Anda yakin ingin keluar dari akun admin?</p>
        <div class="admin-logout-actions">
            <button type="button" class="btn admin-logout-cancel" data-admin-logout-cancel>Batal</button>
            <a href="logout.php" class="btn admin-logout-confirm">Keluar</a>
        </div>
    </div>
</dialog>

<script>
    (() => {
        const body = document.body;
        const toggle = document.querySelector('[data-admin-sidebar-toggle]');
        const sidebar = document.querySelector('.admin-sidebar');
        const menuLinks = [...document.querySelectorAll('.admin-sidebar-menu a')];
        const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
        const routeByPage = {
            'dashboard.php': 'dashboard',
            'products.php': 'products',
            'product-create.php': 'products',
            'product-edit.php': 'products',
            'product-delete.php': 'products',
            'orders.php': 'orders',
            'order-detail.php': 'orders',
            'reports.php': 'reports',
            'print-report.php': 'reports',
            'finance-report.php': 'finance-report',
            'categories.php': 'categories',
            'promos.php': 'promos'
        };
        let collapsed = false;

        try {
            const savedPreference = localStorage.getItem('wasibu-admin-sidebar-collapsed');
            collapsed = savedPreference === null
                ? window.matchMedia('(max-width: 767.98px)').matches
                : savedPreference === 'true';
        } catch (error) {
            collapsed = window.matchMedia('(max-width: 767.98px)').matches;
        }

        const setCollapsed = (nextValue, persist) => {
            collapsed = nextValue;
            body.classList.toggle('admin-sidebar-collapsed', collapsed);
            if (toggle) {
                toggle.setAttribute('aria-expanded', String(!collapsed));
                toggle.setAttribute('aria-label', collapsed ? 'Buka sidebar' : 'Ciutkan sidebar');
                toggle.title = collapsed ? 'Buka sidebar' : 'Ciutkan sidebar';
            }
            if (persist) {
                try {
                    localStorage.setItem('wasibu-admin-sidebar-collapsed', String(collapsed));
                } catch (error) {
                    // The sidebar remains usable when browser storage is unavailable.
                }
            }
        };

        setCollapsed(collapsed, false);
        toggle?.addEventListener('click', () => setCollapsed(!collapsed, true));

        const activeRoute = routeByPage[currentPage];
        menuLinks.forEach((link) => {
            if (link.dataset.adminRoute === activeRoute) {
                link.classList.add('is-active');
                link.setAttribute('aria-current', 'page');
            }
        });

        const logoutDialog = document.querySelector('.admin-logout-dialog');
        if (!logoutDialog) return;

        document.querySelectorAll('[data-admin-logout-open]').forEach((button) => {
            button.addEventListener('click', () => logoutDialog.showModal());
        });

        logoutDialog.querySelector('[data-admin-logout-cancel]').addEventListener('click', () => {
            logoutDialog.close();
        });

        logoutDialog.addEventListener('click', (event) => {
            if (event.target === logoutDialog) logoutDialog.close();
        });
    })();
</script>
