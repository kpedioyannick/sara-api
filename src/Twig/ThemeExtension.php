<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeExtension extends AbstractExtension
{
    private const PAGE_THEMES = [
        'dashboard' => ['primary' => 'indigo', 'secondary' => 'indigo-600', 'light' => 'indigo-50', 'dark' => 'indigo-900/20'],
        'families' => ['primary' => 'purple', 'secondary' => 'purple-600', 'light' => 'purple-50', 'dark' => 'purple-900/20'],
        'objectives' => ['primary' => 'blue', 'secondary' => 'blue-600', 'light' => 'blue-50', 'dark' => 'blue-900/20'],
        'objectives-detail' => ['primary' => 'blue', 'secondary' => 'blue-600', 'light' => 'blue-50', 'dark' => 'blue-900/20'],
        'tasks' => ['primary' => 'cyan', 'secondary' => 'cyan-600', 'light' => 'cyan-50', 'dark' => 'cyan-900/20'],
        'planning' => ['primary' => 'emerald', 'secondary' => 'emerald-600', 'light' => 'emerald-50', 'dark' => 'emerald-900/20'],
        'proofs' => ['primary' => 'amber', 'secondary' => 'amber-600', 'light' => 'amber-50', 'dark' => 'amber-900/20'],
        'requests' => ['primary' => 'rose', 'secondary' => 'rose-600', 'light' => 'rose-50', 'dark' => 'rose-900/20'],
        'requests-detail' => ['primary' => 'rose', 'secondary' => 'rose-600', 'light' => 'rose-50', 'dark' => 'rose-900/20'],
        'messages' => ['primary' => 'pink', 'secondary' => 'pink-600', 'light' => 'pink-50', 'dark' => 'pink-900/20'],
        'activities' => ['primary' => 'teal', 'secondary' => 'teal-600', 'light' => 'teal-50', 'dark' => 'teal-900/20'],
        'paths' => ['primary' => 'violet', 'secondary' => 'violet-600', 'light' => 'violet-50', 'dark' => 'violet-900/20'],
        'specialists' => ['primary' => 'sky', 'secondary' => 'sky-600', 'light' => 'sky-50', 'dark' => 'sky-900/20'],
        'notifications' => ['primary' => 'yellow', 'secondary' => 'yellow-600', 'light' => 'yellow-50', 'dark' => 'yellow-900/20'],
        'integrations' => ['primary' => 'slate', 'secondary' => 'slate-600', 'light' => 'slate-50', 'dark' => 'slate-900/20'],
        'profile' => ['primary' => 'gray', 'secondary' => 'gray-600', 'light' => 'gray-50', 'dark' => 'gray-900/20'],
        'availabilities' => ['primary' => 'lime', 'secondary' => 'lime-600', 'light' => 'lime-50', 'dark' => 'lime-900/20'],
        'users' => ['primary' => 'gray', 'secondary' => 'gray-600', 'light' => 'gray-50', 'dark' => 'gray-900/20'],
    ];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_primary', [$this, 'getThemePrimary']),
            new TwigFunction('theme_secondary', [$this, 'getThemeSecondary']),
            new TwigFunction('theme_light', [$this, 'getThemeLight']),
            new TwigFunction('theme_dark', [$this, 'getThemeDark']),
            new TwigFunction('theme_button_primary', [$this, 'getButtonPrimaryClasses']),
            new TwigFunction('theme_button_secondary', [$this, 'getButtonSecondaryClasses']),
            new TwigFunction('theme_badge', [$this, 'getBadgeClasses']),
            new TwigFunction('theme_border', [$this, 'getBorderClasses']),
            new TwigFunction('theme_background', [$this, 'getBackgroundClasses']),
        ];
    }

    private function getTheme(string $pageName, string $key): string
    {
        $theme = self::PAGE_THEMES[$pageName] ?? self::PAGE_THEMES['dashboard'];
        return $theme[$key] ?? 'brand';
    }

    public function getThemePrimary(?string $pageName): string
    {
        return $this->getTheme($pageName ?? 'dashboard', 'primary');
    }

    public function getThemeSecondary(?string $pageName): string
    {
        return $this->getTheme($pageName ?? 'dashboard', 'secondary');
    }

    public function getThemeLight(?string $pageName): string
    {
        return $this->getTheme($pageName ?? 'dashboard', 'light');
    }

    public function getThemeDark(?string $pageName): string
    {
        return $this->getTheme($pageName ?? 'dashboard', 'dark');
    }

    public function getButtonPrimaryClasses(?string $pageName, bool $useBrand = false): string
    {
        if ($useBrand) {
            return 'bg-brand-500 hover:bg-brand-600 text-white border-brand-500';
        }
        $primary = $this->getThemePrimary($pageName);
        return "bg-{$primary}-500 hover:bg-{$primary}-600 text-white border-{$primary}-500";
    }

    public function getButtonSecondaryClasses(?string $pageName): string
    {
        $primary = $this->getThemePrimary($pageName);
        $light = $this->getThemeLight($pageName);
        $dark = $this->getThemeDark($pageName);
        return "border-{$primary}-500 bg-white text-{$primary}-500 hover:bg-{$light} dark:bg-gray-800 dark:border-{$primary}-400 dark:text-{$primary}-400 dark:hover:bg-{$dark}";
    }

    public function getBadgeClasses(?string $pageName): string
    {
        $primary = $this->getThemePrimary($pageName);
        $light = $this->getThemeLight($pageName);
        $dark = $this->getThemeDark($pageName);
        return "bg-{$light} text-{$primary}-700 border border-{$primary}-300 dark:bg-{$dark} dark:text-{$primary}-400 dark:border-{$primary}-700";
    }

    public function getBorderClasses(?string $pageName): string
    {
        $primary = $this->getThemePrimary($pageName);
        return "border-{$primary}-500";
    }

    public function getBackgroundClasses(?string $pageName): string
    {
        $light = $this->getThemeLight($pageName);
        $dark = $this->getThemeDark($pageName);
        return "bg-{$light}/30 dark:bg-{$dark}";
    }
}

