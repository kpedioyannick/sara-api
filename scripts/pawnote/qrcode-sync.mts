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
    const { createSessionHandle, loginQrCode, AccountKind, assignmentsFromIntervals, timetableFromIntervals, evaluations, gradebook, notebook, sessionInformation } = pawnote;
    const qrData = JSON.parse(qrDataJson);
    
    // Envoyer les messages de debug sur stderr
    console.error('🔄 Connexion QR code + Synchronisation PRONOTE');
    console.error(`   URL: ${qrData.url}`);
    console.error(`   Login: ${qrData.login}`);
    console.error(`   PIN: ${pin}`);
    console.error();
    
    // Créer session
    const session = createSessionHandle();
    
    // Se connecter avec QR code
    console.error('🔐 Connexion avec QR code...');
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
    
    console.error('✅ Connexion réussie !');
    console.error(`   Username: ${refreshInfo.username}`);
    console.error(`   Token: ${refreshInfo.token.substring(0, 30)}...`);
    console.error();
    
    // Utiliser la session immédiatement pour récupérer les données
    console.error('📚 Récupération des devoirs...');
    const today = new Date();
    const nextWeek = new Date(today);
    nextWeek.setDate(today.getDate() + 14); // 2 semaines
    const assignments = await assignmentsFromIntervals(session, today, nextWeek);
    console.error(`   ✅ ${assignments.length} devoirs récupérés`);
    
    if (assignments.length > 0) {
      console.error('   Exemples:');
      assignments.slice(0, 3).forEach((hw: any, i: number) => {
        const subject = hw.subject?.name || 'N/A';
        const date = hw.date ? new Date(hw.date).toLocaleDateString('fr-FR') : 'N/A';
        const done = hw.done ? '✓' : '○';
        console.error(`      ${i + 1}. ${done} ${subject} - ${date}`);
      });
    }
    console.error();
    
    // Récupérer les cours
    console.error('📅 Récupération des cours...');
    const timetable = await timetableFromIntervals(session, today, nextWeek);
    const lessonsCount = timetable?.classes?.length || 0;
    console.error(`   ✅ ${lessonsCount} cours récupérés`);
    
    if (lessonsCount > 0) {
      console.error('   Exemples:');
      timetable.classes.slice(0, 5).forEach((lesson: any, i: number) => {
        const subject = lesson.subject?.name || 'N/A';
        const start = lesson.start ? new Date(lesson.start).toLocaleString('fr-FR') : 'N/A';
        const room = lesson.room || '';
        console.error(`      ${i + 1}. ${subject} - ${start} ${room ? `(${room})` : ''}`);
      });
    }
    console.error();
    
    // Récupérer sessionInformation une seule fois (nécessaire pour evaluations, gradebook, notebook)
    let sessionInfo = null;
    try {
      sessionInfo = await sessionInformation(session);
    } catch (e: any) {
      console.error(`   ⚠️  Impossible de récupérer sessionInformation: ${e.message}`);
    }
    
    // Récupérer les évaluations/notes
    console.error('📊 Récupération des évaluations...');
    let evaluationsData = [];
    let gradebookData = null;
    if (sessionInfo) {
      try {
        evaluationsData = await evaluations(session, sessionInfo) || [];
        console.error(`   ✅ ${evaluationsData.length} évaluations récupérées`);
      } catch (e: any) {
        console.error(`   ⚠️  Erreur lors de la récupération des évaluations: ${e.message}`);
      }
    } else {
      console.error(`   ⚠️  SessionInformation non disponible`);
    }
    console.error();
    
    // Récupérer le bulletin de notes
    console.error('📚 Récupération du bulletin de notes...');
    if (sessionInfo) {
      try {
        gradebookData = await gradebook(session, sessionInfo);
        console.error(`   ✅ Bulletin de notes récupéré`);
      } catch (e: any) {
        console.error(`   ⚠️  Erreur lors de la récupération du bulletin: ${e.message}`);
      }
    } else {
      console.error(`   ⚠️  SessionInformation non disponible`);
    }
    console.error();
    
    // Récupérer le carnet de correspondance
    console.error('📔 Récupération du carnet de correspondance...');
    let notebookData = [];
    if (sessionInfo) {
      try {
        const notebookResult = await notebook(session, sessionInfo);
        if (Array.isArray(notebookResult)) {
          notebookData = notebookResult;
        } else if (notebookResult && typeof notebookResult === 'object') {
          // Si c'est un objet, essayer d'extraire les messages
          notebookData = (notebookResult as any).messages || (notebookResult as any).observations || [];
        }
        console.error(`   ✅ ${notebookData.length} messages récupérés`);
      } catch (e: any) {
        console.error(`   ⚠️  Erreur lors de la récupération du carnet: ${e.message}`);
      }
    } else {
      console.error(`   ⚠️  SessionInformation non disponible`);
    }
    console.error();
    
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
        assignments: assignments.map((hw: any) => ({
          id: hw.id,
          subject: hw.subject?.name || 'N/A',
          description: hw.description || '',
          date: hw.date ? new Date(hw.date).toISOString().split('T')[0] : null,
          done: hw.done || false
        })),
        lessons_list: timetable?.classes?.map((lesson: any) => ({
          id: lesson.id,
          subject: lesson.subject?.name || 'N/A',
          room: lesson.room || '',
          start: lesson.start ? new Date(lesson.start).toISOString() : null,
          end: lesson.end ? new Date(lesson.end).toISOString() : null,
          teacher: lesson.teacher?.name || lesson.teacher || null,
          group: lesson.group?.name || lesson.group || null,
          raw: lesson
        })) || [],
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
        notebook: notebookData.map((msg: any) => ({
          id: msg.id,
          date: msg.date ? new Date(msg.date).toISOString().split('T')[0] : null,
          author: msg.author?.name || msg.author || 'N/A',
          content: msg.content || msg.text || '',
          kind: msg.kind || msg.type || null,
          raw: msg
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
    console.error('✅ Synchronisation complète réussie !');
    console.error();
    console.error('📊 Résumé:');
    console.error(`   📚 Devoirs: ${result.data.homework}`);
    console.error(`   📅 Cours: ${result.data.lessons}`);
    console.error(`   📊 Évaluations: ${result.data.evaluations?.length || 0}`);
    console.error(`   📚 Bulletin: ${result.data.gradebook ? 'Oui' : 'Non'}`);
    console.error(`   📔 Carnet: ${result.data.notebook?.length || 0} messages`);
    console.error();
    
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

