<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\TimeEntryProject;
use App\Project\ProjectColors;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une ligne d'affectation « projet + heures » pour une journée.
 *
 * Le select projet est restreint aux projets visibles de l'utilisateur courant,
 * passés via l'option `projects` depuis TimeEntryType. Restreindre les `choices`
 * fait aussi office de défense côté serveur : Symfony rejette toute valeur hors
 * de cette liste (projet non visible / inactif).
 */
class TimeEntryProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => fn (Project $project): string => $project->getName(),
                // Icône + couleur exposées sur chaque option pour l'aperçu JS.
                'choice_attr' => fn (Project $project): array => [
                    'data-icon' => $project->getIcon(),
                    'data-hex' => ProjectColors::hex($project->getColor() ?? ProjectColors::DEFAULT),
                ],
                'placeholder' => 'Choisir un projet…',
                'label' => 'Projet',
                // Select natif masqué derrière le widget custom : pas de
                // contrainte HTML5 `required` (sinon "control not focusable" au
                // submit). La présence du projet est validée côté serveur.
                'required' => false,
            ])
            ->add('hours', NumberType::class, [
                'label' => 'Heures',
                'scale' => 1,
                'html5' => true,
                'attr' => ['min' => 0.5, 'step' => 0.5, 'inputmode' => 'decimal'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeEntryProject::class,
            // Projets sélectionnables (visibles par l'utilisateur courant).
            'projects' => [],
        ]);

        $resolver->setAllowedTypes('projects', 'array');
    }
}
