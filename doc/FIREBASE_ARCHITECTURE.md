# 🏗️ Architecture Firebase pour SARA

## 📋 Principe général

**Firebase Realtime Database est utilisé UNIQUEMENT pour les nouveaux messages en temps réel.**

### ✅ Ce que Firebase fait :
- **Nouveaux messages** : Publie les messages fraîchement créés pour la synchronisation temps réel
- **Notifications** : Publie les notifications en temps réel
- **Temps réel uniquement** : Firebase sert de "canal de diffusion" pour les événements récents

### ❌ Ce que Firebase NE fait PAS :
- **Stockage permanent** : Les messages sont stockés dans MySQL, pas dans Firebase
- **Historique** : Les messages existants sont chargés depuis MySQL au chargement de la page
- **Source de vérité** : MySQL reste la source de vérité pour tous les messages

## 🔄 Flux de données

### 1. Chargement initial de la page
```
Utilisateur ouvre la page
    ↓
RequestController charge les messages depuis MySQL
    ↓
Messages affichés dans le template Twig
    ↓
Firebase se connecte pour écouter les NOUVEAUX messages uniquement
```

### 2. Envoi d'un nouveau message
```
Utilisateur envoie un message
    ↓
MessageController/RequestController sauvegarde dans MySQL
    ↓
Message publié dans Firebase (pour temps réel)
    ↓
Autres utilisateurs connectés reçoivent le message via Firebase
```

### 3. Rechargement de la page
```
Page rechargée
    ↓
Messages chargés depuis MySQL (source de vérité)
    ↓
Firebase se reconnecte pour les nouveaux messages
    ↓
Pas besoin de charger depuis Firebase
```

## 🧹 Nettoyage automatique

Firebase ne doit pas accumuler les données indéfiniment. Un nettoyage automatique est nécessaire.

### Commande de nettoyage

```bash
# Nettoyer les messages de plus de 24 heures (défaut)
php bin/console app:cleanup-firebase

# Nettoyer les messages de plus de 12 heures
php bin/console app:cleanup-firebase --hours=12

# Voir ce qui serait supprimé sans supprimer
php bin/console app:cleanup-firebase --dry-run
```

### Configuration Cron (recommandé)

Ajoutez dans votre crontab pour nettoyer automatiquement toutes les heures :

```bash
# Nettoyer Firebase toutes les heures
0 * * * * cd /var/www/php/sara_api && php bin/console app:cleanup-firebase --hours=24
```

### Pourquoi nettoyer ?

1. **Coûts** : Firebase Realtime Database facture selon l'espace utilisé
2. **Performance** : Moins de données = requêtes plus rapides
3. **Sécurité** : Évite l'accumulation de données sensibles
4. **Doublons** : Les messages sont déjà dans MySQL, pas besoin de les garder dans Firebase

## 📊 Structure Firebase

```
/conversations/{conversationId}/messages/{messageKey}
  - id: "123"
  - content: "Message texte"
  - createdAt: "2025-11-15 20:30:00"
  - ...

/requests/{requestId}/messages/{messageKey}
  - id: "456"
  - content: "Message texte"
  - createdAt: "2025-11-15 20:30:00"
  - ...

/notifications/user/{userId}/notifications/{notificationKey}
  - id: "789"
  - ...
```

## ⚙️ Configuration

### Variables d'environnement (.env)

```env
# Firebase Backend (Service Account)
FIREBASE_PROJECT_ID=sara-6c71d
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n..."
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxx@xxx.iam.gserviceaccount.com
FIREBASE_DATABASE_URL=https://sara-6c71d-default-rtdb.europe-west1.firebasedatabase.app

# Firebase Frontend (Client)
FIREBASE_API_KEY=AIzaSy...
FIREBASE_AUTH_DOMAIN=sara-6c71d.firebaseapp.com
FIREBASE_STORAGE_BUCKET=sara-6c71d.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=840962006351
FIREBASE_APP_ID=1:840962006351:web:...
```

## 🔒 Sécurité

