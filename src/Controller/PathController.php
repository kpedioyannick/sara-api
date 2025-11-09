<?php

namespace App\Controller;

use App\Entity\Path\Chapter;
use App\Entity\Path\Classroom;
use App\Entity\Path\Path;
use App\Entity\Path\Subject;
use App\Entity\Path\SubChapter;
use App\Enum\ModuleType;
use App\Message\GeneratePathMessage;
use App\Repository\ChapterRepository;
use App\Repository\ClassroomRepository;
use App\Repository\PathRepository;
use App\Repository\SubChapterRepository;
use App\Repository\SubjectRepository;
use App\Service\Path\PromptTypeMappingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PathController extends AbstractController
{
    public function __construct(
        private readonly PathRepository $pathRepository,
        private readonly ClassroomRepository $classroomRepository,
        private readonly SubjectRepository $subjectRepository,
        private readonly ChapterRepository $chapterRepository,
        private readonly SubChapterRepository $subChapterRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly PromptTypeMappingService $promptMappingService
    ) {
    }

    #[Route('/admin/paths', name: 'admin_paths_list')]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $type = $request->query->get('type');
        $status = $request->query->get('status');
        $classroomId = $request->query->getInt('classroomId', 0) ?: null;
        $subjectId = $request->query->getInt('subjectId', 0) ?: null;
        $chapterId = $request->query->getInt('chapterId', 0) ?: null;

        // Récupération de tous les parcours (tout le monde peut voir tous les parcours)
        $paths = $this->pathRepository->findAll();

        // Filtrage
        if ($search) {
            $paths = array_filter($paths, function($path) use ($search) {
                return stripos($path->getTitle(), $search) !== false 
                    || stripos($path->getDescription() ?? '', $search) !== false;
            });
        }

        if ($type) {
            $paths = array_filter($paths, fn($path) => $path->getType() === $type);
        }

        if ($status) {
            $paths = array_filter($paths, fn($path) => $path->getStatus() === $status);
        }

        if ($classroomId) {
            $paths = array_filter($paths, function($path) use ($classroomId) {
                $chapter = $path->getChapter();
                $subChapter = $path->getSubChapter();
                $subject = $chapter?->getSubject() ?? $subChapter?->getChapter()?->getSubject();
                return $subject && $subject->getClassroom()?->getId() === $classroomId;
            });
        }

        if ($subjectId) {
            $paths = array_filter($paths, function($path) use ($subjectId) {
                $chapter = $path->getChapter();
                $subChapter = $path->getSubChapter();
                $subject = $chapter?->getSubject() ?? $subChapter?->getChapter()?->getSubject();
                return $subject && $subject->getId() === $subjectId;
            });
        }

        if ($chapterId) {
            $paths = array_filter($paths, function($path) use ($chapterId) {
                return $path->getChapter()?->getId() === $chapterId 
                    || $path->getSubChapter()?->getChapter()?->getId() === $chapterId;
            });
        }

        // Conversion en tableau pour le template
        $pathsData = array_map(fn($path) => $path->toArray(), $paths);

        // Récupération des données pour les filtres
        $classrooms = array_map(fn($c) => $c->toArray(), $this->classroomRepository->findAll());
        $subjects = array_map(fn($s) => $s->toArray(), $this->subjectRepository->findAll());
        $chapters = array_map(fn($c) => $c->toArray(), $this->chapterRepository->findAll());

        return $this->render('tailadmin/pages/paths/list.html.twig', [
            'pageTitle' => 'Activités scolaires | TailAdmin',
            'pageName' => 'paths',
            'paths' => $pathsData,
            'classrooms' => $classrooms,
            'subjects' => $subjects,
            'chapters' => $chapters,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'classroomId' => $classroomId,
                'subjectId' => $subjectId,
                'chapterId' => $chapterId,
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Activités scolaires', 'url' => $this->generateUrl('admin_paths_list')],
            ],
        ]);
    }

    #[Route('/admin/paths/{id}', name: 'admin_paths_detail', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function detail(int $id): Response
    {
        $path = $this->pathRepository->find($id);
        if (!$path) {
            throw $this->createNotFoundException('Parcours non trouvé');
        }

        $pathData = $path->toArray();
        $modulesData = array_map(fn($m) => [
            'id' => $m->getId(),
            'title' => $m->getTitle(),
            'description' => $m->getDescription(),
            'type' => $m->getType()?->value,
            'order' => $m->getOrder(),
        ], $path->getModules()->toArray());

        return $this->render('tailadmin/pages/paths/detail.html.twig', [
            'pageTitle' => $path->getTitle() . ' | TailAdmin',
            'pageName' => 'paths',
            'path' => $pathData,
            'modules' => $modulesData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Activités scolaires', 'url' => $this->generateUrl('admin_paths_list')],
                ['label' => $path->getTitle(), 'url' => $this->generateUrl('admin_paths_detail', ['id' => $id])],
            ],
        ]);
    }

    #[Route('/admin/paths/create', name: 'admin_paths_create')]
    #[IsGranted('ROLE_USER')]
    public function create(): Response
    {
        $classrooms = array_map(fn($c) => $c->toArray(), $this->classroomRepository->findAll());
        $moduleTypes = array_map(fn($type) => [
            'value' => $type->value,
            'label' => $type->value,
        ], ModuleType::cases());

        return $this->render('tailadmin/pages/paths/create.html.twig', [
            'pageTitle' => 'Nouveau parcours scolaire | TailAdmin',
            'pageName' => 'paths',
            'classrooms' => $classrooms,
            'moduleTypes' => $moduleTypes,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Activités scolaires', 'url' => $this->generateUrl('admin_paths_list')],
                ['label' => 'Nouveau parcours', 'url' => $this->generateUrl('admin_paths_create')],
            ],
        ]);
    }

    #[Route('/admin/paths/generate', name: 'admin_paths_generate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generate(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non autorisé'], 401);
        }

        $data = json_decode($request->getContent(), true);
        
        // Validation des données
        $classroomId = $data['classroomId'] ?? null;
        $subjectId = $data['subjectId'] ?? null;
        $chapterId = $data['chapterId'] ?? null;
        $subChapterId = $data['subChapterId'] ?? null;
        $type = $data['type'] ?? null;
        $link = $data['link'] ?? null;
        $modules = $data['modules'] ?? [];

        if (!$type) {
            return new JsonResponse(['error' => 'Le type est requis'], 400);
        }

        // Récupération des entités
        $chapter = $chapterId ? $this->chapterRepository->find($chapterId) : null;
        $subChapter = $subChapterId ? $this->subChapterRepository->find($subChapterId) : null;

        if (!$chapter && !$subChapter) {
            return new JsonResponse(['error' => 'Chapitre ou sous-chapitre requis'], 400);
        }

        // Création du Path
        $path = new Path();
        $path->setType($type);
        $path->setCreatedBy($user);
        $path->setStatus(Path::STATUS_DRAFT);
        
        if ($subChapter) {
            $path->setSubChapter($subChapter);
            $path->setChapter($subChapter->getChapter());
            $path->setTitle('Parcours - ' . $subChapter->getChapter()->getName() . ' - ' . $subChapter->getName());
        } elseif ($chapter) {
            $path->setChapter($chapter);
            $path->setTitle('Parcours - ' . $chapter->getName());
        }

        $path->setDescription('Parcours généré pour le chapitre "' . ($chapter?->getName() ?? '') . '"' . ($subChapter ? ' et le sous-chapitre "' . $subChapter->getName() . '"' : ''));

        // Si type != h5p, sauvegarder le lien directement
        if ($type !== Path::TYPE_H5P) {
            if (!$link) {
                return new JsonResponse(['error' => 'Le lien est requis pour ce type'], 400);
            }
            $path->setContent($link);
            $path->setStatus(Path::STATUS_PUBLISHED);
            $this->em->persist($path);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'pathId' => $path->getId(),
                'status' => 'published',
                'message' => 'Parcours créé avec succès'
            ]);
        }

        // Si type = h5p, génération asynchrone
        if (empty($modules)) {
            return new JsonResponse(['error' => 'Au moins un module est requis pour les parcours H5P'], 400);
        }

        // Sauvegarder le path d'abord
        $this->em->persist($path);
        $this->em->flush();

        // Récupérer les prompts
        $chapterPrompts = $chapter?->getPrompts();
        $subChapterPrompts = $subChapter?->getPrompts();

        // Dispatch le message pour génération asynchrone
        $this->messageBus->dispatch(new GeneratePathMessage(
            $path->getId(),
            $modules,
            $chapterPrompts,
            $subChapterPrompts
        ));

        return new JsonResponse([
            'success' => true,
            'pathId' => $path->getId(),
            'status' => 'generating',
            'message' => 'Génération en cours...'
        ]);
    }

    #[Route('/admin/paths/{id}/status', name: 'admin_paths_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(int $id): JsonResponse
    {
        $path = $this->pathRepository->find($id);
        if (!$path) {
            return new JsonResponse(['error' => 'Parcours non trouvé'], 404);
        }

        return new JsonResponse([
            'id' => $path->getId(),
            'status' => $path->getStatus(),
            'type' => $path->getType(),
            'content' => $path->getContent(),
        ]);
    }

    // ========== ROUTES API POUR LES DÉPENDANCES ==========

    #[Route('/api/classrooms', name: 'api_classrooms_list', methods: ['GET'])]
    public function apiClassrooms(): JsonResponse
    {
        $classrooms = array_map(fn($c) => $c->toArray(), $this->classroomRepository->findAll());
        return new JsonResponse($classrooms);
    }

    #[Route('/api/classrooms/{id}/subjects', name: 'api_classroom_subjects', methods: ['GET'])]
    public function apiClassroomSubjects(int $id, Request $request): JsonResponse
    {
        $classroom = $this->classroomRepository->find($id);
        if (!$classroom) {
            return new JsonResponse(['error' => 'Classe non trouvée'], 404);
        }

        $subjects = array_map(fn($s) => $s->toArray(), $classroom->getSubjects()->toArray());
        return new JsonResponse($subjects);
    }

    #[Route('/api/subjects/{id}/chapters', name: 'api_subject_chapters', methods: ['GET'])]
    public function apiSubjectChapters(int $id): JsonResponse
    {
        $subject = $this->subjectRepository->find($id);
        if (!$subject) {
            return new JsonResponse(['error' => 'Matière non trouvée'], 404);
        }

        $chapters = array_map(function($chapter) {
            $data = $chapter->toArray();
            $data['prompts'] = $chapter->getPrompts();
            return $data;
        }, $subject->getChapters()->toArray());

        return new JsonResponse($chapters);
    }

    #[Route('/api/chapters/{id}/subchapters', name: 'api_chapter_subchapters', methods: ['GET'])]
    public function apiChapterSubchapters(int $id): JsonResponse
    {
        $chapter = $this->chapterRepository->find($id);
        if (!$chapter) {
            return new JsonResponse(['error' => 'Chapitre non trouvé'], 404);
        }

        $subChapters = array_map(function($subChapter) {
            $data = [
                'id' => $subChapter->getId(),
                'name' => $subChapter->getName(),
                'content' => $subChapter->getContent(),
                'prompts' => $subChapter->getPrompts(),
            ];
            return $data;
        }, $chapter->getSubChapters()->toArray());

        return new JsonResponse($subChapters);
    }

    #[Route('/api/module-types/{type}/prompt', name: 'api_module_type_prompt', methods: ['GET'])]
    public function apiModuleTypePrompt(string $type): JsonResponse
    {
        $moduleType = ModuleType::tryFrom($type);
        if (!$moduleType) {
            return new JsonResponse(['error' => 'Type de module invalide'], 400);
        }

        $expectedInputs = $moduleType->getExpectedInputs();
        return new JsonResponse([
            'goal' => $expectedInputs['goal'] ?? '',
            'systemMessage' => $expectedInputs['systemMessage'] ?? '',
            'instructions' => $expectedInputs['instructions'] ?? '',
            'outputFormat' => $expectedInputs['outputFormat'] ?? []
        ]);
    }

    #[Route('/api/prompt-types/mapping', name: 'api_prompt_types_mapping', methods: ['GET'])]
    public function apiPromptTypesMapping(): JsonResponse
    {
        $mappableTypes = $this->promptMappingService->getMappableTypes();
        $mapping = [];
        foreach ($mappableTypes as $promptType) {
            $moduleType = $this->promptMappingService->mapPromptTypeToModuleType($promptType);
            if ($moduleType) {
                $mapping[$promptType] = $moduleType->value;
            }
        }
        return new JsonResponse($mapping);
    }
}

