<?php

namespace App\Controller\Specialist;

use App\Entity\Objective;
use App\Entity\Comment;
use App\Repository\ObjectiveRepository;
use App\Repository\CommentRepository;
use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/specialist/objectives')]
class ObjectiveController extends BaseSpecialistController
{
    public function __construct(
        private ObjectiveRepository $objectiveRepository,
        private CommentRepository $commentRepository,
        private ValidatorInterface $validator,
        private SpecialistRepository $specialistRepository
    ) {
        parent::__construct($specialistRepository);
    }

    #[Route('', name: 'specialist_objectives_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        $studentId = $request->query->get('student_id');
        $status = $request->query->get('status');
        
        $objectives = [];
        
        foreach ($students as $student) {
            if ($studentId && $student->getId() != $studentId) {
                continue;
            }
            
            $studentObjectives = $student->getObjectives();
            foreach ($studentObjectives as $objective) {
                if ($status && $objective->getStatus() !== $status) {
                    continue;
                }
                $objectives[] = $objective->toArray();
            }
        }

        return $this->successResponse($objectives, 'Objectives retrieved successfully');
    }

    #[Route('/{id}', name: 'specialist_objectives_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient à un étudiant assigné au spécialiste
        $student = $objective->getStudent();
        if (!$student || !$students->contains($student)) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($objective->toArray(), 'Objective retrieved successfully');
    }

    #[Route('/{id}/tasks', name: 'specialist_objectives_tasks', methods: ['GET'])]
    public function getObjectiveTasks(int $id): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient à un étudiant assigné au spécialiste
        $student = $objective->getStudent();
        if (!$student || !$students->contains($student)) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        $tasks = $objective->getTasks();
        $tasksData = array_map(fn($task) => $task->toArray(), $tasks->toArray());

        return $this->successResponse($tasksData, 'Objective tasks retrieved successfully');
    }

    #[Route('/{id}/comments', name: 'specialist_objectives_add_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            $students = $specialist->getStudents();
            
            $objective = $this->objectiveRepository->find($id);
            
            if (!$objective) {
                return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'objectif appartient à un étudiant assigné au spécialiste
            $student = $objective->getStudent();
            if (!$student || !$students->contains($student)) {
                return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['content']) || empty($data['content'])) {
                return $this->errorResponse('Content is required', Response::HTTP_BAD_REQUEST);
            }

            $comment = Comment::createForCoach($data, $specialist, $objective);
            $this->commentRepository->save($comment, true);

            return $this->successResponse($comment->toArray(), 'Comment added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Comment creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
