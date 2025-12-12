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
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<mixed>
 */
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
                    new Callback(function (string $payload, ExecutionContextInterface $context): void {
                        $emails = (array) preg_split('/[\s,;]+/', $payload, -1, \PREG_SPLIT_NO_EMPTY);

                        foreach ($emails as $email) {
                            if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                                $context->buildViolation('import.invalid_email')
                                    ->setTranslationDomain('greeting')
                                    ->setParameter('{{ email }}', (string) $email)
                                    ->addViolation();
                            }
                        }
                    }),
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
