# Mapping Titre → Type pour les Demandes

## Concept

Le **type** de la demande est déterminé automatiquement en fonction du **titre** choisi par l'utilisateur. Cela simplifie l'interface en supprimant le champ "Type" séparé.

## Liste des Types par Rôle

### 👨‍🏫 Rôle COACH

| Titre (Choice) | Type (Auto) | Champs affichés | Description |
|----------------|-------------|-----------------|-------------|
| **Demande d'aide scolaire pour un élève** | `student_to_specialist` | - Liste élèves<br>- Liste spécialistes | Le coach demande l'intervention d'un spécialiste pour un élève spécifique |
| **Demande d'échange avec un parent** | `parent` | - Liste parents affectés | Le coach souhaite échanger avec un parent |
| **Demande d'échange avec un élève** | `student` | - Liste élèves | Le coach souhaite échanger avec un élève |
| **Demande d'échange avec un spécialiste** | `specialist` | - Liste spécialistes | Le coach souhaite échanger avec un spécialiste |

### 👨‍👩 Rôle PARENT

| Titre (Choice) | Type (Auto) | Champs affichés | Description |
|----------------|-------------|-----------------|-------------|
| **Demande d'aide scolaire pour mon enfant** | `student_help` | - Liste enfants (élèves de la famille) | Le parent demande de l'aide pour son enfant |
| **Demande d'échange avec mon coach** | `coach` | - Aucune liste (coach automatique) | Le parent souhaite échanger avec son coach assigné |

### 👨‍⚕️ Rôle SPÉCIALISTE

| Titre (Choice) | Type (Auto) | Champs affichés | Description |
|----------------|-------------|-----------------|-------------|
| **Demande d'échange avec un élève** | `student` | - Liste élèves affectés (élèves assignés au spécialiste) | Le spécialiste souhaite échanger avec un de ses élèves |
| **Demande d'échange avec mon coach** | `coach` | - Aucune liste (coach automatique) | Le spécialiste souhaite échanger avec le coach |

### 👦👧 Rôle ÉLÈVE

| Titre (Choice) | Type (Auto) | Champs affichés | Description |
|----------------|-------------|-----------------|-------------|
| **Demande d'échange avec un spécialiste** | `specialist` | - Aucune liste (spécialiste assigné automatique) | L'élève souhaite échanger avec son spécialiste |
| **Demande d'échange avec mon coach** | `coach` | - Aucune liste (coach automatique) | L'élève souhaite échanger avec son coach |

## Liste complète des Types (valeurs techniques)

```php
// Types de demandes
const TYPE_STUDENT_TO_SPECIALIST = 'student_to_specialist';  // Coach demande spécialiste pour élève
const TYPE_PARENT = 'parent';                                // Échange avec parent
const TYPE_STUDENT = 'student';                             // Échange avec élève
const TYPE_SPECIALIST = 'specialist';                       // Échange avec spécialiste
const TYPE_COACH = 'coach';                                 // Échange avec coach
const TYPE_STUDENT_HELP = 'student_help';                   // Aide scolaire pour élève (parent)
```

## Mapping Titre → Type (par rôle)

### Coach
```php
[
    'Demande d\'aide scolaire pour un élève' => 'student_to_specialist',
    'Demande d\'échange avec un parent' => 'parent',
    'Demande d\'échange avec un élève' => 'student',
    'Demande d\'échange avec un spécialiste' => 'specialist',
]
```

### Parent
```php
[
    'Demande d\'aide scolaire pour mon enfant' => 'student_help',
    'Demande d\'échange avec mon coach' => 'coach',
]
```

### Spécialiste
```php
[
    'Demande d\'échange avec un élève' => 'student',
    'Demande d\'échange avec mon coach' => 'coach',
]
```

### Élève
```php
[
    'Demande d\'échange avec un spécialiste' => 'specialist',
    'Demande d\'échange avec mon coach' => 'coach',
]
```

## Logique d'affichage des champs

### Champs conditionnels selon le type

```javascript
// Exemple de logique frontend
if (type === 'student_to_specialist') {
    // Afficher : Liste élèves + Liste spécialistes
    showStudentSelect = true;
    showSpecialistSelect = true;
}
else if (type === 'parent') {
    // Afficher : Liste parents
    showParentSelect = true;
}
else if (type === 'student') {
    // Afficher : Liste élèves (filtrée selon le rôle)
    showStudentSelect = true;
}
else if (type === 'specialist') {
    // Afficher : Liste spécialistes (ou aucun si élève)
    if (userRole === 'student') {
        // Pas de liste, spécialiste assigné automatiquement
    } else {
        showSpecialistSelect = true;
    }
}
else if (type === 'coach') {
    // Pas de liste, coach assigné automatiquement
}
else if (type === 'student_help') {
    // Afficher : Liste enfants (élèves de la famille du parent)
    showStudentSelect = true; // Filtrée par famille
}
```

