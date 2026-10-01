<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\EnergyClass;
use App\Enum\HousingType;
use App\Form\Data\PropertyData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PropertyData>
 */
final class PropertyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du bien',
                'help' => 'Pour le retrouver facilement, par exemple « T2 Croix-Rousse ».',
            ])
            ->add('addressLine', TextType::class, [
                'label' => 'Adresse',
                'attr' => ['autocomplete' => 'address-line1'],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'attr' => ['inputmode' => 'numeric', 'maxlength' => 5, 'autocomplete' => 'postal-code'],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'attr' => ['autocomplete' => 'address-level2'],
            ])
            ->add('housingType', EnumType::class, [
                'class' => HousingType::class,
                'label' => 'Type de logement',
                'expanded' => true,
                'choice_label' => static fn (HousingType $type): string => $type->label(),
            ])
            ->add('furnished', CheckboxType::class, [
                'label' => 'Logement meublé',
                'required' => false,
                'help' => 'Un bail meublé autorise un dépôt de garantie de deux mois de loyer, contre un mois en location vide.',
            ])
            ->add('surface', NumberType::class, [
                'label' => 'Surface habitable (m²)',
                'html5' => true,
                'scale' => 2,
                'attr' => ['min' => 1, 'step' => 0.01],
            ])
            ->add('energyClass', EnumType::class, [
                'class' => EnergyClass::class,
                'label' => 'Classe énergie (DPE)',
                'placeholder' => 'Choisir…',
                'choice_label' => static fn (EnergyClass $class): string => 'Classe '.$class->value,
                'help' => 'Le loyer d\'un logement classé F ou G ne peut plus augmenter.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyData::class,
            // Les libellés sont écrits directement en français : pas de passage par le traducteur
            'translation_domain' => false,
        ]);
    }
}
