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
APP_URL=http://localhost:8000

# Base de données MySQL
DATABASE_URL="mysql://sara_api:sara_password@localhost:3306/sara_api?serverVersion=8.0&charset=utf8mb4"

# Configuration Mailer (pour les emails de notifications)
# En développement avec Mailpit (Docker)
# MAILER_DSN=smtp://localhost:1025

# En production avec SendBlue (Brevo) - RECOMMANDÉ
# Configuration SendBlue :
# - Serveur SMTP: smtp-relay.brevo.com
# - Port: 587
# - Connexion: TLS
# Format du DSN:
MAILER_DSN=l de contact (pour recevoir les messages du formulaire)
CONTACT_EMAIL=contact@sara.education
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

### 🔔 Déploiement de Mercure sur Ubuntu

Mercure est nécessaire pour les notifications en temps réel et les messages instantanés.

#### Option 1 : Installation via Docker (Recommandé)

```bash
# Installer Docker si ce n'est pas déjà fait
sudo apt update
sudo apt install -y docker.io docker-compose

# Démarrer Mercure avec Docker Compose
docker-compose up -d mercure

# Vérifier que Mercure fonctionne
curl http://localhost:3000/.well-known/mercure
```

#### Option 2 : Installation binaire sur Ubuntu

```bash
# Télécharger le binaire Mercure
cd /tmp
wget https://github.com/dunglas/mercure/releases/latest/download/mercure_linux_amd64.tar.gz

# Extraire l'archive
tar -xzf mercure_linux_amd64.tar.gz

# Déplacer le binaire dans un répertoire système
sudo mv mercure /usr/local/bin/
sudo chmod +x /usr/local/bin/mercure

# Créer un utilisateur dédié pour Mercure
sudo useradd -r -s /bin/false mercure
```

#### Configuration Mercure comme service systemd

```bash
# Créer le fichier de service
sudo nano /etc/systemd/system/mercure.service
```

Contenu du fichier `/etc/systemd/system/mercure.service` :

```ini
[Unit]
Description=Mercure Hub
After=network.target

[Service]
Type=simple
User=mercure
Group=mercure
ExecStart=/usr/local/bin/mercure \
    --addr=:3000 \
    --cors-allowed-origins=https://votre-domaine.com,http://localhost:8000 \
    --publish-allowed-origins=https://votre-domaine.com,http://localhost:8000 \
    --publisher-jwt-key='!ChangeThisMercureHubJWTSecretKey!' \
    --subscriber-jwt-key='!ChangeThisMercureHubJWTSecretKey!'
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

**Important** : Remplacez `!ChangeThisMercureHubJWTSecretKey!` par une clé secrète forte et identique à celle configurée dans `config/packages/mercure.yaml`.

```bash
# Recharger systemd
sudo systemctl daemon-reload

# Activer le service au démarrage
sudo systemctl enable mercure

# Démarrer Mercure
sudo systemctl start mercure

# Vérifier le statut
sudo systemctl status mercure

# Voir les logs
sudo journalctl -u mercure -f
```

#### Configuration avec Nginx (Reverse Proxy)

Si vous utilisez Nginx, ajoutez cette configuration pour proxifier Mercure :

```nginx
# /etc/nginx/sites-available/sara-api
server {
    listen 443 ssl http2;
    server_name votre-domaine.com;

    # ... configuration SSL ...

    # Proxy pour Mercure
    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:3000;
        proxy_read_timeout 24h;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # CORS headers
        add_header Access-Control-Allow-Origin * always;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Authorization, Content-Type" always;
        
        if ($request_method = OPTIONS) {
            return 204;
        }
    }
}
```

#### Configuration avec Apache (Reverse Proxy)

Si vous utilisez Apache, ajoutez cette configuration :

```apache
# /etc/apache2/sites-available/sara-api.conf
<VirtualHost *:443>
    ServerName votre-domaine.com
    
    # ... configuration SSL ...

    # Proxy pour Mercure
    ProxyPreserveHost On
    ProxyPass /.well-known/mercure http://127.0.0.1:3000/.well-known/mercure
    ProxyPassReverse /.well-known/mercure http://127.0.0.1:3000/.well-known/mercure
    
    # Headers pour WebSocket
    RewriteEngine on
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/.well-known/mercure(.*) ws://127.0.0.1:3000/.well-known/mercure$1 [P,L]
    
    # CORS
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header always set Access-Control-Allow-Headers "Authorization, Content-Type"
</VirtualHost>
```

Activer les modules Apache nécessaires :
```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_wstunnel
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

#### Vérification de l'installation

```bash
# Tester la connexion à Mercure
curl http://localhost:3000/.well-known/mercure

# Ou avec HTTPS si configuré
curl https://votre-domaine.com/.well-known/mercure

# Vérifier que le service est actif
sudo systemctl status mercure
```

#### Configuration dans l'application

Assurez-vous que `config/packages/mercure.yaml` contient la bonne URL :

```yaml
mercure:
    hubs:
        default:
            url: 'https://votre-domaine.com/.well-known/mercure'  # Production
            # url: 'https://localhost:8443/.well-known/mercure'   # Développement
            public_url: 'https://votre-domaine.com/.well-known/mercure'
            jwt:
                secret: '!ChangeThisMercureHubJWTSecretKey!'  # Même secret que dans le service
                publish: ['*']
                subscribe: ['*']
```

#### Dépannage

```bash
# Voir les logs en temps réel
sudo journalctl -u mercure -f

# Redémarrer Mercure
sudo systemctl restart mercure

# Vérifier les ports ouverts
sudo netstat -tlnp | grep 3000

# Tester la connexion WebSocket
wscat -c ws://localhost:3000/.well-known/mercure?topic=/notifications/user/1
```

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
