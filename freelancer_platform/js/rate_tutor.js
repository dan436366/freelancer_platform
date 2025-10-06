document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star');
    const ratingValue = document.getElementById('ratingValue');
    const submitBtn = document.getElementById('submitBtn');
    const ratingText = document.getElementById('ratingText');
    
    const ratingLabels = {
        1: 'Погано - 1 з 5',
        2: 'Незадовільно - 2 з 5', 
        3: 'Задовільно - 3 з 5',
        4: 'Добре - 4 з 5',
        5: 'Відмінно - 5 з 5'
    };
    
    // Встановлюємо поточну оцінку якщо є
    const currentRating = ratingValue.value;
    if (currentRating) {
        updateStars(currentRating);
        updateRatingText(currentRating);
        submitBtn.disabled = false;
    }
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            ratingValue.value = rating;
            updateStars(rating);
            updateRatingText(rating);
            submitBtn.disabled = false;
            
            // Анімація пульсації для вибраної зірки
            this.classList.add('star-pulse');
            setTimeout(() => {
                this.classList.remove('star-pulse');
            }, 300);
        });
        
        star.addEventListener('mouseover', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            highlightStars(rating);
            updateRatingText(rating);
        });
    });
    
    document.getElementById('ratingStars').addEventListener('mouseleave', function() {
        const currentRating = ratingValue.value;
        if (currentRating) {
            updateStars(currentRating);
            updateRatingText(currentRating);
        } else {
            clearStars();
            ratingText.textContent = 'Оберіть оцінку';
        }
    });
    
    function updateStars(rating) {
        stars.forEach((star, index) => {
            star.classList.remove('filled', 'active');
            if (index < rating) {
                star.classList.add('filled');
            }
        });
    }
    
    function highlightStars(rating) {
        stars.forEach((star, index) => {
            star.classList.remove('filled', 'active');
            if (index < rating) {
                star.classList.add('active');
            }
        });
    }
    
    function clearStars() {
        stars.forEach(star => {
            star.classList.remove('filled', 'active');
        });
    }
    
    function updateRatingText(rating) {
        ratingText.textContent = ratingLabels[rating] || 'Оберіть оцінку';
    }
    
    
    const ratingSection = document.querySelector('.rating-section');
    ratingSection.style.opacity = '0';
    ratingSection.style.transform = 'translateY(30px)';
    
    setTimeout(() => {
        ratingSection.style.transition = 'all 0.6s ease';
        ratingSection.style.opacity = '1';
        ratingSection.style.transform = 'translateY(0)';
    }, 100);
});