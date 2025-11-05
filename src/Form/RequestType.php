<?php

namespace App\Form;

use App\Entity\Request;
use App\Entity\Family;
use App\Entity\Specialist;
use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Général' => 'general',
                    'Consultation' => 'consultation',
                    'Aide' => 'aide',
                    'Question' => 'question',
                    'Spécialiste' => 'specialiste',
                ],
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'En cours' => 'in_progress',
                    'Résolu' => 'resolved',
                    'Fermé' => 'closed',
                ],
                'required' => true,
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Faible' => 'low',
                    'Moyenne' => 'medium',
                    'Haute' => 'high',
                ],
                'required' => true,
            ])
            ->add('family', EntityType::class, [
                'class' => Family::class,
                'label' => 'Famille',
                'required' => false,
                'choice_label' => 'familyIdentifier',
            ])
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Élève',
                'required' => false,
                'choice_label' => function (Student $student) {
                    return $student->getFirstName() . ' ' . $student->getLastName();
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
            'data_class' => Request::class,
        ]);
    }
}


