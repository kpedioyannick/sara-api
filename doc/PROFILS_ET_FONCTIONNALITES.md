# 👥 Profils Utilisateurs et Fonctionnalités

## 📊 Vue d'Ensemble

Le projet **SARA** compte **4 profils utilisateurs** distincts :

1. **👨‍🏫 Coach** (`ROLE_COACH`)
2. **👨‍👩‍👧 Parent** (`ROLE_PARENT`)
3. **🎓 Élève** (`ROLE_STUDENT`)
4. **👩‍⚕️ Spécialiste** (`ROLE_SPECIALIST`)

---

## 1. 👨‍🏫 COACH

### 🎯 Rôle
Le coach est le **super-administrateur** du système. Il gère toutes les familles, objectifs, tâches et coordonne l'ensemble des acteurs.

### ✅ Fonctionnalités Complètes

#### 🏠 **Gestion des Familles**
- ✅ **CRUD complet** sur les familles (création, modification, suppression, consultation)
- ✅ **Création d'un parent ET de ses enfants** en une seule opération
- ✅ **Ajout d'enfants** à une famille existante
- ✅ **Filtrage et recherche** de familles
- ✅ **Désactivation** d'une famille (parent + enfants) ou d'un seul enfant
- ✅ **Accès direct** depuis la carte d'un enfant à ses objectifs, planning et demandes
- ✅ **Génération de feuilles de droits** pour les élèves

#### 🎯 **Gestion des Objectifs**
- ✅ **CRUD complet** sur les objectifs
- ✅ **Filtrage par famille → enfant**
- ✅ **Création avec IA** : reformulation automatique du titre et génération des tâches
- ✅ **Ajout de commentaires** et suivi sur un objectif
- ✅ **Génération de suggestions** d'objectifs par IA
- ✅ **Partage d'objectifs** (feuille de suivi)
- ✅ **Consultation des tâches** et preuves associées
- ✅ **Attribution de tâches** à un élève, parent ou spécialiste
- ✅ **Validation des preuves** soumises

#### 📋 **Gestion des Tâches**
- ✅ **CRUD complet** sur les tâches
- ✅ **Types de tâches** : HOMEWORK, ASSESSMENT, INDIVIDUAL_WORK_ON_SITE, etc.
- ✅ **Attribution** à élève, parent, spécialiste ou coach
- ✅ **Paramètres** : fréquence, deadline, etc.
- ✅ **Consultation des preuves** et historique
- ✅ **Suivi du statut** d'avancement

#### 📬 **Gestion des Demandes**
- ✅ **CRUD complet** sur les demandes
- ✅ **Consultation de toutes les demandes** (élèves, parents, spécialistes)
- ✅ **Attribution à soi-même ou à un spécialiste**
- ✅ **Changement de statut** (en attente, en cours, terminée)
- ✅ **Filtrage** par famille, statut, spécialiste ou élève
- ✅ **Réponse directe** à une demande avec chat en temps réel
- ✅ **Assistance IA** pour répondre aux demandes

#### 👩‍⚕️ **Gestion des Spécialistes**
- ✅ **CRUD complet** sur les spécialistes
- ✅ **Création** avec nom, prénom, email, mot de passe et domaines de spécialité
- ✅ **Activation/désactivation** d'un spécialiste
- ✅ **Recherche et filtrage**
- ✅ **Affectation ou révocation** d'élèves à un spécialiste
- ✅ **Gestion des mots de passe** des spécialistes

#### 📅 **Planning**
- ✅ **CRUD complet** sur les événements du planning
- ✅ **Visualisation et filtrage** du planning par élève ou par famille
- ✅ **Accès rapide** depuis le profil d'un enfant
- ✅ **Génération de feuilles** d'événements
- ✅ **Types d'événements** : cours, révision, activité, etc.

#### 🕒 **Disponibilités**
- ✅ **CRUD complet** sur ses propres disponibilités
- ✅ **Gestion des disponibilités** des spécialistes
- ✅ **Filtrage** par spécialité, statut, etc.

#### 💬 **Messages**
- ✅ **Liste des conversations** avec tous les utilisateurs
- ✅ **Chat en temps réel** via Firebase
- ✅ **Envoi de messages** texte, images, audio
- ✅ **Notifications** de nouveaux messages

