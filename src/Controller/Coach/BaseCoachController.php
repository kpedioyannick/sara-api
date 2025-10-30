<?php

namespace App\Controller\Coach;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COACH')]
abstract class BaseCoachController extends BaseController
{
    protected function checkCoachAccess(): void
    {
        $user = $this->getUser();
        
        if (!$user) {
            throw new AccessDeniedException('Authentication required');
        }
        
        if (!in_array('ROLE_COACH', $user->getRoles())) {
            throw new AccessDeniedException('Coach access required');
        }
    }

    protected function getCoach(): \App\Entity\Coach
    {
        $this->checkCoachAccess();
        return $this->getUser();
    }

    protected function successResponse($data = null, string $message = 'Success', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        $this->checkCoachAccess();
        return parent::successResponse($data, $message, $statusCode);
    }

    protected function errorResponse(string $message = 'Error', int $statusCode = Response::HTTP_BAD_REQUEST, array $errors = []): JsonResponse
    {
        $this->checkCoachAccess();
        return parent::errorResponse($message, $statusCode, $errors);
    }
}
