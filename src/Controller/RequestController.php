<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Request;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\RequestRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly RequestRepository $requestRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly FamilyRepository $familyRepository,
        private readonly StudentRepository $studentRepository,
        private readonly SpecialistRepository $specialistRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/requests', name: 'admin_requests_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(HttpRequest $request): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $familyId = $request->query->get('family');
        $studentId = $request->query->get('student');
        $status = $request->query->get('status');
        $specialistId = $request->query->get('specialist');

        // Récupération des demandes avec filtrage
        $requests = $this->requestRepository->findByCoachWithSearch(
            $coach,
            $search ?: null,
            $familyId ? (int) $familyId : null,
            $studentId ? (int) $studentId : null,
            $status ?: null,
            $specialistId ? (int) $specialistId : null
        );

        // Conversion en tableau pour le template
        $requestsData = array_map(fn($request) => $request->toTemplateArray(), $requests);
        
        // Récupérer les données pour les formulaires
        $families = $this->familyRepository->findByCoachWithSearch($coach);
        $familiesData = array_map(fn($family) => [
            'id' => $family->getId(),
            'identifier' => $family->getFamilyIdentifier(),
        ], $families);
        
        $students = $this->studentRepository->findByCoach($coach);
        $studentsData = array_map(fn($student) => [
            'id' => $student->getId(),
            'firstName' => $student->getFirstName(),
            'lastName' => $student->getLastName(),
        ], $students);
        
        $specialists = $this->specialistRepository->findByWithSearch();
        $specialistsData = array_map(fn($specialist) => [
            'id' => $specialist->getId(),
            'firstName' => $specialist->getFirstName(),
            'lastName' => $specialist->getLastName(),
        ], $specialists);

        return $this->render('tailadmin/pages/requests/list.html.twig', [
            'pageTitle' => 'Liste des Demandes | TailAdmin',
            'pageName' => 'Demandes',
            'requests' => $requestsData,
            'families' => $familiesData,
            'students' => $studentsData,
            'specialists' => $specialistsData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/requests/create', name: 'admin_requests_create', methods: ['POST'])]
    public function create(HttpRequest $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $requestEntity = new Request();
        $requestEntity->setCoach($coach);
        $requestEntity->setCreator($coach);
        $requestEntity->setRecipient($coach);
        
        if (isset($data['title'])) $requestEntity->setTitle($data['title']);
        if (isset($data['description'])) $requestEntity->setDescription($data['description']);
        if (isset($data['type'])) $requestEntity->setType($data['type']);
        if (isset($data['status'])) $requestEntity->setStatus($data['status']);
        if (isset($data['priority'])) $requestEntity->setPriority($data['priority']);
        if (isset($data['familyId'])) {
            $family = $this->familyRepository->find($data['familyId']);
            if ($family) $requestEntity->setFamily($family);
        }
        if (isset($data['studentId'])) {
            $student = $this->studentRepository->find($data['studentId']);
            if ($student) {
                $requestEntity->setStudent($student);
                $requestEntity->setParent($student->getFamily()?->getParent());
            }
        }
        if (isset($data['specialistId'])) {
            $specialist = $this->specialistRepository->find($data['specialistId']);
            if ($specialist) $requestEntity->setSpecialist($specialist);
        }

        // Validation
        $errors = $this->validator->validate($requestEntity);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($requestEntity);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $requestEntity->getId(), 'message' => 'Demande créée avec succès']);
    }

    #[Route('/admin/requests/{id}/update', name: 'admin_requests_update', methods: ['POST'])]
    public function update(int $id, HttpRequest $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $requestEntity = $this->requestRepository->find($id);
        if (!$requestEntity || $requestEntity->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $requestEntity->setTitle($data['title']);
        if (isset($data['description'])) $requestEntity->setDescription($data['description']);
        if (isset($data['type'])) $requestEntity->setType($data['type']);
        if (isset($data['status'])) $requestEntity->setStatus($data['status']);
        if (isset($data['priority'])) $requestEntity->setPriority($data['priority']);
        if (isset($data['specialistId'])) {
            $specialist = $this->specialistRepository->find($data['specialistId']);
            if ($specialist) $requestEntity->setSpecialist($specialist);
        }

        // Validation
        $errors = $this->validator->validate($requestEntity);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Demande modifiée avec succès']);
    }

    #[Route('/admin/requests/{id}/delete', name: 'admin_requests_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $requestEntity = $this->requestRepository->find($id);
        if (!$requestEntity || $requestEntity->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }

        $this->em->remove($requestEntity);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Demande supprimée avec succès']);
    }

    #[Route('/admin/requests/{id}', name: 'admin_requests_detail')]
    #[IsGranted('ROLE_COACH')]
    public function detail(int $id): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        $requestEntity = $this->requestRepository->find($id);
        if (!$requestEntity || $requestEntity->getCoach() !== $coach) {
            throw $this->createNotFoundException('Demande non trouvée');
        }

        // Récupérer les messages de la requête
        $messages = $requestEntity->getMessages()->toArray();
        
        // Trier les messages par date de création
        usort($messages, function($a, $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        // Préparer les données des messages pour le template
        $messagesData = [];
        foreach ($messages as $message) {
            $sender = $message->getSender();
            $messagesData[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'isFromMe' => $sender === $coach,
                'sender' => [
                    'id' => $sender->getId(),
                    'firstName' => $sender->getFirstName(),
                    'lastName' => $sender->getLastName(),
                    'userType' => $sender->getUserType(),
                ],
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isRead' => $message->isRead(),
            ];
        }

        // Préparer les données de la requête
        $requestData = $requestEntity->toTemplateArray();
        $requestData['description'] = $requestEntity->getDescription();
        $requestData['response'] = $requestEntity->getResponse();

        // Déterminer l'autre utilisateur (celui avec qui on converse)
        $otherUser = $requestEntity->getCreator();
        if ($otherUser === $coach) {
            $otherUser = $requestEntity->getRecipient();
        }

        return $this->render('tailadmin/pages/requests/detail.html.twig', [
            'pageTitle' => 'Détail de la Demande | TailAdmin',
            'pageName' => 'requests-detail',
            'request' => $requestData,
            'messages' => $messagesData,
            'requestId' => $id,
            'otherUser' => $otherUser ? [
                'id' => $otherUser->getId(),
                'firstName' => $otherUser->getFirstName(),
                'lastName' => $otherUser->getLastName(),
                'email' => $otherUser->getEmail(),
                'userType' => $otherUser->getUserType(),
            ] : null,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Demandes', 'url' => $this->generateUrl('admin_requests_list')],
                ['label' => 'Détail', 'url' => ''],
            ],
        ]);
    }
}

