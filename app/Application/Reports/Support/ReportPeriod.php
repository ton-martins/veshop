<?php

namespace App\Application\Reports\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportPeriod
{
    private const TODAY = 'today';

    private const WEEK = 'week';

    private const MONTH = 'month';

    private const QUARTER = 'quarter';

    private const YEAR = 'year';

    private const CUSTOM = 'custom';

    private const MAX_CUSTOM_DAYS = 366;

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $timezone,
        public readonly CarbonImmutable $startLocal,
        public readonly CarbonImmutable $endLocal,
    ) {}

    public static function fromRequest(Request $request, string $timezone): self
    {
        $now = CarbonImmutable::now($timezone);
        $key = strtolower(trim((string) $request->query('period', self::MONTH)));

        return match ($key) {
            self::TODAY => new self(
                self::TODAY,
                'Hoje',
                $timezone,
                $now->startOfDay(),
                $now->endOfDay(),
            ),
            self::WEEK => new self(
                self::WEEK,
                'Esta semana',
                $timezone,
                $now->startOfWeek(),
                $now->endOfWeek(),
            ),
            self::QUARTER => new self(
                self::QUARTER,
                'Este trimestre',
                $timezone,
                $now->startOfQuarter(),
                $now->endOfQuarter(),
            ),
            self::YEAR => new self(
                self::YEAR,
                'Este ano',
                $timezone,
                $now->startOfYear(),
                $now->endOfYear(),
            ),
            self::CUSTOM => self::fromCustomRangeRequest($request, $timezone, $now),
            default => new self(
                self::MONTH,
                'Este mês',
                $timezone,
                $now->startOfMonth(),
                $now->endOfMonth(),
            ),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::TODAY, 'label' => 'Hoje'],
            ['value' => self::WEEK, 'label' => 'Esta semana'],
            ['value' => self::MONTH, 'label' => 'Este mês'],
            ['value' => self::QUARTER, 'label' => 'Este trimestre'],
            ['value' => self::YEAR, 'label' => 'Este ano'],
            ['value' => self::CUSTOM, 'label' => 'Período personalizado'],
        ];
    }

    public function startUtc(): CarbonImmutable
    {
        return $this->startLocal->setTimezone('UTC');
    }

    public function endUtc(): CarbonImmutable
    {
        return $this->endLocal->setTimezone('UTC');
    }

    public function startDate(): string
    {
        return $this->startLocal->toDateString();
    }

    public function endDate(): string
    {
        return $this->endLocal->toDateString();
    }

    public function totalDays(): int
    {
        return (int) $this->startLocal->startOfDay()->diffInDays($this->endLocal->endOfDay()) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->key,
            'period_label' => $this->label,
            'start_date' => $this->startDate(),
            'end_date' => $this->endDate(),
            'timezone' => $this->timezone,
        ];
    }

    private static function fromCustomRangeRequest(Request $request, string $timezone, CarbonImmutable $now): self
    {
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));

        $fallbackStart = $now->startOfMonth();
        $fallbackEnd = $now->endOfMonth();

        $start = self::parseDateOrFallback($startDate, $timezone, $fallbackStart)->startOfDay();
        $end = self::parseDateOrFallback($endDate, $timezone, $fallbackEnd)->endOfDay();

        if ($end->lessThan($start)) {
            $end = $start->endOfDay();
        }

        if ($start->diffInDays($end) + 1 > self::MAX_CUSTOM_DAYS) {
            $end = $start->addDays(self::MAX_CUSTOM_DAYS - 1)->endOfDay();
        }

        return new self(
            self::CUSTOM,
            'Período personalizado',
            $timezone,
            $start,
            $end,
        );
    }

    private static function parseDateOrFallback(string $value, string $timezone, CarbonImmutable $fallback): CarbonImmutable
    {
        if ($value === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, $timezone);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