1. **Règles Firebase** : Configurez les règles pour limiter l'accès
2. **TTL automatique** : Les messages sont supprimés après 24h
3. **Pas de données sensibles** : Firebase ne contient que les données nécessaires au temps réel
4. **Source de vérité** : MySQL reste la source de vérité

## 📝 Bonnes pratiques

1. ✅ **Toujours sauvegarder dans MySQL d'abord** avant de publier dans Firebase
2. ✅ **Nettoyer régulièrement** Firebase (cron quotidien)
3. ✅ **Ne pas charger l'historique** depuis Firebase au chargement de la page
4. ✅ **Utiliser Firebase uniquement** pour les nouveaux messages
5. ❌ **Ne pas stocker** de données sensibles dans Firebase
6. ❌ **Ne pas utiliser Firebase** comme base de données principale

## 🐛 Dépannage

### Messages en double
- Vérifiez que les messages ne sont pas chargés depuis Firebase au chargement
- Vérifiez que `handleNewMessage` vérifie les doublons avec `data-message-id`

### Firebase trop volumineux
- Exécutez `php bin/console app:cleanup-firebase`
- Vérifiez que le cron de nettoyage fonctionne

### Messages ne s'affichent pas en temps réel
- Vérifiez la console JavaScript pour les erreurs
- Vérifiez que Firebase est bien initialisé
- Vérifiez les règles Firebase dans la console

# Configuration Firebase pour SARA

## ✅ Migration terminée

Toutes les références à Mercure ont été supprimées et remplacées par Firebase.

## 📋 Étapes de configuration

### 1. Créer un projet Firebase

1. Aller sur https://console.firebase.google.com/
2. Cliquer sur "Ajouter un projet"
3. Nommer le projet "SARA" (ou votre nom préféré)
4. Activer Google Analytics (optionnel)
5. Créer le projet

### 2. Activer Realtime Database

1. Dans la console Firebase, aller dans "Realtime Database"
2. Cliquer sur "Créer une base de données"
3. Choisir l'emplacement (Europe de l'Ouest recommandé)
4. Choisir "Mode test" pour commencer (vous pourrez sécuriser plus tard)
5. Copier l'URL de la base de données (format: `https://YOUR_PROJECT_ID-default-rtdb.firebaseio.com`)

### 3. Activer Cloud Messaging (pour les notifications push)

1. Dans la console Firebase, aller dans "Cloud Messaging"
2. Noter le "Sender ID" (sera utilisé pour `FIREBASE_MESSAGING_SENDER_ID`)

### 4. Créer une clé de compte de service

1. Dans la console Firebase, aller dans "Paramètres du projet" > "Comptes de service"
2. Cliquer sur "Générer une nouvelle clé privée"
3. Télécharger le fichier JSON
4. Ouvrir le fichier JSON et copier :
   - `project_id` → `FIREBASE_PROJECT_ID`
   - `private_key` → `FIREBASE_PRIVATE_KEY` (garder les `\n`)
   - `client_email` → `FIREBASE_CLIENT_EMAIL`

### 5. Obtenir les credentials pour le client (JavaScript)

1. Dans la console Firebase, aller dans "Paramètres du projet" > "Vos applications"
2. Cliquer sur l'icône `</>` pour ajouter une application web
3. Nommer l'application "SARA Web"
4. Copier les valeurs de configuration Firebase

### 6. Configurer les variables d'environnement

Ajouter dans votre fichier `.env` ou `.env.local` :

```env
# Firebase Configuration (Frontend - Web App) - Déjà configuré avec les valeurs par défaut
# Ces valeurs sont déjà intégrées dans le code, mais vous pouvez les surcharger dans .env
FIREBASE_API_KEY=AIzaSyAvbJ1Q-uud2-KyPZUJVGsDzvfBjRs2CQ8
FIREBASE_AUTH_DOMAIN=sara-6c71d.firebaseapp.com
FIREBASE_DATABASE_URL=https://sara-6c71d-default-rtdb.firebaseio.com
FIREBASE_PROJECT_ID=sara-6c71d
FIREBASE_STORAGE_BUCKET=sara-6c71d.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=840962006351
FIREBASE_APP_ID=1:840962006351:web:d5ad1b2986100f15ec393a

# Firebase Configuration (Backend - Service Account) - OBLIGATOIRE pour le backend
# Obtenez ces valeurs depuis Firebase Console > Paramètres du projet > Comptes de service
# Téléchargez le fichier JSON de la clé privée et extrayez les valeurs suivantes :
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxxxx@sara-6c71d.iam.gserviceaccount.com
```

