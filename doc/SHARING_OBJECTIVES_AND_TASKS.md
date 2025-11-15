# Solutions pour Partager des Objectifs et Tâches entre Utilisateurs

## 📋 État Actuel

### Structure actuelle
- **Objective** : 
  - Appartient à **1 Student** (ManyToOne)
  - Appartient à **1 Coach** (ManyToOne)
  - A plusieurs **Tasks** (OneToMany)

- **Task** :
  - Appartient à **1 Coach** (ManyToOne, obligatoire)
  - Appartient à **1 Objective** (ManyToOne, obligatoire)
  - Peut être assignée à **1 Student**, **1 ParentUser**, ou **1 Specialist** (ManyToOne, optionnel)
  - Champ `assignedType` indique le type d'assignation

### Limitations actuelles
- Un objectif ne peut être partagé qu'entre 1 élève et 1 coach
- Impossible de partager un objectif entre plusieurs élèves
- Impossible de partager un objectif entre plusieurs coaches
- Les spécialistes ne peuvent voir que les tâches qui leur sont assignées, pas l'objectif complet

---

## 🎯 Solutions Proposées

### **Solution 1 : Relations ManyToMany pour les Objectifs** ⭐ (Recommandée)

#### Principe
Transformer les relations `Student` et `Coach` en ManyToMany pour permettre le partage.

#### Modifications nécessaires

**1. Entity Objective**
```php
// Remplacer :
#[ORM\ManyToOne(inversedBy: 'objectives')]
private ?Student $student = null;

#[ORM\ManyToOne(inversedBy: 'objectives')]
private ?Coach $coach = null;

// Par :
#[ORM\ManyToMany(targetEntity: Student::class, inversedBy: 'sharedObjectives')]
#[ORM\JoinTable(name: 'objective_students')]
private Collection $students;

#[ORM\ManyToMany(targetEntity: Coach::class, inversedBy: 'sharedObjectives')]
#[ORM\JoinTable(name: 'objective_coaches')]
private Collection $coaches;

// Garder un "propriétaire principal" pour la compatibilité
#[ORM\ManyToOne]
private ?Student $ownerStudent = null; // Élève qui a créé l'objectif

#[ORM\ManyToOne]
private ?Coach $ownerCoach = null; // Coach qui a créé l'objectif
```

**2. Migration**
```php
// Créer les tables de jointure
CREATE TABLE objective_students (
    objective_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY(objective_id, student_id),
    FOREIGN KEY (objective_id) REFERENCES objective(id),
    FOREIGN KEY (student_id) REFERENCES user(id)
);

CREATE TABLE objective_coaches (
    objective_id INT NOT NULL,
    coach_id INT NOT NULL,
    PRIMARY KEY(objective_id, coach_id),
    FOREIGN KEY (objective_id) REFERENCES objective(id),
    FOREIGN KEY (coach_id) REFERENCES user(id)
);
```

