<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('titre', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 255])],
                'label' => 'Titre de l\'événement',
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [new Assert\NotBlank()],
                'label' => 'Description',
            ])
            ->add('isOnline', CheckboxType::class, [
                'required' => false,
                'label' => 'Événement en ligne',
            ])
            ->add('lieu', TextType::class, [
                'required' => false,
                'label' => 'Lieu (laisser vide si en ligne)'
            ])
            ->add('debutAt', DateTimeType::class, [
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
                'label' => 'Date et heure de début'
            ])
            ->add('finAt', DateTimeType::class, [
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
                'label' => 'Date et heure de fin'
            ])
            ->add('capacite', IntegerType::class, [
                'required' => false,
                'constraints' => [new Assert\Positive()],
                'label' => 'Capacité (personnes)'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
