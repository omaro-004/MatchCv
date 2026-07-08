<?php

namespace App\Form;

use App\Entity\Offre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class OffreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Intitulé du poste',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(min: 5, max: 255),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du poste',
                'attr' => ['rows' => 6],
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                    new Assert\Length(min: 20, minMessage: 'Décrivez le poste en au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('competencesRequises', TextareaType::class, [
                'label' => 'Compétences demandées',
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Ex : PHP, Symfony, MySQL, React (séparées par des virgules)',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Indiquez au moins une compétence recherchée.'),
                ],
            ])
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat',
                'choices' => [
                    'Stage' => 'stage',
                    'Emploi' => 'emploi',
                ],
                'constraints' => [
                    new Assert\Choice(choices: Offre::TYPES_CONTRAT, message: 'Type de contrat invalide.'),
                ],
            ])
            ->add('dureeContrat', TextType::class, [
                'label' => 'Durée du contrat',
                'required' => false,
                'attr' => ['placeholder' => 'Ex : 6 mois, 12 mois, Indéterminée'],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'attr' => ['placeholder' => 'Paris, France'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La localisation est obligatoire.'),
                ],
            ])
            ->add('modeTravail', ChoiceType::class, [
                'label' => 'Mode de travail',
                'choices' => [
                    'Sur site' => 'sur_site',
                    'Télétravail' => 'teletravail',
                    'Hybride' => 'hybride',
                ],
                'constraints' => [
                    new Assert\Choice(choices: Offre::MODES_TRAVAIL, message: 'Mode de travail invalide.'),
                ],
            ])
            ->add('salaire', NumberType::class, [
                'label' => 'Salaire / Gratification (optionnel)',
                'required' => false,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0', 'placeholder' => 'Ex : 1200'],
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'Le salaire doit être un nombre positif.'),
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début souhaitée (optionnel)',
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offre::class,
        ]);
    }
}