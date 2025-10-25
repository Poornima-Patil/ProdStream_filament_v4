<?php

namespace App\Services\Shift;

use App\Models\Factory;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ShiftScheduleService
{
    protected Collection $shifts;

    public function __construct(protected Factory $factory)
    {
        $this->shifts = Shift::query()
            ->where('factory_id', $factory->id)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get the current shift window (start/end) that contains the reference time, if any.
     */
    public function getCurrentShiftWindow(Carbon|string|null $reference = null): ?array
    {
        $reference = $this->asImmutable($reference);

        foreach ($this->buildWindows($reference) as $window) {
            if ($this->inWindow($reference, $window['start'], $window['end'])) {
                return $window;
            }
        }

        return null;
    }

    /**
     * Get the next shift boundary (either a shift end or the start of the next shift)
     * that occurs after the reference time.
     */
    public function getNextShiftChange(Carbon|string|null $reference = null): ?CarbonImmutable
    {
        $reference = $this->asImmutable($reference);
        $windows = $this->buildWindows($reference);

        if ($windows->isEmpty()) {
            return null;
        }

        $boundaries = $windows->flatMap(function (array $window) use ($reference) {
            return collect([$window['start'], $window['end']])
                ->filter(fn (CarbonImmutable $point) => $point->greaterThan($reference));
        });

        $nextChange = $boundaries->sort()->first();

        return $nextChange instanceof CarbonImmutable ? $nextChange : null;
    }

    /**
     * Suggest a cache TTL (seconds) that expires at the next shift change.
     *
     * @param int $fallbackSeconds  TTL to use when no shift information is available.
     * @param int $minimumSeconds   Minimum TTL to enforce (defaults to 5 minutes).
     */
    public function getSuggestedCacheTTL(
        Carbon|string|null $reference = null,
        int $fallbackSeconds = 3600,
        int $minimumSeconds = 300
    ): int {
        $reference = $this->asImmutable($reference);
        $nextBoundary = $this->getNextShiftChange($reference);

        if (! $nextBoundary) {
            return $fallbackSeconds;
        }

        $seconds = $reference->diffInSeconds($nextBoundary, false);

        if ($seconds <= 0) {
            return $fallbackSeconds;
        }

        return max($minimumSeconds, $seconds);
    }

    /**
     * Build shift windows around the reference date to account for overnight shifts.
     */
    protected function buildWindows(CarbonImmutable $reference): Collection
    {
        if ($this->shifts->isEmpty()) {
            return collect();
        }

        $windows = collect();

        foreach ([-1, 0, 1] as $dayOffset) {
            $day = $reference->addDays($dayOffset)->startOfDay();

            foreach ($this->shifts as $shift) {
                $start = $day->setTimeFromTimeString($shift->start_time);
                $end = $day->setTimeFromTimeString($shift->end_time);

                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }

                $windows->push([
                    'shift' => $shift,
                    'start' => $start,
                    'end' => $end,
                ]);
            }
        }

        return $windows->sortBy('start')->values();
    }

    protected function inWindow(CarbonImmutable $reference, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $reference->greaterThanOrEqualTo($start) && $reference->lessThan($end);
    }

    protected function asImmutable(Carbon|string|null $reference): CarbonImmutable
    {
        if ($reference instanceof CarbonImmutable) {
            return $reference;
        }

        if ($reference instanceof Carbon) {
            return CarbonImmutable::parse($reference->toDateTimeString());
        }

        if (is_string($reference) && $reference !== '') {
            return CarbonImmutable::parse($reference);
        }

        return CarbonImmutable::parse(now());
    }
}
