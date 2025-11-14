# Champs des Tâches et Preuves

## Types de Tâches

### Champs communs à toutes les tâches

- `id` (int) - Identifiant unique
- `title` (string, requis) - Titre de la tâche
- `description` (text, requis) - Description de la tâche
- `status` (string, requis) - Statut : `pending`, `in_progress`, `completed`
- `type` (TaskType, requis, défaut: `TASK`) - Type de tâche
- `frequency` (string, nullable) - Fréquence : `none`, `hourly`, `daily`, `half_day`, `every_2_days`, `weekly`, `monthly`, `yearly`
- `requiresProof` (boolean, défaut: `true`) - Nécessite des preuves
- `proofType` (string, nullable) - Type de preuve requis
- `createdAt` (DateTimeImmutable, requis) - Date de création
- `updatedAt` (DateTimeImmutable, requis) - Date de mise à jour
- `dueDate` (DateTimeImmutable, nullable) - Date limite
- `coach` (Coach, requis) - Coach responsable
- `objective` (Objective, requis) - Objectif associé
- `assignedType` (string, requis) - Type d'assignation : `coach`, `student`, `parent`, `specialist`
- `student` (Student, nullable) - Étudiant assigné (si `assignedType = 'student'`)
- `parent` (ParentUser, nullable) - Parent assigné (si `assignedType = 'parent'`)
- `specialist` (Specialist, nullable) - Spécialiste assigné (si `assignedType = 'specialist'`)
- `activity` (Activity, nullable) - Activité liée (pour `ACTIVITY_TASK`)
- `path` (Path, nullable) - Activité scolaire liée (pour `SCHOOL_ACTIVITY_TASK`)

---

## Champs spécifiques par type de tâche

### 1. TASK - Tâche

**Champs spécifiques :**
- Aucun champ spécifique supplémentaire

**Champs utilisés :**
- Tous les champs communs
- `createdAt` et `dueDate` avec heures (format `datetime-local`)

---

### 2. ACTIVITY_TASK - Tâche activité

**Champs spécifiques :**
- `activity` (Activity, requis) - Activité liée (ManyToOne)

**Comportement :**
- Le titre et la description sont pré-remplis depuis l'activité sélectionnée
- `createdAt` et `dueDate` en format date uniquement (sans heures)

---

### 3. SCHOOL_ACTIVITY_TASK - Tâche activité scolaire

**Champs spécifiques :**
- `path` (Path, requis) - Activité scolaire liée (ManyToOne)

**Comportement :**
- Le titre et la description sont pré-remplis depuis le path sélectionné
- `createdAt` et `dueDate` en format date uniquement (sans heures)

---

### 4. WORKSHOP - Atelier

**Champs spécifiques :**
- `location` (string, nullable) - Lieu de l'atelier
- `family` (Family, nullable) - Famille concernée (ManyToOne)

**Champs dans les preuves (Proof) :**
- `specialists` (Collection<Specialist>, ManyToMany) - Spécialistes présents lors de la soumission
- `students` (Collection<Student>, ManyToMany) - Étudiants concernés
- `activities` (Collection<Activity>, ManyToMany) - Activités liées
- `paths` (Collection<Path>, ManyToMany) - Activités scolaires liées

**Comportement :**
- `createdAt` et `dueDate` en format date uniquement (sans heures)

---

### 5. ASSESSMENT - Bilan

**Champs spécifiques :**
- `assessmentNotes` (text, nullable) - Notes du bilan

**Champs dans les preuves (Proof) :**
- `students` (Collection<Student>, ManyToMany) - Étudiants concernés

**Comportement :**
- `createdAt` et `dueDate` en format date uniquement (sans heures)

---

### 6. INDIVIDUAL_WORK - Travail individuel

**Champs spécifiques :**
- Aucun champ spécifique supplémentaire

**Champs dans les preuves (Proof) :**
- `students` (Collection<Student>, ManyToMany) - Étudiants concernés

**Comportement :**
- `createdAt` et `dueDate` en format date uniquement (sans heures)

---

### 7. INDIVIDUAL_WORK_REMOTE - Travail individuel à distance

**Champs spécifiques :**
- Aucun champ spécifique dans Task

