<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserDataFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('mobilePhone', TelType::class, [
                'help'        => 'registration.form.phone.help',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your phone number',
                    ]),
                    new Length([
                        'min'        => 9,
                        'minMessage' => 'Your phone number should be at least {{ limit }} characters.',
                        'max'        => 15,
                    ]),
                ],
            ])
            ->add('firstName')
            ->add('lastName')
            ->add('birthDate', BirthdayType::class, [
                'widget' => 'choice',
                'years'  => range(date('Y') - 18, date('Y') - 70),
            ])
            ->add('street')
            ->add('postCode')
            ->add('city')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
