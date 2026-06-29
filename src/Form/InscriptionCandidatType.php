<?php

namespace App\Form;

use App\Entity\ProfilCandidat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class InscriptionCandidatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'mapped' => false,
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(message: "L'email est obligatoire."),
                    new Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide."),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'mapped' => false,
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('nomComplet', TextType::class, [
                'label' => 'Nom complet',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
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
                    new Assert\Regex(
                        pattern: '/^\+?[0-9\s\-\(\)]{7,20}$/',
                        message: 'Le numéro de téléphone {{ value }} n\'est pas valide.'
                    ),
                ],
            ])
            ->add('photo', FileType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Photo de profil',
                'constraints' => [
                    new Assert\NotBlank(message: 'La photo de profil est obligatoire.'),
                    new Assert\File(
                        maxSize: '5M',
                        maxSizeMessage: 'La photo de profil ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'La photo de profil doit être une image valide (JPG, PNG, WEBP ou GIF).'
                    ),
                ],
            ])
            ->add('lienLinkedin', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'LinkedIn',
                'constraints' => [
                    new Assert\Url(message: "Le lien LinkedIn '{{ value }}' n'est pas une URL valide."),
                ],
            ])
            ->add('autresLiens', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Autres liens',
                'constraints' => [
                    new Assert\Callback(function (?string $value, ExecutionContextInterface $context): void {
                        if ($value === null || trim($value) === '') {
                            return;
                        }

                        $links = preg_split('/[\s,;\n]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

                        foreach ($links as $link) {
                            if (filter_var($link, FILTER_VALIDATE_URL) === false) {
                                $context->buildViolation('Chaque lien doit être une URL valide.')
                                    ->addViolation();
                                return;
                            }
                        }
                    }),
                ],
            ])
            ->add('cv', FileType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'CV (PDF)',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le CV est obligatoire.'),
                    new Assert\File(
                        maxSize: '10M',
                        maxSizeMessage: 'Le CV ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Le CV doit être au format PDF.'
                    ),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
                'label' => 'Bio',
                'constraints' => [
                    new Assert\Length(
                        max: 1000,
                        maxMessage: 'La bio ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'required' => true,
                'empty_data' => '',
                'constraints' => [],
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfilCandidat::class,
        ]);
    }
}
