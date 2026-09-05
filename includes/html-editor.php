<?php
/**
 * HTML Editor shortcode — a self-contained, front-end embeddable dual-pane HTML
 * editor tool, similar in spirit to html5-editor.net. Nothing here is
 * saved anywhere: it's purely a client-side tool. The left pane is a
 * real code editor (locally-bundled CodeMirror 6, with syntax
 * highlighting, line numbers, bracket matching, auto-complete and a
 * built-in search panel); the right pane is a live, directly-editable
 * preview with a formatting toolbar. The two stay in sync in both
 * directions. No login required.
 *
 * Usage:  [wfv_html_editor]
 *         [wfv_html_editor height="600" demo="no"]
 */

function wfv_h5e_icon( $name ) {
	$icons = array(
		'bold'        => '<path d="M6 4h6a4 4 0 0 1 0 8H6z"/><path d="M6 12h7a4 4 0 0 1 0 8H6z"/>',
		'italic'      => '<line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/>',
		'underline'   => '<path d="M6 3v7a6 6 0 0 0 12 0V3"/><line x1="4" y1="21" x2="20" y2="21"/>',
		'strike'      => '<line x1="4" y1="12" x2="20" y2="12"/><path d="M16 6c-1-1.3-2.7-2-5-2-3 0-5 1.3-5 3.2 0 1.6 1.3 2.4 3 2.8"/><path d="M8 18c1 1.3 2.7 2 5 2 3 0 5-1.3 5-3.2 0-1.6-1.3-2.4-3-2.8"/>',
		'list-bullet' => '<circle cx="5" cy="6" r="1.3"/><line x1="9" y1="6" x2="20" y2="6"/><circle cx="5" cy="12" r="1.3"/><line x1="9" y1="12" x2="20" y2="12"/><circle cx="5" cy="18" r="1.3"/><line x1="9" y1="18" x2="20" y2="18"/>',
		'list-number' => '<text x="1" y="8.5" font-size="7" stroke="none" fill="currentColor">1</text><line x1="9" y1="6" x2="20" y2="6"/><text x="1" y="14.5" font-size="7" stroke="none" fill="currentColor">2</text><line x1="9" y1="12" x2="20" y2="12"/><text x="1" y="20.5" font-size="7" stroke="none" fill="currentColor">3</text><line x1="9" y1="18" x2="20" y2="18"/>',
		'indent'      => '<polyline points="7,8 11,12 7,16"/><line x1="14" y1="6" x2="20" y2="6"/><line x1="14" y1="12" x2="20" y2="12"/><line x1="14" y1="18" x2="20" y2="18"/>',
		'outdent'     => '<polyline points="11,8 7,12 11,16"/><line x1="14" y1="6" x2="20" y2="6"/><line x1="14" y1="12" x2="20" y2="12"/><line x1="14" y1="18" x2="20" y2="18"/>',
		'align-left'  => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="18" y2="18"/>',
		'align-center'=> '<line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="5" y1="18" x2="19" y2="18"/>',
		'align-right' => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="6" y1="18" x2="20" y2="18"/>',
		'link'        => '<path d="M9 15l6-6"/><path d="M11 6l.8-.8a3 3 0 0 1 4.2 4.2l-.8.8"/><path d="M13 18l-.8.8a3 3 0 0 1-4.2-4.2l.8-.8"/>',
		'code'        => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
		'unlink'      => '<path d="M9 15l2-2"/><path d="M11 6l.8-.8a3 3 0 0 1 4.2 4.2l-.8.8"/><path d="M13 18l-.8.8a3 3 0 0 1-4.2-4.2l.8-.8"/><line x1="4" y1="4" x2="20" y2="20"/>',
		'image'       => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><polyline points="21,15 15,9 6,20"/>',
		'hr'          => '<line x1="4" y1="12" x2="20" y2="12"/>',
		'eraser'      => '<path d="M16 3l5 5-9.5 9.5H6L3 14.5 12.5 5z"/><line x1="6" y1="17.5" x2="10" y2="21.5"/><line x1="10" y1="21" x2="21" y2="21"/>',
		'undo'        => '<path d="M9 14L4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
		'redo'        => '<path d="M15 14l5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>',
		'search'      => '<circle cx="10" cy="10" r="6"/><line x1="15" y1="15" x2="20" y2="20"/>',
		'droplet'     => '<path d="M12 3s6 7 6 11a6 6 0 0 1-12 0c0-4 6-11 6-11z"/>',
		'file'        => '<path d="M6 2h8l4 4v16H6z"/><polyline points="14,2 14,6 18,6"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>',
		'trash'       => '<polyline points="4,7 20,7"/><path d="M6 7l1 14h10l1-14"/><path d="M9 7V4h6v3"/>',
		'package'     => '<path d="M3 8l9-5 9 5-9 5-9-5z"/><path d="M3 8v9l9 5 9-5V8"/><line x1="12" y1="13" x2="12" y2="22"/>',
		'monitor'     => '<rect x="2" y="4" width="20" height="13" rx="1"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
		'tablet'      => '<rect x="6" y="2" width="12" height="20" rx="2"/><line x1="11" y1="19" x2="13" y2="19"/>',
		'smartphone'  => '<rect x="7" y="2" width="10" height="20" rx="2"/><line x1="11" y1="18" x2="13" y2="18"/>',
		'minus'       => '<line x1="4" y1="12" x2="20" y2="12"/>',
		'plus'        => '<line x1="12" y1="4" x2="12" y2="20"/><line x1="4" y1="12" x2="20" y2="12"/>',
		'type'        => '<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/>',
		'check'       => '<polyline points="20 6 9 17 4 12"/>',
	);
	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $icons[ $name ] . '</svg>';
}

