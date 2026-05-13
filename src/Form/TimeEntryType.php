<?php

namespace App\Form;

use App\Entity\TimeEntry;
use App\Enum\DayType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'attr' => ['max' => (new \DateTime())->format('Y-m-d')],
            ])
            ->add('isRemote', CheckboxType::class, [
                'label' => 'Télétravail (forfait journalier)',
                'mapped' => false,
                'required' => false,
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Début',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Fin',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('breakDuration', IntegerType::class, [
                'label' => 'Pause (minutes)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 600, 'step' => 5, 'placeholder' => '60'],
            ])
            ->add('note', TextType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => ['placeholder' => 'Optionnel — projet, contexte, etc.', 'maxlength' => 255],
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, [$this, 'syncDayTypeFromToggle'])
        ;
    }

    /**
     * Synchronise le dayType de l'entité depuis le toggle isRemote (non mappé).
     * Doit tourner avant la validation, donc POST_SUBMIT (qui s'exécute avant
     * la validation côté contrôleur).
     */
    public function syncDayTypeFromToggle(FormEvent $event): void
    {
        $entry = $event->getData();
        $form = $event->getForm();
        if (!$entry instanceof TimeEntry || !$form->has('isRemote')) {
            return;
        }

        $isRemote = (bool) $form->get('isRemote')->getData();
        if ($isRemote) {
            $entry->setDayType(DayType::REMOTE);
            $entry->setStartTime(null);
            $entry->setEndTime(null);
            $entry->setBreakDuration(null);
        } else {
            $entry->setDayType(DayType::WORKED);
        }
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
