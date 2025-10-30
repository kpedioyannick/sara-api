# Features - Parent

## Gestion des Familles : page familles

- En tant que Parent, quand je gère mes enfants, je dois pouvoir effectuer les opérations CRUD (Créer, Lire, Mettre à jour, Supprimer) sur mes enfants
  - **API**: `GET /api/parent/family/children`, `POST /api/parent/family/children`, `PUT /api/parent/family/children/{id}`, `DELETE /api/parent/family/children/{id}`
- En tant que Parent, quand je consulte la page Enfants, je dois voir la liste de tous mes enfants
  - **API**: `GET /api/parent/family/children`
- En tant que Parent, quand je clique sur "Ajouter un enfant", je dois pouvoir créer un compte enfant
  - **API**: `POST /api/parent/family/children`
- En tant que Parent, quand je crée un enfant, je dois renseigner son pseudo, son mot de passe, la confirmation du mot de passe et sa classe
  - **API**: `POST /api/parent/family/children`
- En tant que Parent, quand je sélectionne une classe, je dois choisir parmi une liste prédéfinie via un menu déroulant
  - **API**: `GET /api/parent/classes` (pour récupérer les classes disponibles)
- En tant que Parent, quand j'ai une famille existante, je dois pouvoir ajouter des enfants depuis ma famille existante
  - **API**: `POST /api/parent/family/children`
- En tant que Parent, quand je clique sur un enfant, je dois pouvoir consulter le profil détaillé d'un de mes enfants
  - **API**: `GET /api/parent/family/children/{id}`
- En tant que Parent, quand je consulte un enfant, je dois pouvoir modifier ses informations
  - **API**: `PUT /api/parent/family/children/{id}`

## Gestion des Objectifs

- En tant que Parent, quand je consulte la page Objectifs, je dois pouvoir visualiser les objectifs de mes enfants
- En tant que Parent, quand je clique sur "Créer un objectif", je dois pouvoir créer un objectif pour mes enfants
- En tant que Parent, quand je consulte un objectif, je dois voir le détail d'un objectif (titre, description, échéance, statut, etc.)
- En tant que Parent, quand je consulte un objectif, je dois voir la progression globale
- En tant que Parent, quand je consulte un objectif, je dois pouvoir ajouter un commentaire ou un retour sur un objectif
- En tant que Parent, quand je filtre par enfant, je dois pouvoir filtrer les objectifs ou tâches par enfant ou par statut

## Gestion des Tâches

- En tant que Parent, quand je consulte les tâches d'un objectif, je ne peux pas effectuer les opérations CRUD sur les tâches d'un objectif (c'est la mission du Coach)
- En tant que Parent, quand je consulte un objectif, je dois pouvoir visualiser les tâches associées à un objectif
- En tant que Parent, quand je consulte les tâches, je dois voir les tâches qui me sont assignées par le coach (avec les instructions, dates et priorités)
- En tant que Parent, quand je complète une tâche, je dois pouvoir mettre à jour le statut d'une tâche qui m'est assignée (ex. : en cours, terminée)
- En tant que Parent, quand une tâche nécessite une preuve, je dois pouvoir télécharger un fichier
- En tant que Parent, quand je télécharge une preuve, je dois recevoir une confirmation

## Gestion des Demandes

- En tant que Parent, quand j'ai besoin d'aide, je dois pouvoir créer une demande à destination du coach (ex. : demande de suivi, de rendez-vous, de modification d'objectif, etc.)
- En tant que Parent, quand je consulte mes demandes, je dois pouvoir voir la liste de mes demandes
- En tant que Parent, quand je clique sur une demande, je dois pouvoir consulter le détail d'une demande
- En tant que Parent, quand je consulte une demande, je dois pouvoir suivre l'état (statut) de ma demande (en attente, en cours, traitée)
- En tant que Parent, quand je traite une demande, je dois pouvoir répondre ou ajouter un message à une demande en cours
- En tant que Parent, quand je consulte mes demandes, je dois pouvoir filtrer mes demandes par statut ou date

## Planning

- En tant que Parent, quand je consulte le planning, je dois pouvoir visualiser le planning de mes enfants
- En tant que Parent, quand je consulte un événement, je dois pouvoir consulter le détail des événements du planning (objectif, séance, activité, etc.)
- En tant que Parent, quand je filtre le planning, je dois pouvoir filtrer le planning par enfant ou type d'événement

## Dashboard

- En tant que Parent, quand j'accède au dashboard, je dois voir un résumé de l'activité de mes enfants
- En tant que Parent, quand je consulte le dashboard, je dois voir le nombre d'objectifs actifs par enfant
- En tant que Parent, quand je consulte le dashboard, je dois voir les actions qui m'attendent
- En tant que Parent, quand je consulte le dashboard, je dois voir les prochains événements
- En tant que Parent, quand je consulte le dashboard, je dois voir les points gagnés par chaque enfant

## Profil Famille

- En tant que Parent, quand je consulte mon profil famille, je dois voir toutes les informations de ma famille
- En tant que Parent, quand je consulte mon profil, je dois voir la liste complète de mes enfants
- En tant que Parent, quand je consulte mon profil, je dois pouvoir modifier l'identifiant famille

## Paramètres

- En tant que Parent, quand je consulte les paramètres, je dois pouvoir changer mon nom et mon mot de passe
- En tant que Parent, quand je consulte les paramètres, je dois pouvoir configurer mes préférences de notification
