<?php

declare(strict_types=1);

final class GameStore
{
    /** @var array<int, Shelf> */
    private array $shelves = [];

    public function addShelf(int $id, int $capacity): void
    {
        if (isset($this->shelves[$id])) {
            throw new InvalidArgumentException(sprintf('Die Regal-ID %d ist bereits vorhanden.', $id));
        }

        // Der Laden besitzt seine Regale selbst, damit alle Zugänge über addGame laufen.
        $this->shelves[$id] = new Shelf($id, $capacity);
    }

    public function addGame(int $shelfId, Game $game): void
    {
        $shelf = $this->shelves[$shelfId]
            ?? throw new OutOfBoundsException(sprintf('Regal %d existiert nicht.', $shelfId));

        // Alle Regale prüfen, damit jede Spiel-ID im gesamten Laden eindeutig bleibt.
        foreach ($this->getAllGames() as $existingGame) {
            if ($existingGame->id === $game->id) {
                throw new InvalidArgumentException(sprintf('Die Spiel-ID %d ist bereits vorhanden.', $game->id));
            }
        }

        $shelf->addGame($game);
    }

    public function addNewGame(
        int $shelfId,
        string $title,
        string $platform,
        int $usk,
        int $minPlayers,
        int $maxPlayers,
    ): Game {
        // Auch vorgegebene IDs berücksichtigen, wenn Spiele über addGame angelegt wurden.
        $highestId = 0;

        foreach ($this->getAllGames() as $game) {
            $highestId = max($highestId, $game->id);
        }

        if ($highestId === PHP_INT_MAX) {
            throw new OverflowException('Es können keine weiteren Spiel-IDs vergeben werden.');
        }

        $game = new Game($highestId + 1, $title, $platform, $usk, $minPlayers, $maxPlayers);
        $this->addGame($shelfId, $game);

        return $game;
    }

    /** @return list<Game> */
    public function getGamesInShelf(int $id): array
    {
        $shelf = $this->shelves[$id]
            ?? throw new OutOfBoundsException(sprintf('Regal %d existiert nicht.', $id));

        return $shelf->getGames();
    }

    /** @return list<Game> */
    public function getAllGames(): array
    {
        $games = [];

        foreach ($this->shelves as $shelf) {
            foreach ($shelf->getGames() as $game) {
                $games[] = $game;
            }
        }

        return $games;
    }

    /** @return list<Game> */
    public function searchGames(string $title): array
    {
        $title = trim($title);

        if ($title === '') {
            throw new InvalidArgumentException('Bitte einen Suchbegriff eingeben.');
        }

        // preg_quote behandelt Sonderzeichen im Suchbegriff als normalen Text.
        // /iu ignoriert Groß-/Kleinschreibung auch bei Umlauten.
        $pattern = '/' . preg_quote($title, '/') . '/iu';

        return array_values(array_filter(
            $this->getAllGames(),
            static fn (Game $game): bool => preg_match($pattern, $game->title) === 1,
        ));
    }

    public function getGameById(int $id): Game
    {
        foreach ($this->getAllGames() as $game) {
            if ($game->id === $id) {
                return $game;
            }
        }

        throw new OutOfBoundsException(sprintf('Spiel %d existiert nicht.', $id));
    }

    public function borrowGame(int $id, Person $person): void
    {
        $this->getGameById($id)->borrow($person);
    }

    public function returnGame(int $id): void
    {
        $this->getGameById($id)->returnGame();
    }

    /**
     * Liefert nur Regaldaten, damit Aufrufer die verwalteten Regale nicht direkt verändern können.
     *
     * @return list<array{id: int, capacity: int, gameCount: int}>
     */
    public function getShelfOverview(): array
    {
        $overview = [];

        foreach ($this->shelves as $shelf) {
            $overview[] = [
                'id' => $shelf->id,
                'capacity' => $shelf->capacity,
                'gameCount' => count($shelf->getGames()),
            ];
        }

        return $overview;
    }
}
