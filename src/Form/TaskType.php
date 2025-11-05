<?php

namespace App\Form;

use App\Entity\Objective;
use App\Entity\ParentUser;
use App\Entity\Specialist;
use App\Entity\Student;
use App\Entity\Task;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $objective = $options['objective'] ?? null;
        
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'En cours' => 'in_progress',
                    'Terminé' => 'completed',
                ],
                'required' => true,
            ])
            ->add('frequency', ChoiceType::class, [
                'label' => 'Fréquence',
                'choices' => [
                    'Quotidien' => 'daily',
                    'Hebdomadaire' => 'weekly',
                    'Mensuel' => 'monthly',
                    'Ponctuel' => 'once',
                ],
                'required' => false,
            ])
            ->add('requiresProof', CheckboxType::class, [
                'label' => 'Preuve requise',
                'required' => false,
            ])
            ->add('proofType', ChoiceType::class, [
                'label' => 'Type de preuve',
                'choices' => [
                    'Texte' => 'text',
                    'Photo' => 'photo',
                    'Audio' => 'audio',
                    'Vidéo' => 'video',
                ],
                'required' => false,
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Date d\'échéance',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('assignedType', ChoiceType::class, [
                'label' => 'Type d\'assignation',
                'choices' => [
                    'Élève' => 'student',
                    'Parent' => 'parent',
                    'Spécialiste' => 'specialist',
                    'Coach' => 'coach',
                ],
                'required' => true,
            ])
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Élève',
                'required' => false,
                'choice_label' => function (Student $student) {
                    return $student->getFirstName() . ' ' . $student->getLastName();
                },
            ])
            ->add('parent', EntityType::class, [
                'class' => ParentUser::class,
                'label' => 'Parent',
                'required' => false,
                'choice_label' => function (ParentUser $parent) {
                    return $parent->getFirstName() . ' ' . $parent->getLastName();
                },
            ])
            ->add('specialist', EntityType::class, [
                'class' => Specialist::class,
                'label' => 'Spécialiste',
                'required' => false,
                'choice_label' => function (Specialist $specialist) {
                    return $specialist->getFirstName() . ' ' . $specialist->getLastName();
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'objective' => null,
        ]);
    }
}


