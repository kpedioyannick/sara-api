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
use App\Service\FileStorageService;
use App\Service\NotificationService;
use App\Service\RequestAIService;
use App\Service\PermissionService;
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
        private readonly HubInterface $hub,
        private readonly FileStorageService $fileStorageService,
        private readonly RequestAIService $requestAIService,
        private readonly PermissionService $permissionService,
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Mappe le titre de la demande vers son type selon le rôle de l'utilisateur
     */
    private function mapTitleToType(string $title, string $userRole): string
    {
        $titleToTypeMapping = [
            'coach' => [
                'Demande d\'aide scolaire pour un élève' => 'student_to_specialist',
                'Demande d\'échange avec un parent' => 'parent',
                'Demande d\'échange avec un élève' => 'student',
                'Demande d\'échange avec un spécialiste' => 'specialist',
            ],
            'parent' => [
                'Demande d\'aide scolaire pour mon enfant' => 'student_help',
                'Demande d\'échange avec mon coach' => 'coach',
            ],
            'specialist' => [
                'Demande d\'échange avec un élève' => 'student',
                'Demande d\'échange avec mon coach' => 'coach',
            ],
            'student' => [
                'Demande d\'échange avec un spécialiste' => 'specialist',
                'Demande d\'échange avec mon coach' => 'coach',
            ],
        ];

        return $titleToTypeMapping[$userRole][$title] ?? 'general';
    }

    /**
     * Détermine le rôle de l'utilisateur pour le mapping
     */
    private function getUserRole(\App\Entity\User $user): string
    {
        if ($user instanceof \App\Entity\Coach) {
            return 'coach';
        } elseif ($user instanceof \App\Entity\ParentUser) {
            return 'parent';
        } elseif ($user instanceof \App\Entity\Specialist) {
            return 'specialist';
        } elseif ($user instanceof \App\Entity\Student) {
            return 'student';
        }
        return 'coach'; // Fallback
    }

    #[Route('/admin/requests', name: 'admin_requests_list')]
    #[IsGranted('ROLE_USER')]
    public function list(HttpRequest $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $familyId = $request->query->get('family');
        $studentId = $request->query->get('student');
        $status = $request->query->get('status');
        $specialistId = $request->query->get('specialist');
        $profileType = $request->query->get('profileType'); // 'parent', 'specialist', 'student', ou null
        $selectedIds = $request->query->get('selectedIds', ''); // IDs séparés par des virgules

        // Récupération des demandes selon le rôle de l'utilisateur
        if ($user->isCoach()) {
            // Si l'utilisateur est un coach, utiliser directement l'utilisateur connecté
            $coach = $user instanceof \App\Entity\Coach ? $user : $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                throw $this->createAccessDeniedException('Vous devez être un coach pour accéder à cette page');
            }
            $requests = $this->requestRepository->findByCoachWithSearch(
                $coach,
                $search ?: null,
                $familyId ? (int) $familyId : null,
                $studentId ? (int) $studentId : null,
                $status ?: null,
                $specialistId ? (int) $specialistId : null
            );
        } else {
            // Pour les autres rôles, utiliser PermissionService
            $requests = $this->permissionService->getAccessibleRequests($user);
            // Filtrer par recherche si nécessaire
            if ($search) {
                $requests = array_filter($requests, function($req) use ($search) {
                    return stripos($req->getTitle(), $search) !== false 
                        || stripos($req->getDescription(), $search) !== false;
                });
            }
            // Filtrer par statut si nécessaire
            if ($status) {
                $requests = array_filter($requests, function($req) use ($status) {
                    return $req->getStatus() === $status;
                });
            }
            // Pour les spécialistes : filtrer par spécialiste si spécifié
            if ($user->isSpecialist() && $specialistId) {
                $requests = array_filter($requests, function($req) use ($specialistId) {
                    return $req->getSpecialist() && $req->getSpecialist()->getId() == (int)$specialistId;
                });
            }
        }
        
        // Appliquer les filtres par profil si spécifiés (pour les coaches et les parents)
        if (($user->isCoach() || $user->isParent()) && $profileType && $selectedIds) {
            $ids = array_filter(array_map('intval', explode(',', $selectedIds)));
            if (!empty($ids)) {
                $filteredRequests = [];
                foreach ($requests as $request) {
                    $shouldInclude = false;
                    
                    if ($profileType === 'parent') {
                        // Filtrer par parent (demandes créées par le parent ou concernant ses enfants)
                        $creator = $request->getCreator();
                        if ($creator && $creator instanceof \App\Entity\ParentUser && in_array($creator->getId(), $ids)) {
                            $shouldInclude = true;
                        } else {
                            // Vérifier si la demande concerne un enfant du parent
                            $student = $request->getStudent();
                            if ($student && $student->getFamily() && $student->getFamily()->getParent()) {
                                if (in_array($student->getFamily()->getParent()->getId(), $ids)) {
                                    $shouldInclude = true;
                                }
                            }
                        }
                    } elseif ($profileType === 'specialist') {
                        // Filtrer par spécialiste (demandes assignées à ce spécialiste)
                        if ($request->getSpecialist() && in_array($request->getSpecialist()->getId(), $ids)) {
                            $shouldInclude = true;
                        }
                    } elseif ($profileType === 'student') {
                        // Filtrer par élève (demandes créées par cet élève ou qui le concernent)
                        $creator = $request->getCreator();
                        if ($creator && $creator instanceof \App\Entity\Student && in_array($creator->getId(), $ids)) {
                            $shouldInclude = true;
                        } else {
                            // Vérifier si la demande concerne cet élève
                            $student = $request->getStudent();
                            if ($student && in_array($student->getId(), $ids)) {
                                $shouldInclude = true;
                            }
                            // Vérifier si l'élève est assigné à la demande
                            if ($request->getAssignedTo() && $request->getAssignedTo() instanceof \App\Entity\Student) {
                                if (in_array($request->getAssignedTo()->getId(), $ids)) {
                                    $shouldInclude = true;
                                }
                            }
                        }
                    }
                    
                    if ($shouldInclude) {
                        $filteredRequests[] = $request;
                    }
                }
                $requests = $filteredRequests;
            }
        }

        // Trier les demandes par date de création décroissante
        // Si c'est un tableau (pour les non-coaches), le trier manuellement
        if (is_array($requests) && !empty($requests)) {
            usort($requests, function($a, $b) {
                $dateA = $a->getCreatedAt();
                $dateB = $b->getCreatedAt();
                if ($dateA === null && $dateB === null) return 0;
                if ($dateA === null) return 1;
                if ($dateB === null) return -1;
                return $dateB <=> $dateA; // Ordre décroissant
            });
        }

        // Conversion en tableau pour le template
        $requestsData = array_map(fn($request) => $request->toTemplateArray(), $requests);
        
        // Récupérer les données pour les formulaires selon le rôle
        $familiesData = [];
        $studentsData = [];
        $specialistsData = [];
        $parentsData = [];
        $coachesData = [];

        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
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
                'pseudo' => $student->getPseudo(),
                'class' => $student->getClass(),
            ], $students);
            
            $specialists = $this->specialistRepository->findByWithSearch();
            $specialistsData = array_map(fn($specialist) => [
                'id' => $specialist->getId(),
                'firstName' => $specialist->getFirstName(),
                'lastName' => $specialist->getLastName(),
            ], $specialists);
            
            foreach ($families as $family) {
                $parent = $family->getParent();
                if ($parent) {
                    $parentsData[] = [
                        'id' => $parent->getId(),
                        'firstName' => $parent->getFirstName(),
                        'lastName' => $parent->getLastName(),
                        'email' => $parent->getEmail(),
                    ];
                }
            }
            
            $coachesData = [[
                'id' => $coach->getId(),
                'firstName' => $coach->getFirstName(),
                'lastName' => $coach->getLastName(),
            ]];
        } elseif ($user->isParent()) {
            // Pour les parents : récupérer leurs enfants pour les filtres
            $students = $this->permissionService->getAccessibleStudents($user);
            $studentsData = array_map(fn($student) => [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'pseudo' => $student->getPseudo(),
                'class' => $student->getClass(),
            ], $students);
        } else {
            // Pour les autres rôles, utiliser PermissionService
            $students = $this->permissionService->getAccessibleStudents($user);
            $studentsData = array_map(fn($student) => [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
            ], $students);
        }

        return $this->render('tailadmin/pages/requests/list.html.twig', [
            'pageTitle' => 'Liste des Demandes | TailAdmin',
            'pageName' => 'Demandes',
            'requests' => $requestsData,
            'families' => $familiesData,
            'students' => $studentsData,
            'parents' => $parentsData,
            'specialists' => $specialistsData,
            'coaches' => $coachesData,
            'familyFilter' => $familyId,
            'studentFilter' => $studentId,
            'profileType' => $profileType,
            'selectedIds' => $selectedIds,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/requests/create', name: 'admin_requests_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(HttpRequest $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        // Récupérer le coach (soit l'utilisateur connecté s'il est coach, soit le coach de la famille de l'élève)
        $coach = null;
        $creator = null;
        
        if ($user instanceof \App\Entity\Coach) {
            $coach = $user;
            $creator = $user;
        } else {
            // Pour les parents et autres rôles, récupérer le coach de la famille
            if (isset($data['studentId'])) {
                $student = $this->studentRepository->find($data['studentId']);
                if ($student) {
                    // Vérifier les permissions de création
                    if (!$this->permissionService->canCreateRequest($user, $student)) {
                        return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer une demande pour cet élève'], 403);
                    }
                    
                    $family = $student->getFamily();
                    if ($family && $family->getCoach()) {
                        $coach = $family->getCoach();
                    }
                }
            }
            
            // Si pas de coach trouvé, essayer de récupérer via getCurrentCoach
            if (!$coach) {
                $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            }
            
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }
            
            // Le créateur est l'utilisateur connecté (parent, étudiant, etc.)
            $creator = $user;
        }

        $requestEntity = new Request();
        $requestEntity->setCoach($coach);
        $requestEntity->setCreator($creator);
        $requestEntity->setRecipient($coach);
        
        if (isset($data['title'])) {
            $requestEntity->setTitle($data['title']);
            // Mapper le titre vers le type automatiquement
            $userRole = $this->getUserRole($user);
            $type = $this->mapTitleToType($data['title'], $userRole);
            $requestEntity->setType($type);
        }
        if (isset($data['description'])) $requestEntity->setDescription($data['description']);
        // Le type peut aussi être fourni directement (pour compatibilité avec l'ancien système)
        if (isset($data['type']) && !isset($data['title'])) {
            $requestEntity->setType($data['type']);
        }
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
        
        // Gérer l'assignation directe d'un parent (pour les coaches)
        if (isset($data['parentId']) && $user instanceof \App\Entity\Coach) {
            $parent = $this->em->getRepository(\App\Entity\ParentUser::class)->find($data['parentId']);
            if ($parent) {
                $requestEntity->setParent($parent);
            }
        }
        
        // Les parents ne peuvent pas choisir un spécialiste lors de la création
        // Seuls les coaches peuvent assigner un spécialiste
        if (isset($data['specialistId']) && $user instanceof \App\Entity\Coach) {
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

        // Notifier le coach si la demande a été créée par un parent/élève/spécialiste
        try {
            if ($creator->getId() !== $coach->getId()) {
                $this->notificationService->notifyRequestCreated($requestEntity, $creator);
            }
        } catch (\Exception $e) {
            error_log('Erreur notification demande créée: ' . $e->getMessage());
        }

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
    #[IsGranted('ROLE_USER')]
    public function detail(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        $requestEntity = $this->requestRepository->find($id);
        if (!$requestEntity) {
            throw $this->createNotFoundException('Demande non trouvée');
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewRequest($user, $requestEntity)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette demande');
        }

        // Pour les coaches, vérifier qu'ils sont bien le coach de la demande
        $coach = null;
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach || $requestEntity->getCoach() !== $coach) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette demande');
            }
        } else {
            // Pour les autres rôles, utiliser l'utilisateur connecté comme "coach" pour l'affichage
            $coach = $user;
        }

        // Récupérer les messages de la requête via une requête explicite (car fetch: 'EXTRA_LAZY')
        $messages = $this->messageRepository->createQueryBuilder('m')
            ->where('m.request = :request')
            ->setParameter('request', $requestEntity)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Préparer les données des messages pour le template
        $messagesData = [];
        foreach ($messages as $message) {
            $sender = $message->getSender();
            $content = $message->getContent();
            $messageType = $message->getType() ?? 'text';
            $filePath = $message->getFilePath() ? $this->fileStorageService->generateSecureUrl($message->getFilePath()) : null;
            
            $messagesData[] = [
                'id' => $message->getId(),
                'content' => $content ?: '',
                'type' => $messageType,
                'filePath' => $filePath,
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
    #[IsGranted('ROLE_USER')]
    public function createMessage(int $id, HttpRequest $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $requestEntity = $this->requestRepository->find($id);
        if (!$requestEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewRequest($user, $requestEntity)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cette demande'], 403);
        }

        // Récupérer le coach de la demande (toujours requis pour les messages)
        $coach = $requestEntity->getCoach();
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach de la demande non trouvé'], 404);
        }

        // Pour les coaches, vérifier qu'ils sont bien le coach de la demande
        $sender = $user; // L'expéditeur du message
        if ($user->isCoach()) {
            $currentCoach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$currentCoach || $requestEntity->getCoach() !== $currentCoach) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cette demande'], 403);
            }
            $sender = $currentCoach; // Pour les coaches, l'expéditeur est le coach
        }

        // Gérer les fichiers uploadés (FormData) ou JSON
        $messageType = 'text';
        $filePath = null;
        $content = null;

        if ($request->files->has('file')) {
            // Upload de fichier (image ou audio)
            $file = $request->files->get('file');
            $content = $request->request->get('content', '');

            // Déterminer le type de fichier
            $mimeType = $file->getMimeType();
            $originalName = $file->getClientOriginalName();
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            // Vérifier d'abord par extension si le MIME type n'est pas fiable
            if (str_starts_with($mimeType, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $messageType = 'image';
                $filePath = $this->fileStorageService->uploadFile($file, 'messages/images');
            } elseif (str_starts_with($mimeType, 'audio/') || in_array($extension, ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac'])) {
                $messageType = 'audio';
                $filePath = $this->fileStorageService->uploadFile($file, 'messages/audio');
            } else {
                return new JsonResponse([
                    'success' => false, 
                    'message' => 'Type de fichier non supporté. MIME type: ' . $mimeType . ', Extension: ' . $extension
                ], 400);
            }
        } else {
            // Message texte classique (JSON) ou fichier base64
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                $data = [];
            }

            $content = $data['content'] ?? null;
            $messageType = $data['type'] ?? 'text';

            // Si c'est un message avec fichier base64 (photo prise depuis l'appareil)
            if (isset($data['fileData']) && isset($data['fileType'])) {
                $fileType = $data['fileType']; // 'image' ou 'audio'
                $fileExtension = $data['fileExtension'] ?? ($fileType === 'image' ? 'jpg' : 'mp3');
                
                try {
                    $filePath = $this->fileStorageService->saveBase64File(
                        $data['fileData'],
                        $fileExtension,
                        'messages/' . ($fileType === 'image' ? 'images' : 'audio')
                    );
                    $messageType = $fileType;
                } catch (\Exception $e) {
                    return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier: ' . $e->getMessage()], 400);
                }
            }
        }

        // Validation : au moins un contenu ou un fichier
        if (empty(trim($content ?? '')) && !$filePath) {
            return new JsonResponse(['success' => false, 'message' => 'Le contenu du message ou un fichier est requis'], 400);
        }

        // Déterminer le destinataire
        $receiver = $requestEntity->getCreator();
        if ($receiver === $sender) {
            $receiver = $requestEntity->getRecipient();
        }

        if (!$receiver) {
            return new JsonResponse(['success' => false, 'message' => 'Destinataire non trouvé'], 404);
        }

        // Générer ou récupérer le conversationId
        $conversationId = $this->messageRepository->findConversationBetweenUsers($sender, $receiver);
        if (!$conversationId) {
            $conversationId = $this->messageRepository->generateConversationId($sender, $receiver);
        }

        // Créer le message
        $message = Message::create([
            'content' => $content ? trim($content) : null,
            'type' => $messageType,
            'filePath' => $filePath,
            'conversationId' => $conversationId,
            'isRead' => false,
        ], $sender, $receiver);

        // Définir coach, recipient et request
        // Le coach n'est défini que si l'expéditeur est un coach
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
        
        // Rafraîchir la requête pour que la relation soit à jour
        $this->em->refresh($requestEntity);

        // Notifier le créateur de la demande si le message vient du coach
        try {
            if ($sender->getId() !== $requestEntity->getCreator()?->getId()) {
                $this->notificationService->notifyRequestResponded($requestEntity, $sender);
            }
        } catch (\Exception $e) {
            error_log('Erreur notification réponse demande: ' . $e->getMessage());
        }

        // Publier le message via Mercure pour le temps réel (avec gestion d'erreur)
        try {
            $update = new Update(
                topics: ["/conversations/{$conversationId}", "/requests/{$id}/messages"],
                data: json_encode([
                    'id' => $message->getId(),
                    'conversationId' => $conversationId,
                    'requestId' => $id,
                    'content' => $message->getContent(),
                    'type' => $message->getType(),
                    'filePath' => $message->getFilePath() ? $this->fileStorageService->generateSecureUrl($message->getFilePath()) : null,
                    'sender' => [
                        'id' => $sender->getId(),
                        'firstName' => $sender->getFirstName(),
                        'lastName' => $sender->getLastName(),
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
                'type' => $message->getType(),
                'filePath' => $message->getFilePath() ? $this->fileStorageService->generateSecureUrl($message->getFilePath()) : null,
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Route('/admin/requests/{id}/ai-assist', name: 'admin_requests_ai_assist', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function aiAssist(int $id, HttpRequest $request): JsonResponse
    {
        try {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }

            $requestEntity = $this->requestRepository->find($id);
            if (!$requestEntity || $requestEntity->getCoach() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
            }

            $data = json_decode($request->getContent(), true);
            $selectedMessageIds = $data['selectedMessageIds'] ?? [];
            $userQuestion = $data['userQuestion'] ?? null;
            $additionalContext = $data['additionalContext'] ?? null;

            // Récupérer les messages sélectionnés
            $selectedMessages = [];
            if (!empty($selectedMessageIds)) {
                foreach ($selectedMessageIds as $messageId) {
                    $message = $this->messageRepository->find($messageId);
                    if ($message && $message->getRequest() === $requestEntity) {
                        $selectedMessages[] = $message;
                    }
                }
            }

            // Générer l'assistance IA
            try {
                $result = $this->requestAIService->generateAssistance(
                    $requestEntity,
                    $selectedMessages,
                    $userQuestion,
                    $additionalContext
                );

                return new JsonResponse($result);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la génération de l\'assistance : ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
}

