document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('reset-form');
    var passwordField = document.getElementById('password');
    var confirmField = document.getElementById('confirm_password');
    var reqLength = document.getElementById('req-length');
    var reqNumber = document.getElementById('req-number');
    var reqSpecial = document.getElementById('req-special');
    var reqMatch = document.getElementById('req-match');
    var submitBtn = document.getElementById('submit-btn');

    if (!form || !passwordField || !confirmField || !submitBtn) {
        return;
    }

    function hasNumber(value) {
        return /[0-9]/.test(value);
    }

    function hasSpecial(value) {
        return /[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(value);
    }

    function setReqColor(el, isMet) {
        if (!el) {
            return;
        }
        el.style.color = isMet ? '#22c55e' : '#ef4444';
    }

    function updateRequirementColors() {
        var password = passwordField.value || '';
        var confirmPassword = confirmField.value || '';
        setReqColor(reqLength, password.length >= 8);
        setReqColor(reqNumber, hasNumber(password));
        setReqColor(reqSpecial, hasSpecial(password));
        setReqColor(reqMatch, password.length > 0 && confirmPassword.length > 0 && password === confirmPassword);
    }

    passwordField.addEventListener('input', updateRequirementColors);
    confirmField.addEventListener('input', updateRequirementColors);
    updateRequirementColors();

    form.addEventListener('submit', function (e) {
        var password = passwordField.value || '';
        var confirmPassword = confirmField.value || '';
        var btnText = submitBtn.querySelector('.btn-text');
        var btnLoading = submitBtn.querySelector('.btn-loading');

        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return;
        }

        if (password.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long!');
            return;
        }

        if (!hasNumber(password)) {
            e.preventDefault();
            alert('Password must contain at least one number (0-9)!');
            return;
        }

        if (!hasSpecial(password)) {
            e.preventDefault();
            alert('Password must contain at least one special character (!@#$%^&*)!');
            return;
        }

        if (btnText && btnLoading) {
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';
        }
        submitBtn.disabled = true;
    });
});
