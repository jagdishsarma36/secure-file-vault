/* Secure File Vault — Passwords admin page scripts */
(function($) {
    // WP localized data is available as wfvAdminPasswords.ajaxurl, wfvAdminPasswords.nonce, etc.
    var config = window.wfvAdminPasswords || {};

    (function(){
        var wfvGetPwNonce      = config.get_pw_nonce || '';
        var wfvEntryData       = config.entry_data || {};
        var wfvSharedData      = config.shared_data || {};
        var wfvGetSharesNonce  = config.get_shares_nonce || '';
        var wfvGetSharedNonce  = config.get_shared_nonce || '';
        var wfvOpenPasswordId  = config.open_password_id || 0;
        var wfvRevokeUserNonce   = config.revoke_user_nonce || '';
        var wfvPwlinkRevokeNonce = config.pwlink_revoke_nonce || '';
        var wfvColors = config.colors || {};

        var emptyState  = document.getElementById('wfv-pw-detail-empty');
        var mineDetail  = document.getElementById('wfv-pw-detail-content');
        var sharedDetail = document.getElementById('wfv-pw-detail-shared');
        var toast       = document.getElementById('wfv-pw-toast');
        var currentKind = null; // 'mine' | 'shared' | null

        function showToast(text){
            toast.textContent = text;
            toast.classList.add('wfv-show');
            setTimeout(function(){ toast.classList.remove('wfv-show'); }, 1800);
        }
        function copyText(text){
            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText(text);
            } else {
                var t = document.createElement('textarea');
                t.value = text; document.body.appendChild(t); t.select();
                document.execCommand('copy'); document.body.removeChild(t);
            }
        }
        function initials(title){ return (title || '?').substr(0,1).toUpperCase(); }
        function setActiveListItem(id, kind){
            document.querySelectorAll('.wfv-split-item').forEach(function(el){
                el.classList.toggle('wfv-item-active', el.dataset.id === String(id) && el.dataset.kind === kind);
            });
        }

        // ---------------- Fields (My Vault detail form) ----------------
        var pwInput = document.getElementById('wfv-pw-password-field');

        function setColor(colorKey){
            document.querySelectorAll('#wfv-pw-color-swatches input[type=radio]').forEach(function(input){
                input.checked = ( input.value === colorKey );
            });
        }
        function strengthCheck(){
            var val = pwInput.value;
            var score = 0;
            if ( val.length >= 8 ) score++;
            if ( val.length >= 14 ) score++;
            if ( /[a-z]/.test(val) && /[A-Z]/.test(val) ) score++;
            if ( /[0-9]/.test(val) ) score++;
            if ( /[^A-Za-z0-9]/.test(val) ) score++;
            var pct = val.length ? Math.min(100, (score / 5) * 100) : 0;
            var bar = document.getElementById('wfv-pw-strength-bar');
            var label = document.getElementById('wfv-pw-strength-label');
            bar.style.width = pct + '%';
            var text = '', color = '#d63638';
            if ( ! val.length ) { text = ''; }
            else if ( score <= 1 ) { text = 'Weak'; color = '#d63638'; }
            else if ( score <= 2 ) { text = 'Fair'; color = '#dba617'; }
            else if ( score <= 3 ) { text = 'Good'; color = '#2271b1'; }
            else { text = 'Strong'; color = '#1a7f37'; }
            bar.style.background = color;
            label.textContent = text;
        }
        pwInput.addEventListener('input', strengthCheck);
        document.getElementById('wfv-pw-toggle-visibility').addEventListener('click', function(){
            pwInput.type = ( pwInput.type === 'password' ) ? 'text' : 'password';
        });
        document.getElementById('wfv-pw-generate').addEventListener('click', function(){
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+';
            var arr = new Uint32Array(18);
            window.crypto.getRandomValues(arr);
            var out = '';
            for ( var i = 0; i < 18; i++ ) { out += chars[ arr[i] % chars.length ]; }
            pwInput.value = out;
            pwInput.type = 'text';
            strengthCheck();
        });
        document.getElementById('wfv-pw-copy-username').addEventListener('click', function(){
            copyText( document.getElementById('wfv-pw-username-field').value );
            showToast('Username copied');
        });

        function showEmptyState(){
            emptyState.style.display = '';
            mineDetail.style.display = 'none';
            sharedDetail.style.display = 'none';
            currentKind = null;
        }
        function openCreateItem(){
            emptyState.style.display = 'none';
            sharedDetail.style.display = 'none';
            mineDetail.style.display = '';
            currentKind = 'mine';
            setActiveListItem(null, 'mine');

            document.getElementById('wfv-pw-detail-title-text').textContent = 'New password';
            document.getElementById('wfv-pw-detail-updated').textContent = '';
            document.getElementById('wfv-pw-detail-icon').textContent = '＋';
            document.getElementById('wfv-pw-detail-icon').style.background = '#94a3b8';
            document.getElementById('wfv-pw-form-action').value = 'create_password';
            document.getElementById('wfv-pw-form-id').value = '';
            document.getElementById('wfv-pw-title-field').value = '';
            document.getElementById('wfv-pw-username-field').value = '';
            document.getElementById('wfv-pw-url-field').value = '';
            document.getElementById('wfv-pw-notes-field').value = '';
            document.getElementById('wfv-pw-tags-field').value = '';
            pwInput.value = ''; pwInput.type = 'password';
            setColor('gray');
            strengthCheck();
            document.getElementById('wfv-pw-star-btn').style.display = 'none';
            document.getElementById('wfv-pw-share-btn').style.display = 'none';
            document.getElementById('wfv-pw-delete-btn').style.display = 'none';
            document.getElementById('wfv-pw-title-field').focus();
        }
        function openMineItem(id){
            var data = wfvEntryData[id];
            if ( ! data ) { return; }
            emptyState.style.display = 'none';
            sharedDetail.style.display = 'none';
            mineDetail.style.display = '';
            currentKind = 'mine';
            setActiveListItem(id, 'mine');

            document.getElementById('wfv-pw-detail-title-text').textContent = data.title;
            document.getElementById('wfv-pw-detail-updated').textContent = 'Updated ' + data.updated;
            var icon = document.getElementById('wfv-pw-detail-icon');
            icon.textContent = initials(data.title);
            icon.style.background = wfvColors[data.color] || wfvColors.gray;

            document.getElementById('wfv-pw-form-action').value = 'update_password';
            document.getElementById('wfv-pw-form-id').value = id;
            document.getElementById('wfv-pw-title-field').value = data.title;
            document.getElementById('wfv-pw-username-field').value = data.username;
            document.getElementById('wfv-pw-url-field').value = data.url;
            document.getElementById('wfv-pw-tags-field').value = data.tags;
            pwInput.value = ''; pwInput.type = 'password';
            document.getElementById('wfv-pw-notes-field').value = '';
            setColor(data.color);
            strengthCheck();

            var starBtn = document.getElementById('wfv-pw-star-btn');
            starBtn.style.display = '';
            starBtn.textContent = data.starred ? '★' : '☆';
            starBtn.style.color = data.starred ? '#f4b400' : '';
            document.getElementById('wfv-pw-star-id').value = id;
            document.getElementById('wfv-pw-share-btn').style.display = '';
            document.getElementById('wfv-pw-delete-btn').style.display = '';

            // Fetch the actual password + notes on demand (never preloaded).
            var params = new URLSearchParams();
            params.append('action', 'wfv_get_password');
            params.append('nonce', wfvGetPwNonce);
            params.append('id', id);
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if ( res && res.success && document.getElementById('wfv-pw-form-id').value === String(id) ) {
                        pwInput.value = res.data.password || '';
                        document.getElementById('wfv-pw-notes-field').value = res.data.notes || '';
                        strengthCheck();
                    }
                })
                .catch(function(){});
        }
        function openSharedItem(id){
            var data = wfvSharedData[id];
            if ( ! data ) { return; }
            emptyState.style.display = 'none';
            mineDetail.style.display = 'none';
            sharedDetail.style.display = '';
            currentKind = 'shared';
            setActiveListItem(id, 'shared');

            document.getElementById('wfv-shared-detail-title').textContent = data.title;
            document.getElementById('wfv-shared-detail-by').textContent = 'Shared by ' + data.shared_by;
            var icon = document.getElementById('wfv-shared-detail-icon');
            icon.textContent = initials(data.title);
            icon.style.background = wfvColors[data.color] || wfvColors.gray;
            document.getElementById('wfv-shared-username').textContent = data.username || '—';
            document.getElementById('wfv-shared-pw-display').textContent = '••••••••';
            if ( data.url ) {
                document.getElementById('wfv-shared-url-wrap').style.display = '';
                document.getElementById('wfv-shared-url').textContent = data.url;
            } else {
                document.getElementById('wfv-shared-url-wrap').style.display = 'none';
            }
            document.getElementById('wfv-shared-remove-id').value = id;
            document.getElementById('wfv-shared-copy-username').onclick = function(){ copyText(data.username); showToast('Username copied'); };
        }

        document.getElementById('wfv-pw-delete-btn').addEventListener('click', function(){
            if ( ! confirm('Delete this password entry? This cannot be undone.') ) { return; }
            document.getElementById('wfv-pw-delete-id').value = document.getElementById('wfv-pw-form-id').value;
            document.getElementById('wfv-pw-delete-form').submit();
        });
        document.getElementById('wfv-add-pw-trigger').addEventListener('click', openCreateItem);

        document.querySelectorAll('.wfv-split-item').forEach(function(item){
            item.addEventListener('click', function(){
                var id = item.dataset.id, kind = item.dataset.kind;
                if ( 'shared' === kind ) { openSharedItem(id); } else { openMineItem(id); }
            });
        });

        if ( wfvOpenPasswordId && wfvEntryData[wfvOpenPasswordId] ) {
            openMineItem(wfvOpenPasswordId);
        }

        // ---------------- Shared with me: reveal / copy ----------------
        function fetchSharedPassword(id, cb){
            var params = new URLSearchParams();
            params.append('action', 'wfv_get_shared_password');
            params.append('nonce', wfvGetSharedNonce);
            params.append('id', id);
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params })
                .then(function(r){ return r.json(); })
                .then(function(res){ cb( res && res.success ? res.data.password : '' ); })
                .catch(function(){ cb(''); });
        }
        document.getElementById('wfv-shared-reveal').addEventListener('click', function(){
            var id = document.getElementById('wfv-shared-remove-id').value;
            var display = document.getElementById('wfv-shared-pw-display');
            fetchSharedPassword(id, function(pw){
                if ( ! pw ) { return; }
                display.textContent = pw;
                setTimeout(function(){ display.textContent = '••••••••'; }, 6000);
            });
        });
        document.getElementById('wfv-shared-copy-pw').addEventListener('click', function(){
            var id = document.getElementById('wfv-shared-remove-id').value;
            fetchSharedPassword(id, function(pw){
                if ( ! pw ) { return; }
                copyText(pw);
                showToast('Password copied');
            });
        });

        // ---------------- Sidebar: search + tag filter + tabs ----------------
        var activeTag = null;
        var activeListTab = 'mine';
        function applyFilters(){
            var q = (document.getElementById('wfv-pw-search') || {}).value || '';
            q = q.trim().toLowerCase();
            document.querySelectorAll('.wfv-split-item').forEach(function(row){
                if ( row.dataset.kind !== activeListTab ) { row.style.display = 'none'; return; }
                var textMatch = ! q || row.dataset.search.indexOf(q) !== -1;
                var tagList = ' ' + row.dataset.tags + ' ';
                var tagMatch = ! activeTag || tagList.indexOf(' ' + activeTag + ' ') !== -1;
                row.style.display = ( textMatch && tagMatch ) ? '' : 'none';
            });
            document.querySelectorAll('.wfv-split-empty-list').forEach(function(el){
                el.style.display = ( el.dataset.kind === activeListTab ) ? '' : 'none';
            });
        }
        var searchInput = document.getElementById('wfv-pw-search');
        if ( searchInput ) { searchInput.addEventListener('input', applyFilters); }
        document.querySelectorAll('#wfv-pw-tag-filters button').forEach(function(chip){
            chip.addEventListener('click', function(){
                var tag = chip.dataset.tag;
                if ( activeTag === tag ) {
                    activeTag = null;
                    chip.classList.remove('wfv-tag-active');
                } else {
                    document.querySelectorAll('#wfv-pw-tag-filters button').forEach(function(c){ c.classList.remove('wfv-tag-active'); });
                    activeTag = tag;
                    chip.classList.add('wfv-tag-active');
                }
                applyFilters();
            });
        });
        document.querySelectorAll('[data-list-tab]').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('[data-list-tab]').forEach(function(b){ b.classList.remove('wfv-tab-active'); });
                btn.classList.add('wfv-tab-active');
                activeListTab = btn.dataset.listTab;
                showEmptyState();
                applyFilters();
            });
        });
        applyFilters();

        // ---------------- Share modal ----------------
        var shareModal   = document.getElementById('wfv-pw-share-modal');
        var shareHeading = document.getElementById('wfv-pw-share-heading');
        var shareUserIdField = document.getElementById('wfv-pw-share-password-id');
        var linkPwIdField     = document.getElementById('wfv-pw-link-password-id');
        var userListBox  = document.getElementById('wfv-pw-share-user-list');
        var linkListBox  = document.getElementById('wfv-pw-link-list');

        function escHtml(s){
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
        function loadShareLists(id){
            userListBox.textContent = 'Loading…';
            linkListBox.textContent = 'Loading…';
            var params = new URLSearchParams();
            params.append('action', 'wfv_get_password_shares');
            params.append('nonce', wfvGetSharesNonce);
            params.append('id', id);
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if ( ! res || ! res.success ) {
                        userListBox.textContent = 'Could not load shares.';
                        linkListBox.textContent = '';
                        return;
                    }
                    renderUserShares(res.data.user_shares);
                    renderLinkShares(res.data.link_shares);
                })
                .catch(function(){ userListBox.textContent = 'Could not load shares.'; });
        }
        function renderUserShares(list){
            if ( ! list.length ) { userListBox.innerHTML = '<em>Not shared with anyone yet.</em>'; return; }
            var html = '';
            list.forEach(function(s){
                html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0f0f1;">' +
                    '<span>' + escHtml(s.name) + '</span>' +
                    '<form method="post" style="margin:0;" onsubmit="return confirm(\'Revoke this share?\');">' +
                    '<input type="hidden" name="wfv_action" value="revoke_user_share">' +
                    '<input type="hidden" name="ctx_view" value="passwords">' +
                    '<input type="hidden" name="share_id" value="' + s.id + '">' +
                    '<input type="hidden" name="wfv_revoke_user_share_nonce" value="' + wfvRevokeUserNonce + '">' +
                    '<button type="submit" class="button-link" style="color:#c1272d;font-size:12px;">Revoke</button>' +
                    '</form></div>';
            });
            userListBox.innerHTML = html;
        }
        function renderLinkShares(list){
            if ( ! list.length ) { linkListBox.innerHTML = '<em>No share links yet.</em>'; return; }
            var html = '';
            list.forEach(function(s){
                var statusColor = { active: '#1a7f37', revoked: '#c1272d', expired: '#646970', limit: '#b8590a' }[s.status] || '#646970';
                html += '<div style="border:1px solid #e2e4e7;border-radius:8px;padding:8px 10px;margin-bottom:8px;">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">' +
                    '<strong style="font-size:12px;">' + escHtml(s.label || 'Untitled link') + '</strong>' +
                    '<span style="font-size:11px;color:' + statusColor + ';font-weight:600;text-transform:capitalize;">' + s.status + '</span>' +
                    '</div>' +
                    '<input type="text" readonly value="' + escHtml(s.url) + '" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-family:Consolas,Monaco,monospace;font-size:12px;padding:5px 8px;border-radius:5px;border:1px solid #dcdcde;" onclick="this.select();">' +
                    '<div style="font-size:11px;color:#8c8f94;margin-bottom:6px;">' + (s.has_password ? '🔒 Password protected · ' : '') + 'Views: ' + s.views + (s.expires ? ' · Expires ' + s.expires : '') + '</div>' +
                    ( s.status !== 'revoked' ?
                        '<form method="post" style="margin:0;" onsubmit="return confirm(\'Revoke this link?\');">' +
                        '<input type="hidden" name="wfv_action" value="revoke_password_link">' +
                        '<input type="hidden" name="ctx_view" value="passwords">' +
                        '<input type="hidden" name="link_id" value="' + s.id + '">' +
                        '<input type="hidden" name="wfv_pwlink_revoke_nonce" value="' + wfvPwlinkRevokeNonce + '">' +
                        '<button type="submit" class="button button-small">Revoke</button>' +
                        '</form>' : '' ) +
                    '</div>';
            });
            linkListBox.innerHTML = html;
        }
        document.getElementById('wfv-pw-share-btn').addEventListener('click', function(){
            var id = document.getElementById('wfv-pw-form-id').value;
            if ( ! id ) { return; }
            shareHeading.textContent = 'Share "' + (wfvEntryData[id] ? wfvEntryData[id].title : '') + '"';
            shareUserIdField.value = id;
            linkPwIdField.value = id;
            shareModal.style.display = 'flex';
            loadShareLists(id);
        });
        function closeShareModal(){ shareModal.style.display = 'none'; }
        document.getElementById('wfv-pw-share-modal-close').addEventListener('click', closeShareModal);
        document.getElementById('wfv-pw-share-modal-backdrop').addEventListener('click', closeShareModal);
    })();
})(jQuery);
