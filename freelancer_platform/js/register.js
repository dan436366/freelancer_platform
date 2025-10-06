// Додаємо плавну анімацію при завантаженні
document.addEventListener('DOMContentLoaded', function() {
    const card = document.querySelector('.register-card');
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        card.style.transition = 'all 0.5s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
    }, 100);
});

const inputs = document.querySelectorAll('input');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});

// Перевірка надійності пароля
const passwordInput = document.getElementById('password');
const strengthDiv = document.getElementById('passwordStrength');

passwordInput.addEventListener('input', function() {
    const password = this.value;
    let strength = '';
    let className = '';

    if (password.length === 0) {
        strength = '';
    } else if (password.length < 6) {
        strength = 'Пароль занадто короткий (мінімум 6 символів)';
        className = 'strength-weak';
    } else if (password.length < 8) {
        strength = 'Слабкий пароль';
        className = 'strength-weak';
    } else if (password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/)) {
        strength = 'Надійний пароль ✓';
        className = 'strength-strong';
    } else {
        strength = 'Середній пароль';
        className = 'strength-medium';
    }

    strengthDiv.textContent = strength;
    strengthDiv.className = 'password-strength ' + className;
});

// Валідація форми
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const role = document.querySelector('input[name="role"]:checked');

    if (!name || !email || !password || !role) {
        e.preventDefault();
        alert('Будь ласка, заповніть всі поля');
        return;
    }

    if (password.length < 6) {
        e.preventDefault();
        alert('Пароль повинен містити мінімум 6 символів');
        return;
    }

    if (name.length < 2) {
        e.preventDefault();
        alert('Ім\'я повинно містити мінімум 2 символи');
        return;
    }
});

document.getElementById('name').focus();