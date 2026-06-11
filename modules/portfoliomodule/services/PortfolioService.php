<?php
namespace modules\portfoliomodule\services;

use Craft;
use yii\base\Component;

class PortfolioService extends Component
{
    public function getHomepage(): ?\craft\elements\Entry
    {
        return Craft::$app->getEntries()->getEntryByHandle('homepage');
    }

    public function getSkillCards(): array
    {
        return Craft::$app->getEntries()->getSection('skillCards')->getEntries() ?? [];
    }

    public function getExperience(): array
    {
        return Craft::$app->getEntries()->getSection('experience')->getEntries() ?? [];
    }

    public function getStartups(): array
    {
        return Craft::$app->getEntries()->getSection('startups')->getEntries() ?? [];
    }

    public function getTestimonials(): array
    {
        return Craft::$app->getEntries()->getSection('testimonials')->getEntries() ?? [];
    }

    public function getSocialLinks(): array
    {
        return Craft::$app->getEntries()->getSection('socialLinks')->getEntries() ?? [];
    }

    public function statusBadgeConfig(string $status): array
    {
        return match ($status) {
            'active' => ['class' => 'text-primary-500', 'icon' => 'fa-solid fa-arrow-up-right-from-square', 'label' => null],
            'onHold' => ['class' => 'text-red-500', 'icon' => 'fa-regular fa-circle-pause', 'label' => 'on hold'],
            'acquired' => ['class' => 'text-green-500', 'icon' => 'fa-solid fa-handshake', 'label' => 'acquired'],
            'exited' => ['class' => 'text-green-500', 'icon' => 'fa-solid fa-handshake', 'label' => 'exited'],
            default => ['class' => 'text-gray-500', 'icon' => null, 'label' => $status],
        };
    }
}
