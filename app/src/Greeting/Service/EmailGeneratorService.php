<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use Faker\Factory;
use Faker\Generator;

class EmailGeneratorService
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * @return array<int, string>
     */
    public function generateEmails(int $count): array
    {
        $emails = [];

        for ($i = 0; $i < $count; ++$i) {
            $emails[] = $this->faker->unique()->safeEmail;
        }

        return $emails;
    }
}
