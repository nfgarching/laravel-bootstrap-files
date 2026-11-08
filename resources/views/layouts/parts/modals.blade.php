<!-- Modal für Anzeigen von files -->

<div class="modal fade" id="sharedAttachModal" tabindex="-1"
    aria-labelledby="sharedAttachModal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sharedAttachModal-label"></h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a id="sharedAttachModal-download" href="#"
                        class="btn btn-sm btn-outline-secondary" title="In Popup öffnen"
                        onclick="event.preventDefault(); window.open(this.href, '' , this.dataset.popup);">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Schließen"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="sharedAttachModal-body"></div>
        </div>
    </div>
</div>

<script>
    console.log('init FileModal2');

    window.openFileModal = function(event, btn) {
        const url       = btn.dataset.attachUrl;
        const name      = btn.dataset.attachName;
        const type      = btn.dataset.attachType;
        const icon      = btn.dataset.attachIcon || 'file-earmark';
        const popup     = btn.dataset.attachPopup || 'left=100,top=100,width=800,height=800';
        const popupName = btn.dataset.attachPopupName || 'attachPopup';

        if (event.shiftKey) { window.open(url, popupName, popup); return; }
        if (event.ctrlKey || event.metaKey) { window.open(url, '_blank'); return; }

        document.getElementById('sharedAttachModal-label').innerHTML =
            `<i class="bi bi-${icon} me-2"></i>${name}`;
        const dl = document.getElementById('sharedAttachModal-download');
        dl.href = url;
        dl.dataset.popup = popup;
        dl.dataset.popupName = popupName;

        const body = document.getElementById('sharedAttachModal-body');
        if (['application/pdf', 'pdf', 'PDF', 'html'].includes(type)) {
            body.innerHTML = `<iframe src="${url}" class="w-100 border-0" style="min-height:80vh;" title="${name}"></iframe>`;
        } else if (['image', 'png'].includes(type)) {
            body.innerHTML = `<img src="${url}" alt="${name}" class="img-fluid d-block mx-auto p-3">`;
        } else {
            body.innerHTML = `<div class="p-4 text-center">
                <i class="bi bi-file-earmark fs-1 text-secondary mb-3 d-block"></i>
                <p class="text-muted">${name}</p>
                <a href="${url}" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-download me-1"></i> Herunterladen
                </a></div>`;
        }

        const modalEl = document.getElementById('sharedAttachModal');
        modalEl.addEventListener('hidden.bs.modal', () => { body.innerHTML = ''; }, { once: true });
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };
</script>

{{-- <div class="modal fade" id="sharedAttachModal" tabindex="-1"
    aria-labelledby="sharedAttachModal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sharedAttachModal-label"></h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a id="sharedAttachModal-download" href="#"
                        class="btn btn-sm btn-outline-secondary" title="In Popup öffnen"
                        onclick="event.preventDefault(); window.open(this.href, '' , this.dataset.popup);">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Schließen"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="sharedAttachModal-body"></div>
        </div>
    </div>
</div> --}}


<script>
    // console.log('init btnShowFile');

    function btnShowFile(event) {
        // Now you can access the event object (e) directly
        console.log('init components.button.file.modal: btnShowFile');
        // console.log(event);
        // console.log(event.target);
        // console.log(event.target.dataset);
        // console.log(event.target.dataset.keyid); // Muss angezeigt werden!!!
        var host = window.location.host;
        var pathtodisk = 'http://' + host + '/files/' + event.target.dataset.keyid;
        var pathtofile = 'http://' + host + '/files/' + event.target.dataset.keyid + '/edit';
        // console.log(host); console.log(pathtofile);

        if (event.ctrlKey) {
            console.log('ctrl-key');
            window.open(pathtodisk, '_blank');
        } else if (event.shiftKey) {
            console.log('shift-key');
            window.open(pathtodisk, 'Attachment', 'popup').focus();
            // window.open(pathtodisk, 'Attachment','height=1280,width=800,location=false,menubar=false,status=false,toolbar=false').focus();
        } else if (event.metaKey) {
            console.log('meta-key');
            window.open(pathtofile, '_blank');
        } else {
            console.log('show modal - keyid:', event.target.dataset.keyid);
            window.Livewire.dispatch('modalFile', {
                id: event.target.dataset.keyid
            });
        }
    }
</script>

<script>
    // console.log('init btnShowAttach');

    function btnShowAttach(event) {
        // Now you can access the event object (e) directly
        console.log('init components.button.file.show: btnShowAttach');
        console.log(event);
        console.log(event.target);
        console.log(event.target.dataset);
        console.log(event.target.dataset.keyid); // Muss angezeigt werden!!!
        var host = window.location.host;
        var pathtodisk = 'http://' + host + '/files/' + event.target.dataset.keyid;
        var pathtofile = 'http://' + host + '/files/' + event.target.dataset.keyid + '/edit';
        // console.log(host); console.log(pathtofile);

        if (event.ctrlKey) {
            console.log('press-key:', event.metaKey);
            console.log('ctrl-key');
            if (event.ctrlLeft) {
                console.log('ctrl-left');
            } else {
                console.log('ctrl-right');
            }
            window.open(pathtodisk, '_blank');
        } else if (event.shiftKey) {
            console.log('press-key:', event.metaKey);
            console.log('shift-key');
            if (event.shiftLeft) {
                console.log('shift-left');
            } else {
                console.log('shift-right');
            }
            window.open(pathtodisk, 'Attachment',
                'height=1280,width=800,location=false,menubar=false,status=false,toolbar=false').focus();
            // window.open(pathtodisk, 'Attachment','height=1280,width=800,location=false,menubar=false,status=false,toolbar=false').focus();
        } else if (event.altKey) {
            console.log('press-key:', event.metaKey);
            console.log('alt-key');
            if (event.altLeft) {
                console.log('alt-left');
            } else {
                console.log('alt-right');
            }
        } else {
            console.log('press-key:', event.metaKey);
            console.log('show modal - keyid:', event.target.dataset.keyid);
            window.open(pathtofile, '_blank');
        }

    }
</script>
