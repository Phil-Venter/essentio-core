<?php

namespace Essentio\Core\Extra;

use Essentio\Core\{Application, Environment};
use RuntimeException;

use function array_map;
use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function date;
use function fclose;
use function fwrite;
use function implode;
use function rewind;
use function sprintf;
use function uniqid;

class Mailer
{
    protected string $url;

    public string $from = "";

    public array $to = [];

    public string $subject = "";

    public string $text = "";

    public string $html = "";

    public function __construct(string $url, protected string $user, protected string $pass, int $port = 587)
    {
        $this->url = sprintf("smtp://%s:%s", $url, $port);
    }

    public static function create(
        ?string $url = null,
        ?string $user = null,
        ?string $pass = null,
        ?int $port = null
    ): static {
        $env = Application::$container->resolve(Environment::class);

        return new static(
            $url ?? $env->get("MAILER_URL"),
            $user ?? $env->get("MAILER_USER"),
            $pass ?? $env->get("MAILER_PASS"),
            $port ?? 587
        );
    }

    public function setFrom(string $email): static
    {
        $this->from = $email;
        return $this;
    }

    public function addTo(string $email): static
    {
        $this->to[] = $email;
        return $this;
    }

    public function setTo(array|string $emails): static
    {
        $this->to = (array) $emails;
        return $this;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function setHtml(string $html): static
    {
        $this->html = $html;
        return $this;
    }

    public function send(): true
    {
        if (empty($this->from) || empty($this->to) || empty($this->subject)) {
            throw new RuntimeException("Required data missing.");
        }

        $stream = fopen("php://temp", "r+");
        if (!$stream) {
            throw new RuntimeException("Failed to open in-memory stream.");
        }

        fwrite($stream, $this->buildEmail());
        rewind($stream);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_MAIL_FROM => sprintf("<%s>", $this->from),
            CURLOPT_MAIL_RCPT => array_map(fn($to): string => sprintf("<%s>", $to), $this->to),
            CURLOPT_USERNAME => $this->user,
            CURLOPT_PASSWORD => $this->pass,
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
            CURLOPT_READFUNCTION => fn($ch, $stream, $length): string|false => fread($stream, $length),
            CURLOPT_INFILE => $stream,
            CURLOPT_UPLOAD => true,
            CURLOPT_VERBOSE => true,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);
        fclose($stream);

        if ($errno !== 0 || $result === false) {
            throw new RuntimeException("cURL error ({$errno}): {$error}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Email send failed with HTTP status code: {$httpCode}");
        }

        return true;
    }

    protected function buildEmail(): string
    {
        $headers = [
            sprintf("From: %s", $this->from),
            sprintf("To: %s", implode(", ", $this->to)),
            sprintf("Date: %s", date("r")),
            sprintf("Subject: %s", $this->subject),
            "MIME-Version: 1.0",
        ];

        if (!empty($this->text) && !empty($this->html)) {
            $boundary = uniqid("np");
            $headers[] = sprintf("Content-Type: multipart/alternative; boundary=%s", $boundary);
            $body = sprintf(
                <<<'EOT'
--%s\r
Content-Type: text/plain; charset=utf-8\r
\r
%s\r
\r
--%s\r
Content-Type: text/html; charset=utf-8\r
\r
%s\r
\r
--%s--\r
EOT
                ,
                $boundary,
                $this->text,
                $boundary,
                $this->html,
                $boundary
            );
        } elseif (!empty($this->html)) {
            $headers[] = "Content-Type: text/html; charset=utf-8";
            $body = $this->html;
        } else {
            $headers[] = "Content-Type: text/plain; charset=utf-8";
            $body = $this->text;
        }

        return sprintf("%s\r\n\r\n%s", implode("\r\n", $headers), $body);
    }
}
