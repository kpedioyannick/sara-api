# Commande Symfony : Génération de Données Démo

**Commande**: `app:load-demo-data`  
**Description**: Crée un environnement de démonstration complet avec des données réalistes pour tester toutes les fonctionnalités de l'application.

## 📋 Structure des Données à Générer

### 1. Utilisateurs

#### 1.1 Coach Demo
- **Email**: `coach.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Marie`
- **Nom**: `Dupont`
- **Spécialisation**: `Accompagnement scolaire et orientation`
- **Rôle**: `ROLE_COACH`
- **Actif**: `true`

#### 1.2 Spécialistes (5 minimum - Professeurs)

**Spécialiste 1 - Professeur de Mathématiques**
- **Email**: `prof.maths.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Sophie`
- **Nom**: `Martin`
- **Spécialisations**: `["mathématiques", "algèbre", "géométrie"]`
- **Rôle**: `ROLE_SPECIALIST`
- **Actif**: `true`

**Spécialiste 2 - Professeur de Théâtre**
- **Email**: `prof.theatre.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Jean`
- **Nom**: `Bernard`
- **Spécialisations**: `["théâtre", "expression orale", "art dramatique"]`
- **Rôle**: `ROLE_SPECIALIST`
- **Actif**: `true`

**Spécialiste 3 - Professeur de Musique**
- **Email**: `prof.musique.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Claire`
- **Nom**: `Lefebvre`
- **Spécialisations**: `["musique", "solfège", "instrument"]`
- **Rôle**: `ROLE_SPECIALIST`
- **Actif**: `true`

**Spécialiste 4 - Professeur de Collège (Multi-matières)**
- **Email**: `prof.college.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Pierre`
- **Nom**: `Dubois`
- **Spécialisations**: `["français", "histoire", "géographie", "sciences"]`
- **Rôle**: `ROLE_SPECIALIST`
- **Actif**: `true`

**Spécialiste 5 - Professeur d'Arts Plastiques**
- **Email**: `prof.arts.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Marie`
- **Nom**: `Garcia`
- **Spécialisations**: `["arts plastiques", "dessin", "peinture", "créativité"]`
- **Rôle**: `ROLE_SPECIALIST`
- **Actif**: `true`

#### 1.3 Parents (2 minimum)

**Parent 1**
- **Email**: `parent1.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Pierre`
- **Nom**: `Durand`
- **Rôle**: `ROLE_PARENT`
- **Actif**: `true`

**Parent 2**
- **Email**: `parent2.demo@sara.fr`
- **Mot de passe**: `demo123`
- **Prénom**: `Isabelle`
- **Nom**: `Moreau`
- **Rôle**: `ROLE_PARENT`
- **Actif**: `true`

#### 1.4 Élèves (4 minimum, 2 par groupe)

**Groupe 1 - Élèves**
- **Élève 1**
  - **Email**: `eleve1.demo@sara.fr`
  - **Mot de passe**: `demo123`
  - **Prénom**: `Lucas`
  - **Nom**: `Durand`
  - **Pseudo**: `LucasD`
  - **Classe**: `5ème`
  - **École**: `Collège Victor Hugo`
  - **Points**: `150`
  - **Tags de besoins**: `["difficultés en mathématiques", "manque de confiance"]`
  - **Rôle**: `ROLE_STUDENT`
  - **Actif**: `true`

- **Élève 2**
  - **Email**: `eleve2.demo@sara.fr`
  - **Mot de passe**: `demo123`
  - **Prénom**: `Emma`
  - **Nom**: `Durand`
  - **Pseudo**: `EmmaD`
  - **Classe**: `4ème`
  - **École**: `Collège Victor Hugo`
  - **Points**: `200`
  - **Tags de besoins**: `["difficultés en français", "organisation"]`
  - **Rôle**: `ROLE_STUDENT`
  - **Actif**: `true`

**Groupe 2 - Élèves**
- **Élève 3**
  - **Email**: `eleve3.demo@sara.fr`
  - **Mot de passe**: `demo123`
  - **Prénom**: `Thomas`
  - **Nom**: `Moreau`
  - **Pseudo**: `ThomasM`
  - **Classe**: `6ème`
  - **École**: `Collège Jean Jaurès`
  - **Points**: `100`
  - **Tags de besoins**: `["troubles de l'attention", "difficultés en lecture"]`
  - **Rôle**: `ROLE_STUDENT`
  - **Actif**: `true`

- **Élève 4**
  - **Email**: `eleve4.demo@sara.fr`
  - **Mot de passe**: `demo123`
  - **Prénom**: `Léa`
  - **Nom**: `Moreau`
  - **Pseudo**: `LeaM`
  - **Classe**: `3ème`
  - **École**: `Collège Jean Jaurès`
  - **Points**: `250`
  - **Tags de besoins**: `["orientation", "préparation au brevet"]`
  - **Rôle**: `ROLE_STUDENT`
  - **Actif**: `true`

