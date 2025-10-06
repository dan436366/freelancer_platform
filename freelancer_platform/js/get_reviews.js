function showTutorReviews(tutorId) {
    // Створюємо AJAX запит для отримання відгуків
    fetch('get_reviews.php?tutor_id=' + tutorId)
        .then(response => response.json())
        .then(data => {
            let reviewsHtml = '';
            
            if (data.reviews && data.reviews.length > 0) {
                data.reviews.forEach(review => {
                    const stars = '⭐'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
                    const date = new Date(review.created_at).toLocaleDateString('uk-UA');
                    
                    reviewsHtml += `
                        <div class="review-item">
                            <div class="review-header">
                                <div>
                                    <div class="review-student">${review.student_name}</div>
                                    <div class="review-date">${date}</div>
                                </div>
                                <div class="review-rating">${stars}</div>
                            </div>
                            <div class="review-comment">${review.comment || 'Без коментаря'}</div>
                        </div>
                    `;
                });
            } else {
                reviewsHtml = `
                    <div class="no-reviews">
                        <div class="no-reviews-icon">💬</div>
                        <h4>Поки що немає відгуків</h4>
                        <p>Цей репетитор ще не отримав жодного відгуку від студентів.</p>
                    </div>
                `;
            }
            
            // Створюємо модальне вікно
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>💬 Відгуки про ${data.tutor_name}</h3>
                        <button class="close" onclick="closeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${reviewsHtml}
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
            
            // Закривати модальне вікно при кліку поза ним
            modal.onclick = function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            }
        })
        .catch(error => {
            console.error('Помилка:', error);
            alert('Помилка завантаження відгуків');
        });
}

function closeModal() {
    const modal = document.querySelector('.modal');
    if (modal) {
        modal.remove();
    }
}

// Закривати модальне вікно при натисканні Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});