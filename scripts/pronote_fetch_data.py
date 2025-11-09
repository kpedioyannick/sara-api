#!/usr/bin/env python3
"""
Script pour récupérer les données PRONOTE (devoirs, cours, absences, notes)
Utilise les credentials exportés par pronote_qrcode_login.py
Usage: python3 pronote_fetch_data.py <credentials_json>
"""

import json
import sys
import pronotepy
from datetime import datetime, timedelta

def fetch_pronote_data(credentials_json):
    """
    Récupère les données PRONOTE avec les credentials fournis
    """
    try:
        # Parser les credentials
        if isinstance(credentials_json, str):
            credentials = json.loads(credentials_json)
        else:
            credentials = credentials_json
        
        # Se connecter avec les credentials
        # Les credentials exportés contiennent: pronote_url, username, password, client_identifier, uuid
        try:
            # Utiliser token_login avec les credentials exportés
            client = pronotepy.Client.token_login(
                credentials.get('pronote_url'),
                credentials.get('username'),
                credentials.get('password'),
                credentials.get('uuid'),
                client_identifier=credentials.get('client_identifier')
            )
        except Exception as e:
            # Si token_login échoue, essayer la connexion standard
            try:
                client = pronotepy.Client(
                    credentials.get('pronote_url'),
                    username=credentials.get('username'),
                    password=credentials.get('password')
                )
            except Exception as e2:
                return {
                    'success': False,
                    'error': f'Erreur de connexion: {str(e2)}'
                }
        
        if not client.logged_in:
            return {
                'success': False,
                'error': 'Connexion échouée (logged_in = False)'
            }
        
        results = {
            'success': True,
            'homework': [],
            'lessons': [],
            'absences': [],
            'grades': [],
            'carnet_correspondance': []
        }
        
        # Récupérer les devoirs
        try:
            homework = client.homework
            for hw in homework:
                results['homework'].append({
                    'id': str(hw.id) if hasattr(hw, 'id') else None,
                    'subject': hw.subject.name if hasattr(hw, 'subject') and hw.subject else 'N/A',
                    'description': hw.description if hasattr(hw, 'description') else '',
                    'date': hw.date.strftime('%Y-%m-%d') if hasattr(hw, 'date') and hw.date else None,
                    'done': hw.done if hasattr(hw, 'done') else False,
                })
        except Exception as e:
            print(f"⚠️  Erreur lors de la récupération des devoirs: {e}", file=sys.stderr)
        
        # Récupérer les cours (planning de la semaine)
        try:
            today = datetime.now().date()
            week_start = today - timedelta(days=today.weekday())
            week_end = week_start + timedelta(days=6)
            
            for day_offset in range(7):
                current_date = week_start + timedelta(days=day_offset)
                lessons = client.lessons(current_date)
                
                for lesson in lessons:
                    results['lessons'].append({
                        'id': str(lesson.id) if hasattr(lesson, 'id') else None,
                        'subject': lesson.subject.name if hasattr(lesson, 'subject') and lesson.subject else 'N/A',
                        'room': lesson.room if hasattr(lesson, 'room') else '',
                        'start': lesson.start.isoformat() if hasattr(lesson, 'start') and lesson.start else None,
                        'end': lesson.end.isoformat() if hasattr(lesson, 'end') and lesson.end else None,
                        'date': current_date.strftime('%Y-%m-%d'),
                    })
        except Exception as e:
            print(f"⚠️  Erreur lors de la récupération des cours: {e}", file=sys.stderr)
        
        # Récupérer les absences
        try:
            absences = client.absences
            for absence in absences:
                results['absences'].append({
                    'id': str(absence.id) if hasattr(absence, 'id') else None,
                    'date': absence.from_date.strftime('%Y-%m-%d') if hasattr(absence, 'from_date') and absence.from_date else None,
                    'reason': absence.reason if hasattr(absence, 'reason') else '',
                    'justified': absence.justified if hasattr(absence, 'justified') else False,
                })
        except Exception as e:
            print(f"⚠️  Erreur lors de la récupération des absences: {e}", file=sys.stderr)
        
        # Récupérer les notes (première période)
        try:
            periods = client.periods
            if periods:
                grades = periods[0].grades
                for grade in grades:
                    results['grades'].append({
                        'subject': grade.subject.name if hasattr(grade, 'subject') and grade.subject else 'N/A',
                        'grade': grade.grade if hasattr(grade, 'grade') else None,
                        'out_of': grade.out_of if hasattr(grade, 'out_of') else None,
                        'date': grade.date.strftime('%Y-%m-%d') if hasattr(grade, 'date') and grade.date else None,
                        'comment': grade.comment if hasattr(grade, 'comment') else '',
                    })
        except Exception as e:
            print(f"⚠️  Erreur lors de la récupération des notes: {e}", file=sys.stderr)
        
        # Récupérer le carnet de correspondance (messages)
        try:
            # Note: pronotepy peut avoir une méthode pour les messages/carnet
            # À adapter selon l'API disponible
            messages = getattr(client, 'messages', [])
            for msg in messages:
                results['carnet_correspondance'].append({
                    'id': str(msg.id) if hasattr(msg, 'id') else None,
                    'date': msg.date.strftime('%Y-%m-%d') if hasattr(msg, 'date') and msg.date else None,
                    'author': msg.author if hasattr(msg, 'author') else '',
                    'subject': msg.subject if hasattr(msg, 'subject') else '',
                    'content': msg.content if hasattr(msg, 'content') else '',
                })
        except Exception as e:
            print(f"⚠️  Erreur lors de la récupération du carnet: {e}", file=sys.stderr)
        
        return results
        
    except Exception as e:
        import traceback
        return {
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({
            'success': False,
            'error': 'Missing credentials'
        }))
        sys.exit(1)
    
    credentials_json = sys.argv[1]
    result = fetch_pronote_data(credentials_json)
    
    print(json.dumps(result, indent=2, ensure_ascii=False))
    sys.exit(0 if result.get('success') else 1)

