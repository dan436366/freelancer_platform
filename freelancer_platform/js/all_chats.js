document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const chatsList = document.getElementById('chatsList');
    const chatItems = chatsList.querySelectorAll('.chat-item');
    
    // Пошук чатів
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            chatItems.forEach(function(item) {
                const name = item.dataset.name ? item.dataset.name.toLowerCase() : '';
                
                if (name.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Показуємо повідомлення, якщо нічого не знайдено
            const visibleItems = Array.from(chatItems).filter(item => item.style.display !== 'none');
            const existingNoResults = chatsList.querySelector('.no-results');
            
            if (visibleItems.length === 0 && searchTerm && !existingNoResults) {
                const noResults = document.createElement('div');
                noResults.className = 'no-results empty-chats';
                noResults.innerHTML = '<h3>Нічого не знайдено</h3><p>Спробуйте змінити пошуковий запит</p>';
                chatsList.appendChild(noResults);
            } else if (visibleItems.length > 0 && existingNoResults) {
                existingNoResults.remove();
            }
        });
    }
    
    // Автооновлення чатів кожні 30 секунд
    setInterval(function() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newChatsList = doc.getElementById('chatsList');
                
                if (newChatsList && chatsList.innerHTML !== newChatsList.innerHTML) {
                    chatsList.innerHTML = newChatsList.innerHTML;
                }
            })
            .catch(error => console.log('Помилка оновлення чатів:', error));
    }, 30000);
});