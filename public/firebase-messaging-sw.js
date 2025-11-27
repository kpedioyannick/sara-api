// Service Worker pour Firebase Cloud Messaging
// Ce fichier doit être à la racine de public/

importScripts('https://www.gstatic.com/firebasejs/11.0.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/11.0.1/firebase-messaging-compat.js');

// Configuration Firebase (sera récupérée dynamiquement depuis le client)
// Pour l'instant, valeurs par défaut du projet SARA
const firebaseConfig = {
    apiKey: "AIzaSyAvbJ1Q-uud2-KyPZUJVGsDzvfBjRs2CQ8",
    authDomain: "sara-6c71d.firebaseapp.com",
    projectId: "sara-6c71d",
    storageBucket: "sara-6c71d.firebasestorage.app",
    messagingSenderId: "840962006351",
    appId: "1:840962006351:web:d5ad1b2986100f15ec393a"
};

// Initialiser Firebase
firebase.initializeApp(firebaseConfig);

// Récupérer l'instance de messaging
const messaging = firebase.messaging();

// Écouter les messages en arrière-plan
messaging.onBackgroundMessage((payload) => {
    console.log('Message reçu en arrière-plan:', payload);
    
    const notificationTitle = payload.notification?.title || payload.data?.title || 'Nouvelle notification';
    const notificationOptions = {
        body: payload.notification?.body || payload.data?.body || '',
        icon: '/tailadmin/favicon.ico',
        badge: '/tailadmin/favicon.ico',
        vibrate: [200, 100, 200],
        tag: payload.data?.tag || 'default',
        data: payload.data || {}
    };

    return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Gestion du clic sur la notification
self.addEventListener('notificationclick', (event) => {
    console.log('Notification cliquée:', event);
    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/admin/dashboard';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Si une fenêtre est déjà ouverte, la focus
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Sinon, ouvrir une nouvelle fenêtre
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

