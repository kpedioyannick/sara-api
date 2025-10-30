# Features - Coach

## Gestion des Familles

- En tant que Coach, quand je consulte la page Familles, je dois voir la liste de toutes les familles avec leurs informations de bas
  - **API**: `GET /api/coach/families`
- En tant que Coach, quand je clique sur "Ajouter une famille", je dois pouvoir créer une famille en créant le parent et ses enfants
  - **API**: `POST /api/coach/families`
- En tant que Coach, quand je crée une famille, je dois pouvoir ajouter des enfants directement lors de la création
  - **API**: `POST /api/coach/families/{id}/children`
- En tant que Coach, quand je consulte une famille, je dois pouvoir effectuer les opérations CRUD (Créer, Lire, Mettre à jour, Supprimer) sur leurs enfants
  - **API**: `GET /api/coach/families/{id}`, `PUT /api/coach/families/{id}`, `DELETE /api/coach/families/{id}`
- En tant que Coach, quand je consulte la liste des familles, je dois pouvoir filtrer et rechercher une famille spécifique
  - **API**: `GET /api/coach/families?search={query}&filter={filter}`
- En tant que Coach, quand je clique sur une famille, je dois voir le détail complet de cette famille
  - **API**: `GET /api/coach/families/{id}`
- En tant que Coach, quand je suis sur le détail d'une famille, je dois pouvoir ajouter des enfants à cette famille existante
  - **API**: `POST /api/coach/families/{id}/children`
- En tant que Coach, quand je crée un enfant, je dois renseigner son pseudo, son mot de passe, la confirmation du mot de passe et sa classe
  - **API**: `POST /api/coach/families/{id}/children`
- En tant que Coach, quand je sélectionne une classe, je dois choisir parmi une liste prédéfinie via un menu déroulant (CP, CE1, CE2, CM1, CM2, 6ème, etc.)
  - **API**: `GET /api/coach/classes` (pour récupérer les classes disponibles)
- En tant que Coach, quand je consulte un enfant, je dois pouvoir modifier ses informations (pseudo, classe)
  - **API**: `PUT /api/coach/families/{family_id}/children/{child_id}`
- En tant que Coach, quand je consulte un enfant, je dois pouvoir le supprimer
  - **API**: `DELETE /api/coach/families/{family_id}/children/{child_id}`
- En tant que Coach, depuis le profil d'un enfant, je peux accéder à ses objectifs, son planning et ses demandes
  - **API**: `GET /api/coach/objectives?student_id={id}`, `GET /api/coach/planning?student_id={id}`, `GET /api/coach/requests?student_id={id}`
- En tant que Coach, je peux désactiver une famille donc parent et enfants ne peuvent plus se connecter
  - **API**: `PUT /api/coach/families/{id}/deactivate`
- En tant que Coach, je peux désactiver un enfant d'une famille donc l'enfant ne peut plus se connecter
  - **API**: `PUT /api/coach/families/{family_id}/children/{child_id}/deactivate`

## Gestion des Objectifs

- En tant que Coach, quand je consulte la page Objectifs, je dois voir tous les objectifs de tous les élèves
  - **API**: `GET /api/coach/objectives`
- En tant que Coach, quand je clique sur "Créer un objectif", je dois pouvoir ajouter des objectifs aux enfants d'une famille : Filtre famille ensuite enfant
  - **API**: `POST /api/coach/objectives`
- En tant que Coach, quand je crée un objectif, je dois renseigner le titre, la description, la date limite et la catégorie
  - **API**: `POST /api/coach/objectives`
- En tant que Coach, quand je consulte la page des objectifs, je dois pouvoir effectuer les opérations CRUD sur les objectifs des enfants
  - **API**: `GET /api/coach/objectives/{id}`, `PUT /api/coach/objectives/{id}`, `DELETE /api/coach/objectives/{id}`
- En tant que Coach, quand je consulte les objectifs, je dois pouvoir filtrer les objectifs par famille et par enfant
  - **API**: `GET /api/coach/objectives?family_id={id}&student_id={id}`
- En tant que Coach, quand je recherche un objectif, je dois pouvoir rechercher via une recherche par famille et enfant
  - **API**: `GET /api/coach/objectives?search={query}&family_id={id}&student_id={id}`