### 2. Groupes (Uniquement Type: GROUP)

#### 2.1 Groupe 1 (Type: GROUP)
- **Identifiant**: `GRP_DEMO_001`
- **Type**: `GROUP`
- **Lieu**: `Salle de réunion - Collège Victor Hugo`
- **Coach**: Coach Demo (Marie Dupont)
- **Spécialistes**: Professeur de Mathématiques (Sophie Martin), Professeur de Collège (Pierre Dubois)
- **Élèves**: Élève 1 (Lucas), Élève 2 (Emma)
- **Actif**: `true`
- **Créé le**: Date actuelle

#### 2.2 Groupe 2 (Type: GROUP)
- **Identifiant**: `GRP_DEMO_002`
- **Type**: `GROUP`
- **Lieu**: `Centre d'accompagnement - 15 rue de la Paix`
- **Coach**: Coach Demo (Marie Dupont)
- **Spécialistes**: Professeur de Théâtre (Jean Bernard), Professeur de Musique (Claire Lefebvre), Professeur d'Arts Plastiques (Marie Garcia)
- **Élèves**: Élève 3 (Thomas), Élève 4 (Léa)
- **Actif**: `true`
- **Créé le**: Date actuelle

### 3. Objectifs

#### 3.1 Objectifs Individuels pour Élèves

**Objectif 1 - Élève 1 (Lucas)**
- **Titre**: `Améliorer les compétences en mathématiques`
- **Description**: `Travailler sur les opérations de base et la résolution de problèmes`
- **Catégorie**: `Scolaire`
- **Tags**: `["mathématiques", "calcul", "problèmes"]`
- **Statut**: `in_action`
- **Progression**: `40`
- **Date limite**: `+3 mois`
- **Élève**: Élève 1 (Lucas)
- **Coach**: Coach Demo
- **Partagé avec**: Aucun (objectif individuel)

**Objectif 2 - Élève 2 (Emma)**
- **Titre**: `Renforcer la confiance en soi`
- **Description**: `Développer l'estime de soi et la capacité à s'exprimer`
- **Catégorie**: `Personnel`
- **Tags**: `["confiance", "estime de soi", "expression"]`
- **Statut**: `validated`
- **Progression**: `60`
- **Date limite**: `+2 mois`
- **Élève**: Élève 2 (Emma)
- **Coach**: Coach Demo
- **Partagé avec**: Aucun (objectif individuel)

**Objectif 3 - Élève 3 (Thomas)**
- **Titre**: `Améliorer la concentration et l'attention`
- **Description**: `Travailler sur les techniques de concentration et de gestion de l'attention`
- **Catégorie**: `Scolaire`
- **Tags**: `["concentration", "attention", "méthodologie"]`
- **Statut**: `pending_validation`
- **Progression**: `20`
- **Date limite**: `+4 mois`
- **Élève**: Élève 3 (Thomas)
- **Coach**: Coach Demo
- **Partagé avec**: Aucun (objectif individuel)

**Objectif 4 - Élève 4 (Léa)**
- **Titre**: `Préparer l'orientation post-3ème`
- **Description**: `Explorer les différentes filières et options d'orientation`
- **Catégorie**: `Orientation`
- **Tags**: `["orientation", "brevet", "lycée"]`
- **Statut**: `in_action`
- **Progression**: `30`
- **Date limite**: `+6 mois`
- **Élève**: Élève 4 (Léa)
- **Coach**: Coach Demo
- **Partagé avec**: Aucun (objectif individuel)

#### 3.2 Objectifs Partagés entre Élèves du Même Groupe

**Objectif 5 - Partagé entre Élèves du Groupe 1**
- **Titre**: `Améliorer la communication en groupe`
- **Description**: `Apprendre à travailler en équipe et à communiquer efficacement`
- **Catégorie**: `Social`
- **Tags**: `["communication", "travail d'équipe", "coopération"]`
- **Statut**: `in_action`
- **Progression**: `50`
- **Date limite**: `+2 mois`
- **Élève**: Élève 1 (Lucas) - propriétaire
- **Coach**: Coach Demo
- **Partagé avec**: Élève 2 (Emma) - du même groupe

**Objectif 6 - Partagé entre Élèves du Groupe 2**
- **Titre**: `Développer l'autonomie dans les apprentissages`
- **Description**: `Apprendre à organiser son travail et à être autonome`
- **Catégorie**: `Méthodologie`
- **Tags**: `["autonomie", "organisation", "méthodologie"]`
- **Statut**: `validated`
- **Progression**: `70`
- **Date limite**: `+1 mois`
- **Élève**: Élève 3 (Thomas) - propriétaire
- **Coach**: Coach Demo
- **Partagé avec**: Élève 4 (Léa) - du même groupe

