<?php
declare(strict_types=1);

class Badge
{
    const DEFINITIONS = [
        'classes' => [
            ['code' => 'A_PLUS_STUDENT', 'threshold' => 20, 'icon' => '<i class="fa fa-book"></i>',          'title' => 'A+ Student: 20 classes attended',   'label' => 'A+ Student'],
            ['code' => 'NEWBIE',          'threshold' => 1,  'icon' => '<i class="fa fa-graduation-cap"></i>', 'title' => 'Newbie: 1st class attended',         'label' => 'Newbie'],
        ],
        'visits' => [
            ['code' => 'CENTURY_CLUB', 'threshold' => 100, 'icon' => '<i class="fa fa-trophy"></i>',        'title' => 'Century Club: 100 gym visits', 'label' => 'Century Club'],
            ['code' => 'IRON_REGULAR', 'threshold' => 50,  'icon' => '<i class="fa fa-dumbbell"></i>',      'title' => 'Iron Regular: 50 gym visits',  'label' => 'Iron Regular'],
            ['code' => 'GYM_EXPLORER', 'threshold' => 10,  'icon' => '<i class="fa fa-compass"></i>',       'title' => 'Gym Explorer: 10 gym visits',  'label' => 'Gym Explorer'],
        ],
        'time' => [
            ['code' => 'TIME_CHAMPION',     'threshold' => 6000, 'icon' => '<i class="fa fa-crown"></i>',         'title' => 'Time Champion: 100+ hours at the gym',    'label' => '100+ Hours'],
            ['code' => 'GYM_WARRIOR',       'threshold' => 3000, 'icon' => '<i class="fa fa-shield-halved"></i>', 'title' => 'Gym Warrior: 50+ hours at the gym',      'label' => '50+ Hours'],
            ['code' => 'ENDURANCE_BUILDER', 'threshold' => 1200, 'icon' => '<i class="fa fa-bolt"></i>',          'title' => 'Endurance Builder: 20+ hours at the gym', 'label' => '20+ Hours'],
        ],
    ];

    private static function valueFor(string $category, int $visits, int $classes, int $minutes): int
    {
        return match ($category) {
            'classes' => $classes,
            'visits'  => $visits,
            'time'    => $minutes,
            default   => 0,
        };
    }

    static function getEarned(int $totalVisits, int $totalClasses, int $totalMinutes): array
    {
        $earned = [];
        foreach (self::DEFINITIONS as $category => $defs) {
            $value = self::valueFor($category, $totalVisits, $totalClasses, $totalMinutes);
            foreach ($defs as $def) {
                if ($value >= $def['threshold']) {
                    $earned[] = $def;
                    break;
                }
            }
        }
        return $earned;
    }

    static function countEarned(int $totalVisits, int $totalClasses, int $totalMinutes): int
    {
        $count = 0;
        foreach (self::DEFINITIONS as $category => $defs) {
            $value = self::valueFor($category, $totalVisits, $totalClasses, $totalMinutes);
            foreach ($defs as $def) {
                if ($value >= $def['threshold']) {
                    $count++;
                }
            }
        }
        return $count;
    }

    static function getSelected(array $earnedBadges, string $selectedCodes): array
    {
        $codes = array_filter(array_map('trim', explode(',', $selectedCodes)));
        return array_values(array_filter($earnedBadges, fn($b) => in_array($b['code'], $codes, true)));
    }
}
