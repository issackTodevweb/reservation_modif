document.addEventListener('DOMContentLoaded', () => {
    console.log('Script chat.js chargé');

    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const chatLink = document.querySelector('.nav-menu a[href="chat.php"]');

    // Faire défiler vers le bas au chargement
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Indicateur de saisie
    let isTyping = false;
    messageInput.addEventListener('input', () => {
        if (!isTyping) {
            isTyping = true;
            // Optionnel : Envoyer une indication de saisie au serveur via AJAX (non implémenté ici)
        }
        setTimeout(() => {
            isTyping = false;
        }, 2000);
    });

    // Mettre à jour les messages et les notifications toutes les 2 secondes
    let lastMessageId = 0;
    const messages = chatMessages.querySelectorAll('.message');
    if (messages.length > 0) {
        lastMessageId = parseInt(messages[messages.length - 1].dataset.id || 0);
    }

    function fetchNewMessages() {
        fetch('api/get_messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `last_id=${lastMessageId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `message ${msg.user_id == <?php echo $_SESSION['user_id']; ?> ? 'sent' : 'received'}`;
                    messageDiv.dataset.id = msg.id;
                    messageDiv.innerHTML = `
                        <div class="message-header">
                            <i class="fas fa-user-circle"></i>
                            <span class="username">${msg.user_id == <?php echo $_SESSION['user_id']; ?> ? 'Vous' : msg.username}</span>
                        </div>
                        <div class="message-timestamp">${msg.created_at}</div>
                        <div class="message-content">${msg.message}</div>
                    `;
                    chatMessages.appendChild(messageDiv);
                    lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Mettre à jour le badge de notification
            const existingBadge = chatLink.querySelector('.notification-badge');
            if (data.unread_count > 0) {
                if (existingBadge) {
                    existingBadge.textContent = data.unread_count;
                } else {
                    const badge = document.createElement('span');
                    badge.className = 'notification-badge';
                    badge.textContent = data.unread_count;
                    chatLink.appendChild(badge);
                }
            } else if (existingBadge) {
                existingBadge.remove();
            }
        })
        .catch(error => console.error('Erreur lors de la récupération des messages:', error));
    }

    setInterval(fetchNewMessages, 2000);

    // Animation du champ de saisie
    messageInput.addEventListener('focus', () => {
        messageInput.parentElement.classList.add('active');
    });
    messageInput.addEventListener('blur', () => {
        messageInput.parentElement.classList.remove('active');
    });
});