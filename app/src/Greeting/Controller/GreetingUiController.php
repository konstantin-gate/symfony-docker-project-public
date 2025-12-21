<?php

declare(strict_types=1);

namespace App\Greeting\Controller;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Form\GreetingImportType;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Repository\GreetingLogRepository;
use App\Greeting\Service\EmailGeneratorService;
use App\Greeting\Service\GreetingImportHandler;
use App\Greeting\Service\GreetingMailService;
use App\Greeting\Service\GreetingService;
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
        private readonly GreetingImportHandler $greetingImportHandler,
        private readonly GreetingMailService $greetingMailService,
        private readonly TranslatorInterface $translator,
        private readonly GreetingService $greetingService,
        private readonly EmailGeneratorService $emailGeneratorService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/greeting/dashboard', name: 'greeting_dashboard_default', methods: ['GET'])]
    #[Route('/{_locale}/greeting/dashboard', name: 'greeting_dashboard', requirements: ['_locale' => '%app.supported_locales%'], methods: ['GET'])]
    public function index(): Response
    {
        $importForm = $this->createForm(GreetingImportType::class);
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

    #[Route('/{_locale}/greeting/import', name: 'greeting_import', requirements: ['_locale' => '%app.supported_locales%'], methods: ['POST'])]
    public function import(Request $request): Response
    {
        $importForm = $this->createForm(GreetingImportType::class);
        $importForm->handleRequest($request);

        if ($importForm->isSubmitted()) {
            if ($importForm->isValid()) {
                /** @var array{emails: ?string, language: GreetingLanguage, registrationDate: \DateTime} $data */
                $data = $importForm->getData();
                /** @var UploadedFile|null $xmlFile */
                $xmlFile = $importForm->get('xmlFile')->getData();

                try {
                    $xmlContent = $xmlFile ? file_get_contents($xmlFile->getPathname()) : null;
                    $textContent = $data['emails'] ?? null;

                    $countNewEmails = $this->greetingImportHandler->handleImport(
                        $xmlContent !== false ? $xmlContent : null,
                        $textContent,
                        $data['language']
                    );

                    if ($countNewEmails === 0 && empty($xmlContent) && empty($textContent)) {
                        $this->addFlash('error', $this->translator->trans('import.error_no_data', [], 'greeting'));
                    } else {
                        $message = $countNewEmails > 0
                            ? $this->translator->trans('import.success', ['%count%' => $countNewEmails], 'greeting')
                            : $this->translator->trans('import.success_zero', [], 'greeting');
                        $this->addFlash('success', $message);
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', $this->translator->trans('import.error_xml_parsing', [], 'greeting'));
                }
            } else {
                $this->addFlash('error', $this->translator->trans('import.error_validation', [], 'greeting'));
            }
        }

        return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/{_locale}/greeting/send', name: 'greeting_send', requirements: ['_locale' => '%app.supported_locales%'], methods: ['POST'])]
    public function send(Request $request): Response
    {
        $selectedIds = $request->request->all('contacts');
        $subject = (string) $request->request->get('subject');
        $body = (string) $request->request->get('body');

        if (empty($selectedIds)) {
            $this->addFlash('error', $this->translator->trans(
                'dashboard.send_error_no_selection',
                [],
                'greeting'
            ));
        } else {
            $count = $this->greetingMailService->sendGreetings($selectedIds, $subject, $body);

            $this->addFlash('success', $this->translator->trans('dashboard.send_success_queued', [
                '%count%' => $count,
            ], 'greeting'));
        }

        return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
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
