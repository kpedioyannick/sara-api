# 🧪 Guide de Test - Système de Notifications

## 📋 Prérequis

1. **Deux comptes utilisateurs différents** (ou deux navigateurs en navigation privée)
2. **Accès à l'application** : `http://localhost:8000`
3. **Console du navigateur ouverte** (F12) pour voir les logs Firebase

---

## 🧪 Test 1 : Notifications de Nouveaux Messages

### Scénario : Envoyer un message et vérifier la notification

**Étapes :**

1. **Ouvrir deux navigateurs différents** (ou deux fenêtres en navigation privée)
   - Navigateur A : Connecté en tant qu'**Utilisateur 1** (ex: Coach)
   - Navigateur B : Connecté en tant qu'**Utilisateur 2** (ex: Parent/Élève)

2. **Dans le Navigateur A** :
   - Aller sur `http://localhost:8000/admin/requests` (ou créer une demande)
   - Ouvrir une demande existante : `http://localhost:8000/admin/requests/1` (remplacer 1 par un ID valide)
   - Vérifier que l'icône de notifications dans le header affiche le badge avec le nombre de notifications non lues

3. **Dans le Navigateur B** :
   - Aller sur la même demande : `http://localhost:8000/admin/requests/1`
   - Envoyer un message dans le chat
   - Le message devrait apparaître immédiatement dans le chat

4. **Dans le Navigateur A** (destinataire) :
   - ✅ **Vérifier** : Le badge de notifications dans le header devrait s'incrémenter
   - ✅ **Vérifier** : Cliquer sur l'icône de notifications
   - ✅ **Vérifier** : Une nouvelle notification devrait apparaître avec :
     - Catégorie "Messages" (icône bleue de message)
     - Titre "Nouveau message"
     - Message contenant le nom de l'expéditeur et le contenu du message
     - Fond bleu clair (non lu) ou blanc (lu)

5. **Tester le filtre "Messages"** :
   - Cliquer sur le filtre "Messages" dans le panneau de notifications
   - ✅ **Vérifier** : Seules les notifications de type message sont affichées
   - ✅ **Vérifier** : Le compteur affiche le nombre de messages non lus

6. **Tester la redirection** :
   - Cliquer sur la notification
   - ✅ **Vérifier** : Redirection vers la page de la demande avec le chat ouvert

---

## 🧪 Test 2 : Notifications de Tâches

### Scénario : Créer une tâche et vérifier la notification

**Étapes :**

1. **Créer une nouvelle tâche** :
   - Aller sur `http://localhost:8000/admin/objectives` (ou une page de création de tâche)
   - Créer une nouvelle tâche et l'assigner à un utilisateur

2. **Vérifier la notification** :
   - L'utilisateur assigné devrait recevoir une notification
   - ✅ **Vérifier** : Le badge de notifications s'incrémente
   - ✅ **Vérifier** : Cliquer sur l'icône de notifications
   - ✅ **Vérifier** : Une notification apparaît avec :
     - Catégorie "Tâches" (icône orange de tâche)
     - Titre "Nouvelle tâche assignée"
     - Fond orange clair (non lu)

3. **Tester le filtre "Tâches"** :
   - Cliquer sur le filtre "Tâches"
   - ✅ **Vérifier** : Seules les notifications de tâches sont affichées

---

## 🧪 Test 3 : Temps Réel avec Firebase

### Scénario : Vérifier que les notifications arrivent en temps réel

**Étapes :**

1. **Ouvrir la console du navigateur** (F12 → Console)

2. **Dans le Navigateur A** :
   - Vérifier les logs Firebase dans la console
   - ✅ **Vérifier** : Message `✅ Connecté à Firebase pour les notifications temps réel`

3. **Dans le Navigateur B** :
   - Envoyer un message

4. **Dans le Navigateur A** :
   - ✅ **Vérifier** : La notification apparaît **automatiquement** sans recharger la page
   - ✅ **Vérifier** : Le badge se met à jour automatiquement
   - ✅ **Vérifier** : Pas besoin de cliquer sur l'icône pour voir la notification apparaître

---

## 🧪 Test 4 : Filtres et Catégories

### Scénario : Tester tous les filtres

**Étapes :**

1. **Créer des notifications de différents types** :
   - Un nouveau message
   - Une nouvelle tâche assignée
   - Une demande créée (si applicable)

