<?php

declare(strict_types=1);

namespace App\Greeting\Controller;

use App\Enum\Status;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Factory\GreetingContactFactory;
use App\Greeting\Form\GreetingImportType;
use App\Greeting\Repository\GreetingContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GreetingUiController extends AbstractController
{
    public function __construct(
        private readonly GreetingContactRepository $greetingContactRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GreetingContactFactory $greetingContactFactory,
    ) {
    }

    #[Route('/greeting/dashboard', name: 'greeting_dashboard_default')]
    #[Route('/{_locale}/greeting/dashboard', name: 'greeting_dashboard', requirements: ['_locale' => 'cs|en|ru'])]
    public function dashboard(Request $request): Response
    {
        // --- Část 1: Zpracování importu ---
        $importForm = $this->createForm(GreetingImportType::class);
        $importForm->handleRequest($request);

        if ($importForm->isSubmitted() && $importForm->isValid()) {
            $data = $importForm->getData();
            $countNewEmails = $this->handleImport($data);
            $this->addFlash('success', 'Kontakty úspěšně importovány: ' . $countNewEmails);

            return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
        }

        // --- Část 2: Zpracování odeslání (zjednodušené) ---
        if ($request->isMethod('POST') && $request->request->has('send_greeting')) {
            $selectedIds = $request->request->all('contacts');
            $subject = $request->request->get('subject');
            $body = $request->request->get('body');

            if (empty($selectedIds)) {
                $this->addFlash('error', 'Nebyl vybrán žádný kontakt.');
            } else {
                // Zde by byla logika odesílání
                $count = \count($selectedIds);
                $this->addFlash('success', "Simulace odeslání {$count} e-mailů s předmětem '{$subject}'.");

                return $this->redirectToRoute('greeting_dashboard', ['_locale' => $request->getLocale()]);
            }
        }

        // --- Příprava dat pro seznam. ---
        // Získáme všechny aktivní kontakty
        $contacts = $this->greetingContactRepository->findBy(['status' => Status::Active], ['createdAt' => 'DESC']);

        // Seskupíme podle jazyka
        $groupedContacts = [];

        foreach ($contacts as $contact) {
            $lang = $contact->getLanguage()->name;
            $groupedContacts[$lang][] = $contact;
        }

        return $this->render('greeting/dashboard.html.twig', [
            'import_form' => $importForm->createView(),
            'grouped_contacts' => $groupedContacts,
        ]);
    }

    /**
     * @param array{emails: string, registrationDate: \DateTime, language: GreetingLanguage} $data
     */
    private function handleImport(array $data): int
    {
        $language = $data['language'];
        $registrationDate = \DateTimeImmutable::createFromMutable($data['registrationDate']);
        $emails = $this->listEmailsIntoArray($data['emails']);
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

    /**
     * Seznam e-mailů do pole.
     *
     * @return array<string>
     */
    private function listEmailsIntoArray(string $rawEmails): array
    {
        // Oddělovače: čárka, mezera, nový řádek, středník
        $emails = (array) preg_split('/[\s,;]+/', $rawEmails, -1, \PREG_SPLIT_NO_EMPTY);
        $uniqueEmails = array_unique($emails);
        /** @var array<string> $uniqueEmails */
        $filteredEmails = array_filter(
            $uniqueEmails,
            static fn (string $email): bool => filter_var($email, \FILTER_VALIDATE_EMAIL) !== false
        );

        return array_values($filteredEmails);
    }
}
