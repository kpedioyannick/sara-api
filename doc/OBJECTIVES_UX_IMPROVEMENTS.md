# Améliorations UX - Partie Objectifs

## Problèmes identifiés

1. **Difficulté à trouver** : La page objectifs n'est pas facilement accessible à la première utilisation
2. **Navigation après création** : Après création d'un objectif, l'utilisateur est redirigé et perd le contexte
3. **Duplication d'objectifs** : Risque de créer plusieurs fois le même objectif
4. **Modification/Suppression de tâches** : Le champ "Preuve obligatoire" est toujours activé et ne peut pas être désactivé, ce qui bloque la modification/suppression

## Suggestions d'amélioration

### 1. Améliorer la découvrabilité

#### A. Ajouter un guide de démarrage rapide
- **Banner d'onboarding** : Afficher un message d'accueil pour les nouveaux utilisateurs avec un lien direct vers "Créer mon premier objectif"
- **Tooltip contextuel** : Ajouter un tooltip sur le bouton "Nouvel Objectif" expliquant brièvement le processus
- **Page d'accueil améliorée** : Si aucun objectif n'existe, afficher une carte d'invitation avec un bouton CTA proéminent

#### B. Améliorer la navigation
- **Breadcrumb clair** : S'assurer que le breadcrumb indique clairement "Objectifs"
- **Menu latéral** : S'assurer que "Objectifs" est visible et bien positionné dans le menu
- **Raccourci clavier** : Ajouter un raccourci (ex: Ctrl+O) pour créer rapidement un objectif

### 2. Améliorer le flux après création

#### A. Option 1 : Reste sur la page avec feedback (RECOMMANDÉ)
```javascript
// Au lieu de location.reload(), rester sur la page et :
1. Fermer le right sheet
2. Afficher un message de succès avec un bouton "Voir l'objectif"
3. Ajouter l'objectif créé en haut de la liste (sans recharger toute la page)
4. Proposer de créer une tâche directement
```

**Avantages** :
- L'utilisateur garde le contexte
- Pas de perte de scroll position
- Feedback immédiat
- Possibilité de continuer à travailler

#### B. Option 2 : Redirection intelligente
```javascript
// Rediriger vers la page de détail de l'objectif créé
if (data.success && data.id) {
    window.location.href = `/admin/objectives/${data.id}`;
}
```

**Avantages** :
- L'utilisateur voit immédiatement son objectif créé
- Peut ajouter des tâches directement
- Contexte complet de l'objectif

#### C. Option 3 : Mode "Création continue"
```javascript
// Après création, proposer :
- "Créer un autre objectif" (reste sur le formulaire)
- "Voir l'objectif créé" (redirige vers le détail)
- "Retour à la liste" (ferme le sheet)
```

### 3. Prévenir la duplication

#### A. Vérification avant création
- **Détection de doublons** : Avant de créer, vérifier s'il existe déjà un objectif similaire
- **Message d'alerte** : "Un objectif similaire existe déjà : [titre]. Voulez-vous continuer ?"
- **Suggestions** : Proposer de modifier l'objectif existant au lieu d'en créer un nouveau

#### B. Améliorer la recherche
- **Recherche en temps réel** : Pendant la saisie de la description, afficher les objectifs similaires
- **Filtres visuels** : Améliorer les filtres pour faciliter la recherche d'objectifs existants
- **Tags/Catégories** : Utiliser des badges colorés pour différencier rapidement les objectifs

### 4. Améliorer la gestion des tâches

#### A. Rendre "Preuve obligatoire" optionnel
**Problème actuel** : Le champ est toujours activé et ne peut pas être désactivé

**Solution** :
```html
<!-- Remplacer le champ désactivé par un champ actif -->
<div class="flex items-center gap-2">
    <input
        type="checkbox"
        x-model="taskFormData.requiresProof"
        class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
    />
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
        Nécessite une preuve
    </label>
</div>
```

**Avantages** :
- L'utilisateur peut choisir si une preuve est nécessaire
- Plus de flexibilité dans la création de tâches
- Permet de modifier/supprimer des tâches sans contrainte

#### B. Améliorer l'interface de modification/suppression
- **Actions rapides** : Ajouter des boutons "Modifier" et "Supprimer" directement sur chaque tâche dans la liste
- **Confirmation intelligente** : 
  - Si la tâche a des preuves : "Cette tâche contient X preuve(s). Voulez-vous vraiment la supprimer ?"
  - Si pas de preuves : Confirmation simple
