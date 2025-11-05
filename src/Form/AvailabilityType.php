<?php

namespace App\Form;

use App\Entity\Availability;
use App\Entity\Coach;
use App\Entity\Specialist;
use App\Entity\ParentUser;
use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Jour de la semaine',
                'choices' => [
                    'Lundi' => 'monday',
                    'Mardi' => 'tuesday',
                    'Mercredi' => 'wednesday',
                    'Jeudi' => 'thursday',
                    'Vendredi' => 'friday',
                    'Samedi' => 'saturday',
                    'Dimanche' => 'sunday',
                ],
                'required' => true,
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('coach', EntityType::class, [
                'class' => Coach::class,
                'label' => 'Coach',
                'required' => false,
                'choice_label' => function (Coach $coach) {
                    return $coach->getFirstName() . ' ' . $coach->getLastName();
                },
            ])
            ->add('specialist', EntityType::class, [
                'class' => Specialist::class,
                'label' => 'Spécialiste',
                'required' => false,
                'choice_label' => function (Specialist $specialist) {
                    return $specialist->getFirstName() . ' ' . $specialist->getLastName();
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
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Élève',
                'required' => false,
                'choice_label' => function (Student $student) {
                    return $student->getFirstName() . ' ' . $student->getLastName();
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Availability::class,
        ]);
    }
}


