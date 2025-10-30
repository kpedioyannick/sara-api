<?php

namespace App\Controller\Specialist;

use App\Controller\BaseController;
use App\Entity\Specialist;
use App\Repository\SpecialistRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SPECIALIST')]
abstract class BaseSpecialistController extends BaseController
{
    public function __construct(
        private SpecialistRepository $specialistRepository
    ) {}

    protected function getSpecialist(): Specialist
    {
        $user = $this->getUser();
        if (!$user instanceof Specialist) {
            throw new \Exception('User is not a specialist');
        }
        return $user;
    }
}