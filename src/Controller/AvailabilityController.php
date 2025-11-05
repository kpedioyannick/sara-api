<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Availability;
use App\Repository\AvailabilityRepository;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\ParentUserRepository;
use App\Repository\SpecialistRepository;
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

class AvailabilityController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly AvailabilityRepository $availabilityRepository,
        private readonly FamilyRepository $familyRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly SpecialistRepository $specialistRepository,
        private readonly ParentUserRepository $parentRepository,
        private readonly StudentRepository $studentRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/availabilities', name: 'admin_availabilities_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(Request $request): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        // Récupérer toutes les disponibilités du coach
        $coachAvailabilities = $this->availabilityRepository->findByCoach($coach);
        
        // Récupérer les familles du coach pour accéder aux disponibilités des parents et élèves
        $families = $this->familyRepository->findByCoachWithSearch($coach);
        
        // Organiser les disponibilités par jour de la semaine
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        
        // Initialiser les jours de la semaine avec des créneaux vides
        $weekDays = [];
        $startHour = 8;
        $endHour = 19;
        
        foreach ($days as $dayName) {
            $slots = [];
            for ($hour = $startHour; $hour < $endHour; $hour++) {
                $slots[] = [
                    'time' => sprintf('%02d:00', $hour),
                    'selected' => false,
                ];
            }
            $weekDays[$dayName] = [
                'day' => $dayName,
                'slots' => $slots,
            ];
        }
        
        // Traiter les disponibilités du coach
        foreach ($coachAvailabilities as $availability) {
            $dayName = $availability->getDayName();
            
            if ($dayName && isset($weekDays[$dayName])) {
                $timeRange = $availability->getTimeRange();
                
                if ($timeRange['start'] !== null && $timeRange['end'] !== null) {
                    // Marquer les créneaux comme sélectionnés
                    foreach ($weekDays[$dayName]['slots'] as &$slot) {
                        $slotHour = (int) substr($slot['time'], 0, 2);
                        if ($slotHour >= $timeRange['start'] && $slotHour < $timeRange['end']) {
                            $slot['selected'] = true;
                            $slot['id'] = $availability->getId();
                            $slot['ownerType'] = 'coach';
                        }
                    }
                    unset($slot);
                }
            }
        }
        
        // Traiter les disponibilités des parents et élèves des familles
        foreach ($families as $family) {
            $parent = $family->getParent();
            if ($parent) {
                $parentAvailabilities = $this->availabilityRepository->findByParent($parent);
                foreach ($parentAvailabilities as $availability) {
                    $dayName = $availability->getDayName();
                    
                    if ($dayName && isset($weekDays[$dayName])) {
                        $timeRange = $availability->getTimeRange();
                        
                        if ($timeRange['start'] !== null && $timeRange['end'] !== null) {
                            foreach ($weekDays[$dayName]['slots'] as &$slot) {
                                $slotHour = (int) substr($slot['time'], 0, 2);
                                if ($slotHour >= $timeRange['start'] && $slotHour < $timeRange['end']) {
                                    $slot['selected'] = true;
                                    $slot['id'] = $availability->getId();
                                    $slot['ownerType'] = 'parent';
                                }
                            }
                            unset($slot);
                        }
                    }
                }
            }
            
            // Traiter les disponibilités des élèves
            foreach ($family->getStudents() as $student) {
                $studentAvailabilities = $this->availabilityRepository->findByStudent($student);
                foreach ($studentAvailabilities as $availability) {
                    $dayName = $availability->getDayName();
                    
                    if ($dayName && isset($weekDays[$dayName])) {
                        $timeRange = $availability->getTimeRange();
                        
                        if ($timeRange['start'] !== null && $timeRange['end'] !== null) {
                            foreach ($weekDays[$dayName]['slots'] as &$slot) {
                                $slotHour = (int) substr($slot['time'], 0, 2);
                                if ($slotHour >= $timeRange['start'] && $slotHour < $timeRange['end']) {
                                    $slot['selected'] = true;
                                    $slot['id'] = $availability->getId();
                                    $slot['ownerType'] = 'student';
                                }
                            }
                            unset($slot);
                        }
                    }
                }
            }
        }
        
        // Convertir en tableau indexé numériquement pour le template
        $weekDaysArray = array_values($weekDays);
        
        // Récupérer les disponibilités existantes pour le template
        $allAvailabilities = $this->availabilityRepository->findByCoach($coach);
        foreach ($families as $family) {
            $parent = $family->getParent();
            if ($parent) {
                $allAvailabilities = array_merge($allAvailabilities, $this->availabilityRepository->findByParent($parent));
            }
            foreach ($family->getStudents() as $student) {
                $allAvailabilities = array_merge($allAvailabilities, $this->availabilityRepository->findByStudent($student));
            }
        }
        
        $availabilitiesData = array_map(function($av) {
            return [
                'id' => $av->getId(),
                'dayOfWeek' => $av->getDayOfWeek(),
                'startTime' => $av->getStartTime()?->format('H:i'),
                'endTime' => $av->getEndTime()?->format('H:i'),
                'ownerType' => $av->getCoach() ? 'coach' : ($av->getSpecialist() ? 'specialist' : ($av->getParent() ? 'parent' : 'student')),
            ];
        }, $allAvailabilities);
        
        // Récupérer les étudiants et parents pour les formulaires
        $students = [];
        $parents = [];
        foreach ($families as $family) {
            $parent = $family->getParent();
            if ($parent) {
                $parents[] = [
                    'id' => $parent->getId(),
                    'firstName' => $parent->getFirstName(),
                    'lastName' => $parent->getLastName(),
                ];
            }
            foreach ($family->getStudents() as $student) {
                $students[] = [
                    'id' => $student->getId(),
                    'firstName' => $student->getFirstName(),
                    'lastName' => $student->getLastName(),
                ];
            }
        }
        
        $specialists = $this->specialistRepository->findAll();
        $specialistsData = array_map(fn($s) => [
            'id' => $s->getId(),
            'firstName' => $s->getFirstName(),
            'lastName' => $s->getLastName(),
        ], $specialists);

        return $this->render('tailadmin/pages/availabilities/list.html.twig', [
            'pageTitle' => 'Gestion des Disponibilités | TailAdmin',
            'pageName' => 'Disponibilités',
            'weekDays' => $weekDaysArray,
            'availabilities' => $availabilitiesData,
            'students' => $students,
            'parents' => $parents,
            'specialists' => $specialistsData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/availabilities/create', name: 'admin_availabilities_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $availability = new Availability();
        
        if (isset($data['dayOfWeek'])) $availability->setDayOfWeek($data['dayOfWeek']);
        if (isset($data['startTime'])) {
            $availability->setStartTime(new \DateTimeImmutable($data['startTime']));
        }
        if (isset($data['endTime'])) {
            $availability->setEndTime(new \DateTimeImmutable($data['endTime']));
        }
        
        if (isset($data['coachId']) && $data['coachId']) {
            $availability->setCoach($coach);
        } elseif (isset($data['specialistId']) && $data['specialistId']) {
            $specialist = $this->specialistRepository->find($data['specialistId']);
            if ($specialist) $availability->setSpecialist($specialist);
        } elseif (isset($data['parentId']) && $data['parentId']) {
            $parent = $this->parentRepository->find($data['parentId']);
            if ($parent) {
                $availability->setParent($parent);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Parent non trouvé'], 400);
            }
        } elseif (isset($data['studentId']) && $data['studentId']) {
            $student = $this->studentRepository->find($data['studentId']);
            if ($student) {
                $availability->setStudent($student);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 400);
            }
        }

        // Validation
        $errors = $this->validator->validate($availability);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($availability);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $availability->getId(), 'message' => 'Disponibilité créée avec succès']);
    }

    #[Route('/admin/availabilities/{id}/update', name: 'admin_availabilities_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $availability = $this->availabilityRepository->find($id);
        if (!$availability) {
            return new JsonResponse(['success' => false, 'message' => 'Disponibilité non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['dayOfWeek'])) $availability->setDayOfWeek($data['dayOfWeek']);
        if (isset($data['startTime'])) {
            $availability->setStartTime(new \DateTimeImmutable($data['startTime']));
        }
        if (isset($data['endTime'])) {
            $availability->setEndTime(new \DateTimeImmutable($data['endTime']));
        }

        // Validation
        $errors = $this->validator->validate($availability);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Disponibilité modifiée avec succès']);
    }

    #[Route('/admin/availabilities/{id}/delete', name: 'admin_availabilities_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $availability = $this->availabilityRepository->find($id);
        if (!$availability) {
            return new JsonResponse(['success' => false, 'message' => 'Disponibilité non trouvée'], 404);
        }

        $this->em->remove($availability);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Disponibilité supprimée avec succès']);
    }
}