**Champs dans les preuves (Proof) :**
- `request` (Request, nullable) - Demande liée (ManyToOne)
- `students` (Collection<Student>, ManyToMany) - Étudiants concernés

**Comportement :**
- `createdAt` et `dueDate` en format date uniquement (sans heures)

---

### 8. INDIVIDUAL_WORK_ON_SITE - Travail individuel dans un lieu

**Champs spécifiques :**
- `location` (string, nullable) - Lieu du travail

**Champs dans les preuves (Proof) :**
- `students` (Collection<Student>, ManyToMany) - Étudiants concernés

**Comportement :**
- `createdAt` et `dueDate` en format date uniquement (sans heures)

---

## Entité Proof (Preuve)

### Champs communs

- `id` (int) - Identifiant unique
- `title` (string, requis) - Titre de la preuve
- `description` (text, nullable) - Description de la preuve
- `type` (string, requis) - Type de preuve
- `filePath` (string, nullable) - Chemin du fichier
- `fileUrl` (string, nullable) - URL du fichier
- `fileName` (string, nullable) - Nom du fichier
- `fileSize` (int, nullable) - Taille du fichier
- `mimeType` (string, nullable) - Type MIME du fichier
- `content` (text, nullable) - Contenu texte (si pas d'image)
- `createdAt` (DateTimeImmutable, requis) - Date de création
- `updatedAt` (DateTimeImmutable, requis) - Date de mise à jour
- `task` (Task, nullable) - Tâche associée (ManyToOne)
- `planning` (Planning, nullable) - Événement de planning associé (ManyToOne)
- `submittedBy` (User, requis) - Utilisateur qui a soumis la preuve (ManyToOne)

### Champs relationnels (ManyToMany)

- `specialists` (Collection<Specialist>) - Spécialistes présents lors de la soumission
- `activities` (Collection<Activity>) - Activités liées à cette preuve
- `paths` (Collection<Path>) - Activités scolaires liées à cette preuve
- `students` (Collection<Student>) - Étudiants concernés par cette preuve

### Champs relationnels (ManyToOne)

- `request` (Request, nullable) - Demande liée à cette preuve (pour `INDIVIDUAL_WORK_REMOTE`)

---

## Récapitulatif des relations Proof par type de tâche

| Type de Tâche | Specialists | Activities | Paths | Students | Request |
|---------------|-------------|------------|-------|----------|---------|
| TASK | ❌ | ❌ | ❌ | ❌ | ❌ |
| ACTIVITY_TASK | ❌ | ❌ | ❌ | ❌ | ❌ |
| SCHOOL_ACTIVITY_TASK | ❌ | ❌ | ❌ | ❌ | ❌ |
| WORKSHOP | ✅ | ✅ | ✅ | ✅ | ❌ |
| ASSESSMENT | ❌ | ❌ | ❌ | ✅ | ❌ |
| INDIVIDUAL_WORK | ❌ | ❌ | ❌ | ✅ | ❌ |
| INDIVIDUAL_WORK_REMOTE | ❌ | ❌ | ❌ | ✅ | ✅ |
| INDIVIDUAL_WORK_ON_SITE | ❌ | ❌ | ❌ | ✅ | ❌ |

---

## Notes importantes

1. **Champs dans Task vs Proof** :
   - Les champs `specialists`, `activities`, `paths`, `students`, et `request` sont stockés dans **Proof**, pas dans **Task**
   - Cela permet de documenter qui était présent et quelles activités/paths étaient concernées lors de la **soumission** de la preuve, pas lors de la **création** de la tâche

2. **Format des dates** :
   - `TASK` : `datetime-local` (avec heures)
   - Autres types : `date` uniquement (sans heures)

3. **Pré-remplissage** :
   - `ACTIVITY_TASK` : titre et description pré-remplis depuis `activity`
   - `SCHOOL_ACTIVITY_TASK` : titre et description pré-remplis depuis `path`

4. **Champs obligatoires** :
   - `ACTIVITY_TASK` : `activity` est requis
   - `SCHOOL_ACTIVITY_TASK` : `path` est requis
   - `INDIVIDUAL_WORK_REMOTE` : `request` peut être dans Proof (optionnel)

