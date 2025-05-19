<?php

namespace Essentio\Core\Extra;

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

    public protected(set) string $from = "";

    public protected(set) array $to = [];

    public protected(set) string $subject = "";

    public protected(set) string $text = "";

    public protected(set) string $html = "";

    public function __construct(
        string $url,
        protected string $username,
        protected string $password,
        int $port = 587
    ) {
        $this->url = sprintf("smtp://%s:%s", $url, $port);
    }

    /**
     * @param string $email
     * @return static
     */
    public function withFrom(string $email): static
    {
        $that = clone $this;
        $that->from = $email;
        return $that;
    }

    /**
     * @param string $email
     * @return static
     */
    public function addTo(string $email): static
    {
        $that = clone $this;
        $that->to[] = $email;
        return $that;
    }

    /**
     * @param list<string>|string $emails
     * @return static
     */
    public function withTo(array|string $emails): static
    {
        $that = clone $this;
        $that->to = is_string($emails) ? [$emails] : $emails;
        return $that;
    }

    /**
     * @param string $subject
     * @return static
     */
    public function withSubject(string $subject): static
    {
        $that = clone $this;
        $that->subject = $subject;
        return $that;
    }

    /**
     * @param string $text
     * @return static
     */
    public function withText(string $text): static
    {
        $that = clone $this;
        $that->text = $text;
        return $that;
    }

    /**
     * @param string $html
     * @return static
     */
    public function withHtml(string $html): static
    {
        $that = clone $this;
        $that->html = $html;
        return $that;
    }

    /**
     * @return true
     * @throws RuntimeException
     */
    public function send(): true
    {
        assert(!empty($this->from));
        assert(!empty($this->to));
        assert(!empty($this->subject));

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
            CURLOPT_USERNAME => $this->username,
            CURLOPT_PASSWORD => $this->password,
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
            CURLOPT_READFUNCTION => fn ($ch, $stream, $length): string|false => fread($stream, $length),
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
            throw new RuntimeException(sprintf("cURL error (%s): %s", $errno, $error));
        }

        if ($httpCode >= 400) {
            throw new RuntimeException(sprintf("Email send failed with HTTP status code: %s", $httpCode));
        }

        return true;
    }

    /**
     * @return string
     * @internal
     */
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
            $body = sprintf(<<<'EOT'
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
                EOT,
                $boundary, $this->text, $boundary, $this->html, $boundary
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