#### 3.3 Objectifs Partagés entre Coach et Spécialistes (Professeurs)

**Objectif 7 - Partagé avec Spécialistes**
- **Titre**: `Suivi mathématiques et pédagogique`
- **Description**: `Coordination entre le professeur de mathématiques et le coach pour le suivi des difficultés en mathématiques`
- **Catégorie**: `Scolaire`
- **Tags**: `["mathématiques", "coordination", "soutien"]`
- **Statut**: `in_action`
- **Progression**: `45`
- **Date limite**: `+3 mois`
- **Élève**: Élève 1 (Lucas)
- **Coach**: Coach Demo
- **Partagé avec**: Professeur de Mathématiques (Sophie Martin), Professeur de Collège (Pierre Dubois)

**Objectif 8 - Partagé avec Spécialistes**
- **Titre**: `Développement artistique et créatif`
- **Description**: `Suivi conjoint pour développer les compétences artistiques et la créativité`
- **Catégorie**: `Scolaire`
- **Tags**: `["arts", "créativité", "expression"]`
- **Statut**: `validated`
- **Progression**: `55`
- **Date limite**: `+2 mois`
- **Élève**: Élève 2 (Emma)
- **Coach**: Coach Demo
- **Partagé avec**: Professeur de Théâtre (Jean Bernard), Professeur d'Arts Plastiques (Marie Garcia)

### 4. Tâches

**Note sur les jours de la semaine** : 
- Les jours sont représentés par des nombres : `0` = Dimanche, `1` = Lundi, `2` = Mardi, `3` = Mercredi, `4` = Jeudi, `5` = Vendredi, `6` = Samedi
- Pour les tâches avec `fréquence: none`, les jours de la semaine sont `null` (tâche unique)
- Pour les tâches avec `fréquence: daily`, spécifier les jours concernés (ex: `[1, 2, 3, 4, 5]` pour lundi à vendredi)
- Pour les tâches avec `fréquence: weekly`, spécifier le jour de la semaine (ex: `[2]` pour chaque mardi)
- Pour les tâches avec `fréquence: monthly`, spécifier le jour de la semaine pour le premier du mois (ex: `[1]` pour le premier lundi de chaque mois)

#### 4.1 Tâches pour Objectif 1 (Lucas - Mathématiques)

**Tâche 1 - Type: TASK**
- **Titre**: `Faire des devoirs`
- **Description**: `Faire les devoirs quotidiens dans toutes les matières`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `file`
- **Date de début**: `-2 semaines (lundi)`
- **Date limite**: `+2 mois (dernier jour d'école)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`

**Tâche 1b - Type: TASK**
- **Titre**: `Réviser tous les soirs`
- **Description**: `Réviser les leçons de la journée chaque soir pendant 30 minutes`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi)
- **Preuve obligatoire**: `false`
- **Date de début**: `-1 semaine (lundi)`
- **Date limite**: `+2 mois (dernier jour d'école)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`

**Tâche 1c - Type: TASK**
- **Titre**: `Se coucher tôt`
- **Description**: `Se coucher avant 21h30 pour être en forme le lendemain`
- **Type**: `task`
- **Statut**: `pending`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[0, 1, 2, 3, 4, 5, 6]` (Tous les jours)
- **Preuve obligatoire**: `false`
- **Date de début**: `+1 jour (demain)`
- **Date limite**: `+2 mois (fin de période)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`

**Tâche 1d - Type: TASK**
- **Titre**: `Ne pas bavarder en classe`
- **Description**: `Rester concentré et ne pas bavarder pendant les cours`
- **Type**: `task`
- **Statut**: `pending`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi - jours d'école)
- **Preuve obligatoire**: `false`
- **Date de début**: `+1 jour (demain)`
- **Date limite**: `+2 mois (fin de période)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`

**Tâche 2 - Type: INDIVIDUAL_WORK**
- **Titre**: `Faire 10 exercices de calcul mental`
- **Description**: `Compléter une série de 10 exercices de calcul mental`
- **Type**: `individual_work`
- **Statut**: `in_progress`
- **Fréquence**: `weekly`
- **Jours de la semaine**: `[3]` (Mercredi - chaque mercredi)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `file`
- **Date de début**: `-1 semaine (mercredi dernier)`
- **Date limite**: `+1 mois (dernier mercredi du mois)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`

**Tâche 2b - Type: TASK**
- **Titre**: `Faire des devoirs`
- **Description**: `Faire les devoirs quotidiens dans toutes les matières`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `file`
- **Date de début**: `-1 semaine (lundi)`
- **Date limite**: `+2 mois (dernier jour d'école)`
- **Assigné à**: Élève 2 (Emma)
- **Type d'assignation**: `student`

**Tâche 2c - Type: TASK**
- **Titre**: `Réviser tous les soirs`
- **Description**: `Réviser les leçons de la journée chaque soir pendant 30 minutes`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi)
- **Preuve obligatoire**: `false`
- **Date de début**: `-5 jours (mercredi)`
- **Date limite**: `+2 mois (dernier jour d'école)`
- **Assigné à**: Élève 2 (Emma)
- **Type d'assignation**: `student`

