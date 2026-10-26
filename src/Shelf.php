<?php

declare(strict_types=1);

final class Shelf
{
    /** @var array<int, Game> Spiele, nach ihrer eindeutigen ID indiziert. */
    private array $games = [];

    public function __construct(
        public readonly int $id,
        public readonly int $capacity,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException('Die Regal-ID muss eine positive ganze Zahl sein.');
        }

        if ($capacity < 1) {
            throw new InvalidArgumentException('Ein Regal muss mindestens einen Platz besitzen.');
        }
    }

    public function addGame(Game $game): void
    {
        if (isset($this->games[$game->id])) {
            throw new InvalidArgumentException(sprintf('Die Spiel-ID %d ist in Regal %d bereits vorhanden.', $game->id, $this->id));
        }

        if (count($this->games) >= $this->capacity) {
            throw new OverflowException(sprintf('Regal %d ist voll (Kapazität: %d).', $this->id, $this->capacity));
        }

        // Auch ausgeliehene Spiele behalten ihren Platz im Regal.
        $this->games[$game->id] = $game;
    }

    /** @return list<Game> */
    public function getGames(): array
    {
        // Nach außen eine fortlaufende Liste liefern, damit Aufrufer die internen ID-Schlüssel nicht kennen müssen.
        return array_values($this->games);
    }
}
