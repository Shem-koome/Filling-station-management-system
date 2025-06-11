const container = document.getElementById('container');
const registerBtn = document.getElementById('register');
const loginBtn = document.getElementById('login');

registerBtn.addEventListener('click', () => {
    container.classList.add("active");
});

loginBtn.addEventListener('click', () => {
    container.classList.remove("active");
});

document.querySelectorAll('.toggle-password').forEach(eyeIcon => {
    eyeIcon.addEventListener('click', () => {
        const input = eyeIcon.previousElementSibling;
        const isPassword = input.type === 'password';

        input.type = isPassword ? 'text' : 'password';
        eyeIcon.classList.toggle('fa-eye');
        eyeIcon.classList.toggle('fa-eye-slash');

        // Add bounce animation class
        eyeIcon.classList.add('bounce');
        setTimeout(() => {
            eyeIcon.classList.remove('bounce');
        }, 400);
    });
});

document.querySelectorAll('.form input').forEach(input => {
    input.addEventListener('focus', () => {
        input.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', () => {
        if (input.value === '') {
            input.parentElement.classList.remove('focused');
        }
    });
});
// Password Match Validation for Sign Up Form
document.addEventListener('DOMContentLoaded', () => {
    const signUpForm = document.querySelector('.form-container.sign-up form');

    if (signUpForm) {
        signUpForm.addEventListener('submit', function (e) {
            const password = signUpForm.querySelector('input[name="password"]').value;
            const confirmPassword = signUpForm.querySelector('input[name="confirm_password"]').value;

            if (password !== confirmPassword) {
                e.preventDefault(); // Stop form submission
                showAlert('Passwords do not match!', 'error');
            }
        });
    }
});