**Tâche 3 - Type: INDIVIDUAL_WORK_REMOTE**
- **Titre**: `Session de révision en ligne`
- **Description**: `Participer à une session de révision en ligne sur les fractions`
- **Type**: `individual_work_remote`
- **Statut**: `pending`
- **Fréquence**: `none`
- **Jours de la semaine**: `null` (tâche unique)
- **Preuve obligatoire**: `false`
- **Date de début**: `+3 jours (date précise)`
- **Date limite**: `+1 semaine (date précise)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`

**Tâche 4 - Type: INDIVIDUAL_WORK_ON_SITE**
- **Titre**: `Séance de soutien au centre`
- **Description**: `Séance de soutien en mathématiques au centre d'accompagnement`
- **Type**: `individual_work_on_site`
- **Statut**: `pending`
- **Fréquence**: `none`
- **Jours de la semaine**: `null` (tâche unique)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `text`
- **Date de début**: `+5 jours (date précise)`
- **Date limite**: `+2 semaines (date précise)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`

#### 4.2 Tâches pour Objectif 2 (Emma - Confiance)

**Tâche 5 - Type: WORKSHOP**
- **Titre**: `Atelier "Expression orale - Théâtre"`
- **Description**: `Participer à un atelier de théâtre pour améliorer l'expression orale et la prise de parole`
- **Type**: `workshop`
- **Statut**: `completed`
- **Fréquence**: `none`
- **Jours de la semaine**: `null` (atelier unique)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `workshop`
- **Date de début**: `-1 semaine (date précise)`
- **Date limite**: `-3 jours (date précise)`
- **Assigné à**: Élève 2 (Emma)
- **Type d'assignation**: `student`

**Tâche 6 - Type: ASSESSMENT**
- **Titre**: `Bilan de progression en français`
- **Description**: `Réaliser un bilan avec le professeur de collège sur l'évolution en français`
- **Type**: `assessment`
- **Statut**: `in_progress`
- **Fréquence**: `monthly`
- **Jours de la semaine**: `[1]` (Premier lundi de chaque mois)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `text`
- **Date de début**: `-5 jours (premier lundi du mois)`
- **Date limite**: `+1 semaine (date précise)`
- **Assigné à**: Élève 2 (Emma)
- **Type d'assignation**: `student`

#### 4.3 Tâches pour Objectif 3 (Thomas - Concentration)

**Tâche 7 - Type: TASK**
- **Titre**: `Faire des devoirs`
- **Description**: `Faire les devoirs quotidiens dans toutes les matières`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `file`
- **Date de début**: `-1 semaine (lundi)`
- **Date limite**: `+2 mois (dernier jour d'école)`
- **Assigné à**: Élève 3 (Thomas)
- **Type d'assignation**: `student`

