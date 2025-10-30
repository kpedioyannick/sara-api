/**
 * Script JavaScript pour les messages en temps réel avec Mercure
 */

class RealtimeMessages {
    constructor(mercureUrl, conversationId) {
        this.mercureUrl = mercureUrl;
        this.conversationId = conversationId;
        this.eventSource = null;
        this.messageContainer = null;
        this.messageForm = null;
        this.apiBaseUrl = '/api/coach/messages';
        this.token = this.getAuthToken();
    }

    /**
     * Récupère le token JWT depuis le localStorage
     */
    getAuthToken() {
        return localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');
    }

    /**
     * Initialise la connexion temps réel
     */
    init() {
        this.setupEventListeners();
        this.connectToMercure();
        this.loadMessages();
    }

    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Formulaire d'envoi de message
        this.messageForm = document.getElementById('messageForm');
        if (this.messageForm) {
            this.messageForm.addEventListener('submit', (e) => this.sendMessage(e));
        }

        // Bouton de marquage comme lu
        const markAsReadBtn = document.getElementById('markAsReadBtn');
        if (markAsReadBtn) {
            markAsReadBtn.addEventListener('click', () => this.markAsRead());
        }
    }

    /**
     * Se connecte au stream SSE pour recevoir les mises à jour temps réel
     */
    connectToSSE() {
        if (!this.token) {
            console.error('Token JWT non trouvé');
            return;
        }

        this.eventSource = new EventSource(this.streamUrl, {
            withCredentials: false
        });

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleRealtimeMessage(data);
            } catch (error) {
                console.error('Erreur lors du parsing du message:', error);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error('Erreur de connexion SSE:', error);
            // Tentative de reconnexion après 5 secondes
            setTimeout(() => this.connectToSSE(), 5000);
        };

        console.log('✅ Connecté au stream SSE pour les messages temps réel');
    }

    /**
     * Traite un message reçu en temps réel
     */
    handleRealtimeMessage(data) {
        if (data.type === 'message') {
            this.addMessageToUI(data.data);
            this.scrollToBottom();
        } else if (data.type === 'ping') {
            // Ping reçu, connexion active
            console.log('Ping reçu - connexion active');
        }
    }

    /**
     * Charge les messages existants
     */
    async loadMessages() {
        try {
            const response = await fetch(`${this.apiBaseUrl}?conversation_id=${this.conversationId}`, {
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                this.displayMessages(result.data);
            } else {
                console.error('Erreur lors du chargement des messages:', result.message);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des messages:', error);
        }
    }

    /**
     * Affiche les messages dans l'interface
     */
    displayMessages(messages) {
        this.messageContainer = document.getElementById('messageContainer');
        if (!this.messageContainer) return;

        this.messageContainer.innerHTML = '';

        messages.forEach(message => {
            this.addMessageToUI(message);
        });

        this.scrollToBottom();
    }

    /**
     * Ajoute un message à l'interface utilisateur
     */
    addMessageToUI(message) {
        if (!this.messageContainer) return;

        const messageElement = document.createElement('div');
        messageElement.className = `message ${message.sender.id === this.getCurrentUserId() ? 'sent' : 'received'}`;
        messageElement.innerHTML = `
            <div class="message-content">
                <div class="message-header">
                    <span class="sender-name">${message.sender.firstName} ${message.sender.lastName}</span>
                    <span class="message-time">${this.formatTime(message.createdAt)}</span>
                </div>
                <div class="message-text">${this.escapeHtml(message.content)}</div>
                ${message.isRead ? '<span class="read-status">✓✓</span>' : '<span class="unread-status">✓</span>'}
            </div>
        `;

        this.messageContainer.appendChild(messageElement);
    }

    /**
     * Envoie un nouveau message
     */
    async sendMessage(event) {
        event.preventDefault();

        const messageInput = document.getElementById('messageInput');
        const content = messageInput.value.trim();

        if (!content) return;

        try {
            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    recipient_id: this.getRecipientId(),
                    content: content,
                    conversation_id: this.conversationId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                messageInput.value = '';
                // Le message sera ajouté automatiquement via Mercure
            } else {
                console.error('Erreur lors de l\'envoi du message:', result.message);
                alert('Erreur lors de l\'envoi du message: ' + result.message);
            }
        } catch (error) {
            console.error('Erreur lors de l\'envoi du message:', error);
            alert('Erreur lors de l\'envoi du message: ' + error.message);
        }
    }

    /**
     * Marque les messages comme lus
     */
    async markAsRead() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/mark-read`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    conversation_id: this.conversationId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                console.log(`${result.data.marked_count} messages marqués comme lus`);
                // Mettre à jour l'interface
                this.updateReadStatus();
            } else {
                console.error('Erreur lors du marquage comme lu:', result.message);
            }
        } catch (error) {
            console.error('Erreur lors du marquage comme lu:', error);
        }
    }

    /**
     * Met à jour le statut de lecture dans l'interface
     */
    updateReadStatus() {
        const unreadElements = document.querySelectorAll('.unread-status');
        unreadElements.forEach(element => {
            element.textContent = '✓✓';
            element.className = 'read-status';
        });
    }

    /**
     * Fait défiler vers le bas de la conversation
     */
    scrollToBottom() {
        if (this.messageContainer) {
            this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
        }
    }

    /**
     * Formate l'heure d'un message
     */
    formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('fr-FR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }

    /**
     * Échappe le HTML pour éviter les injections
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Récupère l'ID de l'utilisateur actuel (à implémenter selon votre logique)
     */
    getCurrentUserId() {
        // À implémenter selon votre logique d'authentification
        return parseInt(localStorage.getItem('user_id') || '0');
    }

    /**
     * Récupère l'ID du destinataire (à implémenter selon votre logique)
     */
    getRecipientId() {
        // À implémenter selon votre logique
        return parseInt(document.getElementById('recipientId')?.value || '0');
    }

    /**
     * Déconnecte la connexion temps réel
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }
}

// Initialisation automatique si les éléments existent
document.addEventListener('DOMContentLoaded', function() {
    const conversationId = document.getElementById('conversationId')?.value;
    
    if (conversationId) {
        const realtimeMessages = new RealtimeMessages(conversationId);
        realtimeMessages.init();
        
        // Nettoyer la connexion lors de la fermeture de la page
        window.addEventListener('beforeunload', () => {
            realtimeMessages.disconnect();
        });
    }
});
