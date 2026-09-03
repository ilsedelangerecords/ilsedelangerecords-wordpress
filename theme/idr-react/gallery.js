/* Lightbox voor de galerij: klik = grote weergave, Esc/klik = sluiten. */
(function () {
	var overlay = document.createElement('div');
	overlay.className = 'idr-lightbox';
	overlay.innerHTML = '<figure><img alt=""><figcaption></figcaption></figure>';
	document.body.appendChild(overlay);
	var img = overlay.querySelector('img');
	var caption = overlay.querySelector('figcaption');

	function close() { overlay.classList.remove('open'); img.src = ''; }
	overlay.addEventListener('click', close);
	document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });

	document.querySelectorAll('.gallery a').forEach(function (a) {
		a.addEventListener('click', function (ev) {
			ev.preventDefault();
			img.src = a.getAttribute('href');
			caption.textContent = a.dataset.caption || '';
			overlay.classList.add('open');
		});
	});
})();