## Avantages de cette approche

✅ **Simplicité** : Un seul champ à remplir (titre) au lieu de deux (titre + type)
✅ **Clarté** : Les titres sont explicites et orientés action
✅ **Adaptabilité** : Les options changent selon le rôle de l'utilisateur
✅ **Moins d'erreurs** : Impossible de choisir un type incompatible avec le titre
✅ **UX améliorée** : L'utilisateur comprend immédiatement ce qu'il fait

## Points d'attention

⚠️ **Migration** : Il faudra migrer les anciennes demandes qui ont un type mais pas de titre correspondant
⚠️ **Extensibilité** : Si on ajoute de nouveaux types, il faut ajouter de nouveaux titres
⚠️ **Traduction** : Les titres doivent être traduits si l'app est multilingue

## Implémentation suggérée

### Backend (Controller)
```php
// Dans RequestController::create()
$titleToTypeMapping = [
    'coach' => [
        'Demande d\'aide scolaire pour un élève' => 'student_to_specialist',
        'Demande d\'échange avec un parent' => 'parent',
        'Demande d\'échange avec un élève' => 'student',
        'Demande d\'échange avec un spécialiste' => 'specialist',
    ],
    'parent' => [
        'Demande d\'aide scolaire pour mon enfant' => 'student_help',
        'Demande d\'échange avec mon coach' => 'coach',
    ],
    'specialist' => [
        'Demande d\'échange avec un élève' => 'student',
        'Demande d\'échange avec mon coach' => 'coach',
    ],
    'student' => [
        'Demande d\'échange avec un spécialiste' => 'specialist',
        'Demande d\'échange avec mon coach' => 'coach',
    ],
];

$userRole = $user->getDiscriminator(); // 'coach', 'parent', 'specialist', 'student'
$type = $titleToTypeMapping[$userRole][$data['title']] ?? 'general';
$requestEntity->setType($type);
```

### Frontend (Template)
```html
<div>
    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
        Type de demande <span class="text-error-500">*</span>
    </label>
    <select
        x-model="formData.title"
        @change="updateTypeFromTitle()"
        required
        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800"
    >
        <option value="">Sélectionner un type...</option>
        {% if app.user.isCoach() %}
            <option value="Demande d'aide scolaire pour un élève">Demande d'aide scolaire pour un élève</option>
            <option value="Demande d'échange avec un parent">Demande d'échange avec un parent</option>
            <option value="Demande d'échange avec un élève">Demande d'échange avec un élève</option>
            <option value="Demande d'échange avec un spécialiste">Demande d'échange avec un spécialiste</option>
        {% elseif app.user.isParent() %}
            <option value="Demande d'aide scolaire pour mon enfant">Demande d'aide scolaire pour mon enfant</option>
            <option value="Demande d'échange avec mon coach">Demande d'échange avec mon coach</option>
        {% elseif app.user.isSpecialist() %}
            <option value="Demande d'échange avec un élève">Demande d'échange avec un élève</option>
            <option value="Demande d'échange avec mon coach">Demande d'échange avec mon coach</option>
        {% elseif app.user.isStudent() %}
            <option value="Demande d'échange avec un spécialiste">Demande d'échange avec un spécialiste</option>
            <option value="Demande d'échange avec mon coach">Demande d'échange avec mon coach</option>
        {% endif %}
    </select>
</div>
```

## Mon avis

### ✅ Excellente idée !

Cette approche est **très pertinente** car :

1. **Simplifie l'UX** : Un seul choix au lieu de deux
2. **Réduit les erreurs** : Le type est cohérent avec le titre
3. **Plus intuitif** : Les utilisateurs comprennent mieux ce qu'ils font
4. **Adapté aux rôles** : Chaque rôle voit uniquement les options pertinentes

### Suggestions d'amélioration

1. **Ajouter des descriptions courtes** sous chaque option pour clarifier davantage
2. **Gérer les cas spéciaux** : Que faire si un parent a plusieurs enfants ? Afficher une liste
3. **Validation** : S'assurer que les champs requis (élève, spécialiste, etc.) sont bien remplis selon le type
4. **Historique** : Garder une trace du titre choisi pour l'affichage dans la liste des demandes

### Prochaines étapes

1. ✅ Valider cette liste de types
2. ⏳ Implémenter le mapping titre → type dans le contrôleur
3. ⏳ Modifier le template pour afficher les titres au lieu du type
4. ⏳ Adapter la logique d'affichage des champs conditionnels
5. ⏳ Migrer les données existantes si nécessaire

