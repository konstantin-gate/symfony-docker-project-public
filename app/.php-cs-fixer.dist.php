<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->notPath('src/Kernel.php')
    ->notPath('config/bundles.php')
    ->notPath('config/preload.php')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP80Migration:risky' => true,
        '@PHPUnit84Migration:risky' => true,
        'declare_strict_types' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'yoda_style' => false,
        'blank_line_before_statement' => [
            'statements' => ['foreach', 'return', 'if', 'try', 'while'],
        ],
    ])
    ->setFinder($finder)
;
