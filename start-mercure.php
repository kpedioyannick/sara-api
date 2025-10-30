<?php
/**
 * Script simple pour démarrer Mercure en local
 * Usage: php start-mercure.php
 */

echo "🚀 Démarrage de Mercure Hub...\n";

// Configuration
$port = 3000;
$jwtSecret = '!ChangeThisMercureHubJWTSecretKey!';
$corsOrigins = 'http://localhost:3000,http://127.0.0.1:3000,http://localhost:8000,http://127.0.0.1:8000';

// Vérifier si le port est libre
$socket = @fsockopen('localhost', $port, $errno, $errstr, 1);
if ($socket) {
    fclose($socket);
    echo "❌ Le port $port est déjà utilisé. Arrêtez le processus qui l'utilise.\n";
    exit(1);
}

echo "🌐 Mercure sera disponible sur: http://localhost:$port\n";
echo "📡 Endpoint: http://localhost:$port/.well-known/mercure\n";
echo "🔑 JWT Secret: $jwtSecret\n";
echo "🌍 CORS Origins: $corsOrigins\n";
echo "\nPour arrêter Mercure, appuyez sur Ctrl+C\n\n";

// Démarrer Mercure avec les variables d'environnement
putenv("MERCURE_PUBLISHER_JWT_KEY=$jwtSecret");
putenv("MERCURE_SUBSCRIBER_JWT_KEY=$jwtSecret");
putenv("MERCURE_EXTRA_DIRECTIVES=cors_origins $corsOrigins");

// Commande pour démarrer Mercure
$command = "mercure --addr=:$port --cors-allowed-origins=$corsOrigins --publish-allowed-origins=http://localhost:8000,http://127.0.0.1:8000";

echo "Exécution: $command\n\n";

// Exécuter la commande
passthru($command);
