# Améliorations UX - Affichage des Tâches dans la Page Détail d'un Objectif

## Proposition de l'utilisateur

Réorganiser l'affichage des tâches avec une structure plus claire et intuitive :

### Structure proposée (de haut en bas) :

1. **Case à cocher** (checkbox)
2. **Titre** de la tâche
3. **Affectation** : Celui à qui la tâche est affectée (en dessous du titre)
4. **Dates** : Date de début et date de fin
5. **Étoiles** : Système d'étoiles pour les preuves
   - Une étoile = une preuve ajoutée
   - Le nombre d'étoiles dépend du nombre de preuves à ajouter
6. **Boutons d'action** (en dessous) :
   - Détail
   - Configurer
   - Supprimer
   - Historique des preuves

## Questions de clarification

### 1. Système d'étoiles

**Question** : Comment déterminer le nombre total d'étoiles à afficher ?

**Options possibles** :
- **Option A** : Nombre fixe (ex: 5 étoiles max) - Les étoiles remplies = nombre de preuves ajoutées
- **Option B** : Nombre dynamique basé sur la fréquence de la tâche
  - Tâche quotidienne → 7 étoiles (une par jour de la semaine)
  - Tâche hebdomadaire → 4 étoiles (une par semaine du mois)
  - Tâche mensuelle → 1 étoile
- **Option C** : Basé sur la date d'échéance
  - Si dueDate existe : nombre de jours entre createdAt et dueDate
  - Sinon : nombre fixe (ex: 5)
- **Option D** : Configurable par tâche (champ `targetProofCount` dans la tâche)

**Recommandation** : Option C avec fallback sur Option A

### 2. Affichage des étoiles

**Question** : Comment afficher les étoiles ?

**Options** :
- **Option A** : Étoiles pleines (⭐) pour les preuves ajoutées, étoiles vides (☆) pour les manquantes
- **Option B** : Étoiles colorées (dorées) pour les preuves ajoutées, grises pour les manquantes
- **Option C** : Barre de progression avec étoiles (ex: ⭐⭐⭐☆☆ = 3/5)

**Recommandation** : Option B (plus visuel et moderne)

### 3. Case à cocher

**Question** : Quel est le comportement de la case à cocher ?

**Options** :
- **Option A** : Cocher = marquer la tâche comme complétée (comportement actuel)
- **Option B** : Cocher = ajouter une preuve (ouvre le modal de preuve)
- **Option C** : Cocher = les deux (coche + ouvre modal si preuve requise)

**Recommandation** : Option C (comportement actuel amélioré)

### 4. Bouton "Détail"

**Question** : Que doit afficher le bouton "Détail" ?

**Options** :
- **Option A** : Modal avec toutes les informations de la tâche (description, dates, affectation, etc.)
- **Option B** : Redirection vers une page de détail dédiée
- **Option C** : Expansion inline de la carte de tâche pour afficher plus d'infos

**Recommandation** : Option A (modal rapide)

### 5. Ordre des boutons

**Question** : Dans quel ordre afficher les boutons ?

**Proposition** :
1. **Détail** (lecture seule, accessible à tous)
2. **Configurer** (modification, selon permissions)
3. **Historique des preuves** (lecture, accessible à tous)
4. **Supprimer** (destructif, selon permissions)

**Alternative** : Grouper par type
- **Actions de consultation** : Détail, Historique
- **Actions de modification** : Configurer, Supprimer

### 6. Affichage de l'affectation

**Question** : Comment afficher "celui à qui elle est affectée" ?

**Options** :
- **Option A** : Texte simple "Affectée à : [Nom]"
- **Option B** : Badge avec icône (comme actuellement)
- **Option C** : Avatar + nom
- **Option D** : Icône selon le type (👤 Élève, 👨‍👩 Parent, 👨‍⚕️ Spécialiste, 👨‍🏫 Coach)

**Recommandation** : Option D (plus visuel et informatif)

### 7. Dates

**Question** : Format d'affichage des dates ?

**Options** :
- **Option A** : "Début: 13/11/2025 - Fin: 20/11/2025"
- **Option B** : "Du 13/11/2025 au 20/11/2025"
- **Option C** : Sur deux lignes séparées
- **Option D** : Avec icônes calendrier (comme actuellement)

**Recommandation** : Option B (plus compact et lisible)

### 8. Responsive design

**Question** : Comment adapter sur mobile ?

