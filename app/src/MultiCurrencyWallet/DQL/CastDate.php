<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Vlastní DQL funkce PG_DATE pro PostgreSQL.
 * Tato funkce slouží k přetypování (CAST) výrazu s časovým údajem na kalendářní datum.
 *
 * Použití v DQL: PG_DATE(e.fetchedAt)
 * Výsledné SQL: CAST(e.fetched_at AS DATE)
 */
class CastDate extends FunctionNode
{
    /** @var Node|string Výraz s datem, který se má přetypovat. */
    private Node|string $dateExpression;

    /**
     * Zpracuje (parse) DQL výraz a extrahuje argumenty funkce.
     *
     * @throws QueryException
     */
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->dateExpression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    /**
     * Vygeneruje odpovídající SQL kód pro PostgreSQL.
     */
    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            'CAST(%s AS DATE)',
            $this->dateExpression instanceof Node ? $this->dateExpression->dispatch($sqlWalker) : $this->dateExpression
        );
    }
}
