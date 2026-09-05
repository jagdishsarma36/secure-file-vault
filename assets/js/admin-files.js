/* Secure File Vault — Files admin page scripts */
(function($) {
    // WP localized data is available as wfvFilesData.ajaxurl, wfvFilesData.uploadNonce, etc.
    var config = window.wfvFilesData || {};

    (function(){
        // Accordion toggle for the shares sub-row.
        document.querySelectorAll('.wfv-toggle-shares').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var row = document.getElementById('wfv-shares-' + btn.dataset.file);
                row.style.display = (row.style.display === 'none') ? '' : 'none';
            });
        });

        // Folder edit panel toggle.
        document.querySelectorAll('.wfv-folder-edit-toggle').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var panel = document.getElementById('wfv-folder-edit-' + btn.dataset.folder);
                panel.style.display = (panel.style.display === 'none') ? '' : 'none';
            });
        });

        // Folder share panel toggle.
        document.querySelectorAll('.wfv-folder-share-toggle').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var panel = document.getElementById('wfv-folder-share-' + btn.dataset.folder);
                panel.style.display = (panel.style.display === 'none') ? '' : 'none';
            });
        });

        // New folder form toggle.
        var newFolderBtn = document.getElementById('wfv-new-folder-btn');
        if ( newFolderBtn ) {
            newFolderBtn.addEventListener('click', function(){
                var form = document.getElementById('wfv-new-folder-form');
                form.style.display = (form.style.display === 'none') ? '' : 'none';
            });
        }

        // Live filename/uploader search.
        var searchInput = document.getElementById('wfv-search-input');
        if ( searchInput ) {
            searchInput.addEventListener('input', function(){
                var q = this.value.trim().toLowerCase();
                document.querySelectorAll('.wfv-file-row').forEach(function(row){
                    var match = row.dataset.search.indexOf(q) !== -1;
                    row.style.display = match ? '' : 'none';
                    var sharesRow = document.getElementById('wfv-shares-' + row.dataset.fileId);
                    if ( sharesRow && ! match ) {
                        sharesRow.style.display = 'none';
                    }
                });
            });
        }

        // Drag & drop upload zone (supports multiple files).
        var dz = document.getElementById('wfv-dropzone');
        var fileInput = document.getElementById('wfv-file-input');
        var fileLabel = document.getElementById('wfv-dz-filename');
        var uploadBtn = document.getElementById('wfv-upload-btn');
        function updateDropzone(files){
            if ( ! files || ! files.length ) { return; }
            var names = [];
            for ( var i = 0; i < files.length; i++ ) { names.push(files[i].name); }
            var shown = names.length === 1 ? names[0] : names.length + ' files';
            fileLabel.textContent = shown;
            fileLabel.title = names.join('\n');
            if ( uploadBtn ) { uploadBtn.disabled = false; }
        }
        if ( dz && fileInput ) {
            dz.addEventListener('click', function(){ fileInput.click(); });
            fileInput.addEventListener('change', function(){
                updateDropzone(fileInput.files);
            });
            [ 'dragenter', 'dragover' ].forEach(function(evt){
                dz.addEventListener(evt, function(e){ e.preventDefault(); dz.classList.add('wfv-drag'); });
            });
            [ 'dragleave', 'drop' ].forEach(function(evt){
                dz.addEventListener(evt, function(e){ e.preventDefault(); dz.classList.remove('wfv-drag'); });
            });
            dz.addEventListener('drop', function(e){
                if ( e.dataTransfer.files.length ) {
                    fileInput.files = e.dataTransfer.files;
                    updateDropzone(e.dataTransfer.files);
                }
            });
        }

        // AJAX upload: send files one-at-a-time so a big batch never runs as a
        // single long request (which caused upload timeouts with large files).
        var uploadForm = document.getElementById('wfv-upload-form');
        var progressBox = null;
        if ( uploadForm ) {
            uploadForm.addEventListener('submit', function(e){
                var files = fileInput.files;
                if ( ! files || ! files.length ) { return; }
                e.preventDefault();

                var folderId = (uploadForm.querySelector('input[name="folder_id"]') || {}).value || '0';
                var nonce    = (uploadForm.querySelector('input[name="wfv_upload_nonce"]') || {}).value || '';
                var total    = files.length;
                var done     = 0;
                var failed   = 0;

                if ( uploadBtn ) { uploadBtn.disabled = true; }

                if ( ! progressBox ) {
                    var box = document.createElement('div');
                    box.id = 'wfv-upload-progress';
                    box.className = 'wfv-upload-progress';
                    box.innerHTML =
                        '<div class="wfv-up-label" id="wfv-upload-progress-label">Uploading…</div>' +
                        '<div class="wfv-up-track"><div class="wfv-up-bar" id="wfv-upload-progress-bar"></div></div>';
                    uploadForm.parentNode.insertBefore(box, uploadForm.nextSibling);
                    progressBox = box;
                }
                var label = document.getElementById('wfv-upload-progress-label');
                var bar   = document.getElementById('wfv-upload-progress-bar');

                function updateProgress(){
                    var fileCount = done + failed;
                    var pct = Math.round(fileCount / total * 100);
                    bar.style.width = pct + '%';
                    label.textContent = 'Uploading ' + fileCount + ' of ' + total +
                        (failed ? ' (' + failed + ' failed)' : '') + '…';
                }
                function uploadOne(i){
                    if ( i >= total ) {
                        if ( failed ) {
                            window.alert(failed + ' of ' + total + ' file(s) failed to upload.');
                        }
                        window.location.reload();
                        return;
                    }
                    var fd = new FormData();
                    fd.append('action', 'wfv_upload_ajax');
                    fd.append('wfv_upload_nonce', nonce);
                    fd.append('folder_id', folderId);
                    fd.append('wfv_file', files[i]);

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', config.ajaxurl);
                    xhr.onload = function(){
                        if ( xhr.status === 200 ) {
                            try {
                                var res = JSON.parse(xhr.responseText);
                                if ( res && res.success ) { done++; }
                                else { failed++; }
                            } catch (err) { failed++; }
                        } else { failed++; }
                        updateProgress();
                        uploadOne(i + 1);
                    };
                    xhr.onerror = function(){ failed++; updateProgress(); uploadOne(i + 1); };
                    xhr.send(fd);
                }
                updateProgress();
                uploadOne(0);
            });
        }

        // Admin preview modal.
        document.querySelectorAll('.wfv-preview-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                var url = btn.dataset.url, mime = btn.dataset.mime;
                var body = document.getElementById('wfv-modal-body');
                body.innerHTML = '';
                if ( mime.indexOf('image/') === 0 ) {
                    var img = document.createElement('img');
                    img.src = url;
                    body.appendChild(img);
                } else if ( mime === 'application/pdf' ) {
                    var embed = document.createElement('embed');
                    embed.src = url;
                    embed.type = 'application/pdf';
                    body.appendChild(embed);
                }
                document.getElementById('wfv-modal').style.display = 'flex';
            });
        });
        function wfvCloseModal(){
            document.getElementById('wfv-modal').style.display = 'none';
            document.getElementById('wfv-modal-body').innerHTML = '';
        }
        document.getElementById('wfv-modal-close').addEventListener('click', wfvCloseModal);
        document.getElementById('wfv-modal-backdrop').addEventListener('click', wfvCloseModal);
    })();

    window.wfvCopyLink = function(btn){
        var input = btn.previousElementSibling;
        input.select();
        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText(input.value);
        } else {
            document.execCommand('copy');
        }
        var toast = document.getElementById('wfv-toast');
        toast.textContent = 'Link copied to clipboard';
        toast.classList.add('wfv-show');
        setTimeout(function(){ toast.classList.remove('wfv-show'); }, 1800);
    };
})(jQuery);