2. **Tester chaque filtre** :
   - **Filtre "Toutes"** :
     - ✅ **Vérifier** : Toutes les notifications sont affichées
     - ✅ **Vérifier** : Le compteur affiche le total de non lus
   
   - **Filtre "Messages"** :
     - ✅ **Vérifier** : Seules les notifications de messages sont affichées
     - ✅ **Vérifier** : Le compteur affiche uniquement les messages non lus
   
   - **Filtre "Tâches"** :
     - ✅ **Vérifier** : Seules les notifications de tâches sont affichées
     - ✅ **Vérifier** : Le compteur affiche uniquement les tâches non lues
   
   - **Filtre "Autres"** :
     - ✅ **Vérifier** : Les autres types de notifications sont affichés

---

## 🧪 Test 5 : Actions sur les Notifications

### Scénario : Marquer comme lu, supprimer, etc.

**Étapes :**

1. **Marquer une notification comme lue** :
   - Cliquer sur une notification non lue
   - ✅ **Vérifier** : La notification change de couleur (fond blanc)
   - ✅ **Vérifier** : Le badge décrémente

2. **Marquer toutes comme lues** :
   - Cliquer sur "Tout marquer comme lu"
   - ✅ **Vérifier** : Toutes les notifications deviennent blanches
   - ✅ **Vérifier** : Le badge disparaît (0 notifications non lues)

3. **Supprimer une notification** :
   - Cliquer sur le bouton "X" d'une notification
   - ✅ **Vérifier** : La notification disparaît
   - ✅ **Vérifier** : Si elle était non lue, le badge décrémente

---

## 🧪 Test 6 : Interface et Design

### Scénario : Vérifier l'apparence visuelle

**Étapes :**

1. **Vérifier les icônes** :
   - ✅ **Messages** : Icône bleue de message (bulle de chat)
   - ✅ **Tâches** : Icône orange de tâche (clipboard)
   - ✅ **Autres** : Icône grise de notification (cloche)

2. **Vérifier les couleurs** :
   - ✅ **Messages non lus** : Fond bleu clair (`bg-blue-50`)
   - ✅ **Tâches non lues** : Fond orange clair (`bg-orange-50`)
   - ✅ **Autres non lues** : Fond gris clair (`bg-gray-50`)
   - ✅ **Notifications lues** : Fond blanc

3. **Vérifier la responsivité** :
   - Tester sur mobile (mode responsive dans le navigateur)
   - ✅ **Vérifier** : Le panneau s'adapte correctement
   - ✅ **Vérifier** : Les filtres restent accessibles

---

## 🐛 Dépannage

### Problème : Les notifications n'apparaissent pas en temps réel

**Solutions :**
1. Vérifier la console du navigateur pour les erreurs Firebase
2. Vérifier que Firebase est bien initialisé : `✅ Connecté à Firebase pour les notifications temps réel`
3. Vérifier la configuration Firebase dans `.env`
4. Vérifier que le service Firebase fonctionne : `php bin/console app:test-firebase` (si la commande existe)

### Problème : Les notifications ne sont pas créées

**Solutions :**
1. Vérifier les logs Symfony : `tail -f var/log/dev.log`
2. Vérifier que `NotificationService` est bien injecté dans les contrôleurs
3. Vérifier que la base de données contient bien les notifications : `SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10;`

### Problème : Les filtres ne fonctionnent pas

**Solutions :**
1. Vérifier que la méthode `getCategory()` retourne bien les bonnes catégories
2. Vérifier dans la console du navigateur que `notification.category` est bien défini
3. Vérifier que les notifications ont bien le champ `category` dans `toArray()`

---

## ✅ Checklist de Test Complète

- [ ] Notifications de nouveaux messages créées automatiquement
- [ ] Badge de notifications s'incrémente correctement
- [ ] Notifications apparaissent en temps réel (sans recharger)
- [ ] Filtre "Messages" fonctionne
- [ ] Filtre "Tâches" fonctionne
- [ ] Filtre "Autres" fonctionne
- [ ] Filtre "Toutes" fonctionne
- [ ] Compteurs par catégorie corrects
- [ ] Icônes affichées correctement selon la catégorie
- [ ] Couleurs de fond correctes selon la catégorie
- [ ] Marquer comme lu fonctionne
- [ ] Marquer toutes comme lues fonctionne
- [ ] Supprimer une notification fonctionne
- [ ] Redirection vers l'URL de la notification fonctionne
- [ ] Interface responsive

---

## 📝 Notes

- Les notifications sont stockées en base de données MySQL
- Les mises à jour en temps réel utilisent Firebase Realtime Database
- Le système fonctionne même si Firebase est temporairement indisponible (les notifications sont quand même créées en base)

