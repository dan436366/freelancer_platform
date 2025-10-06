function filterTutors() {
    const nameFilter = document.getElementById('nameFilter').value.toLowerCase().trim();
    const ratingFilter = document.getElementById('ratingFilter').value;
    const subjectFilter = document.getElementById('subjectFilter').value.toLowerCase().trim();
    const tutorCards = document.querySelectorAll('.tutor-card');
    let visibleCount = 0;
    
    console.log('Фільтри:', { nameFilter, ratingFilter, subjectFilter }); 
    
    tutorCards.forEach(card => {
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const rating = parseFloat(card.getAttribute('data-rating')) || 0;
        const subjects = card.getAttribute('data-subjects') || '';
        
        console.log('Картка:', { name, rating, subjects }); 
        
        let showCard = true;
        
        // Фільтр по імені
        if (nameFilter && !name.includes(nameFilter)) {
            showCard = false;
        }
        
        // Фільтр по рейтингу
        if (ratingFilter && rating < parseFloat(ratingFilter)) {
            showCard = false;
        }
        
        // Фільтр по спеціалізації 
        if (subjectFilter && subjectFilter !== '') {
            
            const subjectsWithSpaces = ' ' + subjects.toLowerCase() + ' ';
            const filterWithSpaces = ' ' + subjectFilter + ' ';
            
            const hasSubject = subjectsWithSpaces.includes(filterWithSpaces);
            
            if (!hasSubject) {
                showCard = false;
            }
        }
        
        if (showCard) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    const resultsCount = document.querySelector('.results-count');
    if (resultsCount) {
        resultsCount.innerHTML = `Знайдено <strong>${visibleCount}</strong> репетиторів`;
    }
    
    console.log('Показано карток:', visibleCount); 
}

function clearFilters() {
    document.getElementById('nameFilter').value = '';
    document.getElementById('ratingFilter').value = '';
    document.getElementById('subjectFilter').value = '';
    filterTutors();
}

document.getElementById('nameFilter').addEventListener('input', filterTutors);
document.getElementById('ratingFilter').addEventListener('change', filterTutors);
document.getElementById('subjectFilter').addEventListener('change', filterTutors);

// Анімації при завантаженні
document.addEventListener('DOMContentLoaded', function() {
    const searchSection = document.querySelector('.search-section');
    const resultsHeader = document.querySelector('.results-header');
    
    setTimeout(() => {
        if (searchSection) {
            searchSection.style.opacity = '1';
            searchSection.style.transform = 'translateY(0)';
        }
    }, 100);
    
    setTimeout(() => {
        if (resultsHeader) {
            resultsHeader.style.opacity = '1';
            resultsHeader.style.transform = 'translateY(0)';
        }
    }, 200);
});