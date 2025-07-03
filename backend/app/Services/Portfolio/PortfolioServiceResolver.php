<?php

// app/Services/Portfolio/PortfolioServiceResolver.php
namespace App\Services\Portfolio;

class PortfolioServiceResolver
{
    public function resolve(string $url): PortfolioServiceInterface
    {
        if (str_contains($url, 'behance.net')) {
            return app(BehanceService::class);
        }

        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return app(YouTubeService::class);
        }

        if (str_contains($url, 'vimeo.com')) {
            return app(VimeoService::class);
        }
        throw new \Exception('Unsupported portfolio source.');
    }
}
