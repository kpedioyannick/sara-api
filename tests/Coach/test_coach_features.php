<?php

/**
 * Tests pour toutes les features Coach selon COACH_FEATURES.md
 */

global $baseUrl, $tokens, $testResults;

$headers = ['Authorization: ' . $tokens['coach']];

echo "Testing Coach Dashboard...\n";
testEndpoint('Coach Dashboard', "$baseUrl/coach/dashboard", 'GET', null, 200, $headers);

echo "Testing Coach Families Management...\n";
testEndpoint('Coach Families List', "$baseUrl/coach/families", 'GET', null, 200, $headers);

// Créer une famille
$familyData = [
    'parent' => [
        'email' => 'parent1@example.com',
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'password' => 'password123'
    ],
    'students' => [
        [
            'email' => 'student1@example.com',
            'firstName' => 'Alice',
            'lastName' => 'Doe',
            'pseudo' => 'alice_d',
            'class' => 'CM2',
            'password' => 'password123'
        ]
    ]
];
testEndpoint('Coach Create Family', "$baseUrl/coach/families", 'POST', $familyData, 201, $headers);

echo "Testing Coach Objectives Management...\n";
testEndpoint('Coach Objectives List', "$baseUrl/coach/objectives", 'GET', null, 200, $headers);

// Créer un objectif
$objectiveData = [
    'title' => 'Améliorer la lecture',
    'description' => 'Objectif de lecture pour l\'élève',
    'student_id' => 1,
    'category' => 'education',
    'priority' => 'high',
    'target_date' => '2024-12-31'
];
testEndpoint('Coach Create Objective', "$baseUrl/coach/objectives", 'POST', $objectiveData, 201, $headers);

echo "Testing Coach Tasks Management...\n";
testEndpoint('Coach Tasks List', "$baseUrl/coach/tasks", 'GET', null, 200, $headers);

// Créer une tâche
$taskData = [
    'title' => 'Lire 10 pages',
    'description' => 'Lire 10 pages du livre assigné',
    'objective_id' => 1,
    'priority' => 'medium',
    'frequency' => 'daily',
    'requires_proof' => true,
    'proof_type' => 'file',
    'assigned_to' => 1,
    'due_date' => '2024-12-15'
];
testEndpoint('Coach Create Task', "$baseUrl/coach/tasks", 'POST', $taskData, 201, $headers);

echo "Testing Coach Requests Management...\n";
testEndpoint('Coach Requests List', "$baseUrl/coach/requests", 'GET', null, 200, $headers);

// Créer une demande
$requestData = [
    'title' => 'Demande de suivi',
    'description' => 'Demande de suivi pour l\'élève',
    'creator_id' => 1,
    'recipient_id' => 1,
    'family_id' => 1,
    'student_id' => 1,
    'priority' => 'medium',
    'type' => 'follow_up'
];
testEndpoint('Coach Create Request', "$baseUrl/coach/requests", 'POST', $requestData, 201, $headers);

echo "Testing Coach Specialists Management...\n";
testEndpoint('Coach Specialists List', "$baseUrl/coach/specialists", 'GET', null, 200, $headers);

// Créer un spécialiste
$specialistData = [
    'email' => 'specialist1@example.com',
    'firstName' => 'Dr. Bob',
    'lastName' => 'Wilson',
    'password' => 'password123',
    'specializations' => ['Speech Therapy', 'Occupational Therapy']
];
testEndpoint('Coach Create Specialist', "$baseUrl/coach/specialists", 'POST', $specialistData, 201, $headers);

echo "Testing Coach Planning Management...\n";
testEndpoint('Coach Planning List', "$baseUrl/coach/planning", 'GET', null, 200, $headers);

// Créer un événement de planning
$planningData = [
    'title' => 'Séance de lecture',
    'description' => 'Séance de lecture avec l\'élève',
    'date' => '2024-12-15 14:00:00',
    'student_id' => 1,
    'type' => 'session',
    'duration' => 60,
    'location' => 'Salle de classe',
    'notes' => 'Séance importante'
];
testEndpoint('Coach Create Planning', "$baseUrl/coach/planning", 'POST', $planningData, 201, $headers);

echo "Testing Coach Availability Management...\n";
testEndpoint('Coach Availability List', "$baseUrl/coach/availability", 'GET', null, 200, $headers);

// Créer une disponibilité
$availabilityData = [
    'start_time' => '2024-12-15 09:00:00',
    'end_time' => '2024-12-15 17:00:00',
    'date' => '2024-12-15',
    'day_of_week' => 'Monday',
    'is_available' => true,
    'notes' => 'Disponible toute la journée'
];
testEndpoint('Coach Create Availability', "$baseUrl/coach/availability", 'POST', $availabilityData, 201, $headers);

echo "Testing Coach Settings...\n";
testEndpoint('Coach Settings Profile', "$baseUrl/coach/settings/profile", 'GET', null, 200, $headers);

echo "✅ Coach Features Testing Complete!\n";