- En tant que Coach, quand je consulte un objectif, je dois pouvoir ajouter un commentaire ou un retour sur un objectif
  - **API**: `POST /api/coach/objectives/{id}/comments`

## Gestion des Tâches d'un objectif

- En tant que Coach, quand je consulte un objectif, je dois pouvoir effectuer les opérations CRUD sur les tâches liées à un objectif
  - **API**: `GET /api/coach/objectives/{id}/tasks`, `POST /api/coach/objectives/{id}/tasks`, `PUT /api/coach/tasks/{id}`, `DELETE /api/coach/tasks/{id}`
- En tant que Coach, quand je crée une tâche, je dois renseigner le titre, la description, le status
  - **API**: `POST /api/coach/objectives/{id}/tasks`
- En tant que Coach, quand je crée une tâche, je dois pouvoir assigner une tâche à un élève, à un parent ou à un spécialiste
  - **API**: `POST /api/coach/objectives/{id}/tasks`
- En tant que Coach, quand je crée une tâche, je dois pouvoir paramétrer une tâche (la fréquence de la tache, faut il fournir des preuves, le type de preuve
  - **API**: `POST /api/coach/objectives/{id}/tasks`
- En tant que Coach, quand une tâche nécessite une preuve, je dois pouvoir consulter les preuves soumises
  - **API**: `GET /api/coach/tasks/{id}/proofs`
- En tant que Coach, quand je peux voir l'historique de la progression de la tache
  - **API**: `GET /api/coach/tasks/{id}/history`
- En tant que Coach, quand je consulte une tâche, je dois voir son statut de complétion
  - **API**: `GET /api/coach/tasks/{id}`

## Gestion des Demandes

- En tant que coach, je définis une demande comme une requête formulée par un créateur, adressée à un destinataire, et traitée par la personne en charge de sa réalisation.
- En tant que Coach, quand je consulte la page Demandes, je dois pouvoir effectuer les opérations CRUD sur les demandes (pour un élève ou un parent pour un spécialiste)
  - **API**: `GET /api/coach/requests`, `POST /api/coach/requests`, `PUT /api/coach/requests/{id}`, `DELETE /api/coach/requests/{id}`
- En tant que Coach, quand je consulte la page Demandes, je dois voir toutes les demandes (élèves, parents, spécialistes)
  - **API**: `GET /api/coach/requests`
- En tant que Coach, quand je traite une demande, je dois pouvoir m'affecter une demande ou l'affecter à un spécialiste
  - **API**: `PUT /api/coach/requests/{id}/assign`
- En tant que Coach, quand je traite une demande, je dois pouvoir modifier son statut (en attente, en cours, terminée)
  - **API**: `PUT /api/coach/requests/{id}/status`
- En tant que Coach, quand je consulte une demande, je dois pouvoir répondre à une demande
  - **API**: `POST /api/coach/requests/{id}/response`
- En tant que Coach, quand je consulte les demandes, je dois pouvoir filtrer les demandes par famille, statut, spécialiste ou élève
  - **API**: `GET /api/coach/requests?family_id={id}&status={status}&specialist_id={id}&student_id={id}`

## Gestion des Spécialistes

- En tant que Coach, quand je consulte la page Spécialistes, je dois pouvoir effectuer les opérations CRUD sur les spécialistes
  - **API**: `GET /api/coach/specialists`, `POST /api/coach/specialists`, `PUT /api/coach/specialists/{id}`, `DELETE /api/coach/specialists/{id}`
- En tant que Coach, quand je consulte la page Spécialistes, je dois voir la liste de tous les spécialistes
  - **API**: `GET /api/coach/specialists`
- En tant que Coach, quand je clique sur "Ajouter un spécialiste", je dois pouvoir créer un compte spécialiste : nom prenom email mot de passes et ses spécialistes
  - **API**: `POST /api/coach/specialists`
- En tant que Coach, quand je crée un spécialiste, je dois renseigner ses domaines de spécialité
  - **API**: `POST /api/coach/specialists`
- En tant que Coach, quand je consulte un spécialiste, je dois pouvoir activer ou désactiver un utilisateur de type spécialiste
  - **API**: `PUT /api/coach/specialists/{id}/toggle-status`
- En tant que Coach, quand je recherche un spécialiste, je dois pouvoir retrouver un spécialiste
  - **API**: `GET /api/coach/specialists?search={query}`
- En tant que Coach, quand je gère les spécialistes, je dois pouvoir affecter ou révoquer des élèves à un spécialiste
  - **API**: `POST /api/coach/specialists/{id}/students`, `DELETE /api/coach/specialists/{id}/students/{student_id}`

## Planning

- En tant que Coach, quand je consulte la page planning, je peux visualiser le planning d'un élève
  - **API**: `GET /api/coach/planning?student_id={id}`
- En tant que Coach, quand je consulte la page planning, je dois pouvoir effectuer les opérations CRUD sur les événements du planning d'un élève
  - **API**: `GET /api/coach/planning/{id}`, `POST /api/coach/planning`, `PUT /api/coach/planning/{id}`, `DELETE /api/coach/planning/{id}`
- En tant que Coach, quand je consulte le planning, je dois pouvoir filtrer ou rechercher un élève pour accéder à son planning
  - **API**: `GET /api/coach/planning?search={query}&student_id={id}`

## Disponibilités

- En tant que Coach, quand je consulte la page Disponibilités, je dois voir mes de disponibilités
  - **API**: `GET /api/coach/availability`
- En tant que Coach, quand je définis mes disponibilités, je dois pouvoir ajouter des créneaux par jour
  - **API**: `POST /api/coach/availability`
- En tant que Coach, quand je consulte mes disponibilités, je dois pouvoir les modifier ou les supprimer
  - **API**: `PUT /api/coach/availability/{id}`, `DELETE /api/coach/availability/{id}`
- En tant que Coach, je dois pouvoir gérer les disponibilités de mes spécialistes (créneaux horaires, jours disponibles, etc.)
  - **API**: `GET /api/coach/specialists/{id}/availability`, `POST /api/coach/specialists/{id}/availability`
- En tant que Coach, quand je gère les disponibilités, je dois pouvoir effectuer les opérations CRUD sur les créneaux de disponibilité d'un spécialiste
  - **API**: `PUT /api/coach/specialists/{id}/availability/{availabilityId}`, `DELETE /api/coach/specialists/{id}/availability/{availabilityId}`
- En tant que Coach, quand je consulte les spécialistes, je dois pouvoir filtrer les spécialistes selon différents critères (disponibilité, spécialité, statut, etc.)
  - **API**: `GET /api/coach/specialists?available={true}&specialization={spec}&status={status}`

## Dashboard

- En tant que Coach, quand j'accède au dashboard, je dois voir un résumé des statistiques importantes
  - **API**: `GET /api/coach/dashboard`
- En tant que Coach, quand je consulte le dashboard, je dois voir le nombre total de familles actives
  - **API**: `GET /api/coach/dashboard` (inclut dans la réponse)
- En tant que Coach, quand je consulte le dashboard, je dois voir le nombre d'objectifs en cours
  - **API**: `GET /api/coach/dashboard` (inclut dans la réponse)
- En tant que Coach, quand je consulte le dashboard, je dois voir le nombre de demandes en attente
  - **API**: `GET /api/coach/dashboard` (inclut dans la réponse)
- En tant que Coach, quand je consulte le dashboard, je dois avoir un accès rapide aux actions urgentes
  - **API**: `GET /api/coach/dashboard` (inclut dans la réponse)

## Paramètres

- En tant que Coach, quand je consulte les paramètres, je dois pouvoir changer mon nom et mon mot de passe
  - **API**: `GET /api/coach/settings`, `PUT /api/coach/settings`


Familles => il y'a des bugs 
  -- Un parent ne peut avoir qu'une seule famille 
  -- Quand un coach ajoute un enfant dans une famille, on doit retrouver l'enfant dans la famille 
  -- Quand un coach,ou un parent ajoute un enfant dans une famille, l'app le deconnecte pour reconncte avec le nouvelle utilisateur , il ne faut pas 
  --- Quand, on clique sur 'Modifier le parent', le bloc ne doit pas être vide,il doit contenir  les infos du parent          
  --- bloc 'Modifier l'enfant', il doit contenir les infos de l'enfant
  --- sur chaque enfant il manque le bouton pour accéder a ses objectifs et son planning  à coté des boutons 'edition' 
  
