<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Message;
use App\Entity\Request;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\MessageRepository;
use App\Repository\RequestRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
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
        private readonly ValidatorInterface $validator,
        private readonly MessageRepository $messageRepository,
        private readonly HubInterface $hub
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
        $creatorProfile = $request->query->get('creatorProfile'); // 'parent', 'student', 'specialist', 'coach'
        $creatorUserId = $request->query->get('creatorUser'); // ID de l'utilisateur créateur

        // Récupération des demandes avec filtrage
        $requests = $this->requestRepository->findByCoachWithSearch(
            $coach,
            $search ?: null,
            $familyId ? (int) $familyId : null,
            $studentId ? (int) $studentId : null,
            $status ?: null,
            $specialistId ? (int) $specialistId : null,
            $creatorProfile ?: null,
            $creatorUserId ? (int) $creatorUserId : null
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
        
        // Récupérer tous les parents pour le filtre
        $parentsData = [];
        foreach ($families as $family) {
            $parent = $family->getParent();
            if ($parent) {
                $parentsData[] = [
                    'id' => $parent->getId(),
                    'firstName' => $parent->getFirstName(),
                    'lastName' => $parent->getLastName(),
                ];
            }
        }
        
        // Récupérer le coach pour le filtre
        $coachesData = [[
            'id' => $coach->getId(),
            'firstName' => $coach->getFirstName(),
            'lastName' => $coach->getLastName(),
        ]];

        return $this->render('tailadmin/pages/requests/list.html.twig', [
            'pageTitle' => 'Liste des Demandes | TailAdmin',
            'pageName' => 'Demandes',
            'requests' => $requestsData,
            'families' => $familiesData,
            'students' => $studentsData,
            'parents' => $parentsData,
            'specialists' => $specialistsData,
            'coaches' => $coachesData,
            'creatorProfileFilter' => $creatorProfile,
            'creatorUserFilter' => $creatorUserId,
            'familyFilter' => $familyId,
            'studentFilter' => $studentId,
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
        // Priorité par défaut : 'medium' si non fournie
        $requestEntity->setPriority($data['priority'] ?? 'medium');
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
        
        // Trier les messages par date de création (du plus récent au plus ancien)
        usort($messages, function($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        // Préparer les données des messages pour le template
        $messagesData = [];
        foreach ($messages as $message) {
            $sender = $message->getSender();
            $content = $message->getContent();
            $messagesData[] = [
                'id' => $message->getId(),
                'content' => $content ?: '', // S'assurer que content n'est jamais null
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

    #[Route('/admin/requests/{id}/messages/create', name: 'admin_requests_messages_create', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function createMessage(int $id, HttpRequest $request): JsonResponse
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

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return new JsonResponse(['success' => false, 'message' => 'Le contenu du message est requis'], 400);
        }

        // Déterminer le destinataire
        $receiver = $requestEntity->getCreator();
        if ($receiver === $coach) {
            $receiver = $requestEntity->getRecipient();
        }

        if (!$receiver) {
            return new JsonResponse(['success' => false, 'message' => 'Destinataire non trouvé'], 404);
        }

        // Générer ou récupérer le conversationId
        $conversationId = $this->messageRepository->findConversationBetweenUsers($coach, $receiver);
        if (!$conversationId) {
            $conversationId = $this->messageRepository->generateConversationId($coach, $receiver);
        }

        // Créer le message
        $message = Message::create([
            'content' => trim($data['content']),
            'conversationId' => $conversationId,
            'isRead' => false,
        ], $coach, $receiver);

        // Définir coach, recipient et request
        $message->setCoach($coach);
        $message->setRecipient($receiver);
        $message->setRequest($requestEntity);

        // Validation
        $errors = $this->validator->validate($message);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($message);
        $this->em->flush();

        // Publier le message via Mercure pour le temps réel (avec gestion d'erreur)
        try {
            $update = new Update(
                topics: ["/conversations/{$conversationId}", "/requests/{$id}/messages"],
                data: json_encode([
                    'id' => $message->getId(),
                    'conversationId' => $conversationId,
                    'requestId' => $id,
                    'content' => $message->getContent(),
                    'sender' => [
                        'id' => $coach->getId(),
                        'firstName' => $coach->getFirstName(),
                        'lastName' => $coach->getLastName(),
                    ],
                    'receiverId' => $receiver->getId(),
                    'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'isRead' => false,
                ]),
                private: true
            );
            $this->hub->publish($update);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'envoi du message
            error_log('Erreur Mercure: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => [
                'id' => $message->getId(),
                'conversationId' => $conversationId,
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}

