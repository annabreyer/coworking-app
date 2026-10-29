<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BillingAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BillingAddressFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'user_account.edit_data.billing_address.name',
            ])
            ->add('street', null, [
                'label' => 'Street',
            ])
            ->add('postCode', null, [
                'label' => 'Post code',
            ])
            ->add('city', null, [
                'label' => 'City',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BillingAddress::class,
            'required'   => false,
        ]);
    }
}
