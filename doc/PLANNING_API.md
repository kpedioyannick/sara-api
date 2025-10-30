# 📅 API Planning - Coach

## 🎯 **Endpoints disponibles**

### 1. **Récupérer les plannings d'un élève**
```http
POST /api/coach/plannings/student-planning
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (obligatoire) :**
```json
{
  "student_id": 1,
  "type": "homework",
  "status": "to_do",
  "start_date": "2025-01-01",
  "end_date": "2025-01-31"
}
```

**Champs obligatoires :**
- `student_id` : ID de l'élève (obligatoire)

**Champs optionnels :**
- `type` : Type de planning (homework, revision, task, assessment, course, training, detention, activity, exam, objective, other)
- `status` : Statut (to_do, in_progress, completed, incomplete)
- `start_date` : Date de début de recherche (format Y-m-d)
- `end_date` : Date de fin de recherche (format Y-m-d)

**Réponse :**
```json
{
  "success": true,
  "message": "Plannings retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Devoir de mathématiques",
      "description": "Exercices pages 45-50",
      "startDate": "2025-01-15 14:00:00",
      "endDate": "2025-01-15 16:00:00",
      "date": "2025-01-15 14:00:00",
      "type": "homework",
      "status": "to_do",
      "student": {
        "id": 1,
        "firstName": "Jean",
        "lastName": "Dupont",
        "pseudo": "jean.dupont",
        "email": "jean.dupont@example.com"
      },
      "createdAt": "2025-01-10 10:00:00",
      "updatedAt": "2025-01-10 10:00:00"
    }
  ]
}
```

### 2. **Créer un planning pour un élève (simple ou récurrent)**
```http
POST /api/coach/plannings
Authorization: Bearer {token}
Content-Type: application/json
```

**Body pour un planning simple :**
```json
{
  "title": "Devoir de mathématiques",
  "description": "Exercices pages 45-50",
  "student_id": 1,
  "start_date": "2025-01-15 14:00:00",
  "end_date": "2025-01-15 16:00:00",
  "type": "homework",
  "status": "to_do"
}
```

**Body pour un planning récurrent :**
```json
{
  "title": "Cours de mathématiques",
  "description": "Cours hebdomadaire de mathématiques",
  "student_id": 1,
  "start_date": "2025-01-15 14:00:00",
  "end_date": "2025-01-15 16:00:00",
  "type": "course",
  "status": "to_do",
  "recurrence": "weekly",
  "recurrence_interval": 1,
  "recurrence_end": "2025-06-15 16:00:00",
  "max_occurrences": 20,
  "metadata": {
    "subject": "Mathématiques",
    "chapter": "Géométrie",
    "level": "6ème",
    "teacher": "M. Dupont",
    "room": "Salle 201"
  }
}
```

**Body pour un planning avec métadonnées :**
```json
{
  "title": "Devoir de français",
  "description": "Rédaction sur le thème de l'aventure",
  "student_id": 1,
  "start_date": "2025-01-20 09:00:00",
  "end_date": "2025-01-20 10:00:00",
  "type": "homework",
  "metadata": {
    "subject": "Français",
    "chapter": "Rédaction",
    "level": "6ème",
    "difficulty": "moyen",
    "estimated_duration": "60 minutes",
    "materials": ["cahier", "stylo", "dictionnaire"]
  }
}
```

**Champs obligatoires :**
- `title` : Titre du planning
- `student_id` : ID de l'élève
- `start_date` : Date de début (format Y-m-d H:i:s)
- `end_date` : Date de fin (format Y-m-d H:i:s)
- `type` : Type de planning

**Champs optionnels :**
- `description` : Description du planning
- `status` : Statut (défaut: to_do)
- `recurrence` : Type de récurrence (daily, weekly, monthly, yearly)
- `recurrence_interval` : Intervalle de répétition (défaut: 1)
- `recurrence_end` : Date de fin de récurrence (défaut: +3 mois)
- `max_occurrences` : Nombre maximum d'occurrences (défaut: 50)
- `metadata` : Métadonnées supplémentaires (objet JSON)

**Réponse :**
```json
{
  "success": true,
  "message": "Planning created successfully",
  "data": {
    "id": 1,
    "title": "Devoir de mathématiques",
    "description": "Exercices pages 45-50",
    "startDate": "2025-01-15 14:00:00",
    "endDate": "2025-01-15 16:00:00",
    "date": "2025-01-15 14:00:00",
    "type": "homework",
    "status": "to_do",
    "student": {
      "id": 1,
      "firstName": "Jean",
      "lastName": "Dupont",
      "pseudo": "jean.dupont",
      "email": "jean.dupont@example.com"
    },
    "createdAt": "2025-01-10 10:00:00",
    "updatedAt": "2025-01-10 10:00:00"
  }
}
```

### 3. **Récupérer un planning spécifique**
```http
GET /api/coach/plannings/{id}
Authorization: Bearer {token}
```

**Réponse :**
```json
{
  "success": true,
  "message": "Planning retrieved successfully",
  "data": {
    "id": 1,
    "title": "Devoir de mathématiques",
    "description": "Exercices pages 45-50",
    "startDate": "2025-01-15 14:00:00",
    "endDate": "2025-01-15 16:00:00",
    "date": "2025-01-15 14:00:00",
    "type": "homework",
    "status": "to_do",
    "student": {
      "id": 1,
      "firstName": "Jean",
      "lastName": "Dupont",
      "pseudo": "jean.dupont",
      "email": "jean.dupont@example.com"
    },
    "createdAt": "2025-01-10 10:00:00",
    "updatedAt": "2025-01-10 10:00:00"
  }
}
```

### 4. **Modifier un planning**
```http
PUT /api/coach/plannings/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Body :**
```json
{
  "title": "Devoir de mathématiques - Modifié",
  "description": "Exercices pages 45-55",
  "start_date": "2025-01-16 14:00:00",
  "end_date": "2025-01-16 16:00:00",
  "type": "homework",
  "status": "in_progress"
}
```

