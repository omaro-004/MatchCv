<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => "Titre de l'événement",
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : Journée portes ouvertes MatchCV',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Décrivez le déroulement, le public visé, le programme...',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                ],
            ])
            ->add('isOnline', CheckboxType::class, [
                'required' => false,
                'label' => 'Cet événement se déroule en ligne',
            ])
            ->add('lieu', TextType::class, [
                'required' => false,
                'label' => 'Lieu',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : Tunis, Tunisie — laisser vide si en ligne',
                ],
                'constraints' => [
                    new Assert\Callback(function (?string $value, ExecutionContextInterface $context): void {
                        /** @var Evenement $evenement */
                        $evenement = $context->getRoot()->getData();

                        if (!$evenement->isOnline() && ($value === null || trim($value) === '')) {
                            $context->buildViolation('Le lieu est obligatoire pour un événement en présentiel.')
                                ->atPath('lieu')
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('debutAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date et heure de début',
                'attr' => ['class' => 'form-control'],
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de début est obligatoire.'),
                ],
            ])
            ->add('finAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date et heure de fin',
                'attr' => ['class' => 'form-control'],
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de fin est obligatoire.'),
                    new Assert\Callback(function (?\DateTimeInterface $value, ExecutionContextInterface $context): void {
                        /** @var Evenement $evenement */
                        $evenement = $context->getRoot()->getData();

                        try {
                            $debut = $evenement->getDebutAt();
                        } catch (\Error) {
                            // Propriété non initialisée : le champ debutAt est vide/invalide,
                            // son propre NotBlank se chargera déjà de le signaler.
                            return;
                        }

                        if ($value !== null && $debut !== null && $value <= $debut) {
                            $context->buildViolation('La date de fin doit être postérieure à la date de début.')
                                ->atPath('finAt')
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('capacite', IntegerType::class, [
                'required' => false,
                'label' => 'Capacité (nombre de participants)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : 100 (laisser vide si illimité)',
                ],
                'constraints' => [
                    new Assert\Positive(message: 'La capacité doit être un nombre positif.'),
                ],
            ]);

        $builder->add('photo', FileType::class, [
            'label' => 'Photo de l\'événement (JPEG, PNG, WebP — max 2MB)',
            'required' => false,
            'mapped' => false,
            'constraints' => [
                new Assert\File([
                    'maxSize' => '2M',
                    'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                    'mimeTypesMessage' => 'Veuillez uploader une image au format JPEG, PNG ou WebP.',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}