**Proposition** :
- Sur mobile : Empiler les éléments verticalement
- Sur desktop : Layout horizontal avec colonnes

## Structure HTML proposée

```html
<div class="task-card">
  <!-- Ligne 1: Checkbox + Titre -->
  <div class="flex items-start gap-3">
    <input type="checkbox" />
    <div class="flex-1">
      <h4 class="font-medium">Titre de la tâche</h4>
      
      <!-- Ligne 2: Affectation -->
      <div class="mt-1 text-sm text-gray-600">
        <span class="inline-flex items-center gap-1">
          <svg>...</svg>
          Affectée à : [Nom]
        </span>
      </div>
      
      <!-- Ligne 3: Dates -->
      <div class="mt-1 text-sm text-gray-500">
        Du 13/11/2025 au 20/11/2025
      </div>
      
      <!-- Ligne 4: Étoiles -->
      <div class="mt-2 flex items-center gap-1">
        <span class="text-yellow-400">⭐</span>
        <span class="text-yellow-400">⭐</span>
        <span class="text-yellow-400">⭐</span>
        <span class="text-gray-300">☆</span>
        <span class="text-gray-300">☆</span>
        <span class="ml-2 text-xs text-gray-500">3/5 preuves</span>
      </div>
    </div>
  </div>
  
  <!-- Ligne 5: Boutons d'action -->
  <div class="mt-4 flex flex-wrap gap-2">
    <button class="btn-detail">Détail</button>
    <button class="btn-configure">Configurer</button>
    <button class="btn-history">Historique</button>
    <button class="btn-delete">Supprimer</button>
  </div>
</div>
```

## Avantages de cette approche

✅ **Hiérarchie claire** : Information organisée de manière logique
✅ **Visibilité des preuves** : Les étoiles donnent un feedback visuel immédiat
✅ **Actions accessibles** : Tous les boutons sont visibles sans menu déroulant
✅ **Meilleure lisibilité** : Structure verticale plus facile à scanner
✅ **Feedback visuel** : Les étoiles montrent la progression

## Points d'attention

⚠️ **Espace** : Plus de contenu = cartes plus grandes
⚠️ **Mobile** : S'assurer que tout reste lisible sur petit écran
⚠️ **Performance** : Calculer le nombre d'étoiles peut nécessiter des requêtes supplémentaires

## Implémentation suggérée

### Backend
- Ajouter une méthode pour calculer le nombre d'étoiles cibles
- Retourner le nombre de preuves dans les données de la tâche

### Frontend
- Restructurer le template des tâches
- Ajouter le composant d'étoiles
- Réorganiser les boutons d'action

## Questions à répondre avant implémentation

1. **Nombre d'étoiles** : Quelle option choisir ? (A, B, C ou D)
2. **Style des étoiles** : Quelle option choisir ? (A, B ou C)
3. **Comportement checkbox** : Quelle option choisir ? (A, B ou C)
4. **Bouton Détail** : Quelle option choisir ? (A, B ou C)
5. **Ordre des boutons** : Valider l'ordre proposé ou préférer un autre ?
6. **Affichage affectation** : Quelle option choisir ? (A, B, C ou D)
7. **Format dates** : Quelle option choisir ? (A, B, C ou D)

## Prochaines étapes

Une fois les réponses obtenues :
1. ✅ **IMPLÉMENTÉ** - Implémenter la nouvelle structure HTML
2. ✅ **IMPLÉMENTÉ** - Ajouter le système d'étoiles
3. ✅ **IMPLÉMENTÉ** - Réorganiser les boutons d'action
4. ✅ **IMPLÉMENTÉ** - Adapter le responsive design
5. ⏳ Tester sur différents rôles (coach, parent, élève, spécialiste)

## Implémentation réalisée

### Structure finale
- ✅ Case à cocher + Titre
- ✅ Affectation avec icônes (👤 Élève, 👨‍👩 Parent, 👨‍⚕️ Spécialiste, 👨‍🏫 Coach)
- ✅ Dates au format "Du ... au ..."
- ✅ Étoiles dorées basées sur le nombre de preuves (affichage uniquement)
- ✅ Boutons d'action : Détail, Configurer, Historique, Supprimer

### Détails techniques
- Macro Twig `renderTask()` créée pour éviter la duplication
- Étoiles affichées uniquement si `task.requiresProof` est vrai
- Bouton "Détail" ouvre le right sheet existant via `openTaskConfig()`
- Comportement checkbox : compléter + ajouter preuve si requise

