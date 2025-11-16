# 👤 Gestion des Utilisateurs par l'Administrateur

## 📋 Vue d'ensemble

Le système dispose maintenant d'un rôle **Admin** (`ROLE_ADMIN`) qui permet de gérer tous les utilisateurs de l'application. Les administrateurs peuvent :

- ✅ Lister tous les utilisateurs (Coach, Parent, Élève, Spécialiste, Admin)
- ✅ Voir les détails d'un utilisateur
- ✅ Modifier les informations d'un utilisateur
- ✅ Changer le mot de passe d'un utilisateur
- ✅ Générer un lien de connexion par token pour un utilisateur
- ✅ Supprimer un utilisateur

---

## 🚀 Création d'un Administrateur

### Via la ligne de commande

```bash
php bin/console app:create-admin <email> <password> <firstName> <lastName> [options]
```

**Exemples :**

```bash
# Créer un admin simple
php bin/console app:create-admin admin@sara.education Admin123! Jean Dupont

# Créer un admin avec un token de connexion (valide 30 jours)
php bin/console app:create-admin admin@sara.education Admin123! Jean Dupont --generate-token

# Créer un admin avec un token valide 60 jours
php bin/console app:create-admin admin@sara.education Admin123! Jean Dupont --generate-token --validity-days=60
```

**Options disponibles :**
- `-t, --generate-token` : Génère un token d'authentification
- `-d, --validity-days=DAYS` : Nombre de jours de validité du token (défaut: 30)

---

## 🔐 Authentification par Token

### Génération d'un token pour un utilisateur existant

1. Se connecter en tant qu'admin
2. Aller dans **Utilisateurs** → Sélectionner un utilisateur
3. Cliquer sur **"Générer un lien de connexion"**
4. Le lien de connexion sera affiché et pourra être copié

### Utilisation du lien de connexion

Le lien généré a le format suivant :
```
/login/token?username=<email_ou_pseudo>&token=<token>
```

**Exemple :**
```
http://127.0.0.1:8000/login/token?username=student1&token=abc123def456...
```

L'utilisateur sera automatiquement connecté en cliquant sur ce lien.

**Note :** Pour les élèves, le `username` peut être soit l'email soit le pseudo.

---

## 📍 Routes Disponibles

### Liste des utilisateurs
- **Route :** `/admin/users`
- **Méthode :** GET
- **Accès :** ROLE_ADMIN uniquement
- **Paramètres :**
  - `search` : Recherche par nom, email
  - `type` : Filtrer par type (all, admin, coach, parent, student, specialist)
  - `page` : Numéro de page (pagination)

### Vue détaillée
- **Route :** `/admin/users/{id}`
- **Méthode :** GET
- **Accès :** ROLE_ADMIN uniquement

### Édition
- **Route :** `/admin/users/{id}/edit`
- **Méthode :** GET, POST
- **Accès :** ROLE_ADMIN uniquement

### Changement de mot de passe
- **Route :** `/admin/users/{id}/change-password`
- **Méthode :** POST
- **Accès :** ROLE_ADMIN uniquement
- **Body JSON :**
  ```json
  {
    "password": "nouveau_mot_de_passe"
  }
  ```

### Génération de token
- **Route :** `/admin/users/{id}/generate-token`
- **Méthode :** POST
- **Accès :** ROLE_ADMIN uniquement
- **Body JSON :**
  ```json
  {
    "validityDays": 30
  }
  ```
- **Réponse :**
  ```json
  {
    "success": true,
    "token": "abc123...",
    "loginUrl": "http://.../login/token?username=...&token=...",
    "expiresAt": "2025-12-16 15:00:00",
    "message": "Token généré avec succès."
  }
  ```

### Suppression
- **Route :** `/admin/users/{id}/delete`
- **Méthode :** POST
- **Accès :** ROLE_ADMIN uniquement
- **Note :** Un admin ne peut pas supprimer son propre compte

---

## 🎨 Interface Utilisateur

### Menu Sidebar

Le menu **"Utilisateurs"** apparaît automatiquement dans la sidebar pour les utilisateurs ayant le rôle `ROLE_ADMIN`.

### Pages disponibles

1. **Liste des utilisateurs** (`/admin/users`)
   - Tableau avec tous les utilisateurs
   - Filtres par type et recherche
   - Actions : Voir, Modifier

2. **Vue détaillée** (`/admin/users/{id}`)
   - Informations complètes de l'utilisateur
   - Bouton "Changer le mot de passe"
   - Bouton "Générer un lien de connexion"
   - Affichage du lien de connexion actuel (s'il existe)

3. **Édition** (`/admin/users/{id}/edit`)
   - Formulaire pour modifier :
     - Prénom
     - Nom
     - Email
     - Statut (Actif/Inactif)

---

## 🔒 Sécurité

- Les routes `/admin/users/*` sont protégées par `ROLE_ADMIN` dans `security.yaml`
- Un admin ne peut pas supprimer son propre compte
- Les tokens d'authentification ont une date d'expiration
- Les mots de passe sont hashés avec l'algorithme configuré dans Symfony

---

## 📝 Notes Importantes

1. **Création d'un premier admin :** Utilisez la commande `app:create-admin` pour créer le premier administrateur
2. **Token expiré :** Si un token est expiré, il faut en générer un nouveau
3. **Pseudo vs Email :** Pour les élèves, le lien de connexion peut utiliser soit l'email soit le pseudo
4. **Validation :** Tous les champs sont validés avant sauvegarde

---

## 🛠️ Commandes Utiles

```bash
# Créer un admin
php bin/console app:create-admin admin@example.com password123 Admin User

# Générer un token pour un utilisateur existant
php bin/console app:generate-auth-token user@example.com 30

# Réinitialiser un mot de passe
php bin/console app:reset-password user@example.com newpassword123
```

---

## 📚 Fichiers Modifiés/Créés

- `src/Entity/Admin.php` - Entité Admin
- `src/Repository/AdminRepository.php` - Repository Admin
- `src/Controller/AdminUserController.php` - Contrôleur de gestion
- `src/Command/CreateAdminCommand.php` - Commande de création
- `templates/tailadmin/pages/users/list.html.twig` - Template liste
- `templates/tailadmin/pages/users/view.html.twig` - Template vue
- `templates/tailadmin/pages/users/edit.html.twig` - Template édition
- `config/packages/security.yaml` - Configuration sécurité (ROLE_ADMIN)
- `templates/tailadmin/components/sidebar.html.twig` - Menu sidebar

---

## ✅ Checklist d'Installation

1. ✅ Exécuter les migrations pour ajouter les champs `authToken` et `authTokenExpiresAt`
2. ✅ Créer un premier admin avec `app:create-admin`
3. ✅ Se connecter en tant qu'admin
4. ✅ Vérifier que le menu "Utilisateurs" apparaît dans la sidebar
5. ✅ Tester la création d'un token pour un utilisateur

---

**Date de création :** 2025-11-16  
**Version :** 1.0

admin@sara.education Admin123!