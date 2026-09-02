/* Releases/songs-browser · React-island zonder buildstap (React UMD + createElement). */
(function () {
	var e = React.createElement;

	function useIndex() {
		var state = React.useState(null);
		React.useEffect(function () {
			fetch(IDR_BROWSE.endpoint)
				.then(function (r) { return r.json(); })
				.then(function (d) { state[1](d); })
				.catch(function () { state[1]({ error: true }); });
		}, []);
		return state[0];
	}

	function uniq(items, key) {
		var seen = {};
		items.forEach(function (it) { if (it[key]) seen[it[key]] = true; });
		return Object.keys(seen).sort();
	}

	function Select(props) {
		return e('select', { value: props.value, onChange: function (ev) { props.onChange(ev.target.value); } },
			[e('option', { key: '', value: '' }, props.all)].concat(props.options.map(function (o) {
				return e('option', { key: o, value: o }, props.labels && props.labels[o] ? props.labels[o] : o);
			})));
	}

	function ReleaseCard(r) {
		return e('a', { className: 'card', href: r.url, key: r.url },
			e('span', { className: 'cover' + (r.cover ? '' : ' empty') },
				r.cover ? e('img', { src: r.cover, alt: r.title, loading: 'lazy' }) : '♪'),
			e('h3', null, r.title),
			e('span', { className: 'sub' }, [r.year, r.format && r.format !== 'unknown' ? r.format : null]
				.filter(Boolean).join(' · ')));
	}

	function SongRow(s) {
		return e('li', { key: s.url }, e('a', { href: s.url },
			e('span', { className: 't' }, s.title),
			s.language ? e('span', { className: 'badge' }, s.language.toUpperCase()) : null,
			s.hasLyrics ? e('span', { className: 'badge accent' }, 'lyrics') : null));
	}

	function Browser() {
		var data = useIndex();
		var q = React.useState(''); var artist = React.useState('');
		var section = React.useState(''); var format = React.useState('');
		var decade = React.useState('');
		if (!data) { return e('p', null, 'Archief laden…'); }
		if (data.error) { return e('p', null, 'Kon het archief niet laden.'); }

		var mode = IDR_BROWSE.mode;
		var items = (mode === 'songs' ? data.songs : data.releases).slice();
		items.sort(mode === 'songs'
			? function (a, b) { return a.title.localeCompare(b.title); }
			: function (a, b) { return (b.year || 0) - (a.year || 0) || a.title.localeCompare(b.title); });

		var decades = {};
		items.forEach(function (it) { if (it.year) decades[Math.floor(it.year / 10) * 10 + 's'] = true; });

		var shown = items.filter(function (it) {
			if (q[0] && it.title.toLowerCase().indexOf(q[0].toLowerCase()) === -1) return false;
			if (artist[0] && it.artist !== artist[0]) return false;
			if (section[0] && it.section !== section[0]) return false;
			if (format[0] && it.format !== format[0]) return false;
			if (decade[0] && (!it.year || Math.floor(it.year / 10) * 10 + 's' !== decade[0])) return false;
			return true;
		});

		var filters = [
			e('input', { key: 'q', type: 'search', placeholder: 'Zoek op titel…', value: q[0],
				onChange: function (ev) { q[1](ev.target.value); } }),
			e(Select, { key: 'a', value: artist[0], onChange: artist[1], all: 'Alle artiesten', options: uniq(items, 'artist') }),
			e(Select, { key: 's', value: section[0], onChange: section[1], all: 'Alle secties', options: uniq(items, 'section') }),
		];
		if (mode !== 'songs') {
			filters.push(e(Select, { key: 'f', value: format[0], onChange: format[1], all: 'Alle formaten',
				options: uniq(items, 'format').filter(function (f) { return f !== 'unknown'; }) }));
			filters.push(e(Select, { key: 'd', value: decade[0], onChange: decade[1], all: 'Alle decennia', options: Object.keys(decades).sort() }));
		}
		filters.push(e('span', { key: 'n', className: 'browse-count' }, shown.length + ' van ' + items.length));

		return e(React.Fragment, null,
			e('div', { className: 'browse-filters' }, filters),
			mode === 'songs'
				? e('ul', { className: 'rows' }, shown.map(SongRow))
				: e('div', { className: 'grid' }, shown.map(ReleaseCard)));
	}

	var root = document.getElementById('idr-browse-root');
	if (root) { ReactDOM.createRoot(root).render(e(Browser)); }
})();
