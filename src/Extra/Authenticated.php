<?php

namespace Essentio\Core\Extra;

use Essentio\Core\{Application, Session};
use PDO;
use SensitiveParameter;

class Authenticated
{
    protected const USER_KEY = '\0USER';

    public function __construct(protected PDO $pdo, protected Session $session) {}

    public static function create(?PDO $pdo = null, ?Session $session = null): static
    {
        return new static(
            $pdo ?? Application::$container->resolve(PDO::class),
            $session ?? Application::$container->resolve(Session::class)
        );
    }

    public function user(): ?object
    {
        return ($user = $this->session->get(static::USER_KEY)) ? (object) $user : null;
    }

    public function login(string $username, #[SensitiveParameter] string $password): bool
    {
        if ($this->session->get(static::USER_KEY)) {
            return true;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");

        if (!$stmt) {
            return false;
        }

        $stmt->bindValue(1, $username, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, (string) $row["password"])) {
            return false;
        }

        unset($row["password"]);
        session_regenerate_id(true);
        $this->session->set(static::USER_KEY, $row);
        return true;
    }

    public function logout(): void
    {
        $this->session->set(static::USER_KEY, null);
        session_regenerate_id(true);
    }
}
