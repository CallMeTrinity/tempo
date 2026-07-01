<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\User;
use App\Project\ProjectColors;
use App\Project\ProjectIcons;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de projet d'équipe (scope TEAM forcé côté contrôleur).
 *
 * Icône et couleur sont choisies parmi des jeux finis définis dans
 * App\Project\ProjectIcons et App\Project\ProjectColors. Le rendu visuel des
 * radios est géré dans `admin/project_form.html.twig`.
 */
class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => ['maxlength' => 255, 'placeholder' => 'Refonte du site'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['maxlength' => 255, 'rows' => 2, 'placeholder' => 'Optionnel'],
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('icon', ChoiceType::class, [
                'label' => 'Icône',
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                // clé => libellé ; ChoiceType attend libellé => valeur.
                'choices' => array_flip(ProjectIcons::ICONS),
                'placeholder' => false,
                'constraints' => [new Assert\Choice(choices: array_keys(ProjectIcons::ICONS))],
            ])
            ->add('color', ChoiceType::class, [
                'label' => 'Couleur',
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choices' => array_combine(
                    array_column(ProjectColors::COLORS, 'label'),
                    array_keys(ProjectColors::COLORS),
                ),
                'placeholder' => false,
                // Teinte exposée sur chaque radio pour l'aperçu en direct.
                'choice_attr' => fn (string $key): array => ['data-hex' => ProjectColors::hex($key)],
                'constraints' => [new Assert\Choice(choices: array_keys(ProjectColors::COLORS))],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Projet actif',
                'required' => false,
            ])
        ;

        // Un projet personnel n'a pas de membres : on n'expose pas le champ.
        if (!$options['personal']) {
            $builder->add('members', EntityType::class, [
                'label' => 'Membres',
                'class' => User::class,
                'choice_label' => fn (User $user): string => $user->getFullName() ?? $user->getEmail(),
                'query_builder' => fn (EntityRepository $repo) => $repo->createQueryBuilder('u')
                    ->orderBy('u.firstName', 'ASC')
                    ->addOrderBy('u.email', 'ASC'),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'personal' => false,
        ]);

        $resolver->setAllowedTypes('personal', 'bool');
    }
}
