# 📡 Gestion du Temps Réel avec Mercure

## Comment ça fonctionne ?

Le système de messages utilise **Mercure** pour le temps réel. Voici comment c'est implémenté :

### Architecture

1. **Publication côté serveur** : Quand un message est envoyé, le `MessageController` publie un événement via Mercure
2. **Abonnement côté client** : Le navigateur s'abonne aux topics Mercure via Server-Sent Events (SSE)
3. **Réception instantanée** : Les nouveaux messages apparaissent immédiatement sans rechargement

### Flux de données

```
┌─────────────┐
│  Utilisateur│
│   A envoie  │
│   message   │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│ MessageController   │
│  - Sauvegarde en DB │
│  - Publie via Mercure│
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│   Mercure Hub       │
│  (Server-Sent Events)│
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│  Utilisateur B      │
│  Reçoit instantanément│
│  via EventSource    │
└─────────────────────┘
```

### Topics Mercure utilisés

1. **`/conversations/{conversationId}`** : Pour recevoir les messages d'une conversation spécifique
2. **`/conversations/user/{userId}`** : Pour recevoir les notifications de nouvelles conversations

### Configuration

Le fichier `config/packages/mercure.yaml` contient :
- URL du Hub Mercure : `https://localhost:8443/.well-known/mercure`
- Secret JWT : `!ChangeThisMercureHubJWTSecretKey!`

### Démarrage du Hub Mercure

Pour le développement local, vous devez démarrer le Hub Mercure :

```bash
# Avec Docker
docker run -d -p 8443:80 \
  -e MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
  -e MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
  dunglas/mercure

# Ou avec Symfony CLI
symfony mercure:start
```

### Endpoints

- `GET /admin/messages/mercure-token` : Génère un JWT pour s'abonner à Mercure
- `POST /admin/messages/create` : Envoie un message et publie via Mercure

### Fallback

Si Mercure n'est pas disponible ou échoue, le système bascule automatiquement sur un rafraîchissement manuel toutes les 5 secondes.

### Sécurité

- Les topics sont **privés** (`private: true`)
- Un JWT est requis pour s'abonner
- Seuls les utilisateurs authentifiés peuvent accéder aux messages

### Améliorations possibles

1. **Authentification JWT personnalisée** : Générer des tokens spécifiques par utilisateur
2. **Topics sélectifs** : Limiter les topics accessibles par utilisateur
3. **Reconnexion automatique** : Gérer les déconnexions réseau
4. **Notifications push** : Intégrer avec les notifications du navigateur


