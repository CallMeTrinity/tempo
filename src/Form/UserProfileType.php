<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\ContractType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
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
