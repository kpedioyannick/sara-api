# 🎓 API Sara - Système de Gestion Éducative

Une API complète développée avec Symfony pour la gestion des familles, objectifs, tâches et suivi éducatif selon les spécifications des 4 profils : Coach, Parent, Étudiant et Spécialiste.

## 🚀 Fonctionnalités Principales

### 👨‍🏫 **Coach**
- Gestion des familles et étudiants
- Création et suivi des objectifs
- Gestion des tâches et assignations
- Traitement des demandes
- Gestion des spécialistes
- Planning et disponibilités
- Dashboard avec statistiques

### 👨‍👩‍👧‍👦 **Parent**
- Gestion des enfants
- Visualisation des objectifs et tâches
- Création de demandes
- Suivi du planning
- Dashboard familial

### 🎒 **Étudiant**
- Visualisation des objectifs
- Suivi des tâches assignées
- Création de demandes
- Système de points
- Planning personnel

### 👨‍⚕️ **Spécialiste**
- Gestion des spécialisations
- Suivi des étudiants assignés
- Gestion des disponibilités
- Traitement des demandes
- Dashboard spécialisé

## 🛠️ Technologies Utilisées

- **Symfony 6.x** - Framework PHP
- **MySQL 8.0** - Base de données
- **Doctrine ORM** - Mapping objet-relationnel
- **PHP 8.1+** - Langage de programmation
- **Composer** - Gestionnaire de dépendances

## 📋 Prérequis

- PHP 8.1 ou supérieur
- MySQL 8.0 ou supérieur
- Composer
- Extensions PHP : pdo_mysql, mbstring, xml, curl

## 🚀 Installation

### 1. Cloner le projet
```bash
git clone <repository-url>
cd sara_api
```

### 2. Installer les dépendances
```bash
composer install
```

### 3. Configuration de la base de données
```bash
# Créer l'utilisateur et la base de données MySQL
sudo mysql -e "CREATE USER IF NOT EXISTS 'sara_api'@'localhost' IDENTIFIED BY 'sara_password';"
sudo mysql -e "CREATE DATABASE IF NOT EXISTS sara_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "GRANT ALL PRIVILEGES ON sara_api.* TO 'sara_api'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### 4. Configuration de l'environnement
```bash
# Copier le fichier d'environnement
cp .env.example .env

# Éditer le fichier .env avec vos paramètres
nano .env
```

### 5. Créer les tables
```bash
# Générer les migrations
php bin/console doctrine:migrations:diff

# Appliquer les migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. Démarrer le serveur
```bash
# Serveur de développement
php -S localhost:8000 -t public/

# Ou avec Symfony CLI
symfony serve
```

## 🧪 Tests

### Tests automatiques
```bash
# Lancer tous les tests
php test_api.php
```

### Tests manuels
Consultez le [Guide de Test](TESTING_GUIDE.md) pour des exemples détaillés.

## 📚 Documentation de l'API

### Endpoints Principaux

#### 🔐 Authentification
- `POST /api/auth/register` - Inscription
- `POST /api/auth/login` - Connexion
- `POST /api/auth/logout` - Déconnexion
- `GET /api/auth/me` - Profil utilisateur

#### 👨‍🏫 Gestion des Familles
- `GET /api/families` - Liste des familles
- `POST /api/families` - Créer une famille
- `GET /api/families/{id}` - Détails d'une famille
- `PUT /api/families/{id}` - Modifier une famille
- `DELETE /api/families/{id}` - Supprimer une famille

#### 🎯 Objectifs
- `GET /api/objectives` - Liste des objectifs
- `POST /api/objectives` - Créer un objectif
- `GET /api/objectives/{id}` - Détails d'un objectif
- `PUT /api/objectives/{id}` - Modifier un objectif
- `DELETE /api/objectives/{id}` - Supprimer un objectif

#### 📋 Tâches
- `GET /api/tasks` - Liste des tâches
- `POST /api/tasks` - Créer une tâche
- `GET /api/tasks/{id}` - Détails d'une tâche
- `PUT /api/tasks/{id}` - Modifier une tâche
- `PATCH /api/tasks/{id}/status` - Modifier le statut

#### 📝 Demandes
- `GET /api/requests` - Liste des demandes
- `POST /api/requests` - Créer une demande
- `GET /api/requests/{id}` - Détails d'une demande
- `PUT /api/requests/{id}` - Modifier une demande
- `PATCH /api/requests/{id}/status` - Modifier le statut

