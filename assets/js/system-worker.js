// System-wide Service Worker Registration and Offline Tracking
document.addEventListener('DOMContentLoaded', () => {
    // 1. Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then(reg => console.log('System Service Worker registered successfully! Scope:', reg.scope))
                .catch(err => console.log('System Service Worker registration failed: ', err));
        });
    }

    // 2. Offline/Online Toast Logic
    const toastHTML = `
        <div id="global-offline-toast" style="display: none; position: fixed; top: 16px; right: 16px; z-index: 10000; padding: 12px 16px; border-radius: 8px; box-shadow: 0 8px 20px rgba(15,23,42,0.12); min-width: 240px; max-width: 360px; font-size: 13px; font-weight: 600; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; transition: opacity 0.5s ease-out;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center;">
                    <i id="global-offline-icon" class="fas fa-wifi" style="margin-right:8px; font-size:16px; position:relative;">
                        <div id="global-offline-slash" style="position:absolute; top:-2px; left:8px; width:2px; height:20px; background:#dc2626; transform:rotate(-45deg);"></div>
                    </i>
                    <span id="global-offline-text">You are offline</span>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', toastHTML);
    const toast = document.getElementById('global-offline-toast');
    const toastIcon = document.getElementById('global-offline-icon');
    const toastSlash = document.getElementById('global-offline-slash');
    const toastText = document.getElementById('global-offline-text');
    let fadeTimeout = null;

    function applyOfflineStyle() {
        toast.style.background = '#fef2f2';
        toast.style.borderLeft = '4px solid #dc2626';
        toast.style.color = '#991b1b';
        toastIcon.className = 'fas fa-wifi';
        toastSlash.style.display = 'block';
        toastSlash.style.background = '#dc2626';
        toastText.textContent = 'You are offline';
        toast.style.opacity = '1';
        toast.style.display = 'block';
    }

    function applyOnlineStyle() {
        toast.style.background = '#ecfdf3';
        toast.style.borderLeft = '4px solid #16a34a';
        toast.style.color = '#166534';
        toastIcon.className = 'fas fa-wifi';
        toastSlash.style.display = 'none';
        toastText.textContent = 'Connection Established';
        toast.style.opacity = '1';
        toast.style.display = 'block';
    }

    // Flag to ensure "Connection Established" only shows if they were previously offline while viewing the page
    let wasOffline = false;

    function updateOnlineStatus() {
        if (fadeTimeout) {
            clearTimeout(fadeTimeout);
        }

        if (!navigator.onLine) {
            wasOffline = true;
            applyOfflineStyle();
        } else {
            if (wasOffline) {
                applyOnlineStyle();
                fadeTimeout = setTimeout(() => {
                    toast.style.opacity = '0';
                    fadeTimeout = setTimeout(() => {
                        toast.style.display = 'none';
                    }, 500); // Wait for transition to finish
                }, 2500); // Show "Connection Established" for 2.5 seconds
            } else {
                toast.style.display = 'none';
            }
            wasOffline = false;
        }
    }
    
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    
    if(!navigator.onLine) {
        updateOnlineStatus();
    }
});
