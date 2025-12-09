<?php

declare(strict_types=1);

namespace App\Greeting\Form;

use App\Greeting\Enum\GreetingLanguage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class GreetingImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emails', TextareaType::class, [
                'label' => 'import.emails_label',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'import.emails_placeholder',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('registrationDate', DateType::class, [
                'label' => 'import.date_label',
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('language', EnumType::class, [
                'class' => GreetingLanguage::class,
                'label' => 'import.language_label',
                'choice_label' => fn (GreetingLanguage $choice) => 'language.' . $choice->name,
            ])
            ->add('import', SubmitType::class, [
                'label' => 'import.submit',
                'attr' => ['class' => 'btn btn-primary w-100'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'greeting',
        ]);
    }
}
