<?php

namespace Essentio\Core\Extra;

use Essentio\Core\FrameworkException;
use Essentio\Core\Response;

/**
 * @api
 */
class HttpClient
{
    /**
     * Send an HTTP request and return a Response.
     *
     * @param array<string,mixed> $headers
     */
    public static function request(string $method, string $url, array $headers = [], string $body = ""): Response
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new FrameworkException("Unable to initialize cURL.");
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => array_map(fn($k, $v): string => sprintf("%s: %s", $k, $v), array_keys($headers), $headers),
            CURLOPT_POSTFIELDS => $body,
        ]);

        try {
            $raw = curl_exec($curl);

            if ($raw === false) {
                throw new FrameworkException("Curl error: " . curl_error($curl));
            }

            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            $headerText = substr((string) $raw, 0, $headerSize);
            $bodyText = substr((string) $raw, $headerSize);

            $response = new Response();
            $response->setStatus($statusCode);
            $response->setBody($bodyText);

            $headerLines = explode("\r\n", trim($headerText));
            $parsedHeaders = [];

            foreach ($headerLines as $headerLine) {
                if (str_contains($headerLine, ":")) {
                    /** @psalm-suppress PossiblyUndefinedArrayOffset */
                    [$key, $value] = explode(":", $headerLine, 2);
                    $parsedHeaders[trim($key)] = trim($value);
                }
            }

            $response->addHeaders($parsedHeaders);
            return $response;
        } finally {
            curl_close($curl);
        }
    }
}
