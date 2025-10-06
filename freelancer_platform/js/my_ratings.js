function deleteRating(ratingId) {
    if (confirm('Ви впевнені, що хочете видалити цей відгук? Цю дію неможливо скасувати.')) {
        // Створюємо форму для видалення
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_rating.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'rating_id';
        input.value = ratingId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Анімація появи карток
document.addEventListener('DOMContentLoaded', function() {
    const ratingCards = document.querySelectorAll('.rating-card');
    ratingCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateX(-30px)';
        card.style.animation = `slideInFromLeft 0.6s ease-out ${index * 0.1}s forwards`;
    });
});