**Important** : 
- Les valeurs frontend sont déjà configurées par défaut dans le code, mais vous pouvez les surcharger dans `.env`
- Pour `FIREBASE_PRIVATE_KEY`, garder les `\n` dans la chaîne. Exemple :
```env
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC...\n-----END PRIVATE KEY-----\n"
```
- **OBLIGATOIRE** : Vous devez configurer `FIREBASE_PRIVATE_KEY` et `FIREBASE_CLIENT_EMAIL` pour que le backend fonctionne

### 7. Configurer les règles de sécurité Realtime Database

Dans la console Firebase > Realtime Database > Règles :

```json
{
  "rules": {
    "conversations": {
      "$conversationId": {
        "messages": {
          ".read": "auth != null",
          ".write": "auth != null"
        }
      },
      "user": {
        "$userId": {
          "updates": {
            ".read": "$userId === auth.uid",
            ".write": "$userId === auth.uid"
          }
        }
      }
    },
    "requests": {
      "$requestId": {
        "messages": {
          ".read": "auth != null",
          ".write": "auth != null"
        }
      }
    },
    "notifications": {
      "user": {
        "$userId": {
          ".read": "$userId === auth.uid",
          ".write": "auth != null",
          "notifications": {
            ".read": "$userId === auth.uid"
          },
          "updates": {
            ".read": "$userId === auth.uid"
          }
        }
      }
    }
  }
}
```

**Note** : Pour le développement, vous pouvez utiliser des règles plus permissives :
```json
{
  "rules": {
    ".read": true,
    ".write": true
  }
}
```

⚠️ **Ne jamais utiliser ces règles en production !**

## 🧪 Tester la configuration

1. Vider le cache Symfony : `php bin/console cache:clear`
2. Ouvrir l'application dans le navigateur
3. Ouvrir la console du navigateur (F12)
4. Vous devriez voir : `✅ Connecté à Firebase pour les notifications temps réel`

## 📝 Structure des données Firebase

### Messages de conversation
```
/conversations/{conversationId}/messages/{messageId}
```

### Messages de demande
```
/requests/{requestId}/messages/{messageId}
```

### Notifications
```
/notifications/user/{userId}/notifications/{notificationId}
/notifications/user/{userId}/updates/{updateId}
```

### Mises à jour de conversations
```
/conversations/user/{userId}/updates/{updateId}
```

## 🔒 Sécurité

- Les credentials Firebase côté serveur (Service Account) sont stockés dans `.env` et ne doivent jamais être commités
- Les credentials côté client (Web App) peuvent être exposés (c'est normal pour Firebase)
- Configurez les règles de sécurité Realtime Database selon vos besoins
- Utilisez Firebase Authentication pour authentifier les utilisateurs si nécessaire

## 🚀 Avantages de Firebase vs Mercure

✅ **Notifications push natives** via Firebase Cloud Messaging  
✅ **Fonctionne offline** avec synchronisation automatique  
✅ **Pas de problèmes CORS/HTTPS**  
✅ **SDK JavaScript officiel** optimisé pour les PWA  
✅ **Gratuit jusqu'à 100K connexions simultanées**  
✅ **Service Workers natifs** pour les PWA  

## 📚 Documentation

- [Firebase Realtime Database](https://firebase.google.com/docs/database)
- [Firebase Cloud Messaging](https://firebase.google.com/docs/cloud-messaging)
- [Firebase pour les PWA](https://firebase.google.com/docs/web/setup)

