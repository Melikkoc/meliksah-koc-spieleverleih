<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Person.php';
require_once __DIR__ . '/src/Game.php';
require_once __DIR__ . '/src/Shelf.php';
require_once __DIR__ . '/src/GameStore.php';
require_once __DIR__ . '/src/JsonStorage.php';

function readInput(string $prompt): string
{
    echo $prompt;
    $input = fgets(STDIN);

    if ($input === false) {
        echo "\nEingabe beendet. Auf Wiedersehen!\n";
        exit(0);
    }

    return trim($input);
}

function readInteger(string $prompt): int
{
    // Auch Dezimalzahlen, Text und Werte außerhalb des int-Bereichs werden abgewiesen.
    $value = filter_var(readInput($prompt), FILTER_VALIDATE_INT);

    if ($value === false) {
        throw new InvalidArgumentException('Bitte eine gültige ganze Zahl eingeben.');
    }

    return $value;
}

/** @param list<Game> $games */
function printGames(array $games): void
{
    if ($games === []) {
        echo "Keine Spiele gefunden.\n";
        return;
    }

    foreach ($games as $game) {
        $players = $game->minPlayers === $game->maxPlayers
            ? (string) $game->minPlayers
            : $game->minPlayers . '–' . $game->maxPlayers;
        $borrower = $game->getBorrower();

        printf(
            "[%d] %s | %s | USK %d | Spieler: %s | %s\n",
            $game->id,
            $game->title,
            $game->platform,
            $game->usk,
            $players,
            $borrower === null ? 'verfügbar' : 'ausgeliehen an ' . $borrower->name,
        );
    }
}

function printShelves(GameStore $store): void
{
    foreach ($store->getShelfOverview() as $shelf) {
        printf(
            "Regal %d: %d/%d Plätze belegt (%d frei).\n",
            $shelf['id'],
            $shelf['gameCount'],
            $shelf['capacity'],
            $shelf['capacity'] - $shelf['gameCount'],
        );
    }

    echo "Ausgeliehene Spiele behalten ihren Regalplatz.\n";
}

try {
    $storage = new JsonStorage(__DIR__ . '/spieleverleih.json');
    $store = $storage->load();

    // Nur bei einer fehlenden Datendatei den Ausgangsbestand anlegen.
    if ($store === null) {
        $store = new GameStore();
        $store->addShelf(1, 3);
        $store->addShelf(2, 2);
        $store->addNewGame(1, 'Mario Kart 8', 'Switch', 0, 1, 4);
        $store->addNewGame(2, 'Die Siedler von Catan', 'Brettspiel', 6, 3, 4);
        $store->addNewGame(1, 'The Last of Us', 'PlayStation', 18, 1, 1);
        $storage->save($store);
    }
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'Fehler beim Start: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "Willkommen beim Spieleverleih!\n";

while (true) {
    echo "\n1. Alle Spiele anzeigen\n"
        . "2. Spiel suchen\n"
        . "3. Spiel ausleihen\n"
        . "4. Spiel zurückgeben\n"
        . "5. Regale und Kapazitäten anzeigen\n"
        . "6. Neues Spiel zu einem Regal hinzufügen\n"
        . "0. Programm beenden\n\n";

    try {
        switch (readInput('Auswahl: ')) {
            case '1':
                printGames($store->getAllGames());
                break;

            case '2':
                printGames($store->searchGames(readInput('Titel oder Teil des Titels: ')));
                break;

            case '3':
                $gameId = readInteger('Spiel-ID: ');
                printGames([$store->getGameById($gameId)]);
                $name = readInput('Name: ');
                $age = readInteger(sprintf('Alter (%d–%d Jahre): ', Person::MIN_AGE, Person::MAX_AGE));
                $person = new Person($name, $age);
                $store->borrowGame($gameId, $person);
                $storage->save($store);
                printf("„%s“ wurde an %s ausgeliehen.\n", $store->getGameById($gameId)->title, $person->name);
                break;

            case '4':
                $gameId = readInteger('Spiel-ID: ');
                $store->returnGame($gameId);
                $storage->save($store);
                printf("„%s“ wurde zurückgegeben und ist wieder verfügbar.\n", $store->getGameById($gameId)->title);
                break;

            case '5':
                printShelves($store);
                break;

            case '6':
                printShelves($store);
                echo "Die Spiel-ID wird automatisch vergeben.\n";
                $shelfId = readInteger('Regal-ID: ');
                $game = $store->addNewGame(
                    $shelfId,
                    readInput('Titel: '),
                    readInput('Plattform: '),
                    readInteger('USK (0, 6, 12, 16, 18): '),
                    readInteger('Minimale Spieleranzahl: '),
                    readInteger('Maximale Spieleranzahl: '),
                );
                $storage->save($store);
                printf("„%s“ wurde mit Spiel-ID %d zu Regal %d hinzugefügt.\n", $game->title, $game->id, $shelfId);
                break;

            case '0':
                echo "Auf Wiedersehen!\n";
                exit(0);

            default:
                echo "Ungültige Auswahl. Bitte einen Menüpunkt von 0 bis 6 wählen.\n";
        }
    } catch (InvalidArgumentException | DomainException | OutOfBoundsException | OverflowException $exception) {
        // Nach einem erwartbaren Fehler kann der Benutzer im Hauptmenü eine neue Aktion wählen.
        echo 'Fehler: ' . $exception->getMessage() . "\n";
    } catch (RuntimeException $exception) {
        fwrite(STDERR, 'Fehler beim Speichern: ' . $exception->getMessage()
            . "\nDas Programm wird beendet; die aktuelle Änderung wurde nicht gespeichert.\n");
        exit(1);
    }
}
