            </main>
        </div>
        <div id="residentToastContainer" class="ui-toast-container" aria-live="polite" aria-atomic="true"></div>

        <div id="residentConfirmOverlay" class="ui-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="residentConfirmTitle">
            <div class="ui-modal">
                <div id="residentConfirmTitle" class="ui-modal-header">Confirm Action</div>
                <div id="residentConfirmMessage" class="ui-modal-body"></div>
                <div class="ui-modal-footer">
                    <button id="residentConfirmCancel" type="button" class="ui-btn ui-btn-secondary">Cancel</button>
                    <button id="residentConfirmOk" type="button" class="ui-btn ui-btn-primary">Confirm</button>
                </div>
            </div>
        </div>

        <div id="residentPromptOverlay" class="ui-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="residentPromptTitle">
            <div class="ui-modal">
                <div id="residentPromptTitle" class="ui-modal-header">Input Required</div>
                <div class="ui-modal-body">
                    <div id="residentPromptMessage"></div>
                    <input id="residentPromptInput" class="ui-modal-input" type="text" maxlength="255" />
                </div>
                <div class="ui-modal-footer">
                    <button id="residentPromptCancel" type="button" class="ui-btn ui-btn-secondary">Cancel</button>
                    <button id="residentPromptOk" type="button" class="ui-btn ui-btn-primary">Submit</button>
                </div>
            </div>
        </div>

        <?php include 'bottom-nav.php'; ?>
    </div>
<script>
    // Shared resident UI helpers for consistent toast/confirm/prompt placement and style.
    (function() {
        function byId(id) {
            return document.getElementById(id);
        }

        window.residentShowToast = function(message, type) {
            var toastType = type === 'error' ? 'error' : 'success';
            var container = byId('residentToastContainer');
            if (!container) {
                return;
            }

            var toast = document.createElement('div');
            toast.className = 'ui-toast ' + toastType;
            toast.innerHTML = (toastType === 'error' ? '<i class="fas fa-exclamation-circle" aria-hidden="true"></i>' : '<i class="fas fa-check-circle" aria-hidden="true"></i>')
                + '<span>' + String(message || '') + '</span>';

            container.appendChild(toast);

            setTimeout(function() {
                toast.classList.add('hide');
                setTimeout(function() {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 260);
            }, 2600);
        };

        window.residentConfirm = function(message, onConfirm, options) {
            var opts = options || {};
            var overlay = byId('residentConfirmOverlay');
            var title = byId('residentConfirmTitle');
            var msg = byId('residentConfirmMessage');
            var ok = byId('residentConfirmOk');
            var cancel = byId('residentConfirmCancel');
            if (!overlay || !title || !msg || !ok || !cancel) {
                return;
            }

            title.textContent = opts.title || 'Confirm Action';
            msg.textContent = String(message || 'Are you sure?');
            ok.textContent = opts.confirmText || 'Confirm';
            cancel.textContent = opts.cancelText || 'Cancel';
            ok.className = 'ui-btn ' + (opts.danger ? 'ui-btn-danger' : 'ui-btn-primary');

            overlay.classList.remove('hidden');

            var close = function() {
                overlay.classList.add('hidden');
                document.removeEventListener('keydown', escHandler);
            };
            var escHandler = function(e) {
                if (e.key === 'Escape') {
                    close();
                }
            };
            document.addEventListener('keydown', escHandler);

            var newOk = ok.cloneNode(true);
            var newCancel = cancel.cloneNode(true);
            ok.parentNode.replaceChild(newOk, ok);
            cancel.parentNode.replaceChild(newCancel, cancel);

            newCancel.addEventListener('click', close);
            overlay.onclick = function(e) {
                if (e.target === overlay) {
                    close();
                }
            };
            newOk.addEventListener('click', function() {
                close();
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
        };

        window.residentPrompt = function(message, onSubmit, options) {
            var opts = options || {};
            var overlay = byId('residentPromptOverlay');
            var title = byId('residentPromptTitle');
            var msg = byId('residentPromptMessage');
            var input = byId('residentPromptInput');
            var ok = byId('residentPromptOk');
            var cancel = byId('residentPromptCancel');
            if (!overlay || !title || !msg || !input || !ok || !cancel) {
                return;
            }

            title.textContent = opts.title || 'Input Required';
            msg.textContent = String(message || 'Enter value');
            input.value = opts.defaultValue || '';
            input.placeholder = opts.placeholder || '';
            ok.textContent = opts.confirmText || 'Submit';
            cancel.textContent = opts.cancelText || 'Cancel';
            ok.className = 'ui-btn ' + (opts.danger ? 'ui-btn-danger' : 'ui-btn-primary');

            overlay.classList.remove('hidden');
            setTimeout(function() { input.focus(); }, 20);

            var close = function() {
                overlay.classList.add('hidden');
                document.removeEventListener('keydown', escHandler);
            };
            var escHandler = function(e) {
                if (e.key === 'Escape') {
                    close();
                }
                if (e.key === 'Enter') {
                    submit();
                }
            };
            document.addEventListener('keydown', escHandler);

            var submit = function() {
                var value = String(input.value || '').trim();
                if (opts.required && !value) {
                    residentShowToast(opts.requiredMessage || 'This field is required.', 'error');
                    input.focus();
                    return;
                }
                close();
                if (typeof onSubmit === 'function') {
                    onSubmit(value);
                }
            };

            var newOk = ok.cloneNode(true);
            var newCancel = cancel.cloneNode(true);
            ok.parentNode.replaceChild(newOk, ok);
            cancel.parentNode.replaceChild(newCancel, cancel);

            newCancel.addEventListener('click', function() {
                close();
                if (typeof onSubmit === 'function') {
                    onSubmit(null);
                }
            });
            overlay.onclick = function(e) {
                if (e.target === overlay) {
                    close();
                    if (typeof onSubmit === 'function') {
                        onSubmit(null);
                    }
                }
            };
            newOk.addEventListener('click', submit);
        };
    })();

// Mobile scroll fix: force inline styles that override all CSS (highest specificity)
(function() {
    if (window.innerWidth > 767) return;
    var s = function(el, props) {
        if (!el) return;
        for (var k in props) el.style.setProperty(k, props[k], 'important');
    };
    s(document.documentElement, { 'height': 'auto', 'overflow-y': 'auto', 'overflow-x': 'hidden' });
    s(document.body, { 'height': 'auto', 'overflow-y': 'auto', 'overflow-x': 'hidden', 'touch-action': 'manipulation' });
    s(document.querySelector('.page-container'), { 'display': 'block', 'height': 'auto', 'min-height': 'auto', 'overflow': 'visible' });
    s(document.querySelector('.main-content'), { 'display': 'block', 'height': 'auto', 'min-height': 'auto', 'overflow': 'visible' });
    s(document.querySelector('.page-main'), { 'height': 'auto', 'min-height': 'auto', 'overflow': 'visible' });
})();
</script>
</body>
</html> 