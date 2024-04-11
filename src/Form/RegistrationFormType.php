<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('mobilePhone', TextType::class, [
                'help'        => 'registration.form.phone.help',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your phone number',
                    ]),
                    new Length([
                        'min'        => 12,
                        'minMessage' => 'Your phone number should be at least {{ limit }} characters.',
                        'max'        => 20,
                    ]),
                    new Regex([
                        'pattern' => '/\(?\+\(?49\)?[ ()]?([- ()]?\d[- ()]?){10}/',
                        'message' => 'Please enter your phone number in international format, e.g. +491234567890.',
                    ]),
                ],
            ])
            ->add('firstName')
            ->add('lastName')
            ->add('birthDate', BirthdayType::class, [
                'widget' => 'choice',
                'years'  => range(date('Y') - 18, date('Y') - 70),
            ])
            ->add('termsOfUse', CheckboxType::class, [
                'mapped'      => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'registration.form.terms.agreement.necessary',
                    ]),
                ],
            ])
            ->add('codeOfConduct', CheckboxType::class, [
                'mapped'      => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'registration.form.code.of.conduct.agreement.necessary',
                    ]),
                ],
            ])
            ->add('dataProtection', CheckboxType::class, [
                'mapped'      => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'registration.form.data.protection.agreement.necessary',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped'      => false,
                'attr'        => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'password.constraint.not.blank',
                    ]),
                    new Length([
                        'min'        => 12,
                        'minMessage' => 'password.constraint.length.min',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
