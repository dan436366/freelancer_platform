document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    const messageInput = document.getElementById('messageInput');
    const messageForm = document.getElementById('messageForm');
    const sendButton = document.getElementById('sendButton');
    
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    scrollToBottom();
    
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        
        sendButton.disabled = !this.value.trim();
    });
    
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim()) {
                messageForm.submit();
            }
        }
    });
    
    messageInput.focus();
    
    sendButton.disabled = !messageInput.value.trim();
    
    setInterval(function() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMessages = doc.getElementById('chatMessages').innerHTML;
                
                if (chatMessages.innerHTML !== newMessages) {
                    const wasAtBottom = chatMessages.scrollTop >= chatMessages.scrollHeight - chatMessages.clientHeight - 50;
                    chatMessages.innerHTML = newMessages;
                    
                    if (wasAtBottom) {
                        scrollToBottom();
                    }
                }
            })
            .catch(error => console.log('Помилка оновлення чату:', error));
    }, 5000);
    
    function markMessagesAsRead() {
        fetch('mark_messages_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'request_id=<?= $request_id ?>'
        }).catch(error => console.log('Помилка позначення повідомлень як прочитаних:', error));
    }
    
    window.addEventListener('focus', markMessagesAsRead);
    
    markMessagesAsRead();
});