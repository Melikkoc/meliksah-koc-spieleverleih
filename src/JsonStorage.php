<?php

declare(strict_types=1);

final class JsonStorage
{
    /** @var resource|null */
    private mixed $lock = null;

    public function __construct(private readonly string $path)
    {
        // Die Sperre gilt vom Laden bis zum Programmende, damit kein veralteter Bestand zurückgeschrieben wird.
        $lock = @fopen($path . '.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Die Datendatei kann nicht gesperrt werden. Bitte den Pfad und die Schreibrechte prüfen.');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Die Datendatei wird bereits von einem anderen Programmstart verwendet.');
        }

        $this->lock = $lock;
    }

    public function __destruct()
    {
        if (is_resource($this->lock)) {
            fclose($this->lock);
        }
    }

    public function load(): ?GameStore
    {
        if (!file_exists($this->path)) {
            return null;
        }

        $json = @file_get_contents($this->path);

        if ($json === false) {
            throw new RuntimeException('Die Datendatei kann nicht gelesen werden.');
        }

        try {
            $data = $this->object(json_decode($json, false, 512, JSON_THROW_ON_ERROR));
            $store = new GameStore();

            foreach ($this->field($data, 'shelves', 'array') as $shelfData) {
                $shelfData = $this->object($shelfData);
                $shelfId = $this->field($shelfData, 'id', 'int');
                $store->addShelf($shelfId, $this->field($shelfData, 'capacity', 'int'));

                foreach ($this->field($shelfData, 'games', 'array') as $gameData) {
                    $gameData = $this->object($gameData);
                    $game = new Game(
                        $this->field($gameData, 'id', 'int'),
                        $this->field($gameData, 'title', 'string'),
                        $this->field($gameData, 'platform', 'string'),
                        $this->field($gameData, 'usk', 'int'),
                        $this->field($gameData, 'minPlayers', 'int'),
                        $this->field($gameData, 'maxPlayers', 'int'),
                    );
                    $store->addGame($shelfId, $game);

                    if (!property_exists($gameData, 'borrower')) {
                        throw new UnexpectedValueException('Das Feld „borrower“ fehlt.');
                    }

                    if ($gameData->borrower !== null) {
                        $borrower = $this->object($gameData->borrower);
                        // Auch gespeicherte Ausleihen durchlaufen die Alters- und Verfügbarkeitsprüfung.
                        $store->borrowGame($game->id, new Person(
                            $this->field($borrower, 'name', 'string'),
                            $this->field($borrower, 'age', 'int'),
                        ));
                    }
                }
            }

            return $store;
        } catch (JsonException | LogicException | RuntimeException $exception) {
            throw new RuntimeException('Die Datendatei ist ungültig: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function save(GameStore $store): void
    {
        $data = ['shelves' => []];

        foreach ($store->getShelfOverview() as $shelf) {
            $games = [];

            foreach ($store->getGamesInShelf($shelf['id']) as $game) {
                $borrower = $game->getBorrower();
                $games[] = [
                    'id' => $game->id,
                    'title' => $game->title,
                    'platform' => $game->platform,
                    'usk' => $game->usk,
                    'minPlayers' => $game->minPlayers,
                    'maxPlayers' => $game->maxPlayers,
                    'borrower' => $borrower === null ? null : ['name' => $borrower->name, 'age' => $borrower->age],
                ];
            }

            $data['shelves'][] = ['id' => $shelf['id'], 'capacity' => $shelf['capacity'], 'games' => $games];
        }

        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Die Daten können nicht als JSON gespeichert werden: ' . $exception->getMessage(), 0, $exception);
        }

        // Erst vollständig in eine temporäre Datei schreiben, dann die bisherige Datei atomar ersetzen.
        $directory = realpath(dirname($this->path));

        if ($directory === false || !is_writable($directory)) {
            throw new RuntimeException('Das Verzeichnis der Datendatei ist nicht beschreibbar.');
        }

        $temporaryFile = @tempnam($directory, '.spieleverleih-');

        if ($temporaryFile === false) {
            throw new RuntimeException('Eine temporäre Datendatei kann nicht angelegt werden.');
        }

        try {
            if (dirname($temporaryFile) !== $directory) {
                throw new RuntimeException('Die temporäre Datei konnte nicht im Datenverzeichnis angelegt werden.');
            }

            if (@file_put_contents($temporaryFile, $json) !== strlen($json)) {
                throw new RuntimeException('Die Daten konnten nicht vollständig geschrieben werden.');
            }

            if (!@rename($temporaryFile, $this->path)) {
                throw new RuntimeException('Die Datendatei konnte nicht ersetzt werden. Bitte die Schreibrechte prüfen.');
            }
        } finally {
            if (file_exists($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function object(mixed $value): stdClass
    {
        if (!$value instanceof stdClass) {
            throw new UnexpectedValueException('Ein JSON-Objekt für Laden, Regal, Spiel oder Person wurde erwartet.');
        }

        return $value;
    }

    private function field(stdClass $data, string $name, string $type): mixed
    {
        if (!property_exists($data, $name) || get_debug_type($data->{$name}) !== $type) {
            throw new UnexpectedValueException(sprintf('Das Feld „%s“ muss vom Typ %s sein.', $name, $type));
        }

        return $data->{$name};
    }
}
