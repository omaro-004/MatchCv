<?php

namespace App\Form;

use App\Entity\ProfilCandidat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d'édition du profil candidat.
 *
 * IMPORTANT : les champs enrichis par l'IA (langues, compétences,
 * formations, expériences, projets, soft skills) restent des champs
 * "libres" (textarea, un élément par ligne) NON mappés directement sur
 * l'entité, car celle-ci stocke ces données en JSON via des méthodes
 * *Array(). Le contrôleur se charge de la conversion texte <-> tableau
 * au moment de la sauvegarde. Cela permet au candidat de corriger
 * manuellement ce que l'IA a détecté, sans jamais modifier la logique
 * d'analyse automatique (CvAiProfileAnalyzer).
 */
class ProfilCandidatEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomComplet', TextType::class, [
                'label' => 'Nom complet',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom complet est obligatoire.'),
                    new Assert\Length(min: 2, max: 150),
                ],
            ])
            ->add('numTel', TextType::class, [
                'label' => 'Numéro de téléphone',
                'required' => false,
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^\+?[0-9\s\-\(\)]{7,20}$/',
                        message: 'Le numéro de téléphone {{ value }} n\'est pas valide.'
                    ),
                ],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'required' => false,
            ])
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat recherché',
                'choices' => [
                    'Stage' => 'stage',
                    'Emploi' => 'emploi',
                    'Stage & Emploi' => 'les_deux',
                ],
                'constraints' => [
                    new Assert\Choice(choices: ['stage', 'emploi', 'les_deux']),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Bio',
                'required' => false,
                'attr' => ['rows' => 4],
                'constraints' => [
                    new Assert\Length(max: 1000, maxMessage: 'La bio ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('anneesExperience', IntegerType::class, [
                'label' => 'Années d\'expérience',
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'Le nombre d\'années doit être positif.'),
                ],
            ])
            ->add('resumeIa', TextareaType::class, [
                'label' => 'Résumé professionnel',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('photo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Photo de profil',
                'constraints' => [
                    new Assert\File(
                        maxSize: '5M',
                        maxSizeMessage: 'La photo ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'La photo doit être une image valide (JPG, PNG, WEBP ou GIF).'
                    ),
                ],
            ])
            // ── Champs "libres" (un élément par ligne), non mappés ──
            ->add('languesParleesText', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Langues parlées',
                'attr' => ['rows' => 3, 'placeholder' => "Français (natif)\nAnglais (courant)"],
            ])
            ->add('competencesTechniquesText', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Compétences techniques',
                'attr' => ['rows' => 4, 'placeholder' => "PHP\nSymfony\nMySQL"],
            ])
            ->add('formationsText', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Formations',
                'attr' => ['rows' => 3, 'placeholder' => "Licence Informatique — Université X (2022)"],
            ])
            ->add('experiencesProfessionnellesText', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Expériences professionnelles',
                'attr' => ['rows' => 4, 'placeholder' => "Développeur Stagiaire — Entreprise Y (6 mois)"],
            ])
            ->add('projetsAcademiquesText', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Projets académiques',
                'attr' => ['rows' => 3, 'placeholder' => "Application de gestion — Symfony, MySQL"],
            ])
            ->add('softSkillsText', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Soft skills',
                'attr' => ['rows' => 3, 'placeholder' => "Travail en équipe\nCommunication"],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfilCandidat::class,
        ]);
    }
}