#### 📢 **Notifications**
- ✅ **Consultation de toutes les notifications**
- ✅ **Filtres** par catégorie (Messages, Tâches, Autres)
- ✅ **Marquer comme lu** / Tout marquer comme lu
- ✅ **Suppression** de notifications

#### 🔍 **Recherche**
- ✅ **Recherche globale** dans le système
- ✅ **Filtrage** par type de contenu

#### 📊 **Dashboard**
- ✅ **Vue d'ensemble** des statistiques :
  - Nombre de familles actives
  - Objectifs en cours
  - Demandes en attente
- ✅ **Accès rapide** aux actions urgentes

#### ⚙️ **Paramètres**
- ✅ **Modification** du nom et du mot de passe
- ✅ **Gestion du profil**

#### 🎨 **Activités**
- ✅ **CRUD complet** sur les activités
- ✅ **Création** d'activités avec images
- ✅ **Catégorisation** des activités

#### 📚 **Parcours (Paths)**
- ✅ **CRUD complet** sur les parcours pédagogiques
- ✅ **Génération de parcours** par IA
- ✅ **Gestion des chapitres** et sous-chapitres
- ✅ **Intégration Pronote**

---

## 2. 👨‍👩‍👧 PARENT

### 🎯 Rôle
Le parent gère sa famille et suit la progression de ses enfants. Il peut créer des objectifs, des demandes et communiquer avec le coach.

### ✅ Fonctionnalités

#### 🏠 **Gestion de la Famille**
- ✅ **Consultation** de sa famille et de ses enfants
- ✅ **Modification** des informations de ses enfants
- ✅ **Ajout d'enfants** à sa famille (si autorisé)
- ✅ **Consultation** des objectifs, planning et demandes de ses enfants

#### 🎯 **Gestion des Objectifs**
- ✅ **CRUD complet** sur les objectifs de ses enfants
- ✅ **Création d'objectifs** pour ses enfants
- ✅ **Consultation** des objectifs assignés à ses enfants
- ✅ **Ajout de commentaires** sur les objectifs
- ✅ **Consultation des tâches** et preuves

#### 📋 **Gestion des Tâches**
- ✅ **Consultation** des tâches assignées au parent
- ✅ **Soumission de preuves** pour les tâches
- ✅ **Consultation** des tâches de ses enfants

#### 📬 **Gestion des Demandes**
- ✅ **CRUD complet** sur ses propres demandes
- ✅ **Création de demandes** pour ses enfants ou pour lui-même
- ✅ **Consultation** des réponses aux demandes
- ✅ **Chat en temps réel** avec le coach ou spécialiste
- ✅ **Types de demandes** : aide aux devoirs, questions, etc.

#### 📅 **Planning**
- ✅ **Consultation** du planning de ses enfants
- ✅ **Visualisation** des événements planifiés

#### 🕒 **Disponibilités**
- ✅ **CRUD complet** sur ses propres disponibilités
- ✅ **Gestion de ses créneaux** horaires

#### 💬 **Messages**
- ✅ **Liste des conversations** avec le coach et spécialistes
- ✅ **Chat en temps réel** via Firebase
- ✅ **Envoi de messages** texte, images, audio
- ✅ **Notifications** de nouveaux messages

#### 📢 **Notifications**
- ✅ **Consultation** de toutes ses notifications
- ✅ **Filtres** par catégorie (Messages, Tâches, Autres)
- ✅ **Marquer comme lu** / Tout marquer comme lu
- ✅ **Suppression** de notifications

#### 🔍 **Recherche**
- ✅ **Recherche globale** dans le système
- ✅ **Filtrage** par type de contenu

#### 📊 **Dashboard**
- ✅ **Vue d'ensemble** des statistiques de sa famille
- ✅ **Suivi** de la progression de ses enfants

#### ⚙️ **Paramètres**
- ✅ **Modification** du nom et du mot de passe
- ✅ **Gestion du profil**

#### 🎨 **Activités**
- ✅ **Consultation** des activités disponibles

---

## 3. 🎓 ÉLÈVE (STUDENT)

### 🎯 Rôle
L'élève consulte ses objectifs, soumet des preuves pour les tâches et communique avec le coach et ses parents.

### ✅ Fonctionnalités

