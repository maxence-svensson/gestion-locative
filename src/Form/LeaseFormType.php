<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\LeaseData;
use App\Form\Type\EuroAmountType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LeaseData>
 */
final class LeaseFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('paymentDay', IntegerType::class, [
                'label' => 'Jour de paiement du loyer',
                'help' => 'Entre le 1er et le 28, pour que la date existe tous les mois.',
                'attr' => ['min' => 1, 'max' => 28],
            ])
            ->add('rent', EuroAmountType::class, [
                'label' => 'Loyer mensuel hors charges',
            ])
            ->add('charges', EuroAmountType::class, [
                'label' => 'Provision mensuelle sur charges',
                'help' => 'Régularisée chaque année selon les dépenses réelles.',
            ])
            ->add('deposit', EuroAmountType::class, [
                'label' => 'Dépôt de garantie',
                'help' => $options['furnished']
                    ? 'Au maximum deux mois de loyer hors charges (location meublée).'
                    : 'Au maximum un mois de loyer hors charges (location vide).',
            ])
            ->add('irlReferenceQuarter', ChoiceType::class, [
                'label' => 'Trimestre de l\'IRL de référence',
                'placeholder' => 'Choisir…',
                'choices' => [
                    '1er trimestre' => 1,
                    '2e trimestre' => 2,
                    '3e trimestre' => 3,
                    '4e trimestre' => 4,
                ],
                'help' => 'Indiqué dans le bail, par exemple « IRL du 2e trimestre 2026 ».',
            ])
            ->add('irlReferenceYear', IntegerType::class, [
                'label' => 'Année de l\'IRL de référence',
                'attr' => ['min' => 2008],
            ])
            ->add('tenants', CollectionType::class, [
                'label' => 'Locataires',
                'entry_type' => TenantFormType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                // Affiche « Ajoutez au moins un locataire » au niveau de la liste, pas en haut du formulaire
                'error_bubbling' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeaseData::class,
            'translation_domain' => false,
            // Données pré-remplies par le contrôleur (LeaseData::forProperty), jamais créées par le formulaire
            'empty_data' => null,
        ]);
        $resolver->setRequired('furnished');
        $resolver->setAllowedTypes('furnished', 'bool');
    }
}
