<?php

namespace App\Livewire\Portfolio;

use App\Domain\Projects\Models\StudentPortfolio;
use App\Domain\Projects\Services\StudentPortfolioService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Verified Practical SEO Portfolio')]
class PublicPortfolioView extends Component
{
    public string $token;

    public ?StudentPortfolio $portfolio = null;

    public function mount(string $token, StudentPortfolioService $portfolioService): void
    {
        $this->token = $token;
        $this->portfolio = $portfolioService->resolvePublicPortfolio($token);

        if (! $this->portfolio) {
            abort(404, 'Portfolio not found or public sharing has been revoked.');
        }
    }

    public function render()
    {
        return view('livewire.portfolio.public-portfolio-view', [
            'portfolio' => $this->portfolio,
        ]);
    }
}
