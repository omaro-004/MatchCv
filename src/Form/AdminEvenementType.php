<?php

namespace App\Form;

use Symfony\Component\Form\FormBuilderInterface;

class AdminEvenementType extends EvenementType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
    }
}