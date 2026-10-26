<?php

declare(strict_types=1);

final class Game
{
    // Die gespeicherte Person bildet den Ausleihstatus ab: null bedeutet verfügbar.
    private ?Person $borrower = null;

    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $platform,
        public readonly int $usk,
        public readonly int $minPlayers,
        public readonly int $maxPlayers,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException('Die Spiel-ID muss eine positive ganze Zahl sein.');
        }

        if (trim($title) === '' || trim($platform) === '') {
            throw new InvalidArgumentException('Titel und Plattform dürfen nicht leer sein.');
        }

        if (!in_array($usk, [0, 6, 12, 16, 18], true)) {
            throw new InvalidArgumentException('Die USK-Freigabe muss 0, 6, 12, 16 oder 18 sein.');
        }

        if ($minPlayers < 1 || $maxPlayers < $minPlayers) {
            throw new InvalidArgumentException(
                'Die Spieleranzahl muss mindestens 1 sein; das Maximum darf nicht kleiner als das Minimum sein.',
            );
        }
    }

    public function isAvailable(): bool
    {
        return $this->borrower === null;
    }

    public function getBorrower(): ?Person
    {
        return $this->borrower;
    }

    public function borrow(Person $person): void
    {
        if (!$this->isAvailable()) {
            throw new DomainException(sprintf('„%s“ ist bereits ausgeliehen.', $this->title));
        }

        if ($person->age < $this->usk) {
            throw new DomainException(sprintf('„%s“ ist erst ab %d Jahren freigegeben.', $this->title, $this->usk));
        }

        // Den Zustand erst ändern, wenn Verfügbarkeit und Alter erfolgreich geprüft wurden.
        $this->borrower = $person;
    }

    public function returnGame(): void
    {
        if ($this->isAvailable()) {
            throw new DomainException(sprintf('„%s“ ist nicht ausgeliehen und kann nicht zurückgegeben werden.', $this->title));
        }

        $this->borrower = null;
    }
}
