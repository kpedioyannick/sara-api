<?php

/**
 * Tests pour toutes les features Specialist selon SPECIALIST_FEATURES.md
 */

global $baseUrl, $tokens, $testResults;

$headers = ['Authorization: ' . $tokens['specialist']];

echo "Testing Specialist Dashboard...\n";
testEndpoint('Specialist Dashboard', "$baseUrl/specialist/dashboard", 'GET', null, 200, $headers);
testEndpoint('Specialist Pending Requests', "$baseUrl/specialist/dashboard/requests/pending", 'GET', null, 200, $headers);
testEndpoint('Specialist In Progress Requests', "$baseUrl/specialist/dashboard/requests/in-progress", 'GET', null, 200, $headers);
testEndpoint('Specialist Upcoming Interventions', "$baseUrl/specialist/dashboard/interventions/upcoming", 'GET', null, 200, $headers);
testEndpoint('Specialist Pending Tasks', "$baseUrl/specialist/dashboard/tasks/pending", 'GET', null, 200, $headers);
testEndpoint('Specialist Urgent Requests', "$baseUrl/specialist/dashboard/requests/urgent", 'GET', null, 200, $headers);

echo "Testing Specialist Availability Management...\n";
testEndpoint('Specialist Availability List', "$baseUrl/specialist/availability", 'GET', null, 200, $headers);
testEndpoint('Specialist Availability Planning', "$baseUrl/specialist/availability/planning", 'GET', null, 200, $headers);

// Créer une disponibilité
$availabilityData = [
    'start_time' => '2024-12-15 09:00:00',
    'end_time' => '2024-12-15 17:00:00',
    'date' => '2024-12-15',
    'day_of_week' => 'Monday',
    'is_available' => true,
    'notes' => 'Disponible toute la journée'
];
testEndpoint('Specialist Create Availability', "$baseUrl/specialist/availability", 'POST', $availabilityData, 201, $headers);

// Modifier une disponibilité
$updateAvailabilityData = [
    'start_time' => '2024-12-15 10:00:00',
    'end_time' => '2024-12-15 16:00:00',
    'notes' => 'Disponibilité modifiée'
];
testEndpoint('Specialist Update Availability', "$baseUrl/specialist/availability/1", 'PUT', $updateAvailabilityData, 200, $headers);

echo "Testing Specialist Objectives Management...\n";
testEndpoint('Specialist Objectives List', "$baseUrl/specialist/objectives", 'GET', null, 200, $headers);

// Filtrer par étudiant
testEndpoint('Specialist Objectives by Student', "$baseUrl/specialist/objectives?student_id=1", 'GET', null, 200, $headers);

// Détail d'un objectif
testEndpoint('Specialist Objective Detail', "$baseUrl/specialist/objectives/1", 'GET', null, 200, $headers);

// Tâches d'un objectif
testEndpoint('Specialist Objective Tasks', "$baseUrl/specialist/objectives/1/tasks", 'GET', null, 200, $headers);

// Ajouter un commentaire
$commentData = [
    'content' => 'Commentaire du spécialiste sur l\'objectif'
];
testEndpoint('Specialist Add Objective Comment', "$baseUrl/specialist/objectives/1/comments", 'POST', $commentData, 201, $headers);

echo "Testing Specialist Tasks Management...\n";
testEndpoint('Specialist Tasks List', "$baseUrl/specialist/tasks", 'GET', null, 200, $headers);
testEndpoint('Specialist Assigned Tasks', "$baseUrl/specialist/tasks/assigned", 'GET', null, 200, $headers);

// Filtrer par famille
testEndpoint('Specialist Tasks by Family', "$baseUrl/specialist/tasks?family_id=1", 'GET', null, 200, $headers);

// Filtrer par statut
testEndpoint('Specialist Tasks by Status', "$baseUrl/specialist/tasks?status=pending", 'GET', null, 200, $headers);

// Détail d'une tâche
testEndpoint('Specialist Task Detail', "$baseUrl/specialist/tasks/1", 'GET', null, 200, $headers);

