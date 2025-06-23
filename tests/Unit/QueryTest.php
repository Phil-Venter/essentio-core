<?php

use Essentio\Core\Extra\Query;

function setupDatabase(): PDO
{
    $pdo = new PDO("sqlite::memory:");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            active INTEGER
        );
    ");
    return $pdo;
}

it("inserts a row and returns its ID", function () {
    $pdo = setupDatabase();

    $query = new Query($pdo);
    $query->table("users");
    $id = $query->insert(["name" => "Alice", "active" => 1]);

    expect($id)->toBe(1);
});

it("retrieves a single row with where clause", function () {
    $pdo = setupDatabase();
    $pdo->exec("INSERT INTO users (name, active) VALUES ('Bob', 1)");

    $query = new Query($pdo);
    $row = $query->table("users")->where("name", "=", "Bob")->first();

    expect($row["name"])->toBe("Bob");
    expect((int) $row["active"])->toBe(1);
});

it("updates rows based on condition", function () {
    $pdo = setupDatabase();
    $pdo->exec("INSERT INTO users (name, active) VALUES ('Charlie', 0)");

    $query = new Query($pdo);
    $success = $query
        ->table("users")
        ->where("name", "=", "Charlie")
        ->update(["active" => 1]);

    expect($success)->toBeTrue();

    $row = $query->table("users")->where("name", "=", "Charlie")->first();
    expect((int) $row["active"])->toBe(1);
});

it("deletes a row", function () {
    $pdo = setupDatabase();
    $pdo->exec("INSERT INTO users (name, active) VALUES ('Dave', 1)");

    $query = new Query($pdo);
    $success = $query->table("users")->where("name", "=", "Dave")->delete();

    expect($success)->toBeTrue();

    $row = $query->table("users")->where("name", "=", "Dave")->first();
    expect($row)->toBe([]);
});

it("supports limit and ordering", function () {
    $pdo = setupDatabase();
    $pdo->exec("INSERT INTO users (name, active) VALUES ('Eve', 1), ('Zoe', 0), ('Alan', 1)");

    $query = new Query($pdo);
    $rows = iterator_to_array($query->table("users")->orderBy("name", "ASC")->limit(2)->get());

    expect(count($rows))->toBe(2);
    expect($rows[0]["name"])->toBe("Alan");
});
