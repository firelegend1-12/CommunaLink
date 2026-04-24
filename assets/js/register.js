/**
 * Resident registration wizard (multi-step) + validation.
 */
let currentStep = 1;
const totalSteps = 4;

document.addEventListener('DOMContentLoaded', function () {
    updateStepDisplay();
    setupFormValidation();
    attachFormSubmitGuard();
    attachContactBlurNormalize();
});

const nextBtn = document.getElementById('nextBtn');
const prevBtn = document.getElementById('prevBtn');
if (nextBtn) {
    nextBtn.addEventListener('click', nextStep);
}
if (prevBtn) {
    prevBtn.addEventListener('click', prevStep);
}

function syncWindowStep() {
    window.currentStep = currentStep;
}

function nextStep() {
    if (!validateCurrentStep()) {
        return;
    }
    if (currentStep < totalSteps) {
        currentStep++;
        updateStepDisplay();
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepDisplay();
    }
}

function updateStepDisplay() {
    for (let i = 1; i <= totalSteps; i++) {
        const el = document.getElementById('step' + i);
        if (el) {
            el.style.display = 'none';
        }
    }
    const active = document.getElementById('step' + currentStep);
    if (active) {
        active.style.display = 'block';
    }
    updateButtons();
    if (currentStep === totalSteps) {
        populateReview();
    }
    syncWindowStep();
}

function updateButtons() {
    const prev = document.getElementById('prevBtn');
    const next = document.getElementById('nextBtn');
    const submit = document.getElementById('submitBtn');
    if (prev) {
        prev.style.display = currentStep > 1 ? 'block' : 'none';
    }
    if (next) {
        next.style.display = currentStep < totalSteps ? 'block' : 'none';
    }
    if (submit) {
        submit.style.display = currentStep === totalSteps ? 'block' : 'none';
    }
}

function escapeHtml(text) {
    const s = String(text ?? '');
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalizePhMobileInput(raw) {
    return String(raw || '').trim().replace(/[\s\u00A0-]+/g, '');
}

function normalizePhMobileForRegistration(raw) {
    const s = normalizePhMobileInput(raw);
    if (!s) {
        return null;
    }
    if (/^\+639\d{9}$/.test(s)) {
        return s;
    }
    if (/^09\d{9}$/.test(s)) {
        return '+63' + s.slice(1);
    }
    if (/^639\d{9}$/.test(s)) {
        return '+' + s;
    }
    return null;
}

function setFieldBorder(el, ok) {
    if (!el) {
        return;
    }
    el.style.borderColor = ok ? 'rgba(255, 255, 255, 0.2)' : '#EF4444';
}

function validateContactNumber() {
    const input = document.getElementById('contact_no');
    if (!input) {
        return true;
    }
    const canonical = normalizePhMobileForRegistration(input.value);
    const ok = canonical !== null;
    setFieldBorder(input, ok);
    return ok;
}

function validateCurrentStep() {
    const stepEl = document.getElementById('step' + currentStep);
    if (!stepEl) {
        return false;
    }

    let ok = true;
    const required = stepEl.querySelectorAll('[required]');
    required.forEach(function (el) {
        const v = el.value.trim();
        if (!v) {
            el.style.borderColor = '#EF4444';
            ok = false;
        } else {
            el.style.borderColor = 'rgba(255, 255, 255, 0.2)';
        }
    });

    if (currentStep === 1) {
        const pw = document.getElementById('password');
        const cf = document.getElementById('confirm_password');
        if (pw && cf && pw.value !== cf.value) {
            cf.style.borderColor = '#EF4444';
            alert('Passwords do not match!');
            ok = false;
        }
    }

    if (currentStep === 3) {
        if (!validateContactNumber()) {
            alert('Please enter a valid Philippine mobile number (+639XXXXXXXXX or 09171234567).');
            ok = false;
        } else {
            const input = document.getElementById('contact_no');
            const canonical = normalizePhMobileForRegistration(input.value);
            if (canonical && input) {
                input.value = canonical;
            }
        }
    }

    return ok;
}

/**
 * Full validation for submit (may trigger native constraint UI).
 */
function registrationFormIsValid() {
    const form = document.getElementById('registrationForm');
    if (!form) {
        return false;
    }
    if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
        return false;
    }
    const pw = document.getElementById('password');
    const cf = document.getElementById('confirm_password');
    if (pw && cf && pw.value !== cf.value) {
        return false;
    }
    const contactInput = document.getElementById('contact_no');
    return normalizePhMobileForRegistration(contactInput ? contactInput.value : '') !== null;
}

/**
 * Same rules as submit, without reportValidity (safe for review panel / programmatic checks).
 */
