/**
 * WFV HTML Editor — [wfv_html_editor] shortcode script.
 *
 * Uses locally-bundled CodeMirror 6 (window.wfvCodeMirror) for a modern,
 * VS Code-like editing experience: line numbers, bracket matching, auto
 * closing, live HTML auto-complete, integrated search, and a Dracula-dark
 * theme. The right pane remains a live, directly-editable preview that
 * stays in sync with the code pane in both directions. No login required.
 *
 * Reads config from the shortcode element's data-wfv-config attribute.
 */
(function(){
	var root = document.querySelector('.wfv-h5e[data-wfv-config]');
	if ( ! root || root.dataset.wfvInit ) { return; }
	root.dataset.wfvInit = '1';

	var config = {};
	try { config = JSON.parse(root.dataset.wfvConfig); } catch(e) {}

	var CM = window.wfvCodeMirror || null;

	var demoHtml    = config.startingValue || '';
	var textarea    = root.querySelector('.wfv-h5e-source');
	var preview     = root.querySelector('.wfv-h5e-preview');
	var previewport = root.querySelector('.wfv-h5e-previewport');
	var statusCursor = root.querySelector('.wfv-h5e-status-cursor');
	var statusSize   = root.querySelector('.wfv-h5e-status-size');
	var statusMsg    = root.querySelector('.wfv-h5e-status-msgs');
	var codewrap    = root.querySelector('.wfv-h5e-codewrap');

	var bootstrapOn   = false;
	var syncingFromIframe = false;
	var view          = null;
	var fontScale     = 13;

	function getCode(){
		return view ? view.state.doc.toString() : textarea.value;
	}
	function setCode( val ){
		if ( view ) {
			view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: val } });
			view.focus();
		} else {
			textarea.value = val;
		}
		updateStatus();
	}

	/* ---------- CodeMirror 6 init ---------- */
	if ( CM ) {
		var updateListener = CM.EditorView.updateListener.of(function( update ){
			if ( syncingFromIframe ) { return; }
			if ( update.docChanged ) {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(reloadPreview, 250);
			}
			if ( update.docChanged || update.selectionSet ) {
				updateStatus();
			}
		});

		var extensions = [
			CM.basicSetup,
			CM.lang.html(),
			CM.draculaTheme,
			CM.draculaHighlight,
			updateListener
		];

		codewrap.classList.add( 'wfv-h5e-cm-active' );
		view = new CM.EditorView({
			doc: textarea.value,
			parent: codewrap,
			extensions: extensions
		});
		// Keep the hidden source textarea in sync for any external reads.
		view.dom.setAttribute( 'data-wfv-editor', '1' );
	} else {
		textarea.addEventListener('input', function(){
			if ( syncingFromIframe ) { return; }
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(reloadPreview, 250);
			updateStatus();
		});
		textarea.addEventListener('keyup', updateStatus);
		textarea.addEventListener('click', updateStatus);
	}

	function bootstrapLink(){
		return '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">';
	}
	function reloadPreview(){
		var html = getCode();
		if ( bootstrapOn ) { html = bootstrapLink() + html; }
		preview.srcdoc = html;
	}
	var debounceTimer;

	function makeIframeEditable(){
		try {
			var doc = preview.contentDocument;
			if ( ! doc || ! doc.body ) { return; }
			doc.designMode = 'on';
			doc.body.style.minHeight = '100%';
			doc.body.style.outline = 'none';
			doc.body.addEventListener('input', syncFromIframe);
			doc.body.addEventListener('blur', syncFromIframe);
		} catch ( e ) {}
	}
	var flashTimer;
	function flash( msg ){
		statusMsg.textContent = msg;
		clearTimeout( flashTimer );
		flashTimer = setTimeout( function(){ statusMsg.textContent = ''; }, 2600 );
	}
	function updateStatus(){
		var cursorLine = 1, cursorCol = 1;
		if ( view ) {
			var head = view.state.selection.main.head;
			var line = view.state.doc.lineAt( head );
			cursorLine = line.number;
			cursorCol = head - line.from + 1;
		} else {
			var pre = textarea.value.slice( 0, textarea.selectionStart ).split('\n');
			cursorLine = pre.length;
			cursorCol = ( pre.pop() || '' ).length + 1;
		}
		var code = getCode();
		var lines = code.length ? code.split('\n').length : 1;
		statusCursor.textContent = 'Ln ' + cursorLine + ', Col ' + cursorCol;
		statusSize.textContent = code.length + ' chars · ' + lines + ' line' + ( lines === 1 ? '' : 's' );
	}
	function copyHtml(){
		var html = getCode();
		var ok = function(){ flash( 'HTML copied to clipboard' ); };
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( html ).then( ok );
		} else {
			var ta = document.createElement('textarea');
			ta.value = html;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			try { document.execCommand('copy'); ok(); } catch ( e ) { flash( 'Copy failed — select the code manually' ); }
			document.body.removeChild( ta );
		}
	}
	function syncFromIframe(){
		try {
			var doc = preview.contentDocument;
			if ( ! doc ) { return; }
			var headHtml = ( doc.head && doc.head.innerHTML.trim() ) ? doc.head.innerHTML + '\n' : '';
			var bodyHtml = doc.body ? doc.body.innerHTML : '';
			syncingFromIframe = true;
			setCode( headHtml + bodyHtml );
			syncingFromIframe = false;
		} catch ( e ) {}
	}
	preview.addEventListener('load', makeIframeEditable);
	reloadPreview();
	updateStatus();

	function exec( cmd, value ){
		try {
			preview.contentWindow.focus();
			preview.contentDocument.execCommand( cmd, false, value || null );
			syncFromIframe();
		} catch ( e ) {}
	}

	root.querySelectorAll('.wfv-h5e-toolbar-format [data-cmd]').forEach(function(el){
		if ( 'SELECT' === el.tagName ) {
			el.addEventListener('change', function(){
				exec( el.dataset.cmd, el.value );
				el.selectedIndex = 0;
			});
		} else {
			el.addEventListener('click', function(){ exec( el.dataset.cmd ); });
		}
	});
	root.querySelectorAll('.wfv-h5e-toolbar-format [data-act]').forEach(function(btn){
		btn.addEventListener('click', function(){
			if ( 'link' === btn.dataset.act ) {
				var url = window.prompt( 'Link URL:', 'https://' );
				if ( url ) { exec( 'createLink', url ); }
			} else if ( 'image' === btn.dataset.act ) {
				var src = window.prompt( 'Image URL:', 'https://' );
				if ( src ) { exec( 'insertImage', src ); }
			}
		});
	});

	root.querySelectorAll('.wfv-h5e-toolbar-code [data-act]').forEach(function(btn){
		btn.addEventListener('click', function(){
			var act = btn.dataset.act;
			if ( 'demo' === act ) {
				setCode( demoHtml );
				reloadPreview();
			} else if ( 'clear' === act ) {
				if ( getCode() && ! confirm('Clear all HTML in the editor?') ) { return; }
				setCode( '' );
				reloadPreview();
			} else if ( 'minify' === act ) {
				setCode( getCode().replace(/\n\s*/g, '').replace(/>\s+</g, '><').trim() );
				reloadPreview();
				flash( 'HTML minified' );
			} else if ( 'clean' === act || 'lorem' === act || 'color' === act ) {
				togglePop( btn.dataset.pop );
			} else if ( 'findreplace' === act ) {
				toggleFindReplace();
			} else if ( 'replace-all' === act ) {
				applyFindReplace();
			} else if ( 'fr-add' === act ) {
				addFindRule();
			} else if ( 'clean-apply' === act ) {
				applyClean();
			} else if ( 'lorem-insert' === act ) {
				insertLorem( false );
			} else if ( 'lorem-append' === act ) {
				insertLorem( true );
			} else if ( 'color-insert' === act ) {
				insertColor();
			} else if ( 'color-save' === act ) {
				saveColor();
			} else if ( 'bootstrap' === act ) {
				bootstrapOn = ! bootstrapOn;
				btn.textContent = ' Bootstrap: ' + ( bootstrapOn ? 'On' : 'Off' );
				reloadPreview();
			} else if ( 'font-plus' === act || 'font-minus' === act ) {
				fontScale = ( 'font-plus' === act ) ? Math.min(22, fontScale + 1) : Math.max(10, fontScale - 1);
				if ( view ) {
					view.dom.style.fontSize = fontScale + 'px';
					view.requestMeasure();
				}
			} else if ( 'copy' === act ) {
				copyHtml();
			}
		});
	});

	/* ---------- Clean options ---------- */
	function applyClean(){
		var opts = {};
		root.querySelectorAll('.wfv-h5e-cleanopts input[data-clean]').forEach(function(cb){ opts[ cb.dataset.clean ] = cb.checked; });
		var html = getCode();
		if ( opts.inline ) {
			html = html.replace(/\sstyle\s*=\s*"[^"]*"/gi, '').replace(/\sstyle\s*=\s*'[^']*'/gi, '');
		}
		if ( opts.classes ) {
			html = html.replace(/\sclass\s*=\s*"[^"]*"/gi, '').replace(/\sclass\s*=\s*'[^']*'/gi, '')
				.replace(/\sid\s*=\s*"[^"]*"/gi, '').replace(/\sid\s*=\s*'[^']*'/gi, '');
		}
		if ( opts.comments ) {
			html = html.replace(/<!--[\s\S]*?-->/g, '');
		}
		if ( opts.empty ) {
			html = html.replace(/<\s*([a-z0-9]+)(\s[^>]*)?>\s*<\s*\/\s*\1\s*>/gi, '');
		}
		if ( opts.attrs ) {
			html = html.replace(/<([a-z0-9]+)\s+[^>]*>/gi, function(m, tag){ return '<' + tag + '>'; });
		}
		if ( opts.images ) {
			html = html.replace(/<\s*img\b[^>]*>/gi, '');
		}
		if ( opts.links ) {
			html = html.replace(/<\s*a\b[^>]*>/gi, '').replace(/<\s*\/\s*a\s*>/gi, '');
		}
		if ( opts.tables ) {
			html = html.replace(/<\s*table\b[^>]*>/gi, '<div>').replace(/<\s*\/\s*table\s*>/gi, '</div>')
				.replace(/<\s*t[rhd]\b[^>]*>/gi, '<div>').replace(/<\s*\/\s*t[rhd]\s*>/gi, '</div>');
		}
		if ( opts.semantic ) {
			html = html.replace(/<\s*b\s*>/gi, '<strong>').replace(/<\s*\/\s*b\s*>/gi, '</strong>')
				.replace(/<\s*i\s*>/gi, '<em>').replace(/<\s*\/\s*i\s*>/gi, '</em>');
		}
		html = html.trim();
		setCode( html );
		reloadPreview();
		flash( 'Cleaned your HTML' );
	}

	/* ---------- Find & replace (multiple rules) ---------- */
	function addFindRule(){
		var rows = root.querySelector('.wfv-h5e-fr-rows');
		var row = document.createElement('div');
		row.className = 'wfv-h5e-fr-row';
		row.innerHTML = '<input type="text" placeholder="Find…" data-role="find"><input type="text" placeholder="Replace with…" data-role="replace">';
		rows.appendChild( row );
	}
	function applyFindReplace(){
		var rows = root.querySelectorAll('.wfv-h5e-fr-row');
		var html = getCode();
		var count = 0;
		rows.forEach(function(row){
			var find = row.querySelector('[data-role="find"]').value;
			var rep = row.querySelector('[data-role="replace"]').value;
			if ( ! find ) { return; }
			var n = html.split( find ).length - 1;
			if ( n > 0 ) { count += n; html = html.split( find ).join( rep ); }
		});
		if ( ! count ) { flash( 'Nothing to replace' ); return; }
		setCode( html );
		reloadPreview();
		flash( 'Replaced ' + count + ' occurrence' + ( count === 1 ? '' : 's' ) );
	}

	/* ---------- Placeholder text generator ---------- */
	var LOREM_WORDS = ('lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua enim ad minim veniam quis nostrud exercitation ullamco laboris nisi aliquip ex ea commodo consequat duis aute irure in reprehenderit voluptate velit esse cillum eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident sunt culpa qui officia deserunt mollit anim id est laborum').split(' ');
	function loremParagraph(){
		var n = 20 + Math.floor( Math.random() * 30 );
		var words = [];
		for ( var i = 0; i < n; i++ ) { words.push( LOREM_WORDS[ Math.floor( Math.random() * LOREM_WORDS.length ) ] ); }
		words[0] = words[0].charAt(0).toUpperCase() + words[0].slice(1);
		return '<p>' + words.join(' ') + '.</p>';
	}
	function insertLorem( append ){
		var count = parseInt( root.querySelector('[data-role="lorem-count"]').value, 10 ) || 5;
		var paras = [];
		for ( var i = 0; i < count; i++ ) { paras.push( loremParagraph() ); }
		var html = paras.join( '\n' );
		setCode( append ? ( getCode() + ( getCode() ? '\n' : '' ) + html ) : html );
		reloadPreview();
		flash( count + ' paragraph' + ( count === 1 ? '' : 's' ) + ( append ? ' appended' : ' inserted' ) );
	}

	/* ---------- Color palette & mixer ---------- */
	var DEFAULT_SWATCHES = [ '#6366f1', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b', '#0f172a', '#ffffff', '#f97316' ];
	var swatchStorage = 'wfvH5ePalette';
	function getPalette(){
		var saved = null;
		try { saved = JSON.parse( localStorage.getItem( swatchStorage ) || 'null' ); } catch ( e ) {}
		return ( saved && saved.length ) ? saved : DEFAULT_SWATCHES.slice();
	}
	function renderSwatches(){
		var wrap = root.querySelector('[data-role="swatches"]');
		wrap.innerHTML = '';
		getPalette().forEach(function(hex){
			var s = document.createElement('button');
			s.type = 'button';
			s.className = 'wfv-h5e-swatch';
			s.title = hex;
			s.style.background = hex;
			s.addEventListener('click', function(){ insertAtCursor( hex ); });
			wrap.appendChild( s );
		});
	}
	function insertAtCursor( text ){
		if ( view ) {
			view.dispatch( view.state.replaceSelection( text ) );
			view.focus();
		} else {
			var start = textarea.selectionStart, end = textarea.selectionEnd;
			textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
		}
		reloadPreview();
	}
	function currentColor(){ return root.querySelector('[data-role="newcolor"]').value; }
	function insertColor(){ insertAtCursor( currentColor() ); }
	function saveColor(){
		var hex = currentColor();
		var pal = getPalette();
		if ( pal.indexOf( hex ) === -1 ) { pal.push( hex ); localStorage.setItem( swatchStorage, JSON.stringify( pal ) ); }
		renderSwatches();
		flash( 'Color added to palette' );
	}
	renderSwatches();

	/* ---------- Pop panels ---------- */
	function anyPanelOpen(){
		var open = false;
		root.querySelectorAll('.wfv-h5e-pop, .wfv-h5e-findreplace').forEach(function(p){ if ( p.style.display === 'flex' ) { open = true; } });
		return open;
	}
	function closePanels(){ root.querySelectorAll('.wfv-h5e-pop, .wfv-h5e-findreplace').forEach(function(p){ p.style.display = 'none'; }); }
	function togglePop( name ){
		var panel = root.querySelector('.wfv-h5e-pop[data-pop="' + name + '"]');
		if ( ! panel ) { return; }
		var wasOpen = ( panel.style.display === 'flex' );
		closePanels();
		if ( ! wasOpen ) { panel.style.display = 'flex'; }
	}
	function toggleFindReplace(){
		var fr = root.querySelector('.wfv-h5e-findreplace');
		var wasOpen = ( fr.style.display === 'flex' );
		closePanels();
		if ( ! wasOpen ) { fr.style.display = 'flex'; }
	}
	document.addEventListener('click', function(e){
		if ( anyPanelOpen() && ! root.contains( e.target ) ) { closePanels(); }
	},{ capture: true });

	root.querySelectorAll('.wfv-h5e-devicebtns [data-device]').forEach(function(btn){
		btn.addEventListener('click', function(){
			root.querySelectorAll('.wfv-h5e-devicebtns button').forEach(function(b){ b.classList.remove('wfv-h5e-active'); });
			btn.classList.add('wfv-h5e-active');
			previewport.classList.remove('wfv-h5e-device', 'wfv-h5e-tablet', 'wfv-h5e-mobile');
			if ( btn.dataset.device ) {
				previewport.classList.add('wfv-h5e-device', 'wfv-h5e-' + btn.dataset.device);
			}
		});
	});
})();
