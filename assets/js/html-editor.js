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
	var findRow     = root.querySelector('.wfv-h5e-findreplace');
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
			} else if ( 'cleanstyles' === act ) {
				var before = getCode();
				var after = before
					.replace(/\sstyle\s*=\s*"[^"]*"/gi, '')
					.replace(/\sstyle\s*=\s*'[^']*'/gi, '')
					.replace(/<\s*font\b[^>]*>/gi, '')
					.replace(/<\s*\/\s*font\s*>/gi, '')
					.replace(/<\s*span\s*>\s*<\s*\/\s*span\s*>/gi, '')
					.trim();
				var cleanCount = ( before.match(/\sstyle\s*=/gi) || [] ).length
					+ ( before.match(/<\s*font\b/gi) || [] ).length
					+ ( before.match(/<\s*span\s*>\s*<\s*\/\s*span\s*>/gi) || [] ).length;
				setCode( after );
				reloadPreview();
				flash( cleanCount ? ('Removed ' + cleanCount + ' inline style' + ( cleanCount === 1 ? '' : 's' )) : 'No inline styles found' );
			} else if ( 'findreplace' === act ) {
				findRow.style.display = ( findRow.style.display === 'flex' ) ? 'none' : 'flex';
			} else if ( 'replace-all' === act ) {
				var searchTerm = findRow.querySelector('[data-role="find"]').value;
				var replacement = findRow.querySelector('[data-role="replace"]').value;
				if ( ! searchTerm ) { return; }
				setCode( getCode().split(searchTerm).join(replacement) );
				reloadPreview();
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

	root.querySelector('[data-act="color"]').addEventListener('input', function(e){
		var hex = e.target.value;
		if ( view ) {
			view.dispatch( view.state.replaceSelection( hex ) );
			view.focus();
		} else {
			var start = textarea.selectionStart, end = textarea.selectionEnd;
			textarea.value = textarea.value.slice(0, start) + hex + textarea.value.slice(end);
		}
		reloadPreview();
	});

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