#### Avantages
✅ Flexible : permet de partager avec plusieurs élèves/coaches  
✅ Rétrocompatible : peut garder un "propriétaire principal"  
✅ Simple à implémenter  
✅ Permet des scénarios complexes (groupes d'élèves, co-coaching)

#### Inconvénients
⚠️ Nécessite une migration de données  
⚠️ Doit mettre à jour les permissions et les requêtes

---

### **Solution 2 : Table de Partage avec Rôles** ⭐⭐ (Plus flexible)

#### Principe
Créer une table de partage avec des rôles (owner, viewer, editor, collaborator).

#### Modifications nécessaires

**1. Nouvelle Entity ObjectiveShare**
```php
#[ORM\Entity]
class ObjectiveShare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sharedObjectives')]
    private Objective $objective;

    #[ORM\ManyToOne]
    private User $user; // Student, Coach, Specialist, ou ParentUser

    #[ORM\Column(length: 50)]
    private string $role; // 'owner', 'editor', 'viewer', 'collaborator'

    #[ORM\Column]
    private \DateTimeImmutable $sharedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sharedUntil = null; // Partage temporaire
}
```

**2. Entity Objective**
```php
#[ORM\OneToMany(mappedBy: 'objective', targetEntity: ObjectiveShare::class)]
private Collection $shares;

// Garder les relations existantes pour compatibilité
#[ORM\ManyToOne]
private ?Student $student = null; // Propriétaire principal

#[ORM\ManyToOne]
private ?Coach $coach = null; // Coach principal
```

#### Rôles possibles
- **owner** : Propriétaire, peut tout faire
- **editor** : Peut modifier l'objectif et les tâches
- **viewer** : Peut seulement voir
- **collaborator** : Peut ajouter des tâches et commenter

#### Avantages
✅ Très flexible : contrôle granulaire des permissions  
✅ Partage temporaire possible (avec `sharedUntil`)  
✅ Supporte tous les types d'utilisateurs  
✅ Évolutif : facile d'ajouter de nouveaux rôles

#### Inconvénients
⚠️ Plus complexe à implémenter  
⚠️ Nécessite une refonte des permissions

---

### **Solution 3 : Groupes/Équipes d'Objectifs**

#### Principe
Créer des groupes d'objectifs partagés (ex: "Objectifs du groupe Drac").

#### Modifications nécessaires

**1. Nouvelle Entity ObjectiveGroup**
```php
#[ORM\Entity]
class ObjectiveGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToMany(targetEntity: Objective::class)]
    #[ORM\JoinTable(name: 'objective_group_objectives')]
    private Collection $objectives;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'objective_group_members')]
    private Collection $members;

    #[ORM\ManyToOne]
    private User $owner;
}
```

**2. Entity Objective**
```php
#[ORM\ManyToMany(targetEntity: ObjectiveGroup::class, mappedBy: 'objectives')]
private Collection $groups;
```

#### Avantages
✅ Organise les objectifs par groupes  
✅ Partage en masse possible  
✅ Utile pour les ateliers/activités de groupe

#### Inconvénients
⚠️ Moins flexible pour le partage individuel  
⚠️ Ajoute une couche d'abstraction

---

### **Solution 4 : Partage au Niveau des Tâches** (Solution intermédiaire)

#### Principe
Garder les objectifs individuels, mais permettre le partage des tâches.

#### Modifications nécessaires

**1. Entity Task**
```php
// Ajouter une relation ManyToMany pour les collaborateurs
#[ORM\ManyToMany(targetEntity: User::class)]
#[ORM\JoinTable(name: 'task_collaborators')]
private Collection $collaborators;

// Ajouter un flag pour indiquer si la tâche est partagée
#[ORM\Column(type: 'boolean', options: ['default' => false])]
private bool $isShared = false;
```

#### Avantages
✅ Moins de changements structurels  
✅ Partage ciblé au niveau des tâches  
✅ Plus simple à implémenter

#### Inconvénients
⚠️ Ne résout pas le partage d'objectifs complets  
⚠️ Limite la collaboration globale

---

## 🔐 Impact sur les Permissions

### Modifications nécessaires dans PermissionService

**Pour Solution 1 ou 2 :**
```php
public function canViewObjective(User $user, Objective $objective): bool
{
    // Vérifier si l'utilisateur est dans la liste des partagés
    if ($objective->getShares()->exists(fn($share) => $share->getUser() === $user)) {
        return true;
    }
    
    // Vérifier les relations existantes (compatibilité)
    if ($user instanceof Coach) {
        return $objective->getCoaches()->contains($user) 
            || $objective->getCoach() === $user;
    }
    
    if ($user instanceof Student) {
        return $objective->getStudents()->contains($user)
            || $objective->getStudent() === $user;
    }
    
    // ... autres vérifications
}
```

---

## 📊 Comparaison des Solutions

| Critère | Solution 1 (ManyToMany) | Solution 2 (Table Partage) | Solution 3 (Groupes) | Solution 4 (Tâches) |
|---------|------------------------|---------------------------|---------------------|-------------------|
| **Complexité** | ⭐⭐ Moyenne | ⭐⭐⭐ Élevée | ⭐⭐ Moyenne | ⭐ Faible |
| **Flexibilité** | ⭐⭐⭐ Très flexible | ⭐⭐⭐⭐ Maximum | ⭐⭐ Moyenne | ⭐ Faible |
| **Permissions** | ⭐⭐ Basiques | ⭐⭐⭐⭐ Granulaires | ⭐⭐ Basiques | ⭐ Basiques |
| **Rétrocompatibilité** | ⭐⭐⭐ Bonne | ⭐⭐⭐ Bonne | ⭐⭐⭐ Bonne | ⭐⭐⭐⭐ Excellente |
| **Temps implémentation** | 2-3 jours | 4-5 jours | 2-3 jours | 1 jour |
| **Cas d'usage** | Partage simple | Partage avancé | Groupes/ateliers | Partage ciblé |

---

## 🎯 Recommandation

### **Solution 2 (Table de Partage avec Rôles)** pour un système complet et évolutif

**Pourquoi ?**
1. ✅ Contrôle granulaire des permissions
2. ✅ Supporte tous les types d'utilisateurs
3. ✅ Partage temporaire possible
4. ✅ Évolutif pour de futures fonctionnalités
5. ✅ Compatible avec le système actuel

### **Solution 1 (ManyToMany)** pour une implémentation rapide

**Pourquoi ?**
1. ✅ Plus simple à implémenter
2. ✅ Répond aux besoins de base
3. ✅ Moins de changements dans le code existant

---

## 🚀 Plan d'Implémentation (Solution 2)

### Phase 1 : Structure de base
1. Créer l'entity `ObjectiveShare`
2. Créer la migration
3. Ajouter les relations dans `Objective`

### Phase 2 : Permissions
1. Mettre à jour `PermissionService`
2. Ajouter les méthodes de partage dans `ObjectiveController`
3. Créer les endpoints API pour le partage

### Phase 3 : Interface utilisateur
1. Ajouter un bouton "Partager" sur les objectifs
2. Créer un modal de partage avec sélection d'utilisateurs et rôles
3. Afficher la liste des personnes avec qui l'objectif est partagé

### Phase 4 : Tâches partagées
1. Étendre le système aux tâches si nécessaire
2. Mettre à jour les notifications

---

## 📝 Exemples de Cas d'Usage

### Cas 1 : Partage entre plusieurs coaches
**Scénario** : Estelle et Yannick co-coachent un élève
- **Solution** : Solution 1 ou 2
- Les deux coaches peuvent voir et modifier l'objectif

### Cas 2 : Objectif de groupe
**Scénario** : Objectif partagé entre plusieurs élèves d'un groupe
- **Solution** : Solution 1 ou 3
- Tous les élèves du groupe voient le même objectif

### Cas 3 : Partage temporaire avec un spécialiste
**Scénario** : Partager un objectif avec un spécialiste pour consultation
- **Solution** : Solution 2 (avec `sharedUntil`)
- Le spécialiste peut voir l'objectif pendant une période limitée

### Cas 4 : Famille avec plusieurs enfants
**Scénario** : Un parent veut voir les objectifs de tous ses enfants
- **Solution** : Solution actuelle suffit (via Family)
- Mais Solution 2 permettrait un meilleur contrôle

---

## 🔄 Migration des Données

Pour toutes les solutions, il faudra :

1. **Migrer les données existantes**
   ```php
   // Exemple pour Solution 1
   foreach ($objectives as $objective) {
       if ($objective->getStudent()) {
           $objective->addStudent($objective->getStudent());
           $objective->setOwnerStudent($objective->getStudent());
       }
       if ($objective->getCoach()) {
           $objective->addCoach($objective->getCoach());
           $objective->setOwnerCoach($objective->getCoach());
       }
   }
   ```

2. **Mettre à jour les requêtes**
   - Remplacer `WHERE objective.student_id = ?` par `WHERE objective.id IN (SELECT objective_id FROM objective_students WHERE student_id = ?)`

3. **Tester la rétrocompatibilité**
   - S'assurer que le code existant fonctionne toujours

---

## ❓ Questions à Clarifier

1. **Quels sont les cas d'usage prioritaires ?**
   - Partage entre coaches ?
   - Partage entre élèves ?
   - Partage avec spécialistes ?

2. **Niveau de permissions nécessaire ?**
   - Simple (voir/modifier) ou granulaire (rôles) ?

3. **Partage temporaire nécessaire ?**
   - Ou partage permanent uniquement ?

4. **Performance ?**
   - Combien d'utilisateurs par objectif en moyenne ?
   - Combien d'objectifs partagés simultanément ?


