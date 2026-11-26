(() => {
	const activeRouteByPage = {
		'index.php': 'home',
		'daftar-menu.php': 'menu',
		'menu.php': 'menu',
		'product.php': 'menu',
		'cart.php': 'cart',
		'checkout.php': 'cart',
		'payment.php': 'cart',
		'order-success.php': 'cart',
		'pesanan-saya.php': 'orders'
	};
	const currentPage = window.location.pathname.split('/').pop() || 'index.php';

	document.querySelectorAll('.customer-navbar').forEach((navbar) => {
		const navLinks = [...navbar.querySelectorAll('[data-customer-nav]')];
		const scrollShadow = () => navbar.classList.toggle('is-scrolled', window.scrollY > 6);
		const menuToggle = navbar.querySelector('[data-customer-menu-open]');
		const mobileSidebar = navbar.querySelector('.customer-mobile-sidebar');
		const menuCloseButtons = [...navbar.querySelectorAll('[data-customer-menu-close]')];
		const mobileMenuLinks = [...navbar.querySelectorAll('.customer-mobile-nav-link')];
		const mobileNavQuery = window.matchMedia('(max-width: 991.98px)');

		const setMobileMenuOpen = (isOpen, restoreFocus = false) => {
			if (!menuToggle || !mobileSidebar) return;
			navbar.classList.toggle('customer-menu-open', isOpen);
			menuToggle.setAttribute('aria-expanded', String(isOpen));
			mobileSidebar.setAttribute('aria-hidden', String(!isOpen));
			mobileSidebar.inert = !isOpen;
			document.body.classList.toggle('customer-sidebar-open', isOpen);
			if (isOpen) {
				navbar.querySelector('.customer-mobile-menu-close')?.focus();
			} else if (restoreFocus) {
				menuToggle.focus();
			}
		};

		const updateActiveLink = () => {
			const hashRoute = currentPage === 'index.php'
				? { '#tentang': 'about', '#kontak': 'contact' }[window.location.hash]
				: null;
			const currentRoute = hashRoute || activeRouteByPage[currentPage];

			navLinks.forEach((link) => {
				const isActive = link.dataset.customerNav === currentRoute;
				link.classList.toggle('is-active', isActive);
				if (isActive) {
					link.setAttribute('aria-current', 'page');
				} else {
					link.removeAttribute('aria-current');
				}
			});
		};

		updateActiveLink();
		window.addEventListener('hashchange', updateActiveLink);
		scrollShadow();
		window.addEventListener('scroll', scrollShadow, { passive: true });

		menuToggle?.addEventListener('click', () => setMobileMenuOpen(true));
		menuCloseButtons.forEach((button) => {
			button.addEventListener('click', () => setMobileMenuOpen(false, true));
		});
		mobileMenuLinks.forEach((link) => {
			link.addEventListener('click', () => setMobileMenuOpen(false));
		});
		const closeMenuOnDesktop = () => {
			if (!mobileNavQuery.matches) {
				setMobileMenuOpen(false);
			}
		};
		if (mobileNavQuery.addEventListener) {
			mobileNavQuery.addEventListener('change', closeMenuOnDesktop);
		} else {
			mobileNavQuery.addListener(closeMenuOnDesktop);
		}
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && navbar.classList.contains('customer-menu-open')) {
				setMobileMenuOpen(false, true);
			}
		});
	});
})();