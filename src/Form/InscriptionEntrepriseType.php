<?php

namespace App\Form;

use App\Entity\ProfilEntreprise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class InscriptionEntrepriseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'mapped' => false,
                'label' => 'Email de l\'entreprise',
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
            ->add('raisonSociale', TextType::class, [
                'label' => 'Raison sociale',
                'required' => true,
                'empty_data' => '',
                'constraints' => [],
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
            ->add('logo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Logo de l\'entreprise',
                'constraints' => [
                    new Assert\File(
                        maxSize: '5M',
                        maxSizeMessage: 'Le logo ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Le logo doit être une image valide (JPG, PNG, WEBP ou GIF).'
                    ),
                ],
            ])
            ->add('lienLinkedin', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'LinkedIn entreprise',
                'constraints' => [
                    new Assert\Url(message: "Le lien LinkedIn '{{ value }}' n'est pas une URL valide."),
                ],
            ])
            ->add('rne', TextType::class, [
                'label' => 'Numéro RNE',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^[A-Za-z0-9\-\s]+$/',
                        message: 'Le numéro RNE ne peut contenir que des lettres, chiffres, espaces et tirets.'
                    ),
                ],
            ])
            ->add('type', TextType::class, [
                'label' => 'Type d\'entreprise',
                'required' => true,
                'empty_data' => '',
                'constraints' => [],
            ])
            ->add('secteur', TextType::class, [
                'label' => 'Secteur d\'activité',
                'required' => true,
                'empty_data' => '',
                'constraints' => [],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'required' => true,
                'empty_data' => '',
                'constraints' => [],
            ])
            ->add('lienSite', TextType::class, [
                'required' => false,
                'label' => 'Site web',
                'constraints' => [
                    new Assert\Url(message: "Le lien du site '{{ value }}' n'est pas une URL valide."),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfilEntreprise::class,
        ]);
    }
}
