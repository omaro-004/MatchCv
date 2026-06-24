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
            ])
            ->add('numTel', TextType::class, [
                'required' => false,
                'label' => 'Numéro de téléphone',
            ])
            ->add('photo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Photo de profil',
            ])
            ->add('lienLinkedin', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'LinkedIn',
            ])
            ->add('autresLiens', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Autres liens',
            ])
            ->add('cv', FileType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'CV (PDF)',
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
                'label' => 'Bio',
            ])
            ->add('localisation', TextType::class, [
                'required' => false,
                'label' => 'Localisation',
            ])
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat recherché',
                'choices' => [
                    'Stage' => 'stage',
                    'Emploi' => 'emploi',
                    'Stage & Emploi' => 'les_deux',
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
