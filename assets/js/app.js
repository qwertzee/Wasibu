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
	});
})();