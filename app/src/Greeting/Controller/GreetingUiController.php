<?php

declare(strict_types=1);

namespace App\Greeting\Controller;

use App\DTO\EmailRequest;
use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Form\GreetingImportType;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Repository\GreetingLogRepository;
use App\Greeting\Service\EmailGeneratorService;
use App\Greeting\Service\GreetingContactService;
use App\Greeting\Service\GreetingEmailParser;
use App\Greeting\Service\GreetingService;
use App\Greeting\Service\GreetingXmlParser;
use App\Service\EmailSequenceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class GreetingUiController extends AbstractController
{
    public function __construct(
        private readonly GreetingContactRepository $greetingContactRepository,
        private readonly GreetingLogRepository $greetingLogRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GreetingContactService $greetingContactService,
        private readonly TranslatorInterface $translator,
        private readonly GreetingEmailParser $greetingEmailParser,
        private readonly GreetingXmlParser $greetingXmlParser,
        private readonly GreetingService $greetingService,
        private readonly EmailGeneratorService $emailGeneratorService,
        private readonly EmailSequenceService $emailSequenceService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ExceptionInterface|\DateMalformedStringException
     */
    #[Route('/greeting/dashboard', name: 'greeting_dashboard_default')]
    #[Route('/{_locale}/greeting/dashboard', name: 'greeting_dashboard', requirements: ['_locale' => '%app.supported_locales%'])]
    public function dashboard(Request $request): Response
    {
        $importForm = $this->createForm(GreetingImportType::class);
        $importForm->handleRequest($request);

        if ($importForm->isSubmitted()) {
            if ($importForm->isValid()) {
                $data = $importForm->getData();
                $emails = [];

                // 1. Process Text Input
                if (!empty($data['emails'])) {
                    $emails = $this->greetingEmailParser->parse($data['emails']);
                }

                // 2. Process XML File
                /** @var UploadedFile|null $xmlFile */
                $xmlFile = $importForm->get('xmlFile')->getData();

                if ($xmlFile) {
                    try {
                        $xmlEmails = $this->greetingXmlParser->parse($xmlFile);
                        $emails = array_merge($emails, $xmlEmails);
                    } catch (\Exception) {
                        $this->addFlash('error', $this->translator->trans(
                            'import.error_xml_parsing', [], 'greeting')
                        );
                    }
                }

                $emails = array_unique($emails);

                if (empty($emails)) {
                    $this->addFlash('error', $this->translator->trans('import.error_no_data', [], 'greeting'));
                } else {
                    $countNewEmails = $this->greetingContactService->importContacts(
                        $emails,
                        $data['language'],
                        \DateTimeImmutable::createFromMutable($data['registrationDate'])
                    );

                    $message = $countNewEmails > 0
                        ? $this->translator->trans('import.success', ['%count%' => $countNewEmails], 'greeting')
                        : $this->translator->trans('import.success_zero', [], 'greeting');
                    $this->addFlash('success', $message);
                }

                return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
            }

            $this->addFlash('error', $this->translator->trans('import.error_validation', [], 'greeting'));
        }

        if ($request->isMethod('POST') && $request->request->has('send_greeting')) {
            $selectedIds = $request->request->all('contacts');
            /** @var string $subject */
            $subject = $request->request->get('subject');
            $body = $request->request->get('body');

            if (empty($selectedIds)) {
                $this->addFlash('error', $this->translator->trans(
                    'dashboard.send_error_no_selection',
                    [],
                    'greeting'
                ));
            } else {
                $selectedContacts = $this->greetingContactRepository->findBy(['id' => $selectedIds]);
                $emailRequests = [];

                foreach ($selectedContacts as $contact) {
                    /** @var string $email */
                    $email = $contact->getEmail();
                    $emailRequests[] = new EmailRequest(
                        to: $email,
                        subject: $subject,
                        template: 'email/greeting.html.twig',
                        context: ['subject' => $subject, 'body' => $body]
                    );
                }

                $this->emailSequenceService->sendSequence($emailRequests);
                $count = \count($selectedIds);
                $this->addFlash('success', $this->translator->trans('dashboard.send_success_queued', [
                    '%count%' => $count,
                ], 'greeting'));

                return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
            }
        }

        $groupedContacts = $this->greetingService->getContactsGroupedByLanguage();

        // Získáme seznam ID kontaktů, kterým byl odeslán e-mail za posledních 7 dní
        $greetedContactIds = $this->greetingLogRepository->findGreetedContactIdsSince(
            (new \DateTimeImmutable())->modify('-7 days')
        );

        return $this->render('@Greeting/dashboard.html.twig', [
            'import_form' => $importForm->createView(),
            'grouped_contacts' => $groupedContacts,
            'greeted_contact_ids' => $greetedContactIds,
        ]);
    }

    #[Route('/{_locale}/greeting/contact/{id}/delete', name: 'greeting_delete_contact', requirements: ['_locale' => '%app.supported_locales%'], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        if (!$contact = $this->findContact($id)) {
            return $this->json(['success' => false], Response::HTTP_NOT_FOUND);
        }

        if ($contact->getStatus() === Status::Deleted) {
            $this->addFlash('warning', $this->translator->trans(
                'dashboard.delete_error_already_deleted',
                [],
                'greeting'
            ));

            // Return success=true to trigger reload and show the warning
            return $this->json(['success' => true]);
        }

        $contact->setStatus(Status::Deleted);
        $this->entityManager->flush();

        $this->logger->info('Greeting contact deleted: {email}', ['email' => $contact->getEmail()]);
        $this->addFlash('success', $this->translator->trans(
            'dashboard.delete_success',
            ['%email%' => $contact->getEmail()],
            'greeting'
        ));

        return $this->json(['success' => true]);
    }

    #[Route('/{_locale}/greeting/contact/{id}/deactivate', name: 'greeting_deactivate_contact', requirements: ['_locale' => '%app.supported_locales%'], methods: ['POST'])]
    public function deactivate(string $id): Response
    {
        if (!$contact = $this->findContact($id)) {
            return $this->json(['success' => false], Response::HTTP_NOT_FOUND);
        }

        if ($contact->getStatus() === Status::Inactive || $contact->getStatus() === Status::Deleted) {
            $this->addFlash('warning', $this->translator->trans(
                'dashboard.deactivate_error_already_inactive',
                [],
                'greeting'
            ));

            // Return success=true to trigger reload and show the warning
            return $this->json(['success' => true]);
        }

        $contact->setStatus(Status::Inactive);
        $this->entityManager->flush();

        $this->logger->info('Greeting contact deactivated: {email}', ['email' => $contact->getEmail()]);
        $this->addFlash('success', $this->translator->trans(
            'dashboard.deactivate_success',
            ['%email%' => $contact->getEmail()],
            'greeting'
        ));

        return $this->json(['success' => true]);
    }

    #[Route('/greeting/generate-test-emails', name: 'greeting_generate_test_emails', methods: ['GET'])]
    public function generateTestEmails(): Response
    {
        if ($this->getParameter('kernel.environment') === 'prod') {
            throw $this->createNotFoundException();
        }

        $emails = $this->emailGeneratorService->generateEmails(10);

        return new Response(implode(' ', $emails));
    }

    private function findContact(string $id): ?GreetingContact
    {
        $contact = $this->greetingContactRepository->find($id);

        if (!$contact) {
            $this->addFlash('error', $this->translator->trans('dashboard.delete_error_not_found', [], 'greeting'));
        }

        return $contact;
    }
}
