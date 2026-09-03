/* Geluid aan/uit voor de hero-achtergrondvideo (YouTube iframe API via postMessage). */
(function () {
	var iframe = document.getElementById('idr-hero-video');
	var button = document.getElementById('idr-sound');
	if (!iframe || !button) { return; }

	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		iframe.parentNode.removeChild(iframe);
		button.parentNode.removeChild(button);
		return;
	}

	function command(func, args) {
		iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: func, args: args || [] }), '*');
	}

	var soundOn = false;
	button.addEventListener('click', function () {
		soundOn = !soundOn;
		if (soundOn) {
			command('unMute');
			command('setVolume', [55]);
			button.textContent = 'Geluid uit';
		} else {
			command('mute');
			button.textContent = 'Geluid aan';
		}
		button.setAttribute('aria-pressed', String(soundOn));
	});
})();
