<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\AccountActivationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AccountActivationData>
 */
final class AccountActivationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'first_options' => [
                'label' => 'Mot de passe',
                'help' => 'Au moins 12 caractères. Une phrase de plusieurs mots est facile à retenir et difficile à deviner.',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'Confirmez le mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccountActivationData::class,
            'translation_domain' => false,
        ]);
    }
}
