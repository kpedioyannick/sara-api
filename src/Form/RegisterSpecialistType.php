<?php

namespace App\Form;

use App\Entity\Specialist;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegisterSpecialistType extends AbstractType
{

    private const SPECIALIZATIONS = [
        'theatre' => 'Théâtre',
        'dessin' => 'Dessin / Arts plastiques',
        'peinture' => 'Peinture',
        'musique' => 'Musique',
        'chant' => 'Chant',
        'danse' => 'Danse',
        'ecriture_creative' => 'Écriture créative',
        'lecture_expressive' => 'Lecture expressive',
        'jeux_role' => 'Jeux de rôle',
        'jeu' => 'Jeux ludiques',
        'cirque' => 'Arts du cirque',
        'marionnettes' => 'Marionnettes',
        'improvisation' => 'Improvisation',
        'artisanat' => 'Artisanat / DIY',
        'soutien_scolaire' => 'Soutien scolaire',
        'autre' => 'Autre',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'placeholder' => 'info@gmail.com',
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => true,
            ]);
            // Les spécialisations seront gérées via des checkboxes dans le template
            // et traitées dans le contrôleur

        $builder
            ->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => [
                'label' => 'Mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'Confirmer le mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez entrer un mot de passe',
                ]),
                new Length([
                    'min' => 6,
                    'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                    'max' => 4096,
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Specialist::class,
        ]);
    }

    public static function getSpecializations(): array
    {
        return self::SPECIALIZATIONS;
    }
}

