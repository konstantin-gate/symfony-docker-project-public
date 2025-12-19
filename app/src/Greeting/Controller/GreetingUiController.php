<?php

declare(strict_types=1);

namespace App\Greeting\Controller;

use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Factory\GreetingContactFactory;
use App\Greeting\Form\GreetingImportType;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Service\GreetingEmailParser;
use App\Greeting\Service\GreetingService;
use App\Greeting\Service\EmailGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class GreetingUiController extends AbstractController
{
    public function __construct(
        private readonly GreetingContactRepository $greetingContactRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GreetingContactFactory $greetingContactFactory,
        private readonly TranslatorInterface $translator,
        private readonly GreetingEmailParser $greetingEmailParser,
        private readonly GreetingService $greetingService,
        private readonly EmailGeneratorService $emailGeneratorService,
    ) {
    }

    #[Route('/greeting/dashboard', name: 'greeting_dashboard_default')]
    #[Route('/{_locale}/greeting/dashboard', name: 'greeting_dashboard', requirements: ['_locale' => 'cs|en|ru'])]
    public function dashboard(Request $request): Response
    {
        $importForm = $this->createForm(GreetingImportType::class);
        $importForm->handleRequest($request);

        if ($importForm->isSubmitted() && $importForm->isValid()) {
            $data = $importForm->getData();
            $countNewEmails = $this->handleImport($data);
            $message = $countNewEmails > 0
                ? \sprintf($this->translator->trans('import.success', [], 'greeting'), $countNewEmails)
                : $this->translator->trans('import.success_zero', [], 'greeting');
            $this->addFlash('success', $message);

            return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
        }

        if ($request->isMethod('POST') && $request->request->has('send_greeting')) {
            $selectedIds = $request->request->all('contacts');
            $subject = $request->request->get('subject');
            $body = $request->request->get('body');

            if (empty($selectedIds)) {
                $this->addFlash('error', $this->translator->trans('dashboard.send_error_no_selection', [], 'greeting'));
            } else {
                // TODO: Zde bude logika odesílání

                $count = \count($selectedIds);
                $this->addFlash('success', $this->translator->trans('dashboard.send_success_simulation', [
                    '%count%' => $count,
                    '%subject%' => $subject,
                ], 'greeting'));

                return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
            }
        }

        $groupedContacts = $this->greetingService->getContactsGroupedByLanguage();

        return $this->render('@Greeting/dashboard.html.twig', [
            'import_form' => $importForm->createView(),
            'grouped_contacts' => $groupedContacts,
        ]);
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

    /**
     * @param array{emails: string, registrationDate: \DateTime, language: GreetingLanguage} $data
     */
    private function handleImport(array $data): int
    {
        $language = $data['language'];
        $registrationDate = \DateTimeImmutable::createFromMutable($data['registrationDate']);
        $emails = $this->greetingEmailParser->parse($data['emails']);
        $newEmails = $this->greetingContactRepository->findNonExistingEmails($emails);

        if (!empty($newEmails)) {
            foreach ($newEmails as $email) {
                $contact = $this->greetingContactFactory->create($email, $language, $registrationDate);
                $this->entityManager->persist($contact);
            }

            $this->entityManager->flush();
        }

        return \count($newEmails);
    }
}
