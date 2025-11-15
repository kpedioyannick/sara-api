# Plan d'Implémentation des Nouvelles Fonctionnalités

## 📋 Vue d'ensemble des fonctionnalités demandées

### 1. Page Objectif - Partage d'Objectifs
- ✅ Partager des objectifs entre élèves
- ✅ Partager des objectifs entre spécialistes
- ✅ Les tâches de l'objectif partagé sont automatiquement visibles

### 2. Page Famille - Améliorations pour les Groupes
- ✅ Ajouter le champ "Lieu" pour les groupes
- ✅ Ajouter le champ "Spécialistes" pour affecter des spécialistes à un groupe

### 3. Planning - Améliorations des Preuves
- ✅ Au clic sur une tâche : afficher les infos de la tâche
- ✅ Ajouter preuve ou afficher les historiques des preuves à cette date de soumission
- ✅ Quand une preuve est saisie, la date de soumission = date de l'event
- ✅ Les preuves dépendent du Type de Tâches (ateliers => participants, organisateurs, activités ; Bilan => notes, etc.)
- ✅ Lors de la saisie des preuves : à côté de chaque élève/atelier, possibilité d'ajouter une note simplement

---

## 🎯 Questions de Clarification

### Questions sur le Partage d'Objectifs et Tâches

1. **Niveau de permissions** :
   - Les utilisateurs partagés peuvent-ils modifier l'objectif/tâche ou seulement le voir ? => seulement les voir et ajouter des preuves 
   - Faut-il un système de rôles (propriétaire, éditeur, lecteur) ? => Non

2. **Interface de partage** :
   - Comment souhaitez-vous que l'utilisateur partage un objectif/tâche ? (bouton "Partager" avec modal ?) => righsheet on peut les partager avec la liste des élves et des specilistes du Groupes
   - Faut-il une liste des personnes avec qui l'objectif est partagé ?

3. **Notifications** :
   - Faut-il notifier les utilisateurs lorsqu'un objectif/tâche leur est partagé ? => Non

4. **Spécialistes** :
   - Les spécialistes peuvent-ils partager des objectifs => Non seul le coach le peu 

### Questions sur les Groupes (Family)

1. **Champ "Lieu"** :
   - Le lieu est-il un simple texte libre ou une liste prédéfinie ? => Oui
   - Faut-il pouvoir rechercher/filtrer par lieu ?

2. **Spécialistes affectés au groupe** :
   - Un spécialiste peut-il être affecté à plusieurs groupes ? => Oui 
    - Faut-il afficher les spécialistes dans la liste des groupes ? => NOn
   - Les spécialistes affectés au groupe ont-ils automatiquement accès aux objectifs/tâches des élèves du groupe ? => Non

### Questions sur le Planning et les Preuves

1. **Modal d'information sur la tâche** :
   - Quelles informations exactement doivent être affichées ? (titre, description, statut, objectif parent, etc.) => Oui 
   - Faut-il pouvoir modifier la tâche depuis ce modal ? => Non

2. **Historique des preuves** :
   - Comment afficher l'historique ? (liste chronologique, par date de soumission ?) => oui
   - Faut-il pouvoir filtrer par date ? => Non

3. **Date de soumission = date de l'event** :
   - Quand on crée une preuve depuis le planning, `submittedAt` doit être automatiquement rempli avec la date/heure de l'événement Planning ? => OUi
   - Si l'événement Planning a une durée (startDate/endDate), quelle date utiliser ? (startDate ?) => date le'event

4. **Champs des preuves selon le type de tâche** :
   - Pour **WORKSHOP (Atelier)** : participants (students), organisateurs (specialists), activités (activities) ✅ (déjà implémenté)
   - Pour **ASSESSMENT (Bilan)** : notes dans les preuves => ajouter champ `assessmentNotes` (text) dans `Proof`
   - Pour les autres types : voir propositions ci-dessous

5. **Notes rapides lors de la saisie de preuves** :
   - **Note sur les élèves** => Utiliser l'entité `Note` existante (liée à Student uniquement)
   - **Note sur les ateliers** => Utiliser l'entité `Comment` existante (liée à Activity)
   - **Notes sur les séances** => Une séance = Une tâche => Créer une preuve (Proof) de la tâche ✅ (déjà géré)

