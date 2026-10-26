<?php

declare(strict_types=1);

final class Person
{
    // Allgemeine Altersgrenzen; die Freigabe für ein bestimmtes Spiel prüft Game zusätzlich.
    public const int MIN_AGE = 0;
    public const int MAX_AGE = 99;

    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Der Name darf nicht leer sein.');
        }

        if ($age < self::MIN_AGE || $age > self::MAX_AGE) {
            throw new InvalidArgumentException(sprintf(
                'Das Alter muss zwischen %d und %d Jahren liegen.',
                self::MIN_AGE,
                self::MAX_AGE,
            ));
        }
    }
}
