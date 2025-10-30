<?php

/**
 * Tests pour toutes les features Student selon STUDENT_FEATURES.md
 */

global $baseUrl, $tokens, $testResults;

$headers = ['Authorization: ' . $tokens['student']];

echo "Testing Student Dashboard...\n";
testEndpoint('Student Dashboard', "$baseUrl/student/dashboard", 'GET', null, 200, $headers);
testEndpoint('Student Active Objectives', "$baseUrl/student/dashboard/objectives/active", 'GET', null, 200, $headers);
testEndpoint('Student Points', "$baseUrl/student/dashboard/points", 'GET', null, 200, $headers);
testEndpoint('Student Upcoming', "$baseUrl/student/dashboard/upcoming", 'GET', null, 200, $headers);
testEndpoint('Student Today Tasks', "$baseUrl/student/dashboard/today", 'GET', null, 200, $headers);

echo "Testing Student Objectives Management...\n";
testEndpoint('Student Objectives List', "$baseUrl/student/objectives", 'GET', null, 200, $headers);

// Filtrer par statut
testEndpoint('Student Objectives by Status', "$baseUrl/student/objectives?status=in_progress", 'GET', null, 200, $headers);

// Détail d'un objectif
testEndpoint('Student Objective Detail', "$baseUrl/student/objectives/1", 'GET', null, 200, $headers);

// Tâches d'un objectif
testEndpoint('Student Objective Tasks', "$baseUrl/student/objectives/1/tasks", 'GET', null, 200, $headers);

// Progression d'un objectif
testEndpoint('Student Objective Progress', "$baseUrl/student/objectives/1/progress", 'GET', null, 200, $headers);

// Ajouter un commentaire
$commentData = [
    'content' => 'Commentaire de l\'étudiant sur l\'objectif'
];
testEndpoint('Student Add Objective Comment', "$baseUrl/student/objectives/1/comments", 'POST', $commentData, 201, $headers);

echo "Testing Student Tasks Management...\n";
testEndpoint('Student Tasks List', "$baseUrl/student/tasks", 'GET', null, 200, $headers);

// Filtrer par statut
testEndpoint('Student Tasks by Status', "$baseUrl/student/tasks?status=pending", 'GET', null, 200, $headers);

// Détail d'une tâche
testEndpoint('Student Task Detail', "$baseUrl/student/tasks/1", 'GET', null, 200, $headers);

// Mettre à jour le statut d'une tâche
$taskStatusData = [
    'status' => 'in_progress'
];
testEndpoint('Student Update Task Status', "$baseUrl/student/tasks/1/status", 'PUT', $taskStatusData, 200, $headers);

// Uploader une preuve
$proofData = [
    'filename' => 'student_proof.pdf',
    'filePath' => '/uploads/student_proof.pdf',
    'fileType' => 'application/pdf',
    'fileSize' => 2048,
    'description' => 'Preuve de l\'étudiant'
];
testEndpoint('Student Upload Proof', "$baseUrl/student/tasks/1/proofs", 'POST', $proofData, 201, $headers);

echo "Testing Student Planning...\n";
testEndpoint('Student Planning List', "$baseUrl/student/planning", 'GET', null, 200, $headers);

// Filtrer par type
testEndpoint('Student Planning by Type', "$baseUrl/student/planning?type=session", 'GET', null, 200, $headers);

// Détail d'un événement
testEndpoint('Student Planning Detail', "$baseUrl/student/planning/1", 'GET', null, 200, $headers);

echo "Testing Student Requests Management...\n";
testEndpoint('Student Requests List', "$baseUrl/student/requests", 'GET', null, 200, $headers);

// Créer une demande
$requestData = [
    'title' => 'Besoin d\'aide',
    'description' => 'J\'ai besoin d\'aide pour comprendre cette leçon',
    'type' => 'help_request',
    'priority' => 'medium'
];
testEndpoint('Student Create Request', "$baseUrl/student/requests", 'POST', $requestData, 201, $headers);

// Détail d'une demande
testEndpoint('Student Request Detail', "$baseUrl/student/requests/1", 'GET', null, 200, $headers);

// Répondre à une demande
$responseData = [
    'content' => 'Réponse de l\'étudiant à la demande'
];
testEndpoint('Student Add Request Response', "$baseUrl/student/requests/1/response", 'POST', $responseData, 201, $headers);

echo "Testing Student Settings...\n";
testEndpoint('Student Settings Profile', "$baseUrl/student/settings/profile", 'GET', null, 200, $headers);
testEndpoint('Student Settings Display', "$baseUrl/student/settings/display", 'GET', null, 200, $headers);

// Mettre à jour le profil
$profileData = [
    'firstName' => 'Alice Updated',
    'lastName' => 'Smith Updated',
    'pseudo' => 'alice_updated'
];
testEndpoint('Student Update Profile', "$baseUrl/student/settings/profile", 'PUT', $profileData, 200, $headers);

// Changer le mot de passe
$passwordData = [
    'currentPassword' => 'password123',
    'newPassword' => 'newpassword123',
    'confirmPassword' => 'newpassword123'
];
testEndpoint('Student Change Password', "$baseUrl/student/settings/password", 'PUT', $passwordData, 200, $headers);

// Mettre à jour les préférences d'affichage
$displayData = [
    'theme' => 'dark',
    'language' => 'fr',
    'notifications' => true,
    'sound' => false,
    'vibrations' => true
];
testEndpoint('Student Update Display Settings', "$baseUrl/student/settings/display", 'PUT', $displayData, 200, $headers);

echo "✅ Student Features Testing Complete!\n";
