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
});



