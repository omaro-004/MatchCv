<?php

namespace App\Form;

use App\Entity\Candidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CandidatureType
 *
 * Formulaire volontairement court : le CV et les informations du candidat
 * sont déjà présents dans son profil (règle RM-U06). Seuls un message de
 * motivation et un éventuel CV spécifique à CETTE candidature sont demandés.
 */
class CandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lettreMotivation', TextareaType::class, [
                'label' => 'Message de motivation',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Expliquez en quelques lignes pourquoi ce poste vous intéresse (optionnel)...'],
                'constraints' => [
                    new Assert\Length(max: 2000, maxMessage: 'Votre message ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('cv', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Utiliser un CV différent pour cette candidature (optionnel)',
                'constraints' => [
                    new Assert\File(
                        maxSize: '10M',
                        maxSizeMessage: 'Le CV ne peut pas dépasser {{ limit }}.',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Le CV doit être au format PDF.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
        ]);
    }
}