function registrationFormDataComplete() {
    const form = document.getElementById('registrationForm');
    if (!form) {
        return false;
    }
    const fd = new FormData(form);
    const requiredNames = [
        'fullname',
        'email',
        'password',
        'confirm_password',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'place_of_birth',
        'citizenship',
        'civil_status',
        'voter_status',
        'address',
    ];
    for (let i = 0; i < requiredNames.length; i++) {
        const v = fd.get(requiredNames[i]);
        if (v === null || String(v).trim() === '') {
            return false;
        }
    }
    if (String(fd.get('password')) !== String(fd.get('confirm_password'))) {
        return false;
    }
    return normalizePhMobileForRegistration(String(fd.get('contact_no') || '')) !== null;
}

function attachFormSubmitGuard() {
    const form = document.getElementById('registrationForm');
    if (!form) {
        return;
    }
    form.addEventListener('submit', function (e) {
        const contactInput = document.getElementById('contact_no');
        const canonical = normalizePhMobileForRegistration(contactInput ? contactInput.value : '');
        if (canonical && contactInput) {
            contactInput.value = canonical;
        }
        if (!registrationFormIsValid()) {
            e.preventDefault();
            alert('Please fix the errors in the form before submitting. Check all steps, especially your contact number (+63).');
            return false;
        }
    });
}

function attachContactBlurNormalize() {
    const input = document.getElementById('contact_no');
    if (!input) {
        return;
    }
    input.addEventListener('blur', function () {
        const c = normalizePhMobileForRegistration(this.value);
        if (c) {
            this.value = c;
            setFieldBorder(this, true);
        }
    });
}

function populateReview() {
    const container = document.getElementById('reviewContent');
    const form = document.getElementById('registrationForm');
    if (!container || !form) {
        return;
    }

    const fd = new FormData(form);
    function get(name) {
        return fd.get(name);
    }

    const canonicalContact = normalizePhMobileForRegistration(String(get('contact_no') || ''));
    const allOk = canonicalContact !== null && registrationFormDataComplete();

    let html = '';
    html += '<div style="color: #333; background: rgba(255,255,255,0.9); padding: 1rem; border-radius: 8px;">';
    html += '<h4 style="margin-bottom: 1rem; color: #4F46E5;">Please review your information:</h4>';

    html += '<div style="margin-bottom: 1rem;"><strong>Account Information</strong><br>';
    html += 'Name: ' + escapeHtml(get('fullname')) + '<br>';
    html += 'Email: ' + escapeHtml(get('email')) + '<br></div>';

    html += '<div style="margin-bottom: 1rem;"><strong>Personal Information</strong><br>';
    html += 'First Name: ' + escapeHtml(get('first_name')) + '<br>';
    html += 'Middle Initial: ' + escapeHtml(get('middle_initial') || 'N/A') + '<br>';
    html += 'Last Name: ' + escapeHtml(get('last_name')) + '<br>';
    html += 'Gender: ' + escapeHtml(get('gender')) + '<br>';
    html += 'Date of Birth: ' + escapeHtml(get('date_of_birth')) + '<br>';
    html += 'Place of Birth: ' + escapeHtml(get('place_of_birth')) + '<br>';
    html += 'Religion: ' + escapeHtml(get('religion') || 'N/A') + '<br>';
    html += 'Citizenship: ' + escapeHtml(get('citizenship')) + '<br>';
    html += 'Civil Status: ' + escapeHtml(get('civil_status')) + '<br>';
    html += 'Voter Status: ' + escapeHtml(get('voter_status')) + '<br></div>';

    html += '<div style="margin-bottom: 1rem;"><strong>Contact Information</strong><br>';
    html += 'Contact Number: ' + escapeHtml(canonicalContact || String(get('contact_no') || '')) + '<br>';
    html += 'Address: ' + escapeHtml(get('address')) + '<br></div>';

    if (allOk) {
        html += '<p style="color: #10B981; font-weight: 600;">&#10003; All information looks correct. Click &quot;Create Account&quot; to proceed.</p>';
    } else {
        html += '<p style="color: #EF4444; font-weight: 600;">Some information is missing or the contact number is invalid. Use +639XXXXXXXXX or 09171234567, then use Previous to return to step 3 and correct it.</p>';
    }

    html += '</div>';
    container.innerHTML = html;
}

function setupFormValidation() {
    const fullname = document.getElementById('fullname');
    if (fullname) {
        fullname.addEventListener('input', function () {
            const parts = this.value.split(' ');
            const first = parts[0] || '';
            const last = parts[parts.length - 1] || '';
            if (parts.length > 1) {
                const fn = document.getElementById('first_name');
                const ln = document.getElementById('last_name');
                if (fn) {
                    fn.value = first;
                }
                if (ln) {
                    ln.value = last;
                }
            }
        });
    }

    const dob = document.getElementById('date_of_birth');
    if (dob) {
        dob.addEventListener('change', function () {
            const birth = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
        });
    }
}
