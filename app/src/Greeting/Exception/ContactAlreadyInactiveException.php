<?php

declare(strict_types=1);

namespace App\Greeting\Exception;

/**
 * Výjimka vyhazovaná při pokusu o deaktivaci již neaktivního kontaktu.
 */
class ContactAlreadyInactiveException extends \RuntimeException
{
}
