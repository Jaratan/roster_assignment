<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HttpHelper
{
    public static function fetchHtml(string $url): ?string
    {
        try {
            $client = new Client();
            $response = $client->get($url);
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            // You can log the error if needed
            \Log::error("HTTP request failed: " . $e->getMessage());
            return null;
        }
    }
}