add_shortcode( 'wfv_html_editor', 'wfv_html_editor_shortcode' );
function wfv_html_editor_shortcode( $atts ) {
	static $instance     = 0;
	static $assets_done  = false;
	$instance++;

	$atts = shortcode_atts(
		array(
			'height' => '520',
			'demo'   => 'yes', // preload a demo snippet, or start blank
		),
		$atts,
		'wfv_html_editor'
	);

	$uid    = 'wfv-h5e-' . $instance . '-' . wp_rand( 1000, 9999 );
	$height = max( 240, absint( $atts['height'] ) );

	$demo_html = "<section style=\"font-family:sans-serif;padding:40px;text-align:center;background:linear-gradient(135deg,#4f46e5,#3730a3);color:#fff;border-radius:12px;\">\n  <h1>Hello, world 👋</h1>\n  <p>Edit visually on the right using the toolbar, or edit code on the left — they stay in sync.</p>\n  <ul style=\"text-align:left;display:inline-block;\">\n    <li>Try <strong>Bold</strong> or <em>Italic</em></li>\n    <li>Pick a heading style</li>\n    <li>Add a bullet list like this one</li>\n  </ul>\n</section>";
	$starting_value = ( 'no' === strtolower( (string) $atts['demo'] ) ) ? '' : $demo_html;

	ob_start();

	// Enqueue assets once per page.
	if ( ! $assets_done ) :
		$assets_done = true;
		wp_enqueue_style( 'wfv-html-editor', WFV_URL . 'assets/css/html-editor.css', array(), WFV_VERSION );
		wp_enqueue_script( 'wfv-codemirror6', WFV_URL . 'assets/codemirror6/wfv-codemirror6.min.js', array(), '6.0.0', true );
		wp_enqueue_script( 'wfv-html-editor', WFV_URL . 'assets/js/html-editor.js', array( 'wfv-codemirror6' ), WFV_VERSION, true );
	endif;

	$handle = 'wfv-html-editor-' . $instance;
	?>
	<div id="<?php echo esc_attr( $uid ); ?>" class="wfv-h5e" data-wfv-config="<?php echo esc_attr( wp_json_encode( array(
		'uid'           => $uid,
		'height'        => $height,
		'startingValue' => $starting_value,
	) ) ); ?>">
		<?php /* Styles enqueued via html-editor.css */ ?>

		<div class="wfv-h5e-toolbar wfv-h5e-toolbar-format">
			<button type="button" data-cmd="bold" title="Bold"><?php echo wfv_h5e_icon( 'bold' ); ?></button>
			<button type="button" data-cmd="italic" title="Italic"><?php echo wfv_h5e_icon( 'italic' ); ?></button>
			<button type="button" data-cmd="underline" title="Underline"><?php echo wfv_h5e_icon( 'underline' ); ?></button>
			<button type="button" data-cmd="strikeThrough" title="Strikethrough"><?php echo wfv_h5e_icon( 'strike' ); ?></button>
			<span class="wfv-h5e-sep"></span>
			<select data-cmd="formatBlock" title="Paragraph style">
				<option value="p">Paragraph</option>
				<option value="h1">Heading 1</option>
				<option value="h2">Heading 2</option>
				<option value="h3">Heading 3</option>
				<option value="h4">Heading 4</option>
				<option value="blockquote">Quote</option>
				<option value="pre">Code block</option>
			</select>
			<span class="wfv-h5e-sep"></span>
			<button type="button" data-cmd="insertUnorderedList" title="Bullet list"><?php echo wfv_h5e_icon( 'list-bullet' ); ?></button>
			<button type="button" data-cmd="insertOrderedList" title="Numbered list"><?php echo wfv_h5e_icon( 'list-number' ); ?></button>
			<button type="button" data-cmd="outdent" title="Decrease indent"><?php echo wfv_h5e_icon( 'outdent' ); ?></button>
			<button type="button" data-cmd="indent" title="Increase indent"><?php echo wfv_h5e_icon( 'indent' ); ?></button>
			<span class="wfv-h5e-sep"></span>
			<button type="button" data-cmd="justifyLeft" title="Align left"><?php echo wfv_h5e_icon( 'align-left' ); ?></button>
			<button type="button" data-cmd="justifyCenter" title="Align center"><?php echo wfv_h5e_icon( 'align-center' ); ?></button>
			<button type="button" data-cmd="justifyRight" title="Align right"><?php echo wfv_h5e_icon( 'align-right' ); ?></button>
			<span class="wfv-h5e-sep"></span>
			<button type="button" data-act="link" title="Insert link"><?php echo wfv_h5e_icon( 'link' ); ?></button>
			<button type="button" data-cmd="unlink" title="Remove link"><?php echo wfv_h5e_icon( 'unlink' ); ?></button>
			<button type="button" data-act="image" title="Insert image"><?php echo wfv_h5e_icon( 'image' ); ?></button>
			<button type="button" data-cmd="insertHorizontalRule" title="Horizontal rule"><?php echo wfv_h5e_icon( 'hr' ); ?></button>
			<span class="wfv-h5e-sep"></span>
			<button type="button" data-cmd="removeFormat" title="Clear formatting"><?php echo wfv_h5e_icon( 'eraser' ); ?></button>
			<button type="button" data-cmd="undo" title="Undo"><?php echo wfv_h5e_icon( 'undo' ); ?></button>
			<button type="button" data-cmd="redo" title="Redo"><?php echo wfv_h5e_icon( 'redo' ); ?></button>
		</div>

		<div class="wfv-h5e-toolbar wfv-h5e-toolbar-code">
			<button type="button" class="wfv-h5e-wide" data-act="demo"><?php echo wfv_h5e_icon( 'file' ); ?> Demo</button>
			<button type="button" class="wfv-h5e-wide" data-act="clear"><?php echo wfv_h5e_icon( 'trash' ); ?> Clear</button>
			<button type="button" class="wfv-h5e-wide" data-act="minify"><?php echo wfv_h5e_icon( 'package' ); ?> Minify</button>
			<button type="button" class="wfv-h5e-wide" data-act="clean" data-pop="clean" title="Cleaning options"><?php echo wfv_h5e_icon( 'eraser' ); ?> Clean <span class="wfv-h5e-caret"></span></button>
			<button type="button" data-act="findreplace" title="Find &amp; replace"><?php echo wfv_h5e_icon( 'search' ); ?></button>
			<button type="button" data-act="lorem" data-pop="lorem" title="Insert placeholder text"><?php echo wfv_h5e_icon( 'type' ); ?></button>
			<button type="button" data-act="color" data-pop="color" title="Color picker &amp; palette" class="wfv-h5e-colorbtn"><?php echo wfv_h5e_icon( 'droplet' ); ?></button>
			<button type="button" class="wfv-h5e-wide" data-act="bootstrap" title="Preview only, not saved into your HTML">Bootstrap: Off</button>
			<span style="flex:1;"></span>
			<button type="button" data-act="font-minus" title="Smaller font"><?php echo wfv_h5e_icon( 'minus' ); ?></button>
			<button type="button" data-act="font-plus" title="Larger font"><?php echo wfv_h5e_icon( 'plus' ); ?></button>
			<button type="button" data-act="copy" title="Copy HTML to clipboard"><?php echo wfv_h5e_icon( 'file' ); ?></button>
		</div>

		<div class="wfv-h5e-pop" data-pop="clean">
			<div class="wfv-h5e-cleanopts">
				<label><input type="checkbox" data-clean="inline" checked> Clear inline styles</label>
				<label><input type="checkbox" data-clean="classes"> Clear classes &amp; IDs</label>
				<label><input type="checkbox" data-clean="comments"> Clear comments</label>
				<label><input type="checkbox" data-clean="empty"> Clear empty tags</label>
				<label><input type="checkbox" data-clean="attrs"> Clear all other attributes</label>
				<label><input type="checkbox" data-clean="images"> Clear images</label>
				<label><input type="checkbox" data-clean="links"> Clear links</label>
				<label><input type="checkbox" data-clean="tables"> Convert tables to divs</label>
				<label><input type="checkbox" data-clean="semantic"> Convert &lt;b&gt;&rarr;&lt;strong&gt;, &lt;i&gt;&rarr;&lt;em&gt;</label>
			</div>
			<div class="wfv-h5e-cleanopts-actions">
				<button type="button" data-act="clean-apply" class="button button-small"><?php echo wfv_h5e_icon( 'check' ); ?> Apply clean</button>
			</div>
		</div>

		<div class="wfv-h5e-findreplace">
			<div class="wfv-h5e-fr-rows">
				<div class="wfv-h5e-fr-row"><input type="text" placeholder="Find…" data-role="find"><input type="text" placeholder="Replace with…" data-role="replace"></div>
			</div>
			<div class="wfv-h5e-fr-actions">
				<button type="button" data-act="fr-add" class="button button-small" title="Add another find/replace rule"><?php echo wfv_h5e_icon( 'plus' ); ?></button>
				<button type="button" data-act="replace-all" class="button button-small">Replace all</button>
			</div>
		</div>

		<div class="wfv-h5e-pop" data-pop="lorem">
			<label class="wfv-h5e-lorem-count">Paragraphs
				<select data-role="lorem-count">
					<option value="1">1</option>
					<option value="2">2</option>
					<option value="3">3</option>
					<option value="5" selected>5</option>
					<option value="10">10</option>
					<option value="20">20</option>
				</select>
			</label>
			<button type="button" data-act="lorem-insert" class="button button-small">Insert text</button>
			<button type="button" data-act="lorem-append" class="button button-small">Append</button>
		</div>

		<div class="wfv-h5e-pop" data-pop="color">
			<div class="wfv-h5e-colorpick">
				<input type="color" data-role="newcolor" value="#6366f1">
				<button type="button" data-act="color-insert" class="button button-small">Insert</button>
				<button type="button" data-act="color-save" class="button button-small" title="Save to palette">Save</button>
			</div>
			<div class="wfv-h5e-swatches" data-role="swatches"></div>
		</div>

		<div class="wfv-h5e-panes">
			<div class="wfv-h5e-pane">
				<div class="wfv-h5e-tabbar">
					<div class="wfv-h5e-dots"><span></span><span></span><span></span></div>
					<div class="wfv-h5e-filename">index.html</div>
				</div>
				<div class="wfv-h5e-codewrap">
					<textarea class="wfv-h5e-source" spellcheck="false"><?php echo esc_textarea( $starting_value ); ?></textarea>
				</div>
			</div>
			<div class="wfv-h5e-pane">
				<div class="wfv-h5e-previewbar">
					<span class="wfv-h5e-previewbar-label">Preview</span>
					<div class="wfv-h5e-devicebtns">
						<button type="button" data-device="" class="wfv-h5e-active" title="Full width"><?php echo wfv_h5e_icon( 'monitor' ); ?></button>
						<button type="button" data-device="tablet" title="Tablet width"><?php echo wfv_h5e_icon( 'tablet' ); ?></button>
						<button type="button" data-device="mobile" title="Mobile width"><?php echo wfv_h5e_icon( 'smartphone' ); ?></button>
					</div>
				</div>
				<div class="wfv-h5e-previewport">
					<iframe class="wfv-h5e-preview" title="Live preview"></iframe>
				</div>
			</div>
		</div>
		<div class="wfv-h5e-status">
			<span><b>HTML</b></span>
			<span class="wfv-h5e-status-cursor">Ln 1, Col 1</span>
			<span class="wfv-h5e-status-size">0 chars · 1 line</span>
			<span class="wfv-h5e-status-msgs"></span>
		</div>
	</div>

	<?php /* Scripts enqueued via html-editor.js */ ?>
	<?php
	return ob_get_clean();
}