---

## 🏗️ Architecture Simplifiée

### 1. Partage d'Objectifs (UNIQUEMENT)

**Solution TRÈS SIMPLE : Relations ManyToMany uniquement dans Objective**

**Modifications minimales** :

**Dans Objective** : Ajouter 2 relations ManyToMany simples
```php
// Ajouter (garder les relations existantes student et coach)
#[ORM\ManyToMany(targetEntity: Student::class)]
#[ORM\JoinTable(name: 'objective_shared_students')]
private Collection $sharedStudents;

#[ORM\ManyToMany(targetEntity: Specialist::class)]
#[ORM\JoinTable(name: 'objective_shared_specialists')]
private Collection $sharedSpecialists;
```

**C'est tout !** Pas de modification dans Task, pas de nouvelle entité, juste 2 relations ManyToMany dans Objective.

### Comportement du Partage

**Quand on partage un OBJECTIF** :
- ✅ L'utilisateur partagé peut **voir l'objectif**
- ✅ L'utilisateur partagé peut **voir toutes les tâches** de cet objectif (automatiquement)
- ✅ L'utilisateur partagé peut **ajouter des preuves** aux tâches
- ❌ L'utilisateur partagé **ne peut pas modifier** l'objectif ni les tâches

**Logique de visibilité dans les repositories** :
```php
// Pour voir un objectif : 
// - Si je suis le student/coach propriétaire OU
// - Si je suis dans sharedStudents/sharedSpecialists

// Pour voir une tâche :
// - Si je suis le student/coach/parent/specialist assigné OU
// - Si l'objectif parent est partagé avec moi (via sharedStudents/sharedSpecialists)
```

**Modifications nécessaires** :
1. ✅ Ajouter 2 relations ManyToMany dans `Objective` (sharedStudents, sharedSpecialists)
2. ✅ Migration (créer 2 tables de jointure : `objective_shared_students` et `objective_shared_specialists`)
3. ✅ Mettre à jour les repositories pour inclure les partagés dans les requêtes
4. ✅ Créer 1 endpoint API simple : `POST /api/objectives/{id}/share`
5. ✅ Interface : bouton "Partager" avec modal de sélection d'élèves/spécialistes

### 2. Famille - Groupes

**Modifications nécessaires** :

1. **Ajouter champ "Lieu" à Family** :
   ```php
   #[ORM\Column(length: 255, nullable: true)]
   private ?string $location = null;
   ```

2. **Ajouter relation ManyToMany avec Specialist** :
   ```php
   #[ORM\ManyToMany(targetEntity: Specialist::class)]
   #[ORM\JoinTable(name: 'family_specialists')]
   private Collection $specialists;
   ```

3. **Migration** : Ajouter les colonnes/tables nécessaires

4. **Interface** : Ajouter les champs dans le formulaire de création/édition de groupe

### 3. Planning - Preuves

**Modifications nécessaires** :

1. **Modal d'information sur la tâche** :
   - Créer un endpoint API pour récupérer les détails d'une tâche
   - Créer un composant modal frontend

2. **Historique des preuves** :
   - Créer un endpoint API pour récupérer les preuves d'une tâche filtrées par date
   - Afficher dans le modal

3. **Date de soumission automatique** :
   - Modifier le service de création de preuve pour utiliser `planning.startDate` comme `submittedAt` si un planning est associé

4. **Notes rapides sur les preuves** :
   - **Note sur les élèves** : Utiliser l'entité `Note` existante (liée à Student uniquement, pas de lien avec Proof)
   - **Note sur les ateliers** : Utiliser l'entité `Comment` existante (liée à Activity)
   - **ASSESSMENT (Bilan)** : Ajouter champ `assessmentNotes` (text) dans Proof

---

## 📝 Plan d'Implémentation Simplifié

### Phase 1 : Partage d'Objectifs (1 jour)

1. **Backend (1-2h)** :
   - Ajouter 2 relations ManyToMany dans `Objective` (sharedStudents, sharedSpecialists)
   - Créer la migration (2 tables de jointure)
   - Mettre à jour les repositories pour inclure les partagés
   - Créer 1 endpoint API simple : `POST /api/objectives/{id}/share`

