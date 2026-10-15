// UniTrade — shared auth-form behavior (Register.php, change_password.php).
// Server-side PHP re-checks all of this too; this file only exists so the
// person finds out about a mismatch before submitting, not instead of the
// real check.

// Toggles a password field between hidden and visible when its eye button
// is clicked. Called from inline onclick="togglePw('fieldId')".
function togglePw(id) {
    var input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Wires up live "do these two passwords match" feedback between two
// password fields, plus blocking submit until they do.
//   pw1Id  — id of the first ("new"/"create") password input
//   pw2Id  — id of the confirmation password input
//   msgId  — id of the <p> element that receives the feedback text
function initPasswordMatchValidation(pw1Id, pw2Id, msgId) {
    var pw1 = document.getElementById(pw1Id);
    var pw2 = document.getElementById(pw2Id);
    var msg = document.getElementById(msgId);
    if (!pw1 || !pw2 || !msg) return;

    function check() {
        if (pw2.value === '') {
            msg.textContent = '';
            msg.className = 'field-msg';
            return true;
        }
        if (pw1.value.length > 0 && pw1.value.length < 8) {
            msg.textContent = 'Password must be at least 8 characters.';
            msg.className = 'field-msg error';
            return false;
        }
        if (pw1.value !== pw2.value) {
            msg.textContent = "Passwords don't match.";
            msg.className = 'field-msg error';
            return false;
        }
        msg.textContent = 'Passwords match.';
        msg.className = 'field-msg ok';
        return true;
    }

    pw1.addEventListener('input', check);
    pw2.addEventListener('input', check);

    var form = pw1.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!check()) {
                e.preventDefault();
                pw2.focus();
            }
        });
    }
}