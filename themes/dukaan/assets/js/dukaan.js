/**
 * Dukaan — chhoti vanilla JS: mobile menu, search toggle,
 * announcement bar band karna. Koi jQuery nahi.
 */
(function () {
	'use strict';

	function onReady(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	onReady(function () {
		/* --- Mobile menu --- */
		var menuBtn = document.getElementById('dukaan-menu-toggle');
		var mobileNav = document.getElementById('dukaan-mobile-nav');
		if (menuBtn && mobileNav) {
			menuBtn.addEventListener('click', function () {
				var open = mobileNav.hasAttribute('hidden');
				if (open) {
					mobileNav.removeAttribute('hidden');
				} else {
					mobileNav.setAttribute('hidden', '');
				}
				menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		}

		/* --- Search toggle --- */
		var searchBtn = document.getElementById('dukaan-search-toggle');
		var searchBar = document.getElementById('dukaan-searchbar');
		if (searchBtn && searchBar) {
			searchBtn.addEventListener('click', function () {
				var open = searchBar.hasAttribute('hidden');
				if (open) {
					searchBar.removeAttribute('hidden');
					var field = document.getElementById('dukaan-search-field');
					if (field) {
						field.focus();
					}
				} else {
					searchBar.setAttribute('hidden', '');
				}
				searchBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		}

		/* --- Announcement bar close (is session ke liye yaad) --- */
		var bar = document.getElementById('dukaan-announcement');
		var closeBtn = document.getElementById('dukaan-announcement-close');
		if (bar) {
			try {
				if (window.sessionStorage.getItem('dukaan-announcement-closed') === '1') {
					bar.style.display = 'none';
				}
			} catch (e) {
				/* sessionStorage na mile to koi baat nahi */
			}
			if (closeBtn) {
				closeBtn.addEventListener('click', function () {
					bar.style.display = 'none';
					try {
						window.sessionStorage.setItem('dukaan-announcement-closed', '1');
					} catch (e) {
						/* ignore */
					}
				});
			}
		}
	});
})();
