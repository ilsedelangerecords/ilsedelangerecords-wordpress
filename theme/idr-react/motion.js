/* Scroll-reveals: secties onder de vouw glijden rustig in. Respecteert reduced-motion. */
(function () {
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
	var targets = document.querySelectorAll('.section, .record, .rows, .section-rail');
	if (!('IntersectionObserver' in window)) { return; }
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (entry.isIntersecting) {
				entry.target.classList.add('in');
				io.unobserve(entry.target);
			}
		});
	}, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
	targets.forEach(function (el) {
		var rect = el.getBoundingClientRect();
		if (rect.top > window.innerHeight * 0.85) {
			el.classList.add('reveal');
			io.observe(el);
		}
	});
})();
