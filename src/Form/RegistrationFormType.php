<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your email',
                    ]),
                    new Length([
                        'max' => 180,
                    ]),
                ],
            ])
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
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your first name',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your last name',
                    ]),
                ],
            ])
            ->add('birthDate', BirthdayType::class, [
                'widget'      => 'choice',
                'years'       => range(date('Y') - 18, date('Y') - 70),
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your birth date',
                    ]),
                ],
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
