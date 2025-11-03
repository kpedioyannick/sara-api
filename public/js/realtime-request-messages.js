/**
 * Script JavaScript pour les messages de demandes en temps réel avec Mercure
 * Utilise le topic: messages/demand_{requestId}
 */

class RealtimeRequestMessages {
    constructor(requestId, apiBaseUrl = '/api') {
        this.requestId = requestId;
        this.apiBaseUrl = apiBaseUrl;
        this.eventSource = null;
        this.messageContainer = null;
        this.messageForm = null;
        this.apiToken = this.getAuthToken();
        this.mercureToken = null;
        this.mercureUrl = null;
    }

    /**
     * Récupère le token JWT depuis le localStorage
     */
    getAuthToken() {
        return localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');
    }

    /**
     * Récupère le token Mercure depuis l'API
     */
    async getMercureToken() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/auth/mercure/token`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.apiToken}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();

            if (result.success) {
                this.mercureToken = result.data.token;
                this.mercureUrl = result.data.hubUrl;
                return result.data;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Erreur lors de la récupération du token Mercure:', error);
            throw error;
        }
    }

    /**
     * Initialise la connexion temps réel
     */
    async init() {
        try {
            // Récupérer le token Mercure
            await this.getMercureToken();
            
            this.setupEventListeners();
            this.connectToMercure();
            this.loadMessages();
        } catch (error) {
            console.error('Erreur lors de l\'initialisation:', error);
        }
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
    }

    /**
     * Se connecte au stream Mercure pour recevoir les messages en temps réel
     */
    connectToMercure() {
        if (!this.mercureToken || !this.mercureUrl || !this.requestId) {
            console.error('Token Mercure, URL ou ID de demande manquant');
            return;
        }

        // Vérifier si EventSourcePolyfill est disponible
        if (typeof EventSourcePolyfill === 'undefined') {
            console.error('EventSourcePolyfill n\'est pas disponible. Veuillez l\'importer.');
            console.error('Installation: npm install event-source-polyfill');
            return;
        }

        // Construire l'URL avec le topic spécifique à cette demande
        const url = new URL(this.mercureUrl);
        const topic = `messages/demand_${this.requestId}`;
        url.searchParams.append('topic', topic);

        console.log('🔌 Connexion à Mercure');
        console.log('📍 Topic:', topic);
        console.log('🔗 URL:', url.toString());

        // Utiliser EventSourcePolyfill pour supporter les headers
        this.eventSource = new EventSourcePolyfill(url.toString(), {
            headers: {
                'Authorization': `Bearer ${this.mercureToken}`
            },
            withCredentials: false
        });

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                console.log('📨 Message reçu:', data);
                this.handleRealtimeMessage(data);
            } catch (error) {
                console.error('Erreur lors du parsing du message:', error);
            }
        };

        this.eventSource.onopen = () => {
            console.log('✅ Connecté au stream Mercure pour les messages temps réel');
        };

        this.eventSource.onerror = (error) => {
            console.error('❌ Erreur de connexion Mercure:', error);
            
            // Tentative de reconnexion après 5 secondes
            if (this.eventSource?.readyState === EventSourcePolyfill.CLOSED) {
                setTimeout(() => {
                    console.log('🔄 Tentative de reconnexion...');
                    this.connectToMercure();
                }, 5000);
            }
        };
    }

    /**
     * Traite un message reçu en temps réel
     */
    handleRealtimeMessage(data) {
        if (data.type === 'request_message') {
            if (data.action === 'message_added') {
                this.addMessageToUI(data.message);
                this.scrollToBottom();
            }
        } else {
            console.warn('Type de message inattendu:', data.type);
        }
    }

    /**
     * Charge les messages existants
     */
    async loadMessages() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/parent/requests/${this.requestId}`, {
                headers: {
                    'Authorization': `Bearer ${this.apiToken}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success && result.data && result.data.messages) {
                this.displayMessages(result.data.messages);
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
        if (!this.messageContainer) {
            this.messageContainer = document.getElementById('messageContainer');
            if (!this.messageContainer) return;
        }

        const messageElement = document.createElement('div');
        messageElement.className = `message ${message.sender?.id === this.getCurrentUserId() ? 'sent' : 'received'}`;
        messageElement.innerHTML = `
            <div class="message-content">
                <div class="message-header">
                    <span class="sender-name">${this.escapeHtml(message.sender?.firstName || '')} ${this.escapeHtml(message.sender?.lastName || '')}</span>
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
        const content = messageInput?.value.trim();

        if (!content) return;

        try {
            const response = await fetch(`${this.apiBaseUrl}/parent/requests/${this.requestId}/message`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.apiToken}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    content: content
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                if (messageInput) {
                    messageInput.value = '';
                }
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
        if (!dateString) return '';
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
        if (!text) return '';
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
     * Déconnecte la connexion temps réel
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            console.log('Déconnecté de Mercure');
        }
    }
}

// Exemple d'utilisation :
// const requestId = 5; // ID de la demande
// const realtimeMessages = new RealtimeRequestMessages(requestId);
// realtimeMessages.init();

// Nettoyer lors de la fermeture de la page
// window.addEventListener('beforeunload', () => {
//     realtimeMessages.disconnect();
// });

