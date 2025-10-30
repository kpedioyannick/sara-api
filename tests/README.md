# Tests Sara API

## Structure des Tests

```
tests/
├── test_all_features.php          # Script principal - teste toutes les features
├── quick_test.php                 # Test rapide de base
├── Coach/
│   └── test_coach_features.php    # Tests des features Coach
├── Parent/
│   └── test_parent_features.php   # Tests des features Parent
├── Student/
│   └── test_student_features.php  # Tests des features Student
└── Specialist/
    └── test_specialist_features.php # Tests des features Specialist
```

## Utilisation

### Test Rapide
```bash
php tests/quick_test.php
```

### Test Complet
```bash
php tests/test_all_features.php
```

### Test par Rôle
```bash
php tests/Coach/test_coach_features.php
php tests/Parent/test_parent_features.php
php tests/Student/test_student_features.php
php tests/Specialist/test_specialist_features.php
```

## Features Testées

### Coach Features (selon COACH_FEATURES.md)
- ✅ Dashboard
- ✅ Gestion des Familles
- ✅ Gestion des Objectifs
- ✅ Gestion des Tâches
- ✅ Gestion des Demandes
- ✅ Gestion des Spécialistes
- ✅ Gestion du Planning
- ✅ Gestion des Disponibilités
- ✅ Paramètres

### Parent Features (selon PARENT_FEATURES.md)
- ✅ Dashboard
- ✅ Gestion des Familles
- ✅ Gestion des Objectifs
- ✅ Gestion des Tâches
- ✅ Gestion des Demandes
- ✅ Planning
- ✅ Paramètres

### Student Features (selon STUDENT_FEATURES.md)
- ✅ Dashboard
- ✅ Gestion des Objectifs
- ✅ Gestion des Tâches
- ✅ Planning
- ✅ Gestion des Demandes
- ✅ Paramètres

### Specialist Features (selon SPECIALIST_FEATURES.md)
- ✅ Dashboard
- ✅ Gestion des Disponibilités
- ✅ Gestion des Objectifs
- ✅ Gestion des Tâches
- ✅ Planning
- ✅ Gestion des Demandes
- ✅ Paramètres

## Résultats des Tests

Chaque test retourne :
- ✅ **SUCCESS** - Test réussi
- ❌ **FAILED** - Test échoué avec code HTTP et détails

## Configuration

Les tests utilisent l'URL de base : `http://localhost:8000/api`

Pour changer l'URL, modifiez la variable `$baseUrl` dans les scripts.

## Sécurité

Tous les tests utilisent l'authentification JWT avec les tokens appropriés pour chaque rôle.

## Architecture

Les tests suivent l'architecture de l'API :
- **Authentification** - Inscription et connexion par rôle
- **Routes sécurisées** - Test avec tokens JWT
- **CRUD complet** - Création, lecture, mise à jour, suppression
- **Filtrage** - Tests des paramètres de requête
- **Validation** - Tests des données d'entrée