**Tous les champs sont optionnels.**

**Réponse :**
```json
{
  "success": true,
  "message": "Planning updated successfully",
  "data": {
    "id": 1,
    "title": "Devoir de mathématiques - Modifié",
    "description": "Exercices pages 45-55",
    "startDate": "2025-01-16 14:00:00",
    "endDate": "2025-01-16 16:00:00",
    "date": "2025-01-16 14:00:00",
    "type": "homework",
    "status": "in_progress",
    "student": {
      "id": 1,
      "firstName": "Jean",
      "lastName": "Dupont",
      "pseudo": "jean.dupont",
      "email": "jean.dupont@example.com"
    },
    "createdAt": "2025-01-10 10:00:00",
    "updatedAt": "2025-01-10 11:30:00"
  }
}
```

### 5. **Supprimer un planning**
```http
DELETE /api/coach/plannings/{id}
Authorization: Bearer {token}
```

**Réponse :**
```json
{
  "success": true,
  "message": "Planning deleted successfully",
  "data": null
}
```

### 6. **Récupérer les types de planning**
```http
GET /api/coach/plannings/types
Authorization: Bearer {token}
```

**Réponse :**
```json
{
  "success": true,
  "message": "Planning types retrieved successfully",
  "data": [
    "homework",
    "revision",
    "task",
    "assessment",
    "course",
    "training",
    "detention",
    "activity",
    "exam",
    "objective",
    "other"
  ]
}
```

### 7. **Récupérer les statuts de planning**
```http
GET /api/coach/plannings/statuses
Authorization: Bearer {token}
```

**Réponse :**
```json
{
  "success": true,
  "message": "Planning statuses retrieved successfully",
  "data": [
    "to_do",
    "in_progress",
    "completed",
    "incomplete"
  ]
}
```

### 8. **Récupérer les types de récurrence**
```http
GET /api/coach/plannings/recurrence-types
Authorization: Bearer {token}
```

**Réponse :**
```json
{
  "success": true,
  "message": "Recurrence types retrieved successfully",
  "data": {
    "daily": "Quotidien",
    "weekly": "Hebdomadaire",
    "monthly": "Mensuel",
    "yearly": "Annuel"
  }
}
```

### 9. **Récupérer le calendrier d'un élève**
```http
POST /api/coach/plannings/calendar
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (obligatoire) :**
```json
{
  "student_id": 1,
  "start_date": "2025-01-01",
  "end_date": "2025-01-31"
}
```

**Champs obligatoires :**
- `student_id` : ID de l'élève
- `start_date` : Date de début (format Y-m-d)
- `end_date` : Date de fin (format Y-m-d)

**Réponse :**
```json
{
  "success": true,
  "message": "Calendar plannings retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Devoir de mathématiques",
      "description": "Exercices pages 45-50",
      "startDate": "2025-01-15 14:00:00",
      "endDate": "2025-01-15 16:00:00",
      "date": "2025-01-15 14:00:00",
      "type": "homework",
      "status": "to_do",
      "student": {
        "id": 1,
        "firstName": "Jean",
        "lastName": "Dupont",
        "pseudo": "jean.dupont",
        "email": "jean.dupont@example.com"
      },
      "createdAt": "2025-01-10 10:00:00",
      "updatedAt": "2025-01-10 10:00:00"
    }
  ]
}
```

## 🔒 **Sécurité**

- **Authentification JWT obligatoire** pour tous les endpoints
- **Vérification des droits** : Un coach ne peut accéder qu'aux plannings de ses élèves
- **Validation des données** : Tous les champs sont validés avant traitement
- **ID élève obligatoire** : La recherche et le calendrier nécessitent l'ID de l'élève

## 📝 **Types de planning disponibles**

- `homework` : Devoirs
- `revision` : Révisions
- `task` : Tâches
- `assessment` : Évaluations
- `course` : Cours
- `training` : Formation
- `detention` : Retenue
- `activity` : Activité
- `exam` : Examen
- `objective` : Objectif
- `other` : Autre

## 📊 **Statuts de planning disponibles**

- `to_do` : À faire
- `in_progress` : En cours
- `completed` : Terminé
- `incomplete` : Incomplet

## ⚠️ **Codes d'erreur**

- `400` : Données invalides ou manquantes
- `401` : Token JWT invalide ou expiré
- `403` : Accès refusé (élève n'appartient pas au coach)
- `404` : Planning ou élève non trouvé
- `500` : Erreur serveur
