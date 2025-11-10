#!/usr/bin/env node
/**
 * Script de synchronisation PRONOTE avec token existant (Pawnote.js)
 * Usage: node fetch-data.mjs '<credentials_json>'
 */

import { createSessionHandle, loginToken, AccountKind, assignmentsFromIntervals, timetableFromIntervals, evaluations, gradebook, notebook, sessionInformation } from 'pawnote';

async function fetchPronoteData(credentialsJson: string) {
  try {
    const credentials = JSON.parse(credentialsJson);
    
    console.error('🔍 Credentials reçus:');
    console.error(`   Username: ${credentials.username || 'N/A'}`);
    console.error(`   Has refresh_info: ${!!credentials.refresh_info}`);
    console.error();
    
    const session = createSessionHandle();
    
    // Se connecter avec le token
    let refreshInfo;
    if (credentials.refresh_info) {
      // Format Pawnote.js avec refresh_info
      console.error('🔐 Connexion avec refresh_info...');
      refreshInfo = await loginToken(session, {
        kind: credentials.refresh_info.kind || AccountKind.STUDENT,
        url: credentials.refresh_info.url,
        username: credentials.refresh_info.username,
        token: credentials.refresh_info.token,
        deviceUUID: credentials.deviceUUID || credentials.uuid || credentials.username
      });
    } else if (credentials.password && credentials.password.length > 50) {
      // Format legacy (compatible)
      console.error('🔐 Connexion avec token (format legacy)...');
      const baseUrl = credentials.base_url || credentials.pronote_url || '';
      const cleanUrl = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
      refreshInfo = await loginToken(session, {
        kind: credentials.kind || AccountKind.STUDENT,
        url: cleanUrl,
        username: credentials.username,
        token: credentials.password,
        deviceUUID: credentials.uuid || credentials.username
      });
    } else {
      throw new Error('Token manquant ou invalide dans les credentials');
    }
    
    console.error('✅ Connexion réussie !');
    console.error(`   Username: ${refreshInfo.username}`);
    console.error(`   Next Token: ${refreshInfo.token.substring(0, 30)}...`);
    console.error();
    
    // Récupérer les devoirs (sur 2 semaines)
    console.error('📚 Récupération des devoirs...');
    const today = new Date();
    const nextWeek = new Date(today);
    nextWeek.setDate(today.getDate() + 14);
    const assignments = await assignmentsFromIntervals(session, today, nextWeek);
    console.error(`   ✅ ${assignments.length} devoirs récupérés`);
    
    // Récupérer les cours (sur 2 semaines)
    console.error('📅 Récupération des cours...');
    const timetable = await timetableFromIntervals(session, today, nextWeek);
    const lessonsCount = timetable?.classes?.length || 0;
    console.error(`   ✅ ${lessonsCount} cours récupérés`);
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
        console.error(`   ⚠️  Erreur: ${e.message}`);
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
        console.error(`   ✅ Bulletin récupéré`);
      } catch (e: any) {
        console.error(`   ⚠️  Erreur: ${e.message}`);
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
          notebookData = (notebookResult as any).messages || (notebookResult as any).observations || [];
        }
        console.error(`   ✅ ${notebookData.length} messages récupérés`);
      } catch (e: any) {
        console.error(`   ⚠️  Erreur: ${e.message}`);
      }
    } else {
      console.error(`   ⚠️  SessionInformation non disponible`);
    }
    console.error();
    
    // Construire le résultat
    const result = {
      success: true,
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
        evaluations: evaluationsData.map((evaluation: any) => ({
          id: evaluation.id,
          name: evaluation.name || evaluation.subject?.name || 'N/A',
          subject: evaluation.subject?.name || 'N/A',
          date: evaluation.date ? new Date(evaluation.date).toISOString().split('T')[0] : null,
          coefficient: evaluation.coefficient || null,
          average: evaluation.average || null,
          raw: evaluation
        })),
        gradebook: gradebookData ? {
          periods: gradebookData.periods || [],
          subjects: gradebookData.subjects || [],
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
    
    // Afficher le JSON sur stdout (pour PHP)
    console.log(JSON.stringify(result, null, 2));
    
    return result;
    
  } catch (error: any) {
    const errorMsg = error?.message || String(error);
    console.error(`❌ Erreur: ${errorMsg}`);
    
    const errorResult = {
      success: false,
      error: errorMsg,
      traceback: error?.stack,
      needs_qr_code: errorMsg.toLowerCase().includes('challenge') || 
                     errorMsg.toLowerCase().includes('expired') ||
                     errorMsg.toLowerCase().includes('unable to resolve')
    };
    
    // Afficher le JSON sur stdout (pour PHP)
    console.log(JSON.stringify(errorResult, null, 2));
    process.exit(1);
  }
}

// Main
const args = process.argv.slice(2);
if (args.length < 1) {
  console.error('❌ Erreur: Aucun credential fourni');
  console.error('Usage: node fetch-data.mjs \'<credentials_json>\'');
  process.exit(1);
}

const credentialsJson = args[0];
fetchPronoteData(credentialsJson);
