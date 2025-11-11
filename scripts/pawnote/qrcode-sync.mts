#!/usr/bin/env node
/**
 * Script complet : connexion QR code + synchronisation immédiate
 * Utilise la session directement après connexion pour éviter l'expiration du token
 * Usage: node qrcode-sync.mjs '<qr_code_json>' <pin>
 */

import { existsSync } from 'fs';
import { readFile } from 'fs/promises';

async function qrcodeLoginAndSync(qrDataJson: string, pin: string) {
  try {
    // Import différé de Pawnote pour éviter les problèmes de chargement
    const pawnote = await import('pawnote');
    const { createSessionHandle, loginQrCode, AccountKind, assignmentsFromWeek, assignmentsFromIntervals, timetableFromIntervals, evaluations, gradebook, notebook, sessionInformation, translateToWeekNumber, TabLocation } = pawnote;
    const qrData = JSON.parse(qrDataJson);
    
    const session = createSessionHandle();
    
    // Se connecter avec QR code
    const refreshInfo = await loginQrCode(session, {
      deviceUUID: qrData.login,
      pin: pin,
      qr: {
        avecPageConnexion: qrData.avecPageConnexion || false,
        jeton: qrData.jeton,
        login: qrData.login,
        url: qrData.url
      }
    });
    const today = new Date();
    
    // Calculer le début de l'année scolaire (septembre de l'année en cours ou précédente)
    const currentYear = today.getFullYear();
    const currentMonth = today.getMonth(); // 0-11
    
    let schoolYearStart: Date;
    let schoolYearEnd: Date;
    
    if (currentMonth >= 8) {
      // On est entre septembre et décembre : année scolaire = septembre année N → juin année N+1
      schoolYearStart = new Date(currentYear, 8, 1); // 1er septembre année en cours
      schoolYearEnd = new Date(currentYear + 1, 5, 30); // 30 juin année suivante
    } else {
      // On est entre janvier et août : année scolaire = septembre année N-1 → juin année N
      schoolYearStart = new Date(currentYear - 1, 8, 1); // 1er septembre année précédente
      schoolYearEnd = new Date(currentYear, 5, 30); // 30 juin année en cours
    }
    
    // Récupérer sessionInformation pour obtenir la date de début de l'année scolaire
    let sessionInfo = null;
    try {
      sessionInfo = await sessionInformation(session);
    } catch (e: any) {
      console.error(`   ⚠️  Impossible de récupérer sessionInformation: ${e.message}`);
    }
    
    // Convertir les dates en numéros de semaine pour utiliser assignmentsFromWeek
    // translateToWeekNumber(date, startDay) - startDay est la date de début de l'année scolaire
    // On utilise schoolYearStart comme startDay par défaut
    const startDay = schoolYearStart;
    const startWeek = translateToWeekNumber(schoolYearStart, startDay);
    const endWeek = translateToWeekNumber(schoolYearEnd, startDay);
    
    const assignments = await assignmentsFromWeek(session, startWeek, endWeek);
    
    // Récupérer les cours
    const timetable = await timetableFromIntervals(session, schoolYearStart, schoolYearEnd);
    const lessonsCount = timetable?.classes?.length || 0;
    
    if (false) {
      // Code de debug désactivé
      timetable.classes.slice(0, 5).forEach((lesson: any, i: number) => {
        const subject = lesson.subject?.name || 'N/A';
        const start = lesson.start ? new Date(lesson.start).toLocaleString('fr-FR') : 'N/A';
        const room = lesson.room || '';
        console.error(`      ${i + 1}. ${subject} - ${start} ${room ? `(${room})` : ''}`);
      });
    }
    
    // sessionInfo a déjà été récupéré pour les devoirs, on le réutilise
    
    // Récupérer les évaluations/notes
    let evaluationsData = [];
    let gradebookData = null;
    if (sessionInfo) {
      try {
        evaluationsData = await evaluations(session, sessionInfo) || [];
      } catch (e: any) {
        console.error(`❌ Erreur lors de la récupération des évaluations: ${e.message}`);
      }
    }
    
    // Récupérer le bulletin de notes
    if (sessionInfo) {
      try {
        gradebookData = await gradebook(session, sessionInfo);
      } catch (e: any) {
        console.error(`❌ Erreur lors de la récupération du bulletin: ${e.message}`);
      }
    }
    
    // Récupérer le carnet de correspondance
    let notebookData = [];
    try {
      // Récupérer l'onglet Notebook depuis la session
      const tab = session.userResource.tabs.get(TabLocation.Notebook);
      if (!tab) {
        console.error(`❌ Impossible de récupérer l'onglet Notebook`);
      } else {
        // Sélectionner la période par défaut
        const selectedPeriod = tab.defaultPeriod;
        if (!selectedPeriod) {
          console.error(`❌ Aucune période par défaut disponible`);
        }
          const notebookResult = await notebook(session, selectedPeriod);
          
          // Le notebook est un objet avec différentes propriétés (absences, delays, observations, etc.)
          if (notebookResult) {
            // Extraire toutes les données du notebook
            notebookData = [
              ...(notebookResult.absences || []),
              ...(notebookResult.delays || []),
              ...(notebookResult.observations || []),
              ...(notebookResult.punishments || []),
              ...(notebookResult.precautionaryMeasures || [])
            ];
          }
        }
        }
      }
    } catch (e: any) {
      console.error(`❌ Erreur lors de la récupération du carnet: ${e.message}`);
    }
    
    // Note: Les absences ne sont pas disponibles via l'API Pawnote.js standard
    const absencesData: any[] = [];
    
    // Construire le résultat
    const result = {
      success: true,
      credentials: {
        pronote_url: refreshInfo.url || qrData.url.replace('/mobile.eleve.html', '/eleve.html'),
        base_url: (refreshInfo.url || qrData.url).replace('/eleve.html', '/').replace('/mobile.eleve.html', '/'),
        username: refreshInfo.username || qrData.login,
        password: refreshInfo.token || '',
        uuid: qrData.login,
        space: 'student',
        kind: refreshInfo.kind || AccountKind.STUDENT,
        deviceUUID: qrData.login,
        refresh_info: {
          kind: refreshInfo.kind,
          url: refreshInfo.url,
          username: refreshInfo.username,
          token: refreshInfo.token
        }
      },
      data: {
        homework: assignments.length,
        lessons: lessonsCount,
        assignments: assignments.map((hw: any) => {
          // Extraire la date depuis différents champs possibles
          let dateValue = null;
          if (hw.date) {
            dateValue = hw.date;
          } else if (hw.from) {
            dateValue = hw.from;
          } else if (hw.to) {
            dateValue = hw.to;
          } else if (hw.startDate) {
            dateValue = hw.startDate;
          }
          
          return {
            id: hw.id,
            subject: hw.subject?.name || 'N/A',
            description: hw.description || '',
            date: dateValue ? new Date(dateValue).toISOString().split('T')[0] : null,
            done: hw.done || false,
            raw: hw // Inclure l'objet brut pour debug
          };
        }),
        lessons_list: timetable?.classes?.map((lesson: any) => {
          // Extraire les dates depuis startDate/endDate si start/end ne sont pas disponibles
          let startValue = lesson.start || lesson.startDate;
          let endValue = lesson.end || lesson.endDate;
          
          return {
            id: lesson.id,
            subject: lesson.subject?.name || 'N/A',
            room: lesson.room || lesson.classrooms?.[0] || '',
            start: startValue ? new Date(startValue).toISOString() : null,
            end: endValue ? new Date(endValue).toISOString() : null,
            teacher: lesson.teacher?.name || lesson.teacherNames?.[0] || lesson.teacher || null,
            group: lesson.group?.name || lesson.groupNames?.[0] || lesson.group || null,
            raw: lesson
          };
        }) || [],
        evaluations: (evaluationsData as any[]).map((evaluation: any) => ({
          id: evaluation.id,
          name: evaluation.name || evaluation.subject?.name || 'N/A',
          subject: evaluation.subject?.name || 'N/A',
          date: evaluation.date ? new Date(evaluation.date).toISOString().split('T')[0] : null,
          coefficient: evaluation.coefficient || null,
          average: evaluation.average || null,
          raw: evaluation
        })),
        gradebook: gradebookData ? {
          periods: (gradebookData as any).periods || [],
          subjects: (gradebookData as any).subjects || gradebookData.subjects || [],
          raw: gradebookData
        } : null,
        notebook: notebookData.map((msg: any) => {
          // Gérer les différents types d'entrées du notebook
          let dateValue = null;
          let authorValue = 'N/A';
          let contentValue = '';
          let kindValue = null;
          
          // Pour les absences
          if (msg.startDate) {
            dateValue = msg.startDate ? new Date(msg.startDate).toISOString().split('T')[0] : null;
            contentValue = `Absence du ${msg.startDate ? new Date(msg.startDate).toLocaleDateString('fr-FR') : 'N/A'} au ${msg.endDate ? new Date(msg.endDate).toLocaleDateString('fr-FR') : 'N/A'}`;
            if (msg.justified !== undefined) {
              contentValue += ` (${msg.justified ? 'Justifiée' : 'Non justifiée'})`;
            }
            kindValue = 'Absence';
          }
          // Pour les retards
          else if (msg.minutes !== undefined) {
            dateValue = msg.date ? new Date(msg.date).toISOString().split('T')[0] : null;
            contentValue = `Retard de ${msg.minutes} minutes`;
            if (msg.justification) {
              contentValue += ` - ${msg.justification}`;
            }
            if (msg.justified !== undefined) {
              contentValue += ` (${msg.justified ? 'Justifié' : 'Non justifié'})`;
            }
            kindValue = 'Retard';
          }
          // Pour les observations
          else if (msg.name) {
            dateValue = msg.date ? new Date(msg.date).toISOString().split('T')[0] : null;
            contentValue = msg.name;
            if (msg.kind !== undefined) {
              const kindMap: Record<number, string> = {
                0: 'Problème de carnet',
                1: 'Observation',
                2: 'Encouragement'
              };
              kindValue = kindMap[msg.kind] || 'Observation';
            } else {
              kindValue = 'Observation';
            }
          }
          // Pour les punitions (exclusions de cours, etc.)
          else if (msg.giver || msg.title || msg.reasons) {
            dateValue = msg.dateGiven ? new Date(msg.dateGiven).toISOString().split('T')[0] : (msg.date ? new Date(msg.date).toISOString().split('T')[0] : null);
            if (msg.title) {
              contentValue = msg.title;
              if (msg.reasons && Array.isArray(msg.reasons) && msg.reasons.length > 0) {
                contentValue += `: ${msg.reasons.join(', ')}`;
              }
              if (msg.circumstances) {
                contentValue += ` - ${msg.circumstances}`;
              }
              if (msg.durationMinutes) {
                contentValue += ` (${msg.durationMinutes} min)`;
              }
            } else if (msg.reason) {
              contentValue = msg.reason;
              if (msg.nature) {
                contentValue = `${msg.nature}: ${contentValue}`;
              }
            } else {
              contentValue = 'Punition';
            }
            authorValue = msg.giver || 'N/A';
            kindValue = msg.exclusion ? 'Exclusion de cours' : 'Punition';
          }
          // Pour les mesures préventives
          else if (msg.measure) {
            dateValue = msg.date ? new Date(msg.date).toISOString().split('T')[0] : null;
            contentValue = msg.measure;
            kindValue = 'Mesure préventive';
          }
          // Format générique (fallback)
          else {
            dateValue = msg.date ? new Date(msg.date).toISOString().split('T')[0] : null;
            authorValue = msg.author?.name || msg.author || 'N/A';
            contentValue = msg.content || msg.text || msg.name || '';
            kindValue = msg.kind || msg.type || null;
          }
          
          return {
            id: msg.id,
            date: dateValue,
            author: authorValue,
            content: contentValue,
            kind: kindValue,
            raw: msg
          };
        }),
        absences: absencesData.map((absence: any) => ({
          id: absence.id,
          date: absence.date ? new Date(absence.date).toISOString().split('T')[0] : null,
          startDate: absence.startDate ? new Date(absence.startDate).toISOString() : null,
          endDate: absence.endDate ? new Date(absence.endDate).toISOString() : null,
          reason: absence.reason || absence.justification || absence.comment || '',
          justified: absence.justified || false,
          type: absence.type || 'absence',
          raw: absence
        }))
      },
      new_token: {
        kind: refreshInfo.kind,
        url: refreshInfo.url,
        username: refreshInfo.username,
        token: refreshInfo.token
      }
    };
    
    // Envoyer les messages de debug sur stderr pour ne pas polluer stdout
    // Envoyer uniquement le JSON sur stdout (pour le parsing PHP)
    console.log(JSON.stringify(result));
    
    // Le script se termine avec succès (code 0)
    process.exit(0);
    
  } catch (error: any) {
    console.error('❌ Erreur:', error.message);
    if (error.stack) {
      console.error('Stack:', error.stack);
    }
    const errorResult = {
      success: false,
      error: error.message,
      traceback: error.stack,
      needs_qr_code: error.message.toLowerCase().includes('challenge') || error.message.toLowerCase().includes('expired')
    };
    // Envoyer uniquement le JSON sur stdout (pour le parsing PHP)
    console.log(JSON.stringify(errorResult));
    process.exit(1);
  }
}

