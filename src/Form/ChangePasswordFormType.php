<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'attr' => ['autocomplete' => 'new-password', 'placeholder' => '12 caractères ou plus'],
                    'label' => 'Nouveau mot de passe',
                ],
                'second_options' => [
                    'attr' => ['autocomplete' => 'new-password', 'placeholder' => 'Confirmez le mot de passe'],
                    'label' => 'Confirmez le mot de passe',
                ],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new Length(
                        min: 12,
                        max: 4096,
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
