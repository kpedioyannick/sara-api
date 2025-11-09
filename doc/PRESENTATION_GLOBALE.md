# 🎓 SARA - Présentation Globale du Système

## 📋 Vue d'ensemble

**SARA** (Système d'Accompagnement et de Réussite des Apprenants) est une plateforme éducative qui accompagne les Parent dans la reussite scolaire de leur enfant tant sur le scolaire comportemental emotionnelle. Elle permet la gestion collaborative de l'accompagnement éducatif entre coaches, parents, élèves et spécialistes.
Le parent ne sent plus seuls, le parent ne jouera plus le mauvais rolen mauvais flic mais gGrace
Sara est accompagné à travaers des objectifs et taches pour leur enfant , les enfant seron chalengé et les parent seront les encouragent. Parent et enfant travaillent ensemble pour la réusite de leur enfant - guidé par un coach sur l'app 

Features 

Prix

Comment ça fonctionne : 

Les parents définisent leur besoins , le coach traduit een objectif à attenidre par l'enfant acompagne du parent, afin d'atteindre ces objectoif enfant et parent , le coach va définir avec les parents et enfant des actionsn un programme à faire pour y arriver. 
En fonction de  l'objectif l'élève peut etre acommpagené soit par un prifessei 
Un suivi hedbdomadaire est réalisé , et un bilan au bouit de 3 seamines est fait 
---

## 🎯 Objectif Principal

Faciliter le suivi éducatif personnalisé en permettant :
- La création et le suivi d'objectifs pédagogiques
- La gestion de tâches avec preuves de réalisation
- La communication entre tous les acteurs
- Le planning et la coordination des activités
- La génération automatique de contenu pédagogique (H5P)

---

## 👥 Les 4 Profils Utilisateurs

### 👨‍🏫 **COACH** (Super Administrateur)
**Rôle :** Coordinateur principal du système

**Fonctionnalités principales :**
- ✅ **Gestion complète des familles** : CRUD, création parent + enfants, désactivation
- ✅ **Gestion des objectifs** : Création, validation, suivi, changement de statut
- ✅ **Gestion des tâches** : Attribution, validation, suivi des preuves
- ✅ **Gestion des demandes** : Consultation, attribution aux spécialistes, traitement
- ✅ **Gestion des spécialistes** : CRUD, activation/désactivation, affectation d'élèves
- ✅ **Planning** : Gestion des événements pour tous les élèves
- ✅ **Disponibilités** : Gestion de ses propres créneaux
- ✅ **Dashboard** : Statistiques globales (familles actives, objectifs en cours, demandes)
- ✅ **Génération de parcours pédagogiques** : Création de contenus H5P via IA

**Permissions :** Accès total au système, seul à pouvoir changer les statuts des objectifs et activités

---

### 👨‍👩‍👧‍👦 **PARENT**
**Rôle :** Responsable de la famille et des enfants

**Fonctionnalités principales :**
- ✅ **Gestion des enfants** : CRUD sur les enfants de sa famille
- ✅ **Création automatique de famille** : Une famille est créée à l'inscription
- ✅ **Gestion des objectifs** : CRUD sur les objectifs de ses enfants
- ✅ **Visualisation des tâches** : Suivi des tâches assignées à ses enfants
- ✅ **Création de demandes** : Demander de l'aide pour ses enfants
- ✅ **Planning** : Consultation et gestion du planning familial
- ✅ **Dashboard familial** : Vue d'ensemble de la famille

**Permissions :** Droits complets mais uniquement dans le contexte de ses enfants

---

### 🎒 **ÉTUDIANT (Enfant)**
**Rôle :** Apprenant actif

**Fonctionnalités principales :**
- ✅ **Visualisation des objectifs** : Consultation de ses objectifs
- ✅ **Suivi des tâches** : Cocher les tâches avec preuves (texte, photos)
- ✅ **Création de demandes** : Demander de l'aide
- ✅ **Système de points** : Gamification de l'apprentissage
- ✅ **Planning personnel** : Consultation de son planning
- ✅ **Dashboard étudiant** : Vue personnalisée

**Permissions :** Accès en lecture/écriture sur ses propres données, **pas d'accès au menu Familles**

---

### 👨‍⚕️ **SPÉCIALISTE**
**Rôle :** Expert dans un domaine spécifique

**Fonctionnalités principales :**
- ✅ **Gestion des spécialisations** : Domaines d'expertise
- ✅ **Suivi des étudiants assignés** : Élèves qui lui sont confiés
- ✅ **Gestion des disponibilités** : Créneaux horaires disponibles
- ✅ **Traitement des demandes** : Répondre aux demandes qui lui sont assignées
- ✅ **Dashboard spécialisé** : Vue sur ses étudiants et demandes

**Permissions :** Accès limité à ses étudiants assignés et aux demandes qui lui sont confiées

---

## 🔄 Workflows Principaux

### 📊 **Workflow d'un Objectif**

**États possibles :**
1. **En cours de Modification** → Création/édition en cours
2. **Attente de Validation par Coach** → Soumis pour validation
3. **Validé par le Coach** → Approuvé, prêt à être mis en action
4. **En Action** → Objectif actif, tâches en cours
5. **Terminé** → Objectif complété
6. **En pause** → Temporairement suspendu

**Règles :**
- ⚠️ Quand un objectif est **"En cours de Modification"** ou **"Attente de Validation"** : 
  - ❌ Impossible de créer ou modifier des tâches
  - ✅ Seulement consultation (check) des tâches
- ✅ **Seul le Coach** peut changer le statut d'un objectif
- 📝 Message descriptif du statut affiché sur le détail de l'objectif

---

### 🎨 **Workflow d'une Activité**

**États possibles :**
1. **En cours de Modification** → Création/édition en cours
2. **Attente de Validation par Coach** → Soumis pour validation
3. **Validé par le Coach** → Approuvé
4. **Publié** → Activité visible et active

**Règles :**
- ⚠️ Quand une activité n'est **pas** dans les états "En cours de Modification" ou "Attente de Validation" :
  - ❌ Plus possible de la modifier
- ✅ **Seul le Coach** peut changer le statut d'une activité
- 📝 Message descriptif du statut affiché sur le détail de l'activité

---

## 🏗️ Architecture Technique

### **Stack Technologique**

- **Backend :**
  - Symfony 7.3 (Framework PHP)
  - PHP 8.2+
  - MySQL 8.0 (Base de données)
  - Doctrine ORM 3.5 (Mapping objet-relationnel)
  
- **Sécurité & Authentification :**
  - Lexik JWT Authentication Bundle (Authentification JWT)
  - Symfony Security Bundle
  - Rate Limiting (Protection contre les abus)
  
- **Communication Temps Réel :**
  - Mercure Hub (WebSockets pour notifications en temps réel)
  - Symfony Mercure Bundle
  
- **IA & Génération de Contenu :**
  - Intégration OpenAI (Génération de contenu H5P)
  - Service de génération automatique de parcours pédagogiques
  
- **Frontend :**
  - TailAdmin (Template d'administration)
  - Twig (Moteur de templates)
  - Stimulus & Turbo (Interactivité JavaScript)
  - Asset Mapper (Gestion des assets)

---

## 📦 Modules Principaux

### 🏠 **Gestion des Familles**
- CRUD complet sur les familles
- Création simultanée parent + enfants
- Ajout d'enfants à une famille existante
- Désactivation famille/enfant
- Filtrage et recherche
- Accès direct depuis la carte enfant : objectifs, planning, demandes

### 🎯 **Gestion des Objectifs**
- CRUD complet
- Filtrage par famille → enfant
- **IA :** Reformulation automatique du titre et génération des tâches
- Commentaires et suivi
- Tâches à cocher avec preuves (texte, photo, etc.)
- Attribution de tâches à : Student, Parent, Spécialiste, Coach
- Historique des preuves
- Regroupement par rôle
- Paramètres : fréquence

### 📋 **Gestion des Tâches**
- CRUD complet
- Attribution multi-rôles (élève, parent, spécialiste, coach)
- Système de preuves (texte, images)
- Historique des validations
- Statut d'avancement
- Fréquence configurable

### 📬 **Gestion des Demandes**
- CRUD complet
- Consultation de toutes les demandes
- Attribution au coach ou à un spécialiste
- Changement de statut (en attente, en cours, terminée)
- Filtrage par famille, statut, spécialiste, élève
- Réponse directe aux demandes

### 👩‍⚕️ **Gestion des Spécialistes**
- CRUD complet
- Création avec domaines de spécialité
- Activation/désactivation
- Recherche et filtrage
- Affectation/révocation d'élèves

### 📅 **Planning**
- CRUD complet sur les événements
- Visualisation par élève ou famille
- Accès rapide depuis le profil enfant
- Événements avec :
  - Titre (matières prédéfinies)
  - Description
  - Date/heure début et fin
  - Type (cours, révision, activité, etc.)
  - Preuves associées (texte, photo)

### 🕒 **Disponibilités**
- CRUD complet (coach, spécialistes, parents, élèves)
- Créneaux horaires par jour
- Modification/suppression de créneaux
- Filtrage par spécialité, statut

### 📊 **Dashboard**
- Vue d'ensemble des statistiques
- Nombre de familles actives
- Objectifs en cours
- Demandes en attente
- Accès rapide aux actions urgentes
- Dashboard personnalisé par profil

### 💬 **Messages & Communication**
- Système de messagerie en temps réel
- Notifications via Mercure
- Historique des conversations
- Pièces jointes (images, fichiers)

### 📚 **Génération de Parcours Pédagogiques (H5P)**
- **Nouveau module** : Génération automatique de contenu H5P
- Intégration avec API externe pour récupérer les chapitres/sous-chapitres
- Génération via IA (OpenAI) de modules interactifs :
  - MultiChoice (QCM)
  - TrueFalse (Vrai/Faux)
  - Et autres types de modules H5P
- Stockage des prompts pédagogiques par chapitre/sous-chapitre
- Types de parcours : H5P, Video, Link, Kahoot

---

## 🗄️ Structure de la Base de Données

### **Entités Principales**

#### **Utilisateurs (Héritage JOINED)**
- `User` (classe de base)
- `Coach` (hérite de User)
- `ParentUser` (hérite de User)
- `Student` (hérite de User)
- `Specialist` (hérite de User)

#### **Gestion Éducative**
- `Family` - Familles
- `Objective` - Objectifs pédagogiques
- `Task` - Tâches
- `Comment` - Commentaires
- `Proof` - Preuves de réalisation
- `Request` - Demandes d'aide

#### **Organisation**
- `Planning` - Événements de planning
- `Availability` - Disponibilités
- `Activity` - Activités
- `ActivityCategory` - Catégories d'activités
- `ActivityImage` - Images d'activités

#### **Communication**
- `Message` - Messages de chat
- `Integration` - Intégrations externes

#### **Parcours Pédagogiques (Nouveau)**
- `Path` - Parcours pédagogiques (H5P, Video, Link, Kahoot)
- `Path\Chapter` - Chapitres (avec prompts IA)
- `Path\SubChapter` - Sous-chapitres (avec prompts IA)
- `Path\Module` - Modules H5P dans un parcours
- `Path\Subject` - Matières
- `Path\Classroom` - Classes (3ème, 4ème, 5ème)

---

## 🔐 Sécurité

- **Authentification JWT** : Tokens sécurisés pour l'API
- **Rôles et Permissions** : Système de rôles (ROLE_COACH, ROLE_PARENT, ROLE_STUDENT, ROLE_SPECIALIST)
- **Rate Limiting** : Protection contre les abus
- **CSRF Protection** : Protection contre les attaques CSRF
- **Headers de Sécurité** : Headers HTTP sécurisés
- **Validation des Données** : Validation stricte des entrées

---

## 📡 API & Endpoints

### **Authentification**
- `POST /api/auth/register` - Inscription
- `POST /api/auth/login` - Connexion
- `POST /api/auth/logout` - Déconnexion
- `GET /api/auth/me` - Profil utilisateur

### **Ressources Principales**
- `/api/families` - Gestion des familles
- `/api/objectives` - Gestion des objectifs
- `/api/tasks` - Gestion des tâches
- `/api/requests` - Gestion des demandes
- `/api/specialists` - Gestion des spécialistes
- `/api/planning` - Gestion du planning
- `/api/availabilities` - Gestion des disponibilités
- `/api/messages` - Messagerie
- `/api/dashboard/*` - Dashboards par profil
- `/api/activities` - Gestion des activités
- `/api/paths` - Gestion des parcours pédagogiques

---

## 🛠️ Commandes Console

### **Gestion des Données**
- `app:seed-database` - Peupler la base avec des données de test
- `app:import-students` - Importer des étudiants
- `app:load-path-data` - Charger les données de parcours (chapitres, sous-chapitres)
- `app:load-prompts` - Charger les prompts pédagogiques depuis l'API externe

### **Génération de Contenu**
- `app:generate-path` - Générer un parcours H5P avec modules IA
- `app:seed-activities` - Créer des activités de test

### **Administration**
- `app:reset-password` - Réinitialiser un mot de passe
- `app:generate-path` - Génération de parcours pédagogiques

---

## 🚀 Fonctionnalités Avancées

### **Intelligence Artificielle**
- **Reformulation d'objectifs** : Amélioration automatique des titres
- **Génération de tâches** : Création automatique de tâches à partir d'un objectif
- **Génération de contenu H5P** : Création de modules interactifs via OpenAI
- **Prompts pédagogiques** : Stockage et utilisation de prompts par chapitre

### **Temps Réel**
- **Notifications Mercure** : Notifications push en temps réel
- **Messages en direct** : Chat en temps réel
- **Mises à jour automatiques** : Rafraîchissement automatique des données

### **Gestion de Fichiers**
- **Upload de preuves** : Photos, textes pour valider les tâches
- **Stockage sécurisé** : Gestion des fichiers uploadés
- **Images d'activités** : Support multi-images

---

## 📈 Statistiques & Rapports

- **Dashboard Coach** : Vue globale du système
- **Dashboard Parent** : Vue familiale
- **Dashboard Étudiant** : Vue personnelle
- **Dashboard Spécialiste** : Vue spécialisée
- **Rapports sur les prompts** : Analyse des prompts chargés par chapitre/sous-chapitre

---

## 🔄 Intégrations Externes

- **API Sara Education** : Récupération des chapitres et sous-chapitres
- **OpenAI** : Génération de contenu pédagogique
- **Mercure Hub** : Communication temps réel

---

## 📝 Comptes de Test

**Mot de passe par défaut pour tous : `ir`**

### **Coaches**
- `sara@coach.com` - Sara Educateur
- `marie@coach.com` - Marie Dupont
- `pierre@coach.com` - Pierre Leroy

### **Spécialistes**
- `sarah@specialist.com` - Sarah Cohen
- `marc@specialist.com` - Marc Dubois
- `julie@specialist.com` - Julie Moreau

### **Parents**
- `parent@sara.education` - Jean Dupont
- `sophie.martin@sara.education` - Sophie Martin

### **Élèves**
- `lucas@sara.education` - Lucas Dupont
- `sophie@sara.education` - Sophie Dupont
- `tom@sara.education` - Tom Martin
- `emma@sara.education` - Emma Martin

---

## 🎯 Points Forts du Système

1. ✅ **Multi-profils** : 4 types d'utilisateurs avec permissions granulaires
2. ✅ **Workflows structurés** : États clairs pour objectifs et activités
3. ✅ **IA intégrée** : Génération automatique de contenu pédagogique
4. ✅ **Temps réel** : Notifications et messages instantanés
5. ✅ **Flexibilité** : Support de différents types de parcours (H5P, Video, Link, Kahoot)
6. ✅ **Traçabilité** : Historique complet des actions et preuves
7. ✅ **Sécurité** : Authentification JWT, rôles, rate limiting
8. ✅ **Scalabilité** : Architecture Symfony moderne et performante

---

## 📚 Documentation Complémentaire

- `README.md` - Guide d'installation et utilisation
- `doc/COACH_FEATURES.md` - Fonctionnalités détaillées du Coach
- `doc/DEVELOPMENT_STRATEGY.md` - Stratégie de développement
- `doc/ENTITIES_VALIDATION.md` - Validation des entités
- `doc/MERCURE_REALTIME.md` - Documentation Mercure
- `doc/objectifsetTaches.md` - Documentation objectifs et tâches

---

**Développé avec ❤️ pour l'éducation et l'accompagnement personnalisé**

