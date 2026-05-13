<?php

namespace App\Form;

use App\Enum\DayType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de planification d'une plage de jours non-travaillés (ou
 * télétravail à l'avance). Non lié à une entité (data_class=null).
 *
 * Sortie : ['startDate' => \DateTime, 'endDate' => \DateTime, 'dayType' => DayType, 'note' => ?string]
 */
class DayPlanningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $plannableTypes = [DayType::REMOTE, DayType::PTO, DayType::UTO, DayType::OFF];

        $builder
            ->add('dayType', EnumType::class, [
                'class' => DayType::class,
                'label' => 'Type',
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choices' => $plannableTypes,
                'choice_label' => fn (DayType $d) => $d->getLabel(),
                'attr' => ['class' => 'ts-segmented'],
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('note', TextType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => ['placeholder' => 'Optionnel', 'maxlength' => 255],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => false,
        ]);
    }
}
