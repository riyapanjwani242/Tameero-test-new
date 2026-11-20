/**
 * TAMEERO YouTube — facade JS (vanilla, koi dependency nahi).
 * Kaam sirf do: (1) thumbnail fallback — maxres na mile (404 ya
 * 200+120px placeholder) to hqdefault; (2) play click par
 * youtube-nocookie iframe banana. Iframe is se pehle DOM mein
 * hota hi nahi. Idempotent — dobara chalane se kuch nahi bigarta.
 */
(function () {
	'use strict';

	function fixThumb(img) {
		if (!img || img.dataset.tyFixed) { return; }
		// 200 OK ke sath aya 120x90 placeholder: onerror fire nahi
		// hota, is liye naturalWidth check lazmi hai (faisla 2026-11-20).
		var bad = img.complete && img.naturalWidth > 0 && img.naturalWidth <= 120;
		if (bad) {
			img.dataset.tyFixed = '1';
			img.src = img.src.replace('maxresdefault.jpg', 'hqdefault.jpg');
		}
	}

	function thumbFailed(img) {
		if (!img) { return; }
		if (!img.dataset.tyFixed) {
			img.dataset.tyFixed = '1';
			img.src = img.src.replace('maxresdefault.jpg', 'hqdefault.jpg');
		} else {
			img.classList.add('tameero-youtube__thumb--missing');
		}
	}

	function play(wrap) {
		var facade = wrap.querySelector('.tameero-youtube__facade');
		if (!facade || facade.dataset.tyPlayed) { return; }
		facade.dataset.tyPlayed = '1';
		var id = wrap.getAttribute('data-video-id') || '';
		if (!/^[A-Za-z0-9_-]{11}$/.test(id)) { return; }
		var start = parseInt(wrap.getAttribute('data-start') || '0', 10) || 0;
		var src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0' + (start > 0 ? '&start=' + start : '');
		var iframe = document.createElement('iframe');
		iframe.className = 'tameero-youtube__player';
		iframe.src = src;
		iframe.title = 'YouTube video player';
		iframe.loading = 'lazy';
		iframe.referrerPolicy = 'strict-origin-when-cross-origin';
		iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		iframe.allowFullscreen = true;
		facade.innerHTML = '';
		facade.appendChild(iframe);
	}

	function init() {
		var wraps = document.querySelectorAll('.tameero-youtube[data-video-id]');
		Array.prototype.forEach.call(wraps, function (wrap) {
			if (wrap.dataset.tyInit) { return; }
			wrap.dataset.tyInit = '1';
			var img = wrap.querySelector('.tameero-youtube__thumb');
			if (img) {
				img.addEventListener('load', function () { fixThumb(img); });
				img.addEventListener('error', function () { thumbFailed(img); });
				fixThumb(img);
			}
			var btn = wrap.querySelector('.tameero-youtube__play');
			if (btn) {
				btn.addEventListener('click', function () { play(wrap); });
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