- **Mode édition inline** : Permettre l'édition rapide directement dans la liste (double-clic)

#### C. Gestion des preuves existantes
- **Avertissement visuel** : Si une tâche a des preuves, afficher un badge "X preuve(s)"
- **Options de suppression** :
  - Supprimer la tâche et ses preuves
  - Supprimer seulement la tâche (garder les preuves)
  - Archiver la tâche au lieu de la supprimer

### 5. Améliorations supplémentaires

#### A. Workflow amélioré
1. **Création guidée** : Wizard en plusieurs étapes
   - Étape 1 : Description et catégorie
   - Étape 2 : Sélection de l'élève
   - Étape 3 : Prévisualisation et génération des tâches
   - Étape 4 : Personnalisation des tâches (optionnel)

2. **Prévisualisation** : Avant de créer, montrer un aperçu de ce qui sera généré

3. **Templates** : Proposer des templates d'objectifs courants

#### B. Feedback visuel
- **États visuels** : Utiliser des couleurs et icônes pour différencier les statuts
- **Progression** : Afficher une barre de progression pour les objectifs en cours
- **Notifications** : Notifier l'utilisateur des changements importants

#### C. Performance
- **Chargement progressif** : Charger les objectifs par batch
- **Recherche instantanée** : Recherche en temps réel sans rechargement
- **Cache intelligent** : Mettre en cache les données fréquemment utilisées

## Priorités d'implémentation

### Priorité 1 (Critique)
1. ⏳ Rendre "Preuve obligatoire" optionnel dans les tâches
2. ✅ **IMPLÉMENTÉ** - Améliorer le flux après création : Redirection vers la page de détail de l'objectif créé
3. ⏳ Prévenir la duplication (vérification avant création)

### Priorité 2 (Important)
4. Améliorer la découvrabilité (banner, tooltips)
5. Améliorer l'interface de modification/suppression des tâches
6. Gestion intelligente des preuves existantes

### Priorité 3 (Amélioration)
7. Workflow guidé (wizard)
8. Templates d'objectifs
9. Recherche en temps réel

## Exemples de code

### Exemple 1 : Amélioration du flux après création
```javascript
saveObjective() {
    // ... code existant ...
    if (data.success) {
        this.showMessage(data.message || 'Objectif créé avec succès', 'success');
        
        // Option A : Reste sur la page
        this.rightSheetOpen = false;
        
        // Recharger uniquement les objectifs (AJAX)
        this.refreshObjectives().then(() => {
            // Ouvrir automatiquement l'objectif créé en mode édition
            if (data.id) {
                setTimeout(() => {
                    this.openEdit(data.id);
                    this.showMessage('Vous pouvez maintenant ajouter des tâches', 'info');
                }, 500);
            }
        });
        
        // Option B : Redirection intelligente
        // if (data.id) {
        //     window.location.href = `/admin/objectives/${data.id}`;
        // }
    }
}
```

### Exemple 2 : Détection de doublons
```javascript
async checkDuplicate(description) {
    const response = await fetch(`/admin/objectives/check-duplicate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ description })
    });
    const data = await response.json();
    return data.similarObjectives || [];
}

// Dans saveObjective, avant l'envoi :
const duplicates = await this.checkDuplicate(this.formData.description);
if (duplicates.length > 0) {
    const confirm = window.confirm(
        `Un objectif similaire existe déjà : "${duplicates[0].title}". Voulez-vous continuer ?`
    );
    if (!confirm) return;
}
```

### Exemple 3 : Champ "Preuve obligatoire" optionnel
```html
<!-- Remplacer les lignes 730-738 dans list.html.twig -->
<div class="flex items-center gap-2">
    <input
        type="checkbox"
        x-model="taskFormData.requiresProof"
        class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
    />
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
        Nécessite une preuve
    </label>
    <span class="text-xs text-gray-500 dark:text-gray-400">
        (Cochez si la tâche nécessite une preuve de réalisation)
    </span>
</div>
```

## Questions à considérer

1. **Quel flux préférez-vous après création ?**
   - Rester sur la page avec l'objectif ouvert en édition
   - Rediriger vers la page de détail
   - Proposer les deux options

2. **Niveau de détection de doublons ?**
   - Simple (comparaison de texte)
   - Avancé (similarité sémantique avec IA)
   - Désactivable par l'utilisateur

3. **Gestion des preuves lors de la suppression ?**
   - Supprimer automatiquement
   - Demander confirmation
   - Archiver au lieu de supprimer

