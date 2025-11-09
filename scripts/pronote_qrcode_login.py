#!/usr/bin/env python3
"""
Script non-interactif pour se connecter à PRONOTE via QR code
Utilise pronotepy.Client.qrcode_login
Usage: python3 pronote_qrcode_login.py <qr_code_json>
"""

import json
import sys
import uuid
import pronotepy

def qrcode_login(qr_data_json):
    """
    Se connecte à PRONOTE avec les données du QR code
    """
    try:
        # Parser les données du QR code
        if isinstance(qr_data_json, str):
            qr_data = json.loads(qr_data_json)
        else:
            qr_data = qr_data_json
        
        print("📋 Données du QR code reçues:")
        print(f"   URL: {qr_data.get('url', 'N/A')}")
        print(f"   Login: {qr_data.get('login', 'N/A')}")
        print(f"   Jeton: {qr_data.get('jeton', 'N/A')[:20]}...")
        print(f"   PIN: {qr_data.get('pin', 'N/A')}")
        print()
        
        # Préparer les credentials pour qrcode_login
        # Format attendu par qrcode_login: dict avec 'login', 'jeton', 'url'
        # Convertir l'URL mobile en URL standard si nécessaire
        url = qr_data.get('url', '')
        if '/mobile.eleve.html' in url:
            url = url.replace('/mobile.eleve.html', '/eleve.html')
            print(f"⚠️  URL mobile détectée, conversion en: {url}")
        
        qr_code_dict = {
            'url': url,
            'login': qr_data.get('login'),
            'jeton': qr_data.get('jeton'),
        }
        
        pin = qr_data.get('pin')
        # UUID unique pour l'application (peut être généré ou utiliser le login)
        app_uuid = qr_data.get('login')  # Utiliser le login comme UUID par défaut
        
        print("🔄 Tentative de connexion avec qrcode_login...")
        print(f"   QR Code dict: {json.dumps({k: (v[:20] + '...' if isinstance(v, str) and len(v) > 20 else v) for k, v in qr_code_dict.items()}, indent=2)}")
        print(f"   PIN: {pin}")
        print(f"   UUID: {app_uuid}")
        print()
        
        # Utiliser qrcode_login
        # Signature: qrcode_login(qr_code: dict, pin: str, uuid: str, ...)
        try:
            client = pronotepy.Client.qrcode_login(qr_code_dict, pin, app_uuid)
            
            if client.logged_in:
                print("✅ Connexion réussie !")
                print()
                
                try:
                    # Récupération des informations de l'utilisateur
                    print("📋 Informations utilisateur:")
                    user_name = getattr(client.info, 'name', 'N/A')
                    user_class = getattr(client.info, 'class_name', 'N/A')
                    user_school = getattr(client.info, 'school', getattr(client.info, 'establishment', 'N/A'))
                    
                    print(f"   Nom: {user_name}")
                    print(f"   Classe: {user_class}")
                    print(f"   Établissement: {user_school}")
                    print()
                    
                    # Export des credentials pour stockage
                    print("💾 Export des credentials...")
                    exported_credentials = client.export_credentials()
                    
                    return {
                        'success': True,
                        'credentials': exported_credentials,
                        'user_info': {
                            'name': user_name,
                            'class_name': user_class,
                            'school': user_school
                        }
                    }
                except Exception as info_error:
                    # Si on ne peut pas récupérer les infos, on retourne quand même le succès
                    print(f"⚠️  Erreur lors de la récupération des infos: {info_error}")
                    print("   Mais la connexion est réussie, export des credentials...")
                    try:
                        exported_credentials = client.export_credentials()
                        return {
                            'success': True,
                            'credentials': exported_credentials,
                            'user_info': None,
                            'warning': str(info_error)
                        }
                    except:
                        return {
                            'success': True,
                            'credentials': None,
                            'user_info': None,
                            'warning': 'Connexion réussie mais impossible d\'exporter les credentials'
                        }
            else:
                return {
                    'success': False,
                    'error': 'Connexion échouée (logged_in = False)'
                }
                
        except AttributeError:
            # Si qrcode_login n'existe pas, essayer token_login
            print("⚠️  qrcode_login non disponible, tentative avec token_login...")
            url = qr_data.get('url', '').replace('/mobile.eleve.html', '/eleve.html')
            client = pronotepy.Client.token_login(
                url,
                qr_data.get('login'),
                qr_data.get('jeton'),
                qr_data.get('login'),
                account_pin=pin
            )
            
            if client.logged_in:
                print("✅ Connexion réussie avec token_login !")
                exported_credentials = client.export_credentials()
                return {
                    'success': True,
                    'credentials': exported_credentials,
                    'user_info': {
                        'name': client.info.name,
                        'class_name': client.info.class_name,
                        'school': client.info.school
                    }
                }
            else:
                return {
                    'success': False,
                    'error': 'Connexion échouée avec token_login'
                }
                
    except pronotepy.exceptions.CryptoError as e:
        error_msg = str(e)
        if 'expired' in error_msg.lower() or 'qr code' in error_msg.lower():
            print("❌ Erreur: Le QR code a probablement expiré")
            print("   Les QR codes PRONOTE sont valides pendant 10 minutes seulement.")
            print("   Veuillez générer un nouveau QR code depuis l'application PRONOTE.")
        else:
            print(f"❌ Erreur de décryptage: {error_msg}")
        return {
            'success': False,
            'error': error_msg,
            'suggestion': 'Le QR code a peut-être expiré. Générez-en un nouveau depuis l\'app PRONOTE.'
        }
    except Exception as e:
        import traceback
        error_msg = str(e)
        print(f"❌ Erreur: {error_msg}")
        
        # Suggestions selon le type d'erreur
        suggestion = None
        if 'expired' in error_msg.lower() or 'qr code' in error_msg.lower():
            suggestion = 'Le QR code a peut-être expiré. Générez-en un nouveau depuis l\'app PRONOTE.'
        elif 'login' in error_msg.lower() or 'authentication' in error_msg.lower():
            suggestion = 'Vérifiez que les credentials (login, jeton, PIN) sont corrects.'
        elif 'url' in error_msg.lower() or 'connection' in error_msg.lower():
            suggestion = 'Vérifiez que l\'URL PRONOTE est accessible et correcte.'
        
        if suggestion:
            print(f"💡 Suggestion: {suggestion}")
        
        return {
            'success': False,
            'error': error_msg,
            'suggestion': suggestion
        }

if __name__ == "__main__":
    # Récupérer les données du QR code depuis stdin ou argument
    if len(sys.argv) > 1:
        qr_data_json = sys.argv[1]
    else:
        # Lire depuis stdin
        qr_data_json = sys.stdin.read()
    
    if not qr_data_json:
        print("❌ Erreur: Aucune donnée QR code fournie")
        print("Usage: python3 pronote_qrcode_login.py '<qr_code_json>'")
        print("   ou: echo '<qr_code_json>' | python3 pronote_qrcode_login.py")
        sys.exit(1)
    
    # Se connecter
    result = qrcode_login(qr_data_json)
    
    # Afficher le résultat en JSON
    print()
    print("📤 Résultat:")
    print(json.dumps(result, indent=2, ensure_ascii=False))
    
    # Code de sortie
    sys.exit(0 if result.get('success') else 1)