**Tâche 7b - Type: TASK**
- **Titre**: `Se coucher tôt`
- **Description**: `Se coucher avant 21h30 pour être en forme le lendemain`
- **Type**: `task`
- **Statut**: `pending`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[0, 1, 2, 3, 4, 5, 6]` (Tous les jours)
- **Preuve obligatoire**: `false`
- **Date de début**: `+1 jour (demain)`
- **Date limite**: `+2 mois (fin de période)`
- **Assigné à**: Élève 3 (Thomas)
- **Type d'assignation**: `student`

**Tâche 7c - Type: TASK**
- **Titre**: `Ne pas bavarder en classe`
- **Description**: `Rester concentré et ne pas bavarder pendant les cours`
- **Type**: `task`
- **Statut**: `pending`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi - jours d'école)
- **Preuve obligatoire**: `false`
- **Date de début**: `+1 jour (demain)`
- **Date limite**: `+2 mois (fin de période)`
- **Assigné à**: Élève 3 (Thomas)
- **Type d'assignation**: `student`

**Tâche 8 - Type: SCHOOL_ACTIVITY_TASK**
- **Titre**: `Activité scolaire - Exercices de lecture`
- **Description**: `Compléter des exercices de lecture et de compréhension`
- **Type**: `school_activity_task`
- **Statut**: `pending`
- **Fréquence**: `weekly`
- **Jours de la semaine**: `[4]` (Jeudi - chaque jeudi)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `file`
- **Date de début**: `+3 jours (jeudi prochain)`
- **Date limite**: `+2 mois (dernier jeudi du mois)`
- **Assigné à**: Élève 3 (Thomas)
- **Type d'assignation**: `student`

#### 4.4 Tâches pour Objectif 5 (Partagé Groupe 1)

**Tâche 9 - Type: WORKSHOP**
- **Titre**: `Atelier de communication en groupe`
- **Description**: `Atelier pour apprendre à communiquer et travailler en équipe`
- **Type**: `workshop`
- **Statut**: `in_progress`
- **Fréquence**: `none`
- **Jours de la semaine**: `null` (atelier unique)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `workshop`
- **Date de début**: `-3 jours (date précise)`
- **Date limite**: `+1 semaine (date précise)`
- **Assigné à**: Élève 1 (Lucas) et Élève 2 (Emma)
- **Type d'assignation**: `student` (multiple)

#### 4.5 Tâches pour Objectif 7 (Partagé avec Spécialistes)

**Tâche 10 - Type: TASK**
- **Titre**: `Séance de soutien en mathématiques`
- **Description**: `Séance hebdomadaire avec le professeur de mathématiques`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `weekly`
- **Jours de la semaine**: `[2]` (Mardi - chaque mardi)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `text`
- **Date de début**: `-2 semaines (mardi)`
- **Date limite**: `+2 mois (dernier mardi du mois)`
- **Assigné à**: Élève 1 (Lucas)
- **Type d'assignation**: `student`
- **Spécialiste associé**: Professeur de Mathématiques (Sophie Martin)

**Tâche 10b - Type: TASK**
- **Titre**: `Faire des devoirs`
- **Description**: `Faire les devoirs quotidiens dans toutes les matières`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi)
- **Preuve obligatoire**: `true`
- **Type de preuve**: `file`
- **Date de début**: `-1 semaine (lundi)`
- **Date limite**: `+2 mois (dernier jour d'école)`
- **Assigné à**: Élève 4 (Léa)
- **Type d'assignation**: `student`

**Tâche 10c - Type: TASK**
- **Titre**: `Réviser tous les soirs`
- **Description**: `Réviser les leçons de la journée chaque soir pendant 30 minutes`
- **Type**: `task`
- **Statut**: `in_progress`
- **Fréquence**: `daily`
- **Jours de la semaine**: `[1, 2, 3, 4, 5]` (Lundi à Vendredi)
- **Preuve obligatoire**: `false`
- **Date de début**: `-5 jours (mercredi)`
- **Date limite**: `+2 mois (dernier jour d'école)`
- **Assigné à**: Élève 4 (Léa)
- **Type d'assignation**: `student`

### 5. Activités

#### 5.1 Activités pour les Tâches ACTIVITY_TASK

**Activité 1 - Méditation**
- **Titre**: `Méditation guidée pour la concentration`
- **Description**: `Séance de méditation guidée de 10 minutes pour améliorer la concentration`
- **Type**: `individual`
- **Durée**: `10 minutes`
- **Statut**: `published`
- **Coach**: Coach Demo
- **Créé le**: `-1 mois`

**Activité 2 - Exercices de lecture**
- **Titre**: `Exercices de lecture et compréhension`
- **Description**: `Série d'exercices pour améliorer la lecture et la compréhension de texte`
- **Type**: `individual`
- **Durée**: `20-30 minutes`
- **Statut**: `published`
- **Coach**: Coach Demo
- **Créé le**: `-2 semaines`

### 6. Preuves (Proofs)

#### 6.1 Preuves pour Tâche 1 (Tables de multiplication)
- **Titre**: `Photo des tables de multiplication révisées`
- **Description**: `Photo du cahier avec les tables révisées`
- **Type**: `file`
- **Date de soumission**: `-1 semaine`
- **Soumis par**: Élève 1 (Lucas)
- **Tâche**: Tâche 1

#### 6.2 Preuves pour Tâche 5 (Atelier Théâtre)
- **Titre**: `Participation à l'atelier théâtre`
- **Description**: `Résumé de la séance et points abordés sur l'expression orale`
- **Type**: `workshop`
- **Date de soumission**: `-3 jours`
- **Soumis par**: Élève 2 (Emma)
- **Tâche**: Tâche 5
- **Participants**: Élève 2 (Emma), Élève 1 (Lucas)
- **Organisateurs**: Coach Demo, Professeur de Théâtre (Jean Bernard)
- **Activités**: Activité 1 (Méditation)

#### 6.3 Preuves pour Tâche 6 (Bilan français)
- **Titre**: `Bilan de progression en français`
- **Description**: `Notes de la séance avec le professeur de collège : amélioration notable en grammaire, meilleure compréhension des textes`
- **Type**: `text`
- **Date de soumission**: `-2 jours`
- **Soumis par**: Élève 2 (Emma)
- **Tâche**: Tâche 6

### 7. Demandes (Requests) et Messages - Questions Scolaires et Aide aux Devoirs

#### 7.1 Demande 1 - Question Mathématiques (Élève 1 → Coach)
- **Titre**: `Question sur les fractions`
- **Description**: `Je ne comprends pas comment additionner des fractions avec des dénominateurs différents. Pouvez-vous m'expliquer ?`
- **Type**: `soutien_scolaire`
- **Statut**: `in_progress`
- **Priorité**: `high`
- **Créateur**: Élève 1 (Lucas)
- **Coach**: Coach Demo
- **Élève**: Élève 1 (Lucas)
- **Créé le**: `-1 semaine`

**Messages de la Demande 1**:
- **Message 1** (Élève 1 → Coach)
  - **Contenu**: `Bonjour, je ne comprends pas comment additionner des fractions avec des dénominateurs différents. Pouvez-vous m'expliquer ?`
  - **Type**: `text`
  - **Envoyé le**: `-1 semaine`
  - **Expéditeur**: Élève 1 (Lucas)
  - **Destinataire**: Coach Demo

- **Message 2** (Coach → Élève 1)
  - **Contenu**: `Bonjour Lucas, bien sûr ! Pour additionner des fractions avec des dénominateurs différents, il faut d'abord trouver un dénominateur commun. Je vais te préparer un exemple détaillé.`
  - **Type**: `text`
  - **Envoyé le**: `-6 jours`
  - **Expéditeur**: Coach Demo
  - **Destinataire**: Élève 1 (Lucas)

- **Message 3** (Élève 1 → Coach)
  - **Contenu**: `Merci beaucoup ! J'ai essayé avec votre méthode et ça fonctionne mieux maintenant.`
  - **Type**: `text`
  - **Envoyé le**: `-5 jours`
  - **Expéditeur**: Élève 1 (Lucas)
  - **Destinataire**: Coach Demo

#### 7.2 Demande 2 - Aide aux Devoirs Français (Élève 2 → Coach)
- **Titre**: `Aide pour un exercice de français`
- **Description**: `J'ai besoin d'aide pour faire un exercice sur les compléments d'objet. Je ne comprends pas la différence entre COD et COI.`
- **Type**: `soutien_scolaire`
- **Statut**: `pending`
- **Priorité**: `medium`
- **Créateur**: Élève 2 (Emma)
- **Coach**: Coach Demo
- **Élève**: Élève 2 (Emma)
- **Créé le**: `-3 jours`

**Messages de la Demande 2**:
- **Message 4** (Élève 2 → Coach)
  - **Contenu**: `Bonjour, j'ai besoin d'aide pour un exercice de français. Je ne comprends pas la différence entre COD et COI.`
  - **Type**: `text`
  - **Envoyé le**: `-3 jours`
  - **Expéditeur**: Élève 2 (Emma)
  - **Destinataire**: Coach Demo

- **Message 5** (Coach → Élève 2)
  - **Contenu**: `Bonjour Emma, le COD répond à la question "qui ?" ou "quoi ?" après le verbe, tandis que le COI répond à "à qui ?" ou "à quoi ?". Je vais te donner des exemples pour mieux comprendre.`
  - **Type**: `text`
  - **Envoyé le**: `-2 jours`
  - **Expéditeur**: Coach Demo
  - **Destinataire**: Élève 2 (Emma)

#### 7.3 Demande 3 - Question Histoire (Élève 3 → Coach)
- **Titre**: `Question sur la Révolution française`
- **Description**: `Je dois faire un exposé sur la Révolution française mais je ne sais pas par où commencer.`
- **Type**: `soutien_scolaire`
- **Statut**: `resolved`
- **Priorité**: `medium`
- **Créateur**: Élève 3 (Thomas)
- **Coach**: Coach Demo
- **Élève**: Élève 3 (Thomas)
- **Créé le**: `-2 semaines`
- **Réponse**: `J'ai fourni un plan détaillé pour l'exposé et des ressources pour commencer.`

**Messages de la Demande 3**:
- **Message 6** (Élève 3 → Coach)
  - **Contenu**: `Bonjour, je dois faire un exposé sur la Révolution française mais je ne sais pas par où commencer.`
  - **Type**: `text`
  - **Envoyé le**: `-2 semaines`
  - **Expéditeur**: Élève 3 (Thomas)
  - **Destinataire**: Coach Demo

- **Message 7** (Coach → Élève 3)
  - **Contenu**: `Bonjour Thomas, je vais te donner un plan pour ton exposé. Commence par présenter les causes de la Révolution, puis les événements principaux, et enfin les conséquences. Je t'envoie un document avec les dates importantes.`
  - **Type**: `text`
  - **Envoyé le**: `-12 jours`
  - **Expéditeur**: Coach Demo
  - **Destinataire**: Élève 3 (Thomas)

- **Message 8** (Élève 3 → Coach)
  - **Contenu**: `Merci, j'ai commencé mon exposé avec votre plan. C'est beaucoup plus clair maintenant !`
  - **Type**: `text`
  - **Envoyé le**: `-10 jours`
  - **Expéditeur**: Élève 3 (Thomas)
  - **Destinataire**: Coach Demo

#### 7.4 Demande 4 - Aide aux Devoirs Sciences (Élève 4 → Coach)
- **Titre**: `Aide pour un exercice de sciences`
- **Description**: `Je bloque sur un exercice de physique sur les circuits électriques.`
- **Type**: `soutien_scolaire`
- **Statut**: `in_progress`
- **Priorité**: `high`
- **Créateur**: Élève 4 (Léa)
- **Coach**: Coach Demo
- **Élève**: Élève 4 (Léa)
- **Créé le**: `-5 jours`

**Messages de la Demande 4**:
- **Message 9** (Élève 4 → Coach)
  - **Contenu**: `Bonjour, je bloque sur un exercice de physique sur les circuits électriques. Je ne comprends pas comment calculer l'intensité.`
  - **Type**: `text`
  - **Envoyé le**: `-5 jours`
  - **Expéditeur**: Élève 4 (Léa)
  - **Destinataire**: Coach Demo

- **Message 10** (Coach → Élève 4)
  - **Contenu**: `Bonjour Léa, pour calculer l'intensité dans un circuit, tu utilises la loi d'Ohm : I = U/R. Je vais te montrer comment l'appliquer avec ton exercice.`
  - **Type**: `text`
  - **Envoyé le**: `-4 jours`
  - **Expéditeur**: Coach Demo
  - **Destinataire**: Élève 4 (Léa)

### 8. Notes et Commentaires

#### 8.1 Notes sur Élèves

**Note 1 - Élève 1 (Lucas)**
- **Type**: `observation`
- **Contenu**: `Lucas montre une bonne motivation pour les mathématiques. Progrès notables sur les tables de multiplication.`
- **Créée par**: Coach Demo
- **Créée le**: `-1 semaine`

**Note 2 - Élève 2 (Emma)**
- **Type**: `observation`
- **Contenu**: `Emma participe davantage en classe. Confiance en soi en amélioration.`
- **Créée par**: Coach Demo
- **Créée le**: `-5 jours`

#### 8.2 Commentaires sur Objectifs

**Commentaire 1 - Objectif 1**
- **Contenu**: `Excellent travail sur les tables de multiplication ! Continue comme ça.`
- **Créé par**: Coach Demo
- **Créé le**: `-1 semaine`
- **Objectif**: Objectif 1 (Lucas - Mathématiques)

**Commentaire 2 - Objectif 5 (Partagé)**
- **Contenu**: `Belle progression dans le travail en équipe. Les deux élèves collaborent bien ensemble.`
- **Créé par**: Coach Demo
- **Créé le**: `-2 jours`
- **Objectif**: Objectif 5 (Communication en groupe)

### 9. Planning (Événements)

#### 9.1 Événements Planifiés

**Événement 1 - Séance de soutien**
- **Titre**: `Séance de soutien mathématiques - Lucas`
- **Type**: `task`
- **Date de début**: `+3 jours à 14h00`
- **Date de fin**: `+3 jours à 15h30`
- **Statut**: `confirmed`
- **Élève**: Élève 1 (Lucas)
- **Coach**: Coach Demo
- **Tâche associée**: Tâche 2 (Exercices calcul mental)

**Événement 2 - Atelier**
- **Titre**: `Atelier théâtre - Groupe 1`
- **Type**: `workshop`
- **Date de début**: `+5 jours à 16h00`
- **Date de fin**: `+5 jours à 17h30`
- **Statut**: `confirmed`
- **Élèves**: Élève 1 (Lucas), Élève 2 (Emma)
- **Coach**: Coach Demo
- **Spécialistes**: Professeur de Théâtre (Jean Bernard)
- **Tâche associée**: Tâche 9 (Atelier communication)

**Événement 3 - Séance de soutien mathématiques**
- **Titre**: `Séance de soutien mathématiques - Lucas`
- **Type**: `task`
- **Date de début**: `+1 semaine à 10h00`
- **Date de fin**: `+1 semaine à 11h00`
- **Statut**: `confirmed`
- **Élève**: Élève 1 (Lucas)
- **Spécialiste**: Professeur de Mathématiques (Sophie Martin)
- **Tâche associée**: Tâche 10 (Séance de soutien mathématiques)

## 📝 Structure de la Commande

### Arguments et Options

```bash
php bin/console app:load-demo-data [--clear] [--force]
```

**Options**:
- `--clear`: Supprime toutes les données existantes avant de créer les données demo
- `--force`: Force la création même si des données demo existent déjà

### Étapes d'Exécution

1. **Vérification des données existantes**
   - Vérifier si un coach avec l'email `coach.demo@sara.fr` existe
   - Si `--force` n'est pas utilisé et que des données existent, demander confirmation

2. **Nettoyage (si --clear)**
   - Supprimer toutes les données liées aux utilisateurs demo
   - Supprimer les familles, groupes, objectifs, tâches, etc.

3. **Création des utilisateurs**
   - Créer le coach demo
   - Créer les spécialistes (3)
   - Créer les parents (2)
   - Créer les élèves (4)

4. **Création des groupes**
   - Créer le groupe 1
   - Créer le groupe 2
   - Lier les élèves aux groupes
   - Lier les spécialistes (professeurs) aux groupes

5. **Création des objectifs**
   - Créer les objectifs individuels (4)
   - Créer les objectifs partagés entre élèves (2)
   - Créer les objectifs partagés avec spécialistes (2)
   - Configurer les partages (ManyToMany)

6. **Création des tâches**
   - Créer les tâches pour chaque objectif
   - Varier les types de tâches (TASK, ACTIVITY_TASK, SCHOOL_ACTIVITY_TASK, WORKSHOP, ASSESSMENT, INDIVIDUAL_WORK, etc.)
   - Varier les statuts (pending, in_progress, completed)
   - Varier les fréquences (none, daily, weekly, monthly)

7. **Création des activités**
   - Créer les activités pour les tâches ACTIVITY_TASK

8. **Création des preuves**
   - Créer des preuves pour certaines tâches complétées
   - Varier les types de preuves (file, text, workshop)

9. **Création des demandes et messages**
   - Créer 3 demandes de soutien scolaire
   - Créer les messages associés (échanges entre élève et coach)

10. **Création des notes et commentaires**
    - Créer des notes sur les élèves
    - Créer des commentaires sur les objectifs

11. **Création du planning**
    - Créer des événements planifiés pour les prochaines semaines
    - Lier les événements aux tâches

12. **Affichage du résumé**
    - Afficher un tableau récapitulatif de toutes les données créées
    - Afficher les identifiants de connexion (email/mot de passe)

## 🔐 Identifiants de Connexion

### Coach
- **Email**: `coach.demo@sara.fr`
- **Mot de passe**: `demo123`

### Spécialistes (Professeurs)
- **Professeur de Mathématiques**: `prof.maths.demo@sara.fr` / `demo123`
- **Professeur de Théâtre**: `prof.theatre.demo@sara.fr` / `demo123`
- **Professeur de Musique**: `prof.musique.demo@sara.fr` / `demo123`
- **Professeur de Collège**: `prof.college.demo@sara.fr` / `demo123`
- **Professeur d'Arts Plastiques**: `prof.arts.demo@sara.fr` / `demo123`

### Parents
- **Parent 1**: `parent1.demo@sara.fr` / `demo123`
- **Parent 2**: `parent2.demo@sara.fr` / `demo123`

### Élèves
- **Élève 1**: `eleve1.demo@sara.fr` / `demo123`
- **Élève 2**: `eleve2.demo@sara.fr` / `demo123`
- **Élève 3**: `eleve3.demo@sara.fr` / `demo123`
- **Élève 4**: `eleve4.demo@sara.fr` / `demo123`

## ✅ Validation

La commande doit vérifier que :
- Tous les utilisateurs sont créés avec succès
- Toutes les relations sont correctement établies
- Les partages d'objectifs fonctionnent
- Les tâches sont correctement liées aux objectifs
- Les preuves sont correctement liées aux tâches
- Les messages sont correctement liés aux demandes
- Le planning contient des événements valides

## 📊 Statistiques Attendues

Après exécution, la base de données doit contenir :
- **1 coach**
- **5 spécialistes** (professeurs)
- **2 parents**
- **4 élèves**
- **2 groupes** (uniquement type GROUP)
- **8 objectifs** (4 individuels, 2 partagés élèves, 2 partagés avec professeurs)
- **15+ tâches** (incluant "Faire des devoirs", "Réviser tous les soirs", "Se coucher tôt", "Ne pas bavarder en classe", etc.)
- **2 activités**
- **3 preuves**
- **4 demandes** (questions scolaires et aide aux devoirs uniquement)
- **10 messages**
- **2 notes**
- **2 commentaires**
- **3 événements de planning**

