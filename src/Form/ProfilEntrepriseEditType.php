<?php

namespace App\Form;

use App\Entity\ProfilEntreprise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d'édition du profil entreprise (page "Mon profil" style
 * LinkedIn). Contrairement à InscriptionEntrepriseType, ce formulaire
 * ne gère ni l'email ni le mot de passe (déjà définis à l'inscription) —
 * uniquement les informations d'entreprise + logo + photo de couverture.
 */
class ProfilEntrepriseEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('raisonSociale', TextType::class, [
                'label' => 'Raison sociale',
                'constraints' => [
                    new Assert\NotBlank(message: 'La raison sociale est obligatoire.'),
                    new Assert\Length(min: 2, max: 255),
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
            ->add('type', TextType::class, [
                'label' => 'Type d\'entreprise',
                'required' => false,
                'attr' => ['placeholder' => 'PME, Startup, Grand groupe…'],
            ])
            ->add('secteur', TextType::class, [
                'label' => 'Secteur d\'activité',
                'required' => false,
                'attr' => ['placeholder' => 'Tech, Finance, Santé…'],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation (siège social)',
                'required' => false,
            ])
            ->add('rne', TextType::class, [
                'label' => 'Numéro RNE',
                'required' => false,
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^[A-Za-z0-9\-\s]*$/',
                        message: 'Le numéro RNE ne peut contenir que des lettres, chiffres, espaces et tirets.'
                    ),
                ],
            ])
            ->add('lienSite', TextType::class, [
                'label' => 'Site web',
                'required' => false,
                'constraints' => [
                    new Assert\Url(message: "Le lien du site '{{ value }}' n'est pas une URL valide."),
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
            ->add('photoCouverture', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Photo de couverture',
                'constraints' => [
                    new Assert\File(
                        maxSize: '8M',
                        maxSizeMessage: 'La photo de couverture ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'La photo de couverture doit être une image valide (JPG, PNG ou WEBP).'
                    ),
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