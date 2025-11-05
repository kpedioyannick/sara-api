<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Planning;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\PlanningRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PlanningController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly PlanningRepository $planningRepository,
        private readonly FamilyRepository $familyRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly StudentRepository $studentRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/planning', name: 'admin_planning_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(Request $request): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        // Récupérer la semaine demandée (par défaut semaine courante)
        $weekOffset = (int) $request->query->get('week', 0);
        
        // Calculer le début de la semaine (lundi)
        $currentDate = new \DateTime();
        if ($weekOffset != 0) {
            $currentDate->modify('+' . ($weekOffset * 7) . ' days');
        }
        $dayOfWeek = (int) $currentDate->format('w'); // 0 = dimanche, 1 = lundi, etc.
        $mondayOffset = $dayOfWeek == 0 ? -6 : -(($dayOfWeek - 1) % 7);
        $currentDate->modify($mondayOffset . ' days');
        
        $weekStart = \DateTimeImmutable::createFromMutable($currentDate);
        $weekStart = $weekStart->setTime(0, 0, 0);
        
        // Récupérer toutes les familles du coach
        $families = $this->familyRepository->findByCoachWithSearch($coach);
        
        // Récupérer tous les événements de toutes les familles pour la semaine
        $allEvents = [];
        foreach ($families as $family) {
            $events = $this->planningRepository->findByFamilyAndWeek($family, $weekStart);
            $allEvents = array_merge($allEvents, $events);
        }
        
        // Générer les 7 jours de la semaine
        $weekDays = [];
        $dayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->modify('+' . $i . ' days');
            $dayKey = $day->format('Y-m-d');
            
            $weekDays[] = [
                'date' => $dayKey,
                'name' => $dayNames[$i],
                'day' => $day->format('d'),
                'month' => $day->format('m'),
                'events' => [],
            ];
        }
        
        // Distribuer les événements dans les jours de la semaine
        foreach ($allEvents as $event) {
            $eventDate = $event->getStartDate();
            if ($eventDate) {
                $dayKey = $eventDate->format('Y-m-d');
                // Trouver le jour correspondant dans le tableau
                foreach ($weekDays as &$dayData) {
                    if ($dayData['date'] === $dayKey) {
                        $dayData['events'][] = $event->toTemplateArray();
                        break;
                    }
                }
                unset($dayData);
            }
        }
        
        $weekEnd = $weekStart->modify('+6 days');
        
        // Calculer les offsets pour la navigation
        $prevWeek = $weekOffset - 1;
        $nextWeek = $weekOffset + 1;
        
        // Formater la plage de la semaine
        $weekRange = $weekStart->format('d/m/Y') . ' - ' . $weekEnd->format('d/m/Y');
        
        // Récupérer tous les étudiants du coach pour les formulaires
        $students = $this->studentRepository->findByCoach($coach);
        $studentsData = array_map(fn($student) => [
            'id' => $student->getId(),
            'firstName' => $student->getFirstName(),
            'lastName' => $student->getLastName(),
        ], $students);

        return $this->render('tailadmin/pages/planning/list.html.twig', [
            'pageTitle' => 'Planning | TailAdmin',
            'pageName' => 'Planning',
            'weekDays' => $weekDays,
            'weekOffset' => $weekOffset,
            'weekRange' => $weekRange,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'weekStartFormatted' => $weekStart->format('d/m/Y'),
            'weekEndFormatted' => $weekEnd->format('d/m/Y'),
            'students' => $studentsData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/planning/create', name: 'admin_planning_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $planning = new Planning();
        
        if (isset($data['title'])) $planning->setTitle($data['title']);
        if (isset($data['description'])) $planning->setDescription($data['description']);
        if (isset($data['type'])) $planning->setType($data['type']);
        if (isset($data['status'])) $planning->setStatus($data['status']);
        if (isset($data['startDate'])) {
            $planning->setStartDate(new \DateTimeImmutable($data['startDate']));
        }
        if (isset($data['endDate'])) {
            $planning->setEndDate(new \DateTimeImmutable($data['endDate']));
        }
        if (isset($data['studentId'])) {
            $student = $this->studentRepository->find($data['studentId']);
            if ($student) {
                $planning->setStudent($student);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 400);
            }
        }

        // Validation
        $errors = $this->validator->validate($planning);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($planning);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $planning->getId(), 'message' => 'Événement créé avec succès']);
    }

    #[Route('/admin/planning/{id}/update', name: 'admin_planning_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $planning = $this->planningRepository->find($id);
        if (!$planning) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $planning->setTitle($data['title']);
        if (isset($data['description'])) $planning->setDescription($data['description']);
        if (isset($data['type'])) $planning->setType($data['type']);
        if (isset($data['status'])) $planning->setStatus($data['status']);
        if (isset($data['startDate'])) {
            $planning->setStartDate(new \DateTimeImmutable($data['startDate']));
        }
        if (isset($data['endDate'])) {
            $planning->setEndDate(new \DateTimeImmutable($data['endDate']));
        }

        // Validation
        $errors = $this->validator->validate($planning);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Événement modifié avec succès']);
    }

    #[Route('/admin/planning/{id}/delete', name: 'admin_planning_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $planning = $this->planningRepository->find($id);
        if (!$planning) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $this->em->remove($planning);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Événement supprimé avec succès']);
    }
}
