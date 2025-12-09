<?php

declare(strict_types=1);

namespace App\Greeting\Controller;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Form\GreetingImportType;
use App\Greeting\Repository\GreetingContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/greeting')]
class GreetingUiController extends AbstractController
{
    public function __construct(
        private readonly GreetingContactRepository $greetingContactRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/dashboard', name: 'greeting_dashboard')]
    public function dashboard(Request $request): Response
    {
        // --- Часть 1: Обработка импорта ---
        $importForm = $this->createForm(GreetingImportType::class);
        $importForm->handleRequest($request);

        if ($importForm->isSubmitted() && $importForm->isValid()) {
            $data = $importForm->getData();
            $this->handleImport($data);
            $this->addFlash('success', 'Контакты успешно импортированы.');

            return $this->redirectToRoute('greeting_dashboard');
        }

        // --- Часть 2: Обработка отправки (упрощенная) ---
        if ($request->isMethod('POST') && $request->request->has('send_greeting')) {
            $selectedIds = $request->request->all('contacts');
            $subject = $request->request->get('subject');
            $body = $request->request->get('body');

            if (empty($selectedIds)) {
                $this->addFlash('error', 'Не выбрано ни одного контакта.');
            } else {
                // Здесь была бы логика отправки
                $count = \count($selectedIds);
                $this->addFlash('success', "Имитация отправки {$count} писем с темой '{$subject}'.");

                return $this->redirectToRoute('greeting_dashboard');
            }
        }

        // --- Подготовка данных для списка. ---
        // Получаем все активные контакты
        $contacts = $this->greetingContactRepository->findBy(['status' => Status::Active], ['createdAt' => 'DESC']);

        // Группируем по языку
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

    private function handleImport(array $data): void
    {
        $rawEmails = $data['emails'];
        /** @var \DateTimeImmutable $registrationDate */
        $registrationDate = $data['registrationDate'];
        $language = $data['language'];

        // Разделители: запятая, пробел, новая строка, точка с запятой
        $emails = preg_split('/[\s,;]+/', $rawEmails, -1, \PREG_SPLIT_NO_EMPTY);
        $uniqueEmails = array_unique($emails);

        foreach ($uniqueEmails as $email) {
            if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Проверяем дубликаты (простая проверка)
            if ($this->greetingContactRepository->findOneBy(['email' => $email])) {
                continue;
            }

            $contact = new GreetingContact();
            $contact->setEmail($email);
            $contact->setLanguage($language);
            $contact->setCreatedAt($registrationDate);
            $contact->setStatus(Status::Active);

            $this->entityManager->persist($contact);
        }

        $this->entityManager->flush();
    }
}
