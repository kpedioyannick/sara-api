/**
 * Script JavaScript pour les notifications de demandes en temps réel avec Mercure
 */

class RealtimeRequests {
    constructor(mercureUrl, token) {
        this.mercureUrl = mercureUrl;
        this.token = token;
        this.eventSource = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000; // 1 seconde
    }

    /**
     * Initialise la connexion aux notifications temps réel
     */
    init() {
        this.connectToSSE();
        this.setupEventListeners();
    }

    /**
     * Se connecte au serveur Mercure via Server-Sent Events
     */
    connectToSSE() {
        try {
            const url = new URL(this.mercureUrl);
            url.searchParams.append('topic', 'requests');
            
            this.eventSource = new EventSource(url.toString());
            
            this.eventSource.onopen = () => {
                console.log('Connexion aux notifications de demandes établie');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.onConnectionOpen();
            };

            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleRealtimeNotification(data);
                } catch (error) {
                    console.error('Erreur lors du parsing des données:', error);
                }
            };

            this.eventSource.onerror = (error) => {
                console.error('Erreur de connexion aux notifications:', error);
                this.isConnected = false;
                this.handleConnectionError();
            };

        } catch (error) {
            console.error('Erreur lors de l\'initialisation de la connexion:', error);
            this.handleConnectionError();
        }
    }

    /**
     * Gère les erreurs de connexion et tente de se reconnecter
     */
    handleConnectionError() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Tentative de reconnexion ${this.reconnectAttempts}/${this.maxReconnectAttempts} dans ${this.reconnectDelay}ms`);
            
            setTimeout(() => {
                this.connectToSSE();
            }, this.reconnectDelay);
            
            // Augmenter le délai pour la prochaine tentative
            this.reconnectDelay = Math.min(this.reconnectDelay * 2, 30000);
        } else {
            console.error('Impossible de se reconnecter aux notifications');
            this.onConnectionFailed();
        }
    }

    /**
     * Traite une notification reçue en temps réel
     */
    handleRealtimeNotification(data) {
        console.log('Notification reçue:', data);
        
        if (data.type === 'request_update') {
            this.handleRequestUpdate(data);
        } else if (data.type === 'ping') {
            // Ping reçu, connexion active
            console.log('Ping reçu - connexion active');
        }
    }

    /**
     * Traite une mise à jour de demande
     */
    handleRequestUpdate(data) {
        const { action, request, timestamp } = data;
        
        console.log(`Demande ${action}:`, request);
        
        // Déclencher les callbacks appropriés
        switch (action) {
            case 'created':
                this.onRequestCreated(request, timestamp);
                break;
            case 'updated':
                this.onRequestUpdated(request, timestamp);
                break;
            case 'assigned':
                this.onRequestAssigned(request, timestamp);
                break;
            case 'responded':
                this.onRequestResponded(request, timestamp);
                break;
            case 'response_added':
                this.onResponseAdded(request, timestamp);
                break;
            case 'status_updated':
                this.onStatusUpdated(request, timestamp);
                break;
            default:
                console.log('Action non reconnue:', action);
        }
    }

    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Écouter les changements de visibilité de la page
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                console.log('Page cachée - connexion maintenue');
            } else {
                console.log('Page visible - vérification de la connexion');
                if (!this.isConnected) {
                    this.connectToSSE();
                }
            }
        });

        // Écouter les erreurs de réseau
        window.addEventListener('online', () => {
            console.log('Connexion réseau rétablie');
            if (!this.isConnected) {
                this.connectToSSE();
            }
        });

        window.addEventListener('offline', () => {
            console.log('Connexion réseau perdue');
        });
    }

    /**
     * Ferme la connexion
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            this.isConnected = false;
            console.log('Connexion aux notifications fermée');
        }
    }

    // Callbacks à surcharger dans l'application
    onConnectionOpen() {
        console.log('Connexion aux notifications de demandes ouverte');
    }

    onConnectionFailed() {
        console.error('Échec de la connexion aux notifications');
    }

    onRequestCreated(request, timestamp) {
        console.log('Nouvelle demande créée:', request.title);
        this.showNotification(`Nouvelle demande: ${request.title}`, 'info');
    }

    onRequestUpdated(request, timestamp) {
        console.log('Demande mise à jour:', request.title);
        this.showNotification(`Demande mise à jour: ${request.title}`, 'info');
    }

    onRequestAssigned(request, timestamp) {
        console.log('Demande assignée:', request.title);
        this.showNotification(`Demande assignée: ${request.title}`, 'success');
    }

    onRequestResponded(request, timestamp) {
        console.log('Demande répondue:', request.title);
        this.showNotification(`Demande répondue: ${request.title}`, 'success');
    }

    onResponseAdded(request, timestamp) {
        console.log('Réponse ajoutée à la demande:', request.title);
        this.showNotification(`Réponse ajoutée: ${request.title}`, 'info');
    }

    onStatusUpdated(request, timestamp) {
        console.log('Statut de demande mis à jour:', request.title, '->', request.status);
        this.showNotification(`Statut mis à jour: ${request.title} (${request.status})`, 'warning');
    }

    /**
     * Affiche une notification à l'utilisateur
     */
    showNotification(message, type = 'info') {
        // Créer une notification simple
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Style de base
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            max-width: 300px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
        `;

        // Couleurs selon le type
        const colors = {
            'info': '#2196F3',
            'success': '#4CAF50',
            'warning': '#FF9800',
            'error': '#F44336'
        };
        notification.style.backgroundColor = colors[type] || colors.info;

        // Ajouter l'animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);

        document.body.appendChild(notification);

        // Supprimer la notification après 5 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
}

// Export pour utilisation dans d'autres modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealtimeRequests;
}
