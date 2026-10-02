<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\PaymentMethod;
use App\Form\Data\PaymentData;
use App\Form\Type\EuroAmountType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PaymentData>
 */
final class PaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', EuroAmountType::class, [
                'label' => 'Montant reçu',
                'help' => 'Pour un paiement partiel, saisissez le montant reçu : le reste restera à payer.',
            ])
            ->add('paidOn', DateType::class, [
                'label' => 'Date du paiement',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('method', EnumType::class, [
                'label' => 'Moyen de paiement',
                'class' => PaymentMethod::class,
                'choice_label' => static fn (PaymentMethod $method): string => $method->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaymentData::class,
            'translation_domain' => false,
            // Données pré-remplies par le contrôleur (PaymentData::forDue), jamais créées par le formulaire
            'empty_data' => null,
        ]);
    }
}
