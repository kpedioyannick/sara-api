<?php

namespace App\Controller\Student;

use App\Controller\BaseController;
use App\Entity\Student;
use App\Repository\StudentRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STUDENT')]
abstract class BaseStudentController extends BaseController
{
    public function __construct(
        private StudentRepository $studentRepository
    ) {}

    protected function getStudent(): Student
    {
        $user = $this->getUser();
        if (!$user instanceof Student) {
            throw new \Exception('User is not a student');
        }
        return $user;
    }
}