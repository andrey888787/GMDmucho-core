<?php

declare(strict_types=1);

use MuchoCore\Database\Database;
use MuchoCore\Database\Migrator;
use MuchoCore\Migration\CvoltonDatabaseImporter;
use MuchoCore\Migration\SourceDetector;
use PDO;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== "cli") { exit(1); }
require dirname(__DIR__) . "/vendor/autoload.php";

function ask(string $prompt, bool $secret = false): string
{
    fwrite(STDOUT, $prompt);
    if (!$secret) {
        $value = fgets(STDIN);
        return trim($value === false ? "" : $value);
    }
    $tty = trim((string)shell_exec("stty -g 2>/dev/null"));
    if ($tty !== "") { shell_exec("stty -echo"); }
    try {
        $value = fgets(STDIN);
    } finally {
        if ($tty !== "") {
            shell_exec("stty " . escapeshellarg($tty));
            fwrite(STDOUT, PHP_EOL);
        }
    }
    return trim($value === false ? "" : $value);
}

function connectSource(string $host, int $port, string $database, string $user, string $password): PDO
{
    if (!preg_match("/^[A-Za-z0-9._:-]+$/", $host)) throw new RuntimeException("Invalid source host.");
    if (!preg_match("/^[A-Za-z0-9_-]{1,64}$/", $database)) throw new RuntimeException("Invalid source database name.");
    if ($user === "" || strlen($user) > 128) throw new RuntimeException("Invalid source database user.");
    if ($port < 1 || $port > 65535) throw new RuntimeException("Invalid source port.");
    return new PDO(
        "mysql:host=".$host.";port=".$port.";dbname=".$database.";charset=utf8mb4",
        $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION TRANSACTION READ ONLY",
        ]
    );
}

function printPreview(array $detected, array $preflight): void
{
    echo PHP_EOL."Detected source: ".$detected["label"].PHP_EOL;
    echo "Confidence:      ".$detected["confidence"].PHP_EOL;
    echo PHP_EOL."Migration preview".PHP_EOL."-----------------".PHP_EOL;
    foreach ($preflight as $table => $count) {
        echo sprintf("  %-16s %10d rows".PHP_EOL, $table, $count);
    }
}

$options = getopt("", [
    "source-host:", "source-port::", "source-db:", "source-user:", "source-pass::",
    "apply", "confirm:", "json", "help",
]);

if (isset($options["help"])) {
    echo "MuchoCore Migration Center".PHP_EOL.PHP_EOL;
    echo "Interactive: php bin/mucho-migrate.php".PHP_EOL;
    echo "Dry-run is the default; apply requires --confirm=MIGRATE or interactive MIGRATE confirmation.".PHP_EOL;
    exit(0);
}

try {
    echo "MuchoCore Migration Center".PHP_EOL;
    echo "==========================".PHP_EOL;
    echo "Source connection: READ ONLY".PHP_EOL;
    echo "Default mode: DRY RUN".PHP_EOL;

    $host = (string)($options["source-host"] ?? "");
    $port = (int)($options["source-port"] ?? 3306);
    $database = (string)($options["source-db"] ?? "");
    $user = (string)($options["source-user"] ?? "");
    $password = array_key_exists("source-pass", $options)
        ? (string)$options["source-pass"]
        : (string)(getenv("MUCHO_MIGRATION_SOURCE_PASS") ?: "");

    if ($host === "") $host = ask("Source DB host: ");
    if (!isset($options["source-port"])) {
        $entered = ask("Source DB port [3306]: ");
        if ($entered !== "") $port = (int)$entered;
    }
    if ($database === "") $database = ask("Source database name: ");
    if ($user === "") $user = ask("Source database user: ");
    if ($password === "") $password = ask("Source database password: ", true);

    $source = connectSource($host, $port, $database, $user, $password);
    $detected = (new SourceDetector())->detect($source);

    if ($detected["engine"] === "unknown") {
        echo PHP_EOL."Unsupported source schema. Nothing was changed.".PHP_EOL;
        exit(3);
    }

    $target = (new Database())->connection();
    (new Migrator($target, dirname(__DIR__) . "/database/migrations"))->migrate();
    $importer = new CvoltonDatabaseImporter($target);
    $preflight = $importer->preflight($source);

    if (isset($options["json"])) {
        echo json_encode(["mode"=>isset($options["apply"]) ? "apply-requested" : "dry-run","source"=>$detected,"preflight"=>$preflight], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    } else {
        printPreview($detected, $preflight);
    }

    if (!isset($options["apply"])) {
        echo PHP_EOL."DRY-RUN complete. The destination was not modified by the import.".PHP_EOL;
        exit(0);
    }

    if (($options["confirm"] ?? "") !== "MIGRATE") {
        $confirm = ask(PHP_EOL."Type MIGRATE to apply: ");
        if ($confirm !== "MIGRATE") {
            echo "Migration cancelled.".PHP_EOL;
            exit(4);
        }
    }

    $target->beginTransaction();
    try {
        $stats = $importer->apply($source);
        $target->commit();
    } catch (Throwable $e) {
        if ($target->inTransaction()) $target->rollBack();
        throw $e;
    }

    echo PHP_EOL."MIGRATION COMPLETE".PHP_EOL;
    foreach ($stats as $name => $value) {
        echo sprintf("  %-30s %d".PHP_EOL, $name, $value);
    }
    if (($stats["password_resets_required"] ?? 0) > 0) {
        echo PHP_EOL."Password resets required: ".$stats["password_resets_required"].PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: ".$e->getMessage().PHP_EOL);
    exit(10);
}