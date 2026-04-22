document.addEventListener('DOMContentLoaded', function () {
	var toggle = document.getElementById('toggle-password');
	var input = document.getElementById('password');
	var eye = document.getElementById('icon-eye');
	var eyeOff = document.getElementById('icon-eye-off');
	if (toggle && input && eye && eyeOff) {
		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var start = input.selectionStart;
			var end = input.selectionEnd;
			var isHidden = input.type === 'password';
			input.type = isHidden ? 'text' : 'password';
			try { input.setSelectionRange(start, end); } catch (_) {}
			eye.style.display = isHidden ? 'none' : '';
			eyeOff.style.display = isHidden ? '' : 'none';
			toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
		});
	}

	var linkedForm = document.getElementById('linked-choice-form');
	var choiceInput = document.getElementById('account_choice');
	var residentBtn = document.getElementById('choose-resident-choice');
	var showOfficialBtn = document.getElementById('show-official-choice');
	var officialPanel = document.getElementById('official-choice-panel');
	var officialPasswordInput = document.getElementById('official_password');
	var officialSubmitBtn = document.getElementById('submit-official-choice');

	function isOfficialPanelVisible() {
		if (!officialPanel) {
			return false;
		}
		return window.getComputedStyle(officialPanel).display !== 'none';
	}

	function showOfficialPanel() {
		if (!officialPanel) {
			return;
		}

		officialPanel.style.display = 'block';
		if (choiceInput) {
			choiceInput.value = 'official';
		}
		if (officialPasswordInput) {
			officialPasswordInput.focus();
		}
	}

	if (residentBtn && linkedForm && choiceInput) {
		residentBtn.addEventListener('click', function (e) {
			e.preventDefault();
			choiceInput.value = 'resident';
			if (typeof linkedForm.requestSubmit === 'function') {
				linkedForm.requestSubmit(residentBtn);
			} else {
				linkedForm.submit();
			}
		});
	}

	if (showOfficialBtn) {
		showOfficialBtn.addEventListener('click', function (e) {
			e.preventDefault();
			showOfficialPanel();
		});
	}

	if (officialSubmitBtn && choiceInput) {
		officialSubmitBtn.addEventListener('click', function () {
			choiceInput.value = 'official';
		});
	}

	if (linkedForm && choiceInput) {
		linkedForm.addEventListener('submit', function () {
			if (!choiceInput.value) {
				choiceInput.value = isOfficialPanelVisible() ? 'official' : 'resident';
			}
		});
	}
});



