// Анімація з'явлення елементів при завантаженні
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Додаємо анімацію для карток заявок
    const requestCards = document.querySelectorAll('.request-card');
    requestCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateX(0)';
        }, 200 + (index * 50));
    });
});

setInterval(() => {
    console.log('Перевірка нових заявок...');
}, 120000);

// Підтвердження дій
// document.querySelectorAll('form button').forEach(button => {
//     button.addEventListener('click', function(e) {
//         const action = this.textContent.trim();
//         if (action.includes('Прийняти') || action.includes('Відхилити')) {
//             const studentName = this.closest('.request-card').querySelector('.student-name').textContent.trim();
//             if (!confirm(`Ви дійсно хочете ${action.toLowerCase()} заявку від ${studentName}?`)) {
//                 e.preventDefault();
//             }
//         }
//     });
// });