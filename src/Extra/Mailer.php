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
    /** @var string */
    protected string $url;

    /** @var string */
    public protected(set) string $from = "";

    /** @var list<string> */
    public protected(set) array $to = [];

    /** @var string */
    public protected(set) string $subject = "";

    /** @var string */
    public protected(set) string $text = "";

    /** @var string */
    public protected(set) string $html = "";

    /**
     * Initializes a new Mailer instance.
     *
     * @param string $url  SMTP server hostname.
     * @param string $user SMTP username.
     * @param string $pass SMTP password.
     * @param int    $port SMTP port (default 587).
     */
    public function __construct(
        string $url,
        protected string $user,
        protected string $pass,
        int $port = 587
    ) {
        $this->url = sprintf("smtp://%s:%s", $url, $port);
    }

    /**
     * Sets the sender address.
     *
     * @param string $email Sender address.
     * @return static
     */
    public function withFrom(string $email): static
    {
        $that = clone $this;
        $that->from = $email;
        return $that;
    }

    /**
     * Adds a recipient address.
     *
     * @param string $email Recipient address.
     * @return static
     */
    public function addTo(string $email): static
    {
        $that = clone $this;
        $that->to[] = $email;
        return $that;
    }

    /**
     * Replaces recipient list with one or more addresses.
     *
     * @param list<string>|string $emails One or more recipient addresses.
     * @return static
     */
    public function withTo(array|string $emails): static
    {
        $that = clone $this;
        $that->to = (array) $emails;
        return $that;
    }

    /**
     * Sets the message subject.
     *
     * @param string $subject Message subject line.
     * @return static
     */
    public function withSubject(string $subject): static
    {
        $that = clone $this;
        $that->subject = $subject;
        return $that;
    }

    /**
     * Sets the plaintext body.
     *
     * @param string $text Plaintext content.
     * @return static
     */
    public function withText(string $text): static
    {
        $that = clone $this;
        $that->text = $text;
        return $that;
    }

    /**
     * Sets the HTML body.
     *
     * @param string $html HTML content.
     * @return static
     */
    public function withHtml(string $html): static
    {
        $that = clone $this;
        $that->html = $html;
        return $that;
    }

    /**
     * Sends the composed email via SMTP.
     *
     * @return true
     * @throws RuntimeException On transport or cURL error.
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
            CURLOPT_USERNAME => $this->user,
            CURLOPT_PASSWORD => $this->pass,
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
     * Composes the MIME-formatted email message body and headers.
     *
     * @return string Full email content including headers.
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