// Main - Lire depuis fichier temporaire ou arguments
async function main() {
  try {
    let qrDataJson: string;
    let pin: string = '1234';
    
    const args = process.argv.slice(2);
    
    // Si le premier argument est un chemin de fichier, lire depuis le fichier
    if (args.length > 0 && args[0].endsWith('.json')) {
      try {
        if (existsSync(args[0])) {
          qrDataJson = await readFile(args[0], 'utf-8');
          pin = args[1] || '1234';
        } else {
          console.error(`❌ Erreur: Fichier non trouvé: ${args[0]}`);
          process.exit(1);
        }
      } catch (e: any) {
        console.error(`❌ Erreur lors de la lecture du fichier: ${e.message}`);
        process.exit(1);
      }
    } else if (args.length > 0) {
      // Sinon, utiliser les arguments directement
      qrDataJson = args[0];
      pin = args[1] || '1234';
    } else {
      console.error('❌ Erreur: Aucune donnée QR code fournie');
      console.error('Usage: node qrcode-sync.mts <qr_code_json> <pin>');
      console.error('   ou: node qrcode-sync.mts <fichier.json> <pin>');
      process.exit(1);
    }
    
    // Valider que le JSON est valide
    try {
      JSON.parse(qrDataJson);
    } catch (e) {
      console.error('❌ Erreur: JSON invalide dans les données QR code');
      console.error('Données reçues:', qrDataJson.substring(0, 100));
      process.exit(1);
    }
    
    await qrcodeLoginAndSync(qrDataJson, pin);
  } catch (error: any) {
    console.error('❌ Erreur fatale:', error?.message || String(error));
    if (error?.stack) {
      console.error('Stack:', error.stack);
    }
    process.exit(1);
  }
}

// Wrapper pour capturer toutes les erreurs
(async () => {
  try {
    await main();
  } catch (error: any) {
    console.error('❌ Erreur fatale non capturée:', error?.message || String(error));
    if (error?.stack) {
      console.error('Stack:', error.stack);
    }
    process.exit(1);
  }
})();

