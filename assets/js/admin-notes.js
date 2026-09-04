/* Secure File Vault — Notes admin page scripts */
(function($) {
    // WP localized data is available as wfvAdminNotes.ajaxurl, wfvAdminNotes.nonce, etc.
    var config = window.wfvAdminNotes || {};

    (function(){
        var wfvNotesData    = config.notes_data || {};
        var wfvColors       = config.colors || {};
        var wfvReorderNonce = config.reorder_nonce || '';
        var editorReady     = false;

        var emptyState = document.getElementById('wfv-note-detail-empty');
        var content    = document.getElementById('wfv-note-detail-content');
        var toast      = document.getElementById('wfv-note-toast');
        var form       = document.getElementById('wfv-note-detail-form');
        var actionInput = document.getElementById('wfv-note-form-action');
        var idInput      = document.getElementById('wfv-note-form-id');
        var titleInput   = document.getElementById('wfv-note-title-field');
        var tagsInput    = document.getElementById('wfv-note-tags-field');
        var editorTextarea = document.getElementById('wfv_editor');
        var pinBtn  = document.getElementById('wfv-note-pin-btn');
        var pinIdField = document.getElementById('wfv-note-pin-id');
        var deleteBtn = document.getElementById('wfv-note-delete-btn');

        function showToast(text){
            toast.textContent = text;
            toast.classList.add('wfv-show');
            setTimeout(function(){ toast.classList.remove('wfv-show'); }, 1800);
        }
        function initials(title){ return (title || '?').substr(0,1).toUpperCase(); }
        function setColor(colorKey){
            document.querySelectorAll('#wfv-note-color-swatches input[type=radio]').forEach(function(input){
                input.checked = ( input.value === colorKey );
            });
        }
        function setActiveListItem(id){
            document.querySelectorAll('.wfv-split-item').forEach(function(el){
                el.classList.toggle('wfv-item-active', el.dataset.id === String(id));
            });
        }

        function initEditor( html ){
            if ( editorReady && window.wp && wp.editor ) {
                wp.editor.remove( 'wfv_editor' );
                editorReady = false;
            }
            editorTextarea.value = html || '';
            if ( window.wp && wp.editor ) {
                wp.editor.initialize( 'wfv_editor', {
                    tinymce: {
                        wpautop: true,
                        menubar: false,
                        branding: false,
                        statusbar: false,
                        height: 300,
                        plugins: 'lists,link,textcolor,paste,wordpress,wplink,charmap,hr',
                        toolbar1: 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,removeformat,undo,redo'
                    },
                    quicktags: { buttons: 'strong,em,link,ul,ol,li,close' },
                    mediaButtons: false
                } );
                editorReady = true;
            }
        }
        function syncEditor(){
            if ( window.tinymce && tinymce.get( 'wfv_editor' ) ) { tinymce.triggerSave(); }
        }

        function showEmptyState(){
            emptyState.style.display = '';
            content.style.display = 'none';
            if ( editorReady ) { wp.editor.remove( 'wfv_editor' ); editorReady = false; }
        }
        function openCreateNote(){
            emptyState.style.display = 'none';
            content.style.display = '';
            setActiveListItem(null);

            document.getElementById('wfv-note-detail-title-text').textContent = 'New note';
            document.getElementById('wfv-note-detail-updated').textContent = '';
            var icon = document.getElementById('wfv-note-detail-icon');
            icon.textContent = '＋';
            icon.style.background = '#94a3b8';
            actionInput.value = 'create_note';
            idInput.value = '';
            titleInput.value = '';
            tagsInput.value = '';
            setColor('yellow');
            pinBtn.style.display = 'none';
            deleteBtn.style.display = 'none';
            initEditor('');
            titleInput.focus();
        }
        function openNote(id){
            var data = wfvNotesData[id];
            if ( ! data ) { return; }
            emptyState.style.display = 'none';
            content.style.display = '';
            setActiveListItem(id);

            document.getElementById('wfv-note-detail-title-text').textContent = data.title || '(untitled)';
            document.getElementById('wfv-note-detail-updated').textContent = 'Updated ' + data.updated;
            var icon = document.getElementById('wfv-note-detail-icon');
            icon.textContent = initials(data.title);
            icon.style.background = wfvColors[data.color] || wfvColors.yellow;

            actionInput.value = 'update_note';
            idInput.value = id;
            titleInput.value = data.title;
            tagsInput.value = data.tags;
            setColor(data.color);

            pinBtn.style.display = '';
            pinBtn.style.opacity = data.pinned ? '1' : '.4';
            pinBtn.title = data.pinned ? 'Unpin' : 'Pin';
            pinIdField.value = id;
            deleteBtn.style.display = '';
            deleteBtn.dataset.id = id;

            initEditor( data.content );
        }

        document.getElementById('wfv-add-note-trigger').addEventListener('click', openCreateNote);
        document.querySelectorAll('.wfv-split-item').forEach(function(el){
            el.addEventListener('click', function(){ openNote( el.dataset.id ); });
        });
        deleteBtn.addEventListener('click', function(){
            if ( ! deleteBtn.dataset.id ) { return; }
            if ( ! confirm('Delete this note? This cannot be undone.') ) { return; }
            document.getElementById('wfv-note-delete-id').value = deleteBtn.dataset.id;
            document.getElementById('wfv-note-delete-form').submit();
        });
        form.addEventListener('submit', syncEditor);

        // Combined search + pinned/all + tag filter.
        var activeTag = null;
        var activeListTab = 'all';
        function applyFilters(){
            var q = (document.getElementById('wfv-notes-search') || {}).value || '';
            q = q.trim().toLowerCase();
            document.querySelectorAll('.wfv-split-item').forEach(function(el){
                var textMatch = ! q || el.dataset.search.indexOf(q) !== -1;
                var tagList = ' ' + el.dataset.tags + ' ';
                var tagMatch = ! activeTag || tagList.indexOf(' ' + activeTag + ' ') !== -1;
                var pinMatch = ( activeListTab === 'all' ) || el.dataset.pinned === '1';
                el.style.display = ( textMatch && tagMatch && pinMatch ) ? '' : 'none';
            });
        }
        var searchInput = document.getElementById('wfv-notes-search');
        if ( searchInput ) { searchInput.addEventListener('input', applyFilters); }
        document.querySelectorAll('.wfv-split-tabs [data-list-tab]').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.wfv-split-tabs [data-list-tab]').forEach(function(b){ b.classList.remove('wfv-tab-active'); });
                btn.classList.add('wfv-tab-active');
                activeListTab = btn.dataset.listTab;
                applyFilters();
            });
        });
        document.querySelectorAll('#wfv-notes-tag-filters button').forEach(function(chip){
            chip.addEventListener('click', function(){
                var tag = chip.dataset.tag;
                if ( activeTag === tag ) {
                    activeTag = null;
                    chip.classList.remove('wfv-tag-active');
                } else {
                    document.querySelectorAll('#wfv-notes-tag-filters button').forEach(function(c){ c.classList.remove('wfv-tag-active'); });
                    activeTag = tag;
                    chip.classList.add('wfv-tag-active');
                }
                applyFilters();
            });
        });

        // Drag-and-drop manual reordering (swap-on-drop), only in Custom order mode.
        var list = document.getElementById('wfv-notes-list');
        var dragSrc = null;
        list.querySelectorAll('.wfv-split-item[draggable="true"]').forEach(function(item){
            item.addEventListener('dragstart', function(e){
                dragSrc = item;
                item.classList.add('wfv-dragging');
                e.stopPropagation();
            });
            item.addEventListener('dragend', function(){ item.classList.remove('wfv-dragging'); dragSrc = null; });
            item.addEventListener('dragover', function(e){ e.preventDefault(); });
            item.addEventListener('drop', function(e){
                e.preventDefault();
                e.stopPropagation();
                if ( ! dragSrc || dragSrc === item ) { return; }
                var srcNext = dragSrc.nextSibling;
                var tgtNext = item.nextSibling;
                list.insertBefore(dragSrc, tgtNext);
                list.insertBefore(item, srcNext);
                saveNoteOrder();
            });
        });
        function saveNoteOrder(){
            var ids = Array.prototype.map.call( list.querySelectorAll('.wfv-split-item'), function(c){ return c.dataset.id; } );
            var params = new URLSearchParams();
            params.append('action', 'wfv_reorder_notes');
            params.append('nonce', wfvReorderNonce);
            ids.forEach(function(id){ params.append('order[]', id); });
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params }).catch(function(){});
        }
    })();
})(jQuery);
