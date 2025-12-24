<?php

declare(strict_types=1);

namespace App\Greeting\Exception;

/**
 * Výjimka vyhazovaná při pokusu o smazání již smazaného kontaktu.
 */
class ContactAlreadyDeletedException extends \RuntimeException
{
}