// Mettre à jour le statut d'une tâche
$taskStatusData = [
    'status' => 'completed'
];
testEndpoint('Specialist Update Task Status', "$baseUrl/specialist/tasks/1/status", 'PUT', $taskStatusData, 200, $headers);

// Ajouter un commentaire à une tâche
$taskCommentData = [
    'content' => 'Commentaire du spécialiste sur la tâche'
];
testEndpoint('Specialist Add Task Comment', "$baseUrl/specialist/tasks/1/comments", 'POST', $taskCommentData, 201, $headers);

// Historique des tâches d'un étudiant
testEndpoint('Specialist Task History', "$baseUrl/specialist/tasks/history/1", 'GET', null, 200, $headers);

echo "Testing Specialist Planning...\n";
testEndpoint('Specialist Planning List', "$baseUrl/specialist/planning", 'GET', null, 200, $headers);

// Filtrer par étudiant
testEndpoint('Specialist Planning by Student', "$baseUrl/specialist/planning?student_id=1", 'GET', null, 200, $headers);

// Filtrer par type
testEndpoint('Specialist Planning by Type', "$baseUrl/specialist/planning?type=session", 'GET', null, 200, $headers);

// Détail d'un événement
testEndpoint('Specialist Planning Detail', "$baseUrl/specialist/planning/1", 'GET', null, 200, $headers);

echo "Testing Specialist Requests Management...\n";
testEndpoint('Specialist Requests List', "$baseUrl/specialist/requests", 'GET', null, 200, $headers);

// Filtrer par famille
testEndpoint('Specialist Requests by Family', "$baseUrl/specialist/requests?family_id=1", 'GET', null, 200, $headers);

// Filtrer par statut
testEndpoint('Specialist Requests by Status', "$baseUrl/specialist/requests?status=pending", 'GET', null, 200, $headers);

// Détail d'une demande
testEndpoint('Specialist Request Detail', "$baseUrl/specialist/requests/1", 'GET', null, 200, $headers);

// Répondre à une demande
$responseData = [
    'content' => 'Réponse du spécialiste à la demande'
];
testEndpoint('Specialist Add Request Response', "$baseUrl/specialist/requests/1/response", 'POST', $responseData, 201, $headers);

// Mettre à jour le statut d'une demande
$requestStatusData = [
    'status' => 'in_progress'
];
testEndpoint('Specialist Update Request Status', "$baseUrl/specialist/requests/1/status", 'PUT', $requestStatusData, 200, $headers);

echo "Testing Specialist Settings...\n";
testEndpoint('Specialist Settings Profile', "$baseUrl/specialist/settings/profile", 'GET', null, 200, $headers);
testEndpoint('Specialist Settings Specializations', "$baseUrl/specialist/settings/specializations", 'GET', null, 200, $headers);
testEndpoint('Specialist Settings Notifications', "$baseUrl/specialist/settings/notifications", 'GET', null, 200, $headers);

// Mettre à jour le profil
$profileData = [
    'firstName' => 'Dr. Bob Updated',
    'lastName' => 'Wilson Updated'
];
testEndpoint('Specialist Update Profile', "$baseUrl/specialist/settings/profile", 'PUT', $profileData, 200, $headers);

// Mettre à jour les spécialisations
$specializationsData = [
    'specializations' => ['Speech Therapy', 'Occupational Therapy', 'Physical Therapy']
];
testEndpoint('Specialist Update Specializations', "$baseUrl/specialist/settings/specializations", 'PUT', $specializationsData, 200, $headers);

// Changer le mot de passe
$passwordData = [
    'currentPassword' => 'password123',
    'newPassword' => 'newpassword123',
    'confirmPassword' => 'newpassword123'
];
testEndpoint('Specialist Change Password', "$baseUrl/specialist/settings/password", 'PUT', $passwordData, 200, $headers);

// Mettre à jour les notifications
$notificationsData = [
    'email' => true,
    'push' => true,
    'sms' => false,
    'availability_changes' => true,
    'task_assignments' => true,
    'request_assignments' => true,
    'planning_updates' => true
];
testEndpoint('Specialist Update Notifications', "$baseUrl/specialist/settings/notifications", 'PUT', $notificationsData, 200, $headers);

echo "✅ Specialist Features Testing Complete!\n";