#### 🎯 **Gestion des Objectifs**
- ✅ **Consultation** de ses objectifs
- ✅ **Visualisation** des tâches associées
- ✅ **Suivi** de sa progression

#### 📋 **Gestion des Tâches**
- ✅ **Consultation** des tâches qui lui sont assignées
- ✅ **Soumission de preuves** pour les tâches (texte, photos, audio)
- ✅ **Suivi** du statut de ses tâches
- ✅ **Historique** des preuves soumises

#### 📬 **Gestion des Demandes**
- ✅ **Consultation** de ses demandes
- ✅ **Création de demandes** pour demander de l'aide
- ✅ **Consultation** des réponses aux demandes
- ✅ **Chat en temps réel** avec le coach ou spécialiste

#### 📅 **Planning**
- ✅ **Consultation** de son planning personnel
- ✅ **Visualisation** des événements planifiés

#### 🕒 **Disponibilités**
- ✅ **CRUD complet** sur ses propres disponibilités
- ✅ **Gestion de ses créneaux** horaires

#### 💬 **Messages**
- ✅ **Liste des conversations** avec le coach, parents et spécialistes
- ✅ **Chat en temps réel** via Firebase
- ✅ **Envoi de messages** texte, images, audio
- ✅ **Notifications** de nouveaux messages

#### 📢 **Notifications**
- ✅ **Consultation** de toutes ses notifications
- ✅ **Filtres** par catégorie (Messages, Tâches, Autres)
- ✅ **Marquer comme lu** / Tout marquer comme lu
- ✅ **Suppression** de notifications

#### 🔍 **Recherche**
- ✅ **Recherche globale** dans le système
- ✅ **Filtrage** par type de contenu

#### 📊 **Dashboard**
- ✅ **Vue d'ensemble** de sa progression
- ✅ **Statistiques** personnelles (points, objectifs complétés, etc.)

#### ⚙️ **Paramètres**
- ✅ **Modification** du nom et du mot de passe
- ✅ **Gestion du profil**
- ✅ **Gestion du pseudo** et informations personnelles

#### 🎨 **Activités**
- ✅ **Consultation** des activités disponibles

---

## 4. 👩‍⚕️ SPÉCIALISTE

### 🎯 Rôle
Le spécialiste intervient sur des domaines spécifiques (orthophonie, psychologie, etc.). Il peut être assigné à des élèves et répondre à des demandes.

### ✅ Fonctionnalités

#### 🎯 **Gestion des Objectifs**
- ✅ **Consultation** des objectifs des élèves qui lui sont assignés
- ✅ **Consultation** des tâches qui lui sont assignées
- ✅ **Ajout de commentaires** sur les objectifs

#### 📋 **Gestion des Tâches**
- ✅ **Consultation** des tâches qui lui sont assignées
- ✅ **Soumission de preuves** pour les tâches
- ✅ **Validation** des preuves soumises par les élèves (si autorisé)

#### 📬 **Gestion des Demandes**
- ✅ **Consultation** des demandes qui lui sont assignées
- ✅ **Réponse** aux demandes
- ✅ **Chat en temps réel** avec les parents, élèves ou coach
- ✅ **Types de demandes** : questions spécialisées, suivis, etc.

#### 👥 **Gestion des Élèves**
- ✅ **Consultation** des élèves qui lui sont assignés
- ✅ **Suivi** de la progression des élèves

#### 🕒 **Disponibilités**
- ✅ **CRUD complet** sur ses propres disponibilités
- ✅ **Gestion de ses créneaux** horaires

#### 💬 **Messages**
- ✅ **Liste des conversations** avec les parents, élèves et coach
- ✅ **Chat en temps réel** via Firebase
- ✅ **Envoi de messages** texte, images, audio
- ✅ **Notifications** de nouveaux messages

#### 📢 **Notifications**
- ✅ **Consultation** de toutes ses notifications
- ✅ **Filtres** par catégorie (Messages, Tâches, Autres)
- ✅ **Marquer comme lu** / Tout marquer comme lu
- ✅ **Suppression** de notifications

#### 🔍 **Recherche**
- ✅ **Recherche globale** dans le système
- ✅ **Filtrage** par type de contenu

#### 📊 **Dashboard**
- ✅ **Vue d'ensemble** de ses statistiques
- ✅ **Suivi** des élèves assignés