#### 👨‍⚕️ Spécialistes
- `GET /api/specialists` - Liste des spécialistes
- `POST /api/specialists` - Créer un spécialiste
- `GET /api/specialists/{id}` - Détails d'un spécialiste
- `PUT /api/specialists/{id}` - Modifier un spécialiste
- `POST /api/specialists/{id}/students` - Assigner un étudiant

#### 📅 Planning
- `GET /api/planning` - Liste des événements
- `POST /api/planning` - Créer un événement
- `GET /api/planning/{id}` - Détails d'un événement
- `PUT /api/planning/{id}` - Modifier un événement
- `DELETE /api/planning/{id}` - Supprimer un événement

#### ⏰ Disponibilités
- `GET /api/availabilities` - Liste des disponibilités
- `POST /api/availabilities` - Créer une disponibilité
- `GET /api/availabilities/{id}` - Détails d'une disponibilité
- `PUT /api/availabilities/{id}` - Modifier une disponibilité
- `DELETE /api/availabilities/{id}` - Supprimer une disponibilité

#### 💬 Messages
- `GET /api/messages` - Liste des messages
- `POST /api/messages` - Envoyer un message
- `GET /api/messages/{id}` - Détails d'un message
- `PUT /api/messages/{id}` - Modifier un message
- `PATCH /api/messages/{id}/read` - Marquer comme lu

#### 📊 Dashboard
- `GET /api/dashboard/coach` - Dashboard coach
- `GET /api/dashboard/parent` - Dashboard parent
- `GET /api/dashboard/student` - Dashboard étudiant
- `GET /api/dashboard/specialist` - Dashboard spécialiste

#### ⚙️ Paramètres
- `GET /api/settings/profile` - Profil utilisateur
- `PUT /api/settings/profile` - Modifier le profil
- `PUT /api/settings/password` - Changer le mot de passe
- `GET /api/settings/notifications` - Paramètres de notification
- `PUT /api/settings/notifications` - Modifier les notifications

## 🏗️ Architecture

### Structure du Projet
```
sara_api/
├── config/                 # Configuration Symfony
├── doc/                   # Documentation des spécifications
├── public/                # Point d'entrée web
├── src/
│   ├── Controller/        # Contrôleurs API
│   ├── Entity/           # Entités Doctrine
│   ├── Repository/       # Repositories Doctrine
│   └── Kernel.php        # Kernel Symfony
├── var/                  # Fichiers temporaires et logs
├── test_api.php         # Script de test
├── TESTING_GUIDE.md     # Guide de test
└── README.md            # Ce fichier
```

### Entités Principales
- **User** - Utilisateur de base (Coach, Parent, Student, Specialist)
- **Family** - Famille
- **Objective** - Objectif éducatif
- **Task** - Tâche
- **Request** - Demande
- **Planning** - Événement de planning
- **Availability** - Disponibilité d'un spécialiste
- **Comment** - Commentaire
- **Proof** - Preuve de réalisation
- **Message** - Message de chat
- **TaskHistory** - Historique des tâches

## 🔧 Configuration

### Variables d'environnement (.env)
```env
# Configuration Symfony
APP_ENV=dev
APP_SECRET=your-secret-key

# Base de données MySQL
DATABASE_URL="mysql://sara_api:sara_password@localhost:3306/sara_api?serverVersion=8.0&charset=utf8mb4"
```

### Configuration Doctrine (config/packages/doctrine.yaml)
```yaml
doctrine:
    dbal:
        driver: 'pdo_mysql'
        host: 'localhost'
        port: 3306
        dbname: 'sara_api'
        user: 'sara_api'
        password: 'sara_password'
        charset: utf8mb4
```

## 🚀 Déploiement

### Production
1. Configurer l'environnement de production
2. Optimiser l'autoloader : `composer dump-autoload --optimize`
3. Vider le cache : `php bin/console cache:clear --env=prod`
4. Configurer le serveur web (Apache/Nginx)
5. Configurer SSL/TLS

### Docker (optionnel)
```dockerfile
FROM php:8.1-fpm
# Configuration Docker...
```

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🆘 Support

Pour toute question ou problème :
- Créer une issue sur GitHub
- Consulter la documentation
- Vérifier les logs dans `var/log/`

## 🎯 Roadmap

- [ ] Authentification JWT
- [ ] Tests unitaires avec PHPUnit
- [ ] Documentation OpenAPI/Swagger
- [ ] Rate Limiting
- [ ] Cache Redis
- [ ] Monitoring et métriques
- [ ] API GraphQL
- [ ] Webhooks
- [ ] Export/Import de données

---

**Développé avec ❤️ pour l'éducation**
