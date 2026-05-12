<?php

namespace App\Form;

use App\Entity\TimeEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'attr' => ['max' => new \DateTime()->format('Y-m-d')],
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
            ])
            ->add('breakDuration', IntegerType::class, [
                'label' => 'Pause (minutes)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 600, 'step' => 5, 'placeholder' => '120'],
            ])
            ->add('note', TextType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => ['placeholder' => 'Optionnel — projet, contexte, etc.', 'maxlength' => 255],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeEntry::class,
            // Form behind IsGranted('IS_AUTHENTICATED_FULLY'), and Symfony's
            // stateless CSRF JS controller mismatches the server-side token format.
            'csrf_protection' => false,
        ]);
    }
}
