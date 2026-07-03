<?php

namespace App\Form;

use App\Entity\ProfilCandidat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de complétion de profil, affiché après une première
 * connexion GitHub/LinkedIn (l'email, le mot de passe et le nom
 * proviennent déjà du fournisseur OAuth — seules les informations
 * "métier" manquantes sont demandées ici, en particulier le CV
 * obligatoire pour postuler — règle RM-U06).
 */
class CompleteProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomComplet', TextType::class, [
                'label' => 'Nom complet',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom complet est obligatoire.'),
                    new Assert\Regex(
                        pattern: "/^[\\pL\\pM\\s\'-]+$/u",
                        message: 'Le nom complet ne peut contenir que des lettres, des espaces, des apostrophes et des tirets.'
                    ),
                ],
            ])
            ->add('numTel', TextType::class, [
                'label' => 'Numéro de téléphone',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^\+?[0-9\s\-\(\)]{7,20}$/',
                        message: 'Le numéro de téléphone {{ value }} n\'est pas valide.'
                    ),
                ],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'La localisation est obligatoire.'),
                ],
            ])
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat recherché',
                'choices' => [
                    'Stage' => 'stage',
                    'Emploi' => 'emploi',
                    'Stage & Emploi' => 'les_deux',
                ],
                'constraints' => [
                    new Assert\Choice(
                        choices: ['stage', 'emploi', 'les_deux'],
                        message: 'Le type de contrat doit être : stage, emploi ou Stage & Emploi.'
                    ),
                ],
            ])
            ->add('cv', FileType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'CV (PDF)',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le CV est obligatoire pour accéder aux offres matchées.'),
                    new Assert\File(
                        maxSize: '10M',
                        maxSizeMessage: 'Le CV ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Le CV doit être au format PDF.'
                    ),
                ],
            ])
            ->add('photo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Photo de profil (optionnel)',
                'constraints' => [
                    new Assert\File(
                        maxSize: '5M',
                        maxSizeMessage: 'La photo de profil ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'La photo de profil doit être une image valide (JPG, PNG, WEBP ou GIF).'
                    ),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
                'label' => 'Bio (optionnel)',
                'constraints' => [
                    new Assert\Length(max: 1000, maxMessage: 'La bio ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfilCandidat::class,
        ]);
    }
}