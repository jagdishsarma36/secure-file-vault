/**
 * Sticky Notes — shortcode scripts.
 *
 * Extracted from the inline <script> block in wfv_sticky_notes_shortcode().
 * PHP-embedded values replaced with references to window.wfvStickyNotes config.
 * Must be enqueued alongside sticky-notes.css.
 *
 * @package SecureFileVault
 */
(function(){
	var root = document.querySelector('.wfv-sn[data-wfv-config]');
	if ( ! root || root.dataset.wfvInit ) { return; }
	root.dataset.wfvInit = '1';

	var config = {};
	try { config = JSON.parse(root.dataset.wfvConfig); } catch(e) {}

	var loggedIn = config.loggedIn;
	var ajaxurl_ = config.ajaxurl;
	var nonce    = config.nonce;

	var grid      = document.getElementById( config.uid + '-grid' );
	var emptyMsg  = document.getElementById( config.uid + '-empty' );
	var composer  = document.getElementById( config.uid + '-composer' );
	var addBtn    = document.getElementById( config.uid + '-add' );
	var editable  = composer.querySelector('.wfv-sn-editable');
	var colorInputs = Array.prototype.slice.call( composer.querySelectorAll('.wfv-sn-color-row .wfv-sn-swatch') );
	var colorPicker = composer.querySelector('.wfv-sn-color-picker');
	var activeFilter = 'all';
	var notes = [];
	var editingId = null;
	var currentColor = '';

	var ICONS = {
		edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
		trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4,7 20,7"/><path d="M6 7l1 14h10l1-14"/><path d="M9 7V4h6v3"/></svg>',
		grip: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>'
	};

	/* ---------- Formatting (mirrors the server's wfv_sticky_allowed_tags allow-list) ---------- */
	var ALLOWED_TAGS = { B:1, STRONG:1, I:1, EM:1, U:1, S:1, STRIKE:1, BR:1, P:1, UL:1, OL:1, LI:1, A:1, PRE:1, CODE:1, SPAN:1, FONT:1 };
	var BLOCK_TAGS = { DIV:1, H1:1, H2:1, H3:1, H4:1, H5:1, H6:1, P:1, SECTION:1, HEADER:1, FOOTER:1, ARTICLE:1, ASIDE:1, NAV:1, MAIN:1, FIGURE:1, TABLE:1, TR:1, TD:1, TH:1, BLOCKQUOTE:1, ADDRESS:1, PRE:1, FORM:1, FIELDSET:1, DL:1, DT:1, DD:1, HR:1 };
	function sanitizeStickyHtml( html ){
		if ( html == null ) { return ''; }
		var holder = document.createElement('div');
		var wrap = document.createElement('div');
		wrap.innerHTML = html;
		// Recursively rebuild the tree from the allow-list. Disallowed
		// wrappers (Chrome's <div> paragraphs) are unwrapped but get a
		// <br> boundary so typed line breaks survive; every moved child
		// is re-scanned, unlike the old snapshot loop which let a <script>
		// hidden inside a <div> slip through untouched. Inline style/font
		// nodes the browser loves to emit are normalized into simple
		// <b>/<i>/<u>/<s> or kept as color-only <span> — so formatting
		// typed in the editor is preserved rather than stripped.
		function styleProps( el ){
			var props = {};
			( el.getAttribute('style') || '' ).split(';').forEach(function( part ){
				var p = part.indexOf(':');
				if ( p < 0 ) { return; }
				var k = part.slice(0, p).trim().toLowerCase();
				var v = part.slice(p + 1).trim();
				if ( k && v ) { props[k] = v; }
			});
			return props;
		}
		function clean( node ){
			var frag = document.createDocumentFragment();
			Array.prototype.slice.call( node.childNodes ).forEach(function( child ){
				if ( child.nodeType === 3 ) { frag.appendChild( child.cloneNode( true ) ); return; }
				if ( child.nodeType !== 1 ) { return; } // drop comments etc.
				var tag = child.tagName;
				if ( ! ALLOWED_TAGS[ tag ] ) {
					var kids = clean( child );
					// javaScript: appending a <b>DocumentFragment</b> MOVES its
					// children out of it, so its childNodes goes empty — read it
					// BEFORE the append so <br> boundaries are not lost.
					var boundary = BLOCK_TAGS[ tag ] && kids.childNodes.length &&
						( kids.childNodes[ kids.childNodes.length - 1 ].nodeType !== 1 ||
						  kids.childNodes[ kids.childNodes.length - 1 ].tagName !== 'BR' );
					frag.appendChild( kids );
					if ( boundary ) { frag.appendChild( document.createElement('br') ); }
					return;
				}
				var el = document.createElement( tag );
				if ( 'A' === tag ) {
					var href = child.getAttribute('href') || '';
					if ( ! /^\s*javascript:/i.test( href ) ) {
						el.setAttribute( 'href', href );
						el.setAttribute( 'target', '_blank' );
						el.setAttribute( 'rel', 'noopener noreferrer' );
					}
				} else if ( 'SPAN' === tag || 'FONT' === tag ) {
					var props = styleProps( child );
					if ( 'FONT' === tag && child.getAttribute('color') ) {
						props.color = props.color || child.getAttribute('color');
					}
					var fw  = ( props['font-weight'] || '' ).toLowerCase();
					var fs  = ( props['font-style'] || '' ).toLowerCase();
					var td  = ( props['text-decoration'] || '' ).toLowerCase();
					var swap = null;
					if ( 'bold' === fw || 'bolder' === fw || 600 <= parseInt(fw, 10) ) { swap = 'b'; }
					else if ( 'italic' === fs || 'oblique' === fs ) { swap = 'i'; }
					else if ( -1 !== td.indexOf('line-through') ) { swap = 's'; }
					else if ( -1 !== td.indexOf('underline') && -1 === td.indexOf('none') ) { swap = 'u'; }
					if ( swap ) {
						el = document.createElement( swap );
					} else if ( props.color || props['background-color'] ) {
						// Normalize font[color] / font[style] / span[style]
						// to color-only <span> so the server-side allow-list
						// (span[style], font[color]) keeps the color.
						var keep = [];
						if ( props.color ) { keep.push('color:' + props.color); }
						if ( props['background-color'] ) { keep.push('background-color:' + props['background-color']); }
						el = document.createElement('span');
						el.setAttribute( 'style', keep.join(';') );
					} else {
						el = null; // empty/meaningless span → unwrap
					}
				}
				if ( el ) {
					el.appendChild( clean( child ) );
					frag.appendChild( el );
				} else {
					frag.appendChild( clean( child ) ); // unwrap bold-stand-ins / empty spans
				}
			});
			return frag;
		}
		holder.appendChild( clean( wrap ) );
		return holder.innerHTML;
	}
	function saveSelection(){
		var sel = window.getSelection();
		return ( sel && sel.rangeCount ) ? sel.getRangeAt( 0 ) : null;
	}
	function restoreSelection( range ){
		if ( ! range ) { return; }
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange( range );
	}
	function exec( cmd, value ){
		var hadSelection = saveSelection();
		editable.focus();
		if ( hadSelection ) { restoreSelection( hadSelection ); }
		try {
			document.execCommand( 'styleWithCSS', false, false ); // tags, not inline <span style> — so formatting survives the allow-list
			document.execCommand( cmd, false, value || null );
		} catch ( e ) {}
	}
	// Keep focus + selection inside the composer when a toolbar button is pressed.
	composer.querySelectorAll('.wfv-sn-composer-toolbar button').forEach(function( el ){
		el.addEventListener('mousedown', function( e ){ e.preventDefault(); });
	});
	var pendingColorRange = null;
	[composer.querySelector('.wfv-sn-text-color'), composer.querySelector('.wfv-sn-hl-color')].forEach(function( input ){
		input.addEventListener('click', function(){ pendingColorRange = saveSelection(); });
		input.addEventListener('input', function( e ){
			if ( pendingColorRange ) { restoreSelection( pendingColorRange ); pendingColorRange = null; }
			exec( e.target.classList.contains('wfv-sn-hl-color') ? 'hiliteColor' : 'foreColor', e.target.value );
		});
	});
	composer.querySelectorAll('.wfv-sn-composer-toolbar [data-cmd]').forEach(function( btn ){
		btn.addEventListener('click', function(){ exec( btn.dataset.cmd ); });
	});
	composer.querySelector('[data-act="link"]').addEventListener('click', function(){
		var url = window.prompt( 'Link URL:', 'https://' );
		if ( url ) { exec( 'createLink', url ); }
	});
	composer.querySelector('[data-act="code"]').addEventListener('click', function(){
		exec( 'formatBlock', 'pre' );
	});
	colorInputs.forEach(function( sw ){
		sw.addEventListener('click', function(){ setColor( sw.dataset.color || '' ); });
	});
	colorPicker.addEventListener('input', function(){ setColor( colorPicker.value ); });

	/* ---------- Storage backends ---------- */
	function makeLocalBackend(){
		var KEY = 'wfv_sticky_notes_v1';
		function readAll(){
			try { var v = JSON.parse( window.localStorage.getItem(KEY) ); return Array.isArray(v) ? v : []; }
			catch(e){ return []; }
		}
		function writeAll(list){
			try { window.localStorage.setItem( KEY, JSON.stringify(list) ); } catch(e){}
		}
		return {
			list: function(){ return Promise.resolve( readAll() ); },
			save: function( note ){
				var list = readAll();
				var cleanContent = sanitizeStickyHtml( note.content );
				var noteColor = note.color || '';
				if ( note.id ) {
					list = list.map(function(n){
						if ( n.id === note.id ) {
							return Object.assign( {}, n, { content: cleanContent, priority: note.priority, color: noteColor, updated_at: new Date().toISOString() } );
						}
						return n;
					});
				} else {
					note.id = 'local-' + Date.now() + '-' + Math.floor( Math.random() * 100000 );
					note.content = cleanContent;
					note.color = noteColor;
					note.pinned = false;
					note.updated_at = new Date().toISOString();
					list.unshift( note );
				}
				writeAll( list );
				return Promise.resolve( Object.assign( {}, note, { content: cleanContent } ) );
			},
			remove: function( id ){
				writeAll( readAll().filter(function(n){ return n.id !== id; }) );
				return Promise.resolve();
			},
			togglePin: function( id ){
				var updated = null;
				var list = readAll().map(function(n){
					if ( n.id === id ) { n.pinned = ! n.pinned; updated = n; }
					return n;
				});
				writeAll( list );
				return Promise.resolve( updated );
			},
			reorder: function( orderedIds ){
				var list = readAll();
				var step = 10;
				orderedIds.forEach(function( id, idx ){
					list = list.map(function( n ){
						if ( String(n.id) === String(id) ) { n.sort_order = step * ( idx + 1 ); }
						return n;
					});
				});
				writeAll( list );
				return Promise.resolve();
			}
		};
	}

	function makeDbBackend(){
		function call( action, params ){
			var body = new URLSearchParams();
			body.append( 'action', action );
			body.append( 'nonce', nonce );
			Object.keys( params || {} ).forEach(function( k ){ body.append( k, params[k] ); });
			return fetch( ajaxurl_, { method: 'POST', credentials: 'same-origin', body: body } )
				.then(function( r ){ return r.json(); })
				.then(function( res ){
					if ( ! res || ! res.success ) { throw new Error('request failed'); }
					return res.data;
				});
		}
		return {
			list: function(){ return call( 'wfv_sticky_list' ); },
			save: function( note ){ return call( 'wfv_sticky_save', note ); },
			remove: function( id ){ return call( 'wfv_sticky_delete', { id: id } ); },
			togglePin: function( id ){ return call( 'wfv_sticky_toggle_pin', { id: id } ); },
			reorder: function( orderedIds ){
				return call( 'wfv_sticky_reorder', { order: orderedIds } );
			}
		};
	}

	var backend = loggedIn ? makeDbBackend() : makeLocalBackend();

	/* ---------- Rendering ---------- */
	function sortNotes( list ){
		return list.slice().sort(function( a, b ){
			if ( !!a.pinned !== !!b.pinned ) { return a.pinned ? -1 : 1; }
			var ao = ( typeof a.sort_order === 'number' ) ? a.sort_order : Infinity;
			var bo = ( typeof b.sort_order === 'number' ) ? b.sort_order : Infinity;
			if ( ao !== bo ) { return ao - bo; }
			return new Date( b.updated_at ) - new Date( a.updated_at );
		});
	}
	function matchesFilter( n ){
		if ( 'all' === activeFilter ) { return true; }
		if ( 'pinned' === activeFilter ) { return !! n.pinned; }
		return n.priority === activeFilter;
	}
	function render(){
		var visible = sortNotes( notes ).filter( matchesFilter );
		grid.innerHTML = '';
		emptyMsg.style.display = visible.length ? 'none' : '';
		visible.forEach(function( n ){
			grid.appendChild( buildCard( n ) );
		});
		initDragOrder();
	}

	/* ---------- Drag & Drop manual ordering ---------- */
	var dragSource = null;
	function initDragOrder(){
		Array.prototype.forEach.call( grid.children, function( card ){
			card.addEventListener('dragstart', function( e ){
				dragSource = card;
				card.classList.add('wfv-sn-dragging');
				try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', card.dataset.id || ''); } catch( err ) {}
			});
			card.addEventListener('dragend', function(){
				card.classList.remove('wfv-sn-dragging');
				Array.prototype.forEach.call( grid.children, function( c ){ c.classList.remove('wfv-sn-drag-over'); });
				dragSource = null;
			});
			card.addEventListener('dragover', function( e ){
				e.preventDefault();
				if ( dragSource === card ) { return; }
				card.classList.add('wfv-sn-drag-over');
				e.dataTransfer.dropEffect = 'move';
			});
			card.addEventListener('dragleave', function(){
				card.classList.remove('wfv-sn-drag-over');
			});
			card.addEventListener('drop', function( e ){
				e.preventDefault();
				card.classList.remove('wfv-sn-drag-over');
				if ( ! dragSource || dragSource === card ) { return; }
				var srcId = dragSource.dataset.id;
				var targetId = card.dataset.id;
				var rect = card.getBoundingClientRect();
				var before = e.clientY < rect.top + rect.height / 2;
				if ( before ) {
					grid.insertBefore( dragSource, card );
				} else {
					grid.insertBefore( dragSource, card.nextSibling );
				}
				var ordered = Array.prototype.map.call( grid.children, function( c ){ return c.dataset.id; } );
				// Reflect the new order back into the notes array's sort_order.
				var step = 10;
				ordered.forEach(function( id, idx ){
					var match = null;
					for ( var i = 0; i < notes.length; i++ ) { if ( String(notes[i].id) === String(id) ) { match = notes[i]; break; } }
					if ( match ) { match.sort_order = step * ( idx + 1 ); }
				});
				if ( srcId ) { backend.reorder( ordered.filter(Boolean) ).catch(function(){}); }
			});
		});
	}
	function buildCard( n ){
		var card = document.createElement('div');
		card.className = 'wfv-sn-note wfv-sn-priority-' + ( n.priority || 'medium' );
		card.draggable = true;
		card.dataset.id = n.id;
		if ( n.color ) { card.style.backgroundColor = n.color; }

		var grip = document.createElement('button');
		grip.type = 'button';
		grip.className = 'wfv-sn-drag-handle';
		grip.title = 'Drag to reorder';
		grip.innerHTML = ICONS.grip;
		grip.addEventListener('mousedown', function( e ){ e.stopPropagation && e.stopPropagation(); });
		card.appendChild( grip );

		var contentEl = document.createElement('div');
		contentEl.className = 'wfv-sn-note-content';
		contentEl.innerHTML = sanitizeStickyHtml( n.content ); // always re-sanitized client-side too, defense in depth
		card.appendChild( contentEl );

		var pinBtn = document.createElement('button');
		pinBtn.type = 'button';
		pinBtn.className = 'wfv-sn-pin' + ( n.pinned ? ' wfv-sn-pinned' : '' );
		pinBtn.title = n.pinned ? 'Unpin' : 'Pin';
		pinBtn.textContent = '📌';
		pinBtn.addEventListener('click', function(){
			backend.togglePin( n.id ).then(function( updated ){
				if ( updated ) {
					n.pinned = updated.pinned;
				} else {
					n.pinned = ! n.pinned;
				}
				render();
			});
		});
		card.appendChild( pinBtn );

		var footer = document.createElement('div');
		footer.className = 'wfv-sn-note-footer';

		var badge = document.createElement('span');
		badge.className = 'wfv-sn-priority-badge wfv-sn-priority-' + ( n.priority || 'medium' );
		badge.textContent = n.priority || 'medium';
		footer.appendChild( badge );

		var actions = document.createElement('div');
		actions.className = 'wfv-sn-note-actions';

		var editBtn = document.createElement('button');
		editBtn.type = 'button';
		editBtn.title = 'Edit';
		editBtn.innerHTML = ICONS.edit;
		editBtn.addEventListener('click', function(){ openComposer( n ); });
		actions.appendChild( editBtn );

		var delBtn = document.createElement('button');
		delBtn.type = 'button';
		delBtn.title = 'Delete';
		delBtn.innerHTML = ICONS.trash;
		delBtn.addEventListener('click', function(){
			if ( ! window.confirm('Delete this note?') ) { return; }
			backend.remove( n.id ).then(function(){
				notes = notes.filter(function(x){ return x.id !== n.id; });
				render();
			});
		});
		actions.appendChild( delBtn );

		footer.appendChild( actions );
		card.appendChild( footer );
		return card;
	}

	/* ---------- Composer ---------- */
	function syncColorUI( color ){
		var hex = ( color || '' ).toLowerCase().replace('#', '');
		if ( ! hex ) {
			colorInputs.forEach(function( sw ){ sw.classList.toggle( 'wfv-sn-active', ! sw.dataset.color ); });
			return;
		}
		colorInputs.forEach(function( sw ){
			sw.classList.toggle( 'wfv-sn-active', ( sw.dataset.color || '' ).toLowerCase().replace('#', '') === hex );
		});
	}
	function setColor( color ){
		currentColor = color || '';
		syncColorUI( currentColor );
		if ( currentColor ) { colorPicker.value = currentColor; }
	}
	function openComposer( note ){
		editingId = note ? note.id : null;
		editable.innerHTML = note ? sanitizeStickyHtml( note.content ) : '';
		var priority = note ? ( note.priority || 'medium' ) : 'medium';
		root.querySelectorAll('.wfv-sn-priority-choice input').forEach(function( input ){
			input.checked = ( input.value === priority );
		});
		setColor( note ? ( note.color || '' ) : '' );
		composer.style.display = 'block';
		editable.focus();
	}
	function closeComposer(){
		composer.style.display = 'none';
		editingId = null;
		editable.innerHTML = '';
		setColor( '' );
	}
	addBtn.addEventListener('click', function(){ openComposer( null ); });
	composer.querySelector('[data-act="cancel"]').addEventListener('click', closeComposer);
	composer.querySelector('[data-act="save"]').addEventListener('click', function(){
		var content = sanitizeStickyHtml( editable.innerHTML );
		if ( ! content.replace(/<[^>]*>/g, '').trim() ) { closeComposer(); return; }
		var priorityInput = root.querySelector('.wfv-sn-priority-choice input:checked');
		var priority = priorityInput ? priorityInput.value : 'medium';

		var payload = { content: content, priority: priority, color: currentColor };
		if ( editingId ) { payload.id = editingId; }

		backend.save( payload ).then(function( saved ){
			if ( editingId ) {
				notes = notes.map(function( n ){ return n.id === editingId ? saved : n; });
			} else {
				notes.unshift( saved );
			}
			closeComposer();
			render();
		});
	});

	/* ---------- Filters ---------- */
	root.querySelectorAll('.wfv-sn-filters button').forEach(function( btn ){
		btn.addEventListener('click', function(){
			root.querySelectorAll('.wfv-sn-filters button').forEach(function( b ){ b.classList.remove('wfv-sn-active'); });
			btn.classList.add('wfv-sn-active');
			activeFilter = btn.dataset.filter;
			render();
		});
	});

	/* ---------- Boot ---------- */
	backend.list().then(function( list ){
		notes = list || [];
		render();
	});
})();
