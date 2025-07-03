<?php
namespace App\Services\Portfolio;

interface PortfolioServiceInterface
{
    public function fetchProjects(string $url, int $limit = 10);
}
