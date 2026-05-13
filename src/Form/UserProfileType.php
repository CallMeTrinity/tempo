<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\ContractType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['placeholder' => 'Aiyana'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => ['placeholder' => 'Redcloud'],
            ])
            ->add('jobTitle', TextType::class, [
                'label' => 'Poste',
                'required' => false,
                'attr' => ['placeholder' => 'Designer produit, Développeur·euse…'],
            ])
            ->add('contractType', EnumType::class, [
                'class' => ContractType::class,
                'label' => 'Type de contrat',
                'required' => false,
                'placeholder' => '— Sélectionner —',
                'choice_label' => fn (ContractType $c) => $c->getLabel(),
            ])
            ->add('weeklyHours', NumberType::class, [
                'label' => 'Heures hebdomadaires',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.25', 'min' => 0, 'max' => 60, 'placeholder' => '35'],
                'help' => 'Ex : 35, 37.5, 39, 40.',
            ])
            ->add('contractStartDate', DateType::class, [
                'label' => 'Début du contrat',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('workingDaysPerWeek', IntegerType::class, [
                'label' => 'Jours travaillés par semaine',
                'required' => false,
                'attr' => ['min' => 1, 'max' => 5],
                'help' => 'Nombre de jours ouvrés contractuels (1 à 5, week-ends toujours chômés).',
            ])
            ->add('defaultBreakMinutes', IntegerType::class, [
                'label' => 'Pause par défaut (minutes)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 600, 'step' => 5, 'placeholder' => '60'],
                'help' => 'Valeur pré-remplie dans le formulaire de saisie quotidien.',
            ])
            ->add('defaultRemoteDays', ChoiceType::class, [
                'label' => 'Jours de télétravail prédéfinis',
                'required' => false,
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                ],
                'expanded' => true,
                'multiple' => true,
                'attr' => ['class' => 'ts-choices'],
                'help' => 'Ces jours seront pré-cochés en télétravail à l\'ouverture de la saisie quotidienne.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            // Page authentifiée (IsGranted), CSRF JS controller mismatchant côté stateless
            'csrf_protection' => false,
        ]);
    }
}
