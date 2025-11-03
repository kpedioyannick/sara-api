Excellent 👍 tu poses ici la **spécification complète du rôle Parent** dans ton application.

## 👨‍👩‍👧 **Features – Parent**

---

### 🏠 **Gestion des Familles**

* Le **parent** peut CRUD ses **enfants** (ajouter, modifier, supprimer, consulter).
* Chaque enfant est représenté par :

  * pseudo (ex : `lisa@sara.education`)
  * mot de passe
  * confirmation du mot de passe
  * classe (liste prédéfinie : CP, CE1, CE2, CM1, CM2, 6e, etc.)
* Depuis la fiche d’un enfant, le parent peut :

  * Consulter son profil : pseudo, classe 
  * Modifier sa classe ou son mot de passe
  * Accéder directement à ses **Objectifs**, **Planning**, **Demandes**

📌 **UI / UX**

* Un **seul template / route** pour tout le CRUD des enfants.
* Les formulaires s’ouvrent dans des **RightSheets (panneaux latéraux)** sans rechargement de page.
* Chaque encart enfant contient :

  * `Edit` | `Delete` | `Objectifs` | `Planning` | `Demandes`
* La route renvoie aussi le **nombre d’objectifs** et de **demandes** pour chaque enfant.

📌 **Automatisation :**

* Lors de la création d’un enfant : l’email est auto-généré à partir du pseudo
  → exemple : `pseudo@sara.education`.

---

### 🎯 **Gestion des Objectifs**

* CRUD complet sur les objectifs.
* Filtrage par **famille → enfant**.
* Création : saisie type, description, enfant assigné
* Ajout de commentaires et suivi sur un objectif .
* IA : reformulation du titre et génération des tâches automatiquement.
* Un parent peut aussi CRUD ses objectifs.
* Un objectif contient des **tâches à cocher**, si le user coche une tache avec preuves (texte, photo, etc.).
* Tache peut etre affecté à un student , parent ou à un specialiste ou coach 
* Historique des preuves.
* Regroupement des tâches par **rôle** (élève, parent, spécialiste).
* CRUD complet sur les tâches d’un objectif.
* Attribution d’une tâche à un élève, parent ou spécialiste.
* Paramètres : fréquence
* Consultation des preuves et de l’historique.
* Suivi du statut d’avancement.

---

---

### 💬 **Gestion des Demandes**

* Le parent peut **CRUD** ses **demandes** :

  * Demandes adressées au **coach** ou à un **spécialiste**.
  * Exemple : suivi, modification d’objectif, rendez-vous, etc.
* Consultation et suivi du **statut** : en attente, en cours, terminée.
* **Messagerie temps réel** intégrée avec **Mercure.rocks**.
* Possibilité de **répondre** ou **ajouter un message** à une demande.
* Filtrage possible par **statut** ou **date**.

---

### 📅 **Planning**

* Le parent peut **CRUD les événements** du planning de ses enfants.
* Un événement contient :

  * **Titre** (choisi parmi les matières prédéfinies)
  * **Description**
  * **Date et heure** de début et fin
  * **Type** (cours, révision, activité, etc.)
  * **Preuves associées** (texte, photo, image)
* Les tâches planifiées s’affichent aussi dans le planning (selon fréquence, début, fin).
* Visualisation et filtrage par **enfant** ou **type d’événement**.

📌 **UI / UX**

* Pas de route CRUD séparée : utilisation de **RightSheets** pour créer / modifier les événements.
* Les événements sont affichés dans une **vue calendrier** ou **liste enrichie**.

---

### 📊 **Dashboard**

* Vue d’ensemble de la famille :

  * Activité globale des enfants
  * Nombre d’objectifs actifs par enfant
  * Actions en attente
  * Prochains événements
  * Points ou récompenses obtenus

---

### 👨‍👩‍👧 **Profil Famille**

* Affichage complet des informations de la famille.
* Liste complète des enfants.
* Possibilité de **modifier l’identifiant famille**.

---

### ⚙️ **Paramètres**

* Modification du **nom**, **mot de passe** du parent.
* Configuration des **préférences de notification**.

---

### 🕒 **Disponibilités**

* Le parent peut définir ses **créneaux de disponibilités** :

  * CRUD sur des créneaux d’une heure.
  * De lundi à dimanche.
* Ces disponibilités sont visibles par le coach ou les spécialistes.

---
