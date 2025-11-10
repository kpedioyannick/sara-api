# Synchronisation PRONOTE avec Pawnote.js

Script de synchronisation PRONOTE utilisant la bibliothèque [Pawnote.js](https://github.com/LiterateInk/Pawnote.js).

## Installation

```bash
npm install
```

## Utilisation

### Synchronisation complète (QR code + données)

```bash
npm run sync '<qr_code_json>' <pin>
```

Exemple :
```bash
npm run sync '{"avecPageConnexion":false,"jeton":"...","login":"...","url":"https://..."}' 1234
```

## Format des credentials

Le script génère des credentials au format suivant :

```json
{
  "pronote_url": "https://...",
  "base_url": "https://...",
  "username": "...",
  "password": "TOKEN",
  "uuid": "...",
  "space": "student",
  "kind": 6,
  "deviceUUID": "...",
  "refresh_info": {
    "kind": 6,
    "url": "https://...",
    "username": "...",
    "token": "TOKEN"
  }
}
```

## Format de sortie

Le script retourne un JSON avec :
- `success`: booléen indiquant le succès
- `credentials`: credentials pour la prochaine connexion
- `data`: données synchronisées (devoirs, cours)
- `new_token`: nouveau token pour reconnexion
