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
            ])
            ->add('numTel', TextType::class, [
                'required' => false,
                'label' => 'Numéro de téléphone',
            ])
            ->add('logo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Logo de l\'entreprise',
            ])
            ->add('lienLinkedin', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'LinkedIn entreprise',
            ])
            ->add('rne', TextType::class, [
                'required' => false,
                'label' => 'Numéro RNE',
            ])
            ->add('type', TextType::class, [
                'required' => false,
                'label' => 'Type d\'entreprise',
            ])
            ->add('secteur', TextType::class, [
                'required' => false,
                'label' => 'Secteur d\'activité',
            ])
            ->add('localisation', TextType::class, [
                'required' => false,
                'label' => 'Localisation',
            ])
            ->add('lienSite', TextType::class, [
                'required' => false,
                'label' => 'Site web',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfilEntreprise::class,
        ]);
    }
}