#### ⚙️ **Paramètres**
- ✅ **Modification** du nom et du mot de passe
- ✅ **Gestion du profil**
- ✅ **Gestion des spécialisations**

#### 🎨 **Activités**
- ✅ **CRUD complet** sur les activités (mêmes droits que le coach)
- ✅ **Création** d'activités avec images
- ✅ **Catégorisation** des activités

---

## 📊 Tableau Comparatif des Fonctionnalités

| Fonctionnalité | Coach | Parent | Élève | Spécialiste |
|----------------|-------|--------|-------|-------------|
| **Gestion Familles** | ✅ CRUD | ✅ Consultation | ❌ | ❌ |
| **Gestion Objectifs** | ✅ CRUD | ✅ CRUD (enfants) | ✅ Consultation | ✅ Consultation (assignés) |
| **Gestion Tâches** | ✅ CRUD | ✅ Consultation + Preuves | ✅ Consultation + Preuves | ✅ Consultation + Preuves |
| **Gestion Demandes** | ✅ CRUD + Attribution | ✅ CRUD (propres) | ✅ CRUD (propres) | ✅ Consultation + Réponse |
| **Gestion Spécialistes** | ✅ CRUD | ❌ | ❌ | ❌ |
| **Planning** | ✅ CRUD | ✅ Consultation (enfants) | ✅ Consultation | ❌ |
| **Disponibilités** | ✅ CRUD (tous) | ✅ CRUD (propres) | ✅ CRUD (propres) | ✅ CRUD (propres) |
| **Messages** | ✅ Tous | ✅ Coach/Spécialistes | ✅ Tous | ✅ Tous |
| **Notifications** | ✅ Toutes | ✅ Propres | ✅ Propres | ✅ Propres |
| **Recherche** | ✅ Globale | ✅ Globale | ✅ Globale | ✅ Globale |
| **Dashboard** | ✅ Complet | ✅ Famille | ✅ Personnel | ✅ Assignés |
| **Activités** | ✅ CRUD | ✅ Consultation | ✅ Consultation | ✅ CRUD |
| **Parcours (Paths)** | ✅ CRUD | ❌ | ❌ | ❌ |
| **Intégrations** | ✅ Toutes | ❌ | ❌ | ❌ |

---

## 🔐 Rôles et Permissions

### Rôles Symfony
- `ROLE_COACH` : Accès complet au système
- `ROLE_PARENT` : Gestion de sa famille et suivi des enfants
- `ROLE_STUDENT` : Consultation et soumission de preuves
- `ROLE_SPECIALIST` : Intervention spécialisée sur des élèves assignés

### Contrôle d'Accès
- La plupart des routes `/admin/*` sont accessibles à tous les utilisateurs authentifiés (`IS_AUTHENTICATED_FULLY`)
- Certaines fonctionnalités sont restreintes par rôle via `#[IsGranted('ROLE_COACH')]`
- Le service `PermissionService` gère les permissions granulaires par entité

---

## 📝 Notes Importantes

1. **Héritage** : Tous les profils héritent de l'entité `User` avec un mapping JOINED
2. **Discriminator** : Le type de profil est stocké dans le champ `discriminator`
3. **Relations** : Chaque profil a des relations spécifiques (Coach → Families, Parent → Family, Student → Objectives, etc.)
4. **Permissions** : Le service `PermissionService` vérifie les permissions au niveau des entités
5. **Notifications** : Tous les profils reçoivent des notifications en temps réel via Firebase

---

## 🚀 Fonctionnalités Transverses (Tous les Profils)

### 💬 **Messagerie en Temps Réel**
- Chat en temps réel via Firebase Realtime Database
- Support des messages texte, images et audio
- Notifications instantanées de nouveaux messages
- Optimistic UI updates

### 📢 **Système de Notifications**
- Notifications en temps réel via Firebase
- Filtres par catégorie (Messages, Tâches, Autres)
- Compteurs de non lus par catégorie
- Icônes et couleurs différenciées

### 🔍 **Recherche Globale**
- Recherche dans tous les contenus accessibles
- Filtrage par type de contenu

### 📊 **Dashboard Personnalisé**
- Vue d'ensemble adaptée au profil
- Statistiques pertinentes
- Accès rapide aux actions importantes

