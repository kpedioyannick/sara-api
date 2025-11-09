# Script PRONOTE QR Code Login

Script Python non-interactif pour se connecter à PRONOTE via QR code en utilisant `pronotepy.Client.qrcode_login`.

## Installation

```bash
pip3 install -U pronotepy --break-system-packages
```

## Utilisation

### Format des données QR code

Le script attend un JSON avec les clés suivantes :
- `url`: URL PRONOTE (ex: `https://0441608j.index-education.net/pronote/mobile.eleve.html`)
- `login`: Identifiant de connexion
- `jeton`: Jeton d'authentification
- `pin`: Code PIN à 4 chiffres

### Exemple d'utilisation

```bash
python3 scripts/pronote_qrcode_login.py '{
  "url": "https://0441608j.index-education.net/pronote/mobile.eleve.html",
  "login": "61BF3E59EBAEF2A67FA3A20D2A875523",
  "jeton": "8D6138DB9A728CB8C0541466924AA486E82FA8DEB44373A84B9CA9E7831238E15FE322AD498FB4FDA7FEAA631AA9B07A5C600D8AAAD51DC44FFFE8153407B7849C230B722A4D022B7073511FD61FB42F2A2D6DBD25143D160C9B1847F2B862B697D423A1700C8CE929B6B944ACF48758",
  "pin": "1234"
}'
```

### Via stdin

```bash
echo '{"url":"...","login":"...","jeton":"...","pin":"1234"}' | python3 scripts/pronote_qrcode_login.py
```

## Réponse

### Succès

```json
{
  "success": true,
  "credentials": {
    "url": "...",
    "username": "...",
    "password": "...",
    ...
  },
  "user_info": {
    "name": "Nom Prénom",
    "class_name": "3ème A",
    "school": "Nom de l'établissement"
  }
}
```

### Erreur

```json
{
  "success": false,
  "error": "Message d'erreur",
  "suggestion": "Suggestion pour résoudre le problème"
}
```

## Notes importantes

1. **Expiration des QR codes** : Les QR codes PRONOTE sont valides pendant **10 minutes seulement**. Si vous obtenez une erreur de décryptage, le QR code a probablement expiré.

2. **Conversion d'URL** : Le script convertit automatiquement les URLs mobiles (`/mobile.eleve.html`) en URLs standard (`/eleve.html`).

3. **UUID** : Le script utilise le `login` comme UUID par défaut. Cet UUID doit rester constant entre les connexions pour un même utilisateur.

4. **Fallback** : Si `qrcode_login` n'est pas disponible, le script essaie automatiquement `token_login` comme alternative.

## Intégration PHP

Pour appeler ce script depuis PHP :

```php
use Symfony\Component\Process\Process;

$qrData = [
    'url' => $qrCodeData['url'],
    'login' => $qrCodeData['login'],
    'jeton' => $qrCodeData['jeton'],
    'pin' => $qrCodeData['pin']
];

$process = new Process([
    'python3',
    __DIR__ . '/../scripts/pronote_qrcode_login.py',
    json_encode($qrData)
]);

$process->run();

if ($process->isSuccessful()) {
    $output = $process->getOutput();
    // Parser le JSON de sortie
    $result = json_decode($output, true);
    if ($result['success']) {
        // Stocker les credentials
        $credentials = $result['credentials'];
    }
} else {
    $error = $process->getErrorOutput();
    // Gérer l'erreur
}
```

