<?php

namespace App\Repository;

use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    public function save(Student $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Student $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Rechercher les élèves d'un coach
     */
    public function findByCoach($coach): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.family', 'f')
            ->addSelect('f')
            ->where('f.coach = :coach')
            ->setParameter('coach', $coach)
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher les élèves d'un coach avec critères de recherche
     */
    public function findByCoachWithSearch($coach, string $search = '', string $class = '', string $status = '', string $familyId = '', string $specialization = '', string $excludeSpecialistId = ''): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.family', 'f')
            ->leftJoin('f.parent', 'p')
            ->addSelect('f', 'p')
            ->where('f.coach = :coach')
            ->setParameter('coach', $coach);

        // Filtre par statut si fourni
        if (!empty($status)) {
            $isActive = $status === 'active';
            $qb->andWhere('s.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        // Filtre par classe si fournie
        if (!empty($class)) {
            $qb->andWhere('s.class = :class')
               ->setParameter('class', $class);
        }

        // Filtre par famille si fournie
        if (!empty($familyId)) {
            $qb->andWhere('f.id = :familyId')
               ->setParameter('familyId', $familyId);
        }

        // Recherche textuelle si fournie
        if (!empty($search) && $search !== 'undefined') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(s.firstName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.lastName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.pseudo)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.email)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(p.firstName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(p.lastName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(f.familyIdentifier)', 'LOWER(:search)')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        $students = $qb->getQuery()->getResult();

        // Exclure les élèves assignés au spécialiste si spécifié
        if (!empty($excludeSpecialistId)) {
            // Récupérer les IDs des élèves assignés au spécialiste
            $sql = "SELECT student_id FROM specialist_student WHERE specialist_id = :specialistId";
            $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
            $assignedStudentIds = $stmt->executeQuery(['specialistId' => $excludeSpecialistId])->fetchFirstColumn();
            
            // Filtrer les élèves pour exclure ceux assignés au spécialiste
            $students = array_filter($students, function($student) use ($assignedStudentIds) {
                return !in_array($student->getId(), $assignedStudentIds);
            });
        }

        return $students;
    }

    /**
     * Obtenir les statistiques des élèves d'un coach
     */
    public function getCoachStudentsStats($coach): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.family', 'f')
            ->leftJoin('f.parent', 'p')
            ->where('f.coach = :coach')
            ->setParameter('coach', $coach);

        // Compter le total d'élèves
        $totalStudents = $qb->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Compter les élèves actifs
        $activeStudents = $qb->select('COUNT(s.id)')
            ->andWhere('s.isActive = :isActive')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Compter les élèves inactifs
        $inactiveStudents = $totalStudents - $activeStudents;

        // Calculer le total des points
        $totalPoints = $qb->select('SUM(s.points)')
            ->andWhere('s.isActive = :isActive')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Moyenne des points
        $averagePoints = $activeStudents > 0 ? round($totalPoints / $activeStudents, 2) : 0;

        // Statistiques par classe
        $studentsByClass = $qb->select('s.class, COUNT(s.id) as count')
            ->andWhere('s.isActive = :isActive')
            ->setParameter('isActive', true)
            ->groupBy('s.class')
            ->getQuery()
            ->getResult();

        $classStats = [];
        foreach ($studentsByClass as $stat) {
            $classStats[$stat['class']] = (int) $stat['count'];
        }

        // Statistiques par famille
        $studentsByFamily = $qb->select('f.familyIdentifier, p.firstName, p.lastName, COUNT(s.id) as count')
            ->andWhere('s.isActive = :isActive')
            ->setParameter('isActive', true)
            ->groupBy('f.id, f.familyIdentifier, p.firstName, p.lastName')
            ->getQuery()
            ->getResult();

        $familyStats = [];
        foreach ($studentsByFamily as $stat) {
            $familyStats[$stat['familyIdentifier']] = [
                'name' => $stat['firstName'] . ' ' . $stat['lastName'],
                'count' => (int) $stat['count']
            ];
        }

        return [
            'totalStudents' => (int) $totalStudents,
            'activeStudents' => (int) $activeStudents,
            'inactiveStudents' => (int) $inactiveStudents,
            'totalPoints' => (int) $totalPoints,
            'averagePoints' => $averagePoints,
            'studentsByClass' => $classStats,
            'studentsByFamily' => $familyStats
        ];
    }
}
