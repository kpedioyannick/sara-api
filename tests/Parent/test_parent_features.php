<?php

/**
 * Tests pour toutes les features Parent selon PARENT_FEATURES.md
 */

global $baseUrl, $tokens, $testResults;

$headers = ['Authorization: ' . $tokens['parent']];

echo "Testing Parent Dashboard...\n";
testEndpoint('Parent Dashboard', "$baseUrl/parent/dashboard", 'GET', null, 200, $headers);
testEndpoint('Parent Dashboard Actions', "$baseUrl/parent/dashboard/actions", 'GET', null, 200, $headers);
testEndpoint('Parent Dashboard Upcoming Events', "$baseUrl/parent/dashboard/upcoming-events", 'GET', null, 200, $headers);

echo "Testing Parent Family Management...\n";
testEndpoint('Parent Children List', "$baseUrl/parent/family/children", 'GET', null, 200, $headers);
testEndpoint('Parent Family Profile', "$baseUrl/parent/family/profile", 'GET', null, 200, $headers);
testEndpoint('Parent Available Classes', "$baseUrl/parent/family/classes", 'GET', null, 200, $headers);

// Créer un enfant
$childData = [
    'email' => 'child@example.com',
    'firstName' => 'Bob',
    'lastName' => 'Smith',
    'pseudo' => 'bob_s',
    'class' => 'CM1',
    'password' => 'password123',
    'confirmPassword' => 'password123'
];
testEndpoint('Parent Create Child', "$baseUrl/parent/family/children", 'POST', $childData, 201, $headers);

echo "Testing Parent Objectives Management...\n";
testEndpoint('Parent Objectives List', "$baseUrl/parent/objectives", 'GET', null, 200, $headers);

// Créer un objectif
$objectiveData = [
    'title' => 'Améliorer les maths',
    'description' => 'Objectif de mathématiques pour l\'enfant',
    'student_id' => 1,
    'category' => 'education',
    'priority' => 'high',
    'target_date' => '2024-12-31'
];
testEndpoint('Parent Create Objective', "$baseUrl/parent/objectives", 'POST', $objectiveData, 201, $headers);

// Ajouter un commentaire à un objectif
$commentData = [
    'content' => 'Commentaire du parent sur l\'objectif'
];
testEndpoint('Parent Add Objective Comment', "$baseUrl/parent/objectives/1/comments", 'POST', $commentData, 201, $headers);

echo "Testing Parent Tasks Management...\n";
testEndpoint('Parent Tasks List', "$baseUrl/parent/tasks", 'GET', null, 200, $headers);
testEndpoint('Parent Assigned Tasks', "$baseUrl/parent/tasks/assigned", 'GET', null, 200, $headers);

// Mettre à jour le statut d'une tâche
$taskStatusData = [
    'status' => 'in_progress'
];
testEndpoint('Parent Update Task Status', "$baseUrl/parent/tasks/1/status", 'PUT', $taskStatusData, 200, $headers);

// Uploader une preuve
$proofData = [
    'filename' => 'proof.pdf',
    'filePath' => '/uploads/proof.pdf',
    'fileType' => 'application/pdf',
    'fileSize' => 1024,
    'description' => 'Preuve de lecture'
];
testEndpoint('Parent Upload Proof', "$baseUrl/parent/tasks/1/proofs", 'POST', $proofData, 201, $headers);

echo "Testing Parent Requests Management...\n";
testEndpoint('Parent Requests List', "$baseUrl/parent/requests", 'GET', null, 200, $headers);

// Créer une demande
$requestData = [
    'title' => 'Demande d\'aide',
    'description' => 'Besoin d\'aide pour les devoirs',
    'type' => 'help_request',
    'priority' => 'medium'
];
testEndpoint('Parent Create Request', "$baseUrl/parent/requests", 'POST', $requestData, 201, $headers);

// Répondre à une demande
$responseData = [
    'content' => 'Réponse du parent à la demande'
];
testEndpoint('Parent Add Request Response', "$baseUrl/parent/requests/1/response", 'POST', $responseData, 201, $headers);

echo "Testing Parent Planning...\n";
testEndpoint('Parent Planning List', "$baseUrl/parent/planning", 'GET', null, 200, $headers);

echo "Testing Parent Settings...\n";
testEndpoint('Parent Settings Profile', "$baseUrl/parent/settings/profile", 'GET', null, 200, $headers);
testEndpoint('Parent Settings Notifications', "$baseUrl/parent/settings/notifications", 'GET', null, 200, $headers);

// Mettre à jour le profil
$profileData = [
    'firstName' => 'Jane Updated',
    'lastName' => 'Smith Updated'
];
testEndpoint('Parent Update Profile', "$baseUrl/parent/settings/profile", 'PUT', $profileData, 200, $headers);

// Changer le mot de passe
$passwordData = [
    'currentPassword' => 'password123',
    'newPassword' => 'newpassword123',
    'confirmPassword' => 'newpassword123'
];
testEndpoint('Parent Change Password', "$baseUrl/parent/settings/password", 'PUT', $passwordData, 200, $headers);

echo "✅ Parent Features Testing Complete!\n";