2. **Frontend (1-2h)** :
   - Bouton "Partager" sur les objectifs
   - Modal simple avec sélection d'élèves/spécialistes
   - Afficher la liste des personnes avec qui l'objectif est partagé

### Phase 2 : Famille - Groupes (1 jour)

1. **Backend (1-2h)** :
   - Ajouter champ `location` (string nullable) à `Family`
   - Ajouter relation ManyToMany `specialists` à `Family`
   - Migration
   - Mettre à jour le formulaire et le controller

2. **Frontend (1-2h)** :
   - Ajouter champ "Lieu" dans le formulaire de groupe
   - Ajouter sélection multiple de spécialistes dans le formulaire
   - Afficher les spécialistes dans la liste des groupes

### Phase 3 : Planning - Preuves (1-2 jours)

1. **Backend (2-3h)** :
   - Endpoint existant pour détails de tâche (probablement déjà là)
   - Endpoint pour historique des preuves par date : `GET /api/tasks/{id}/proofs?date=YYYY-MM-DD`
   - Modifier service de création de preuve : si `planning` existe, utiliser `planning.startDate` pour `submittedAt`
   - Ajouter champ `assessmentNotes` (text) dans `Proof` pour les bilans

2. **Frontend (2-3h)** :
   - Modal d'information sur la tâche au clic
   - Afficher l'historique des preuves filtré par date
   - Formulaire d'ajout de preuve depuis le planning
   - Interface pour ajouter des notes sur les élèves (utiliser Note existante)
   - Interface pour ajouter des commentaires sur les ateliers (utiliser Comment existant)

---

## 🔄 Ordre de Priorité Suggéré

1. **Priorité 1** : Partage d'Objectifs et Tâches (fonctionnalité principale)
2. **Priorité 2** : Planning - Preuves (amélioration UX importante)
3. **Priorité 3** : Famille - Groupes (amélioration mineure)

---

## ✅ Toutes les Questions sont Résolues

1. **Notes sur les séances** : ✅ Une séance = Une tâche => Créer une preuve (Proof) de la tâche (déjà géré)

2. **Autres types de tâches - Champs spécifiques** :
   - **TASK** : Aucun champ spécifique supplémentaire ✅
   - **ACTIVITY_TASK** : Aucun champ spécifique supplémentaire ✅
   - **SCHOOL_ACTIVITY_TASK** : Aucun champ spécifique supplémentaire ✅
   - **INDIVIDUAL_WORK** : Aucun champ spécifique supplémentaire ✅
   - **INDIVIDUAL_WORK_REMOTE** : Aucun champ spécifique supplémentaire ✅
   - **INDIVIDUAL_WORK_ON_SITE** : Aucun champ spécifique supplémentaire ✅
   - **WORKSHOP** : Déjà implémenté ✅
   - **ASSESSMENT** : Ajouter `assessmentNotes` dans Proof ✅
   
   **Tous les types sont couverts !**

3. **Date de soumission** : ✅ Utiliser `planning.startDate` comme `submittedAt` (confirmé)

---

## 📌 Notes Techniques

- Utiliser les migrations Doctrine pour toutes les modifications de schéma
- Tester la rétrocompatibilité avec les données existantes
- Mettre à jour les tests unitaires et fonctionnels
- Documenter les nouveaux endpoints API


## ✅ Décisions Finales

### Partage d'Objectifs
- ✅ Depuis le rightsheet, avec liste des élèves et spécialistes du groupe
- ✅ Seul le coach peut partager

### Preuves (Proof)
- ✅ **ASSESSMENT (Bilan)** : ajouter champ `assessmentNotes` (text) dans `Proof`
- ✅ **Date de soumission** : utiliser `planning.startDate` comme `submittedAt`
- ✅ **Notes sur les séances** : Une séance = Une tâche => Une note de séance crée une preuve (Proof) de la tâche ✅ (déjà géré)

### Notes
- ✅ **Notes sur les élèves** : Utiliser l'entité `Note` existante (liée à Student uniquement)
- ✅ **Pas de lien entre Note et Proof** : Les notes restent indépendantes
- ❌ **Notes sur les spécialistes** : Ne pas implémenter (oublié)

### Commentaires sur les Ateliers
- ✅ **Notes sur les ateliers** : Utiliser l'entité `Comment` existante (Activity a déjà une relation avec Comment) ✅ 
