<?php

namespace App\Controller\Parent;

use App\Controller\BaseController;
use App\Entity\ParentUser;
use App\Repository\ParentUserRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PARENT')]
abstract class BaseParentController extends BaseController
{
    public function __construct(
        private ParentUserRepository $parentUserRepository
    ) {}

    protected function getParent(): ParentUser
    {
        $user = $this->getUser();
        if (!$user instanceof ParentUser) {
            throw new \Exception('User is not a parent');
        }
        return $user;
    }
}