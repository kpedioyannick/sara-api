<?php

namespace App\Form;

use App\Entity\Objective;
use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObjectiveType extends AbstractType
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
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Académique' => 'academic',
                    'Comportement' => 'Comportement',
                    'Autonomie' => 'Autonomie',
                    'Social' => 'Social',
                    'Émotionnel' => 'Émotionnel',
                ],
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'En cours' => 'en_cours',
                    'Terminé' => 'termine',
                ],
                'required' => true,
            ])
            ->add('progress', IntegerType::class, [
                'label' => 'Progression (%)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Date limite',
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Élève',
                'required' => true,
                'choice_label' => function (Student $student) {
                    return $student->getFirstName() . ' ' . $student->getLastName();
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Objective::class,
        ]);
    }
}


