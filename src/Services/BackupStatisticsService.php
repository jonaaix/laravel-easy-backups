<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives health signals from a list of backup artifacts. All signals are computed
 * from filename, size and modification time only, so no additional state is required.
 */
final class BackupStatisticsService
{
   public const SHRINK_RATIO = 0.75;
   public const SPIKE_RATIO = 2.0;
   public const GAP_FACTOR = 2.0;
   public const BASELINE_SAMPLE = 10;

   private const HOURS_PER_MONTH = 730;

   public function cadence(Collection $backups): ?array
   {
      $timestamps = $backups
         ->pluck('last_modified')
         ->map(fn($timestamp) => (int) $timestamp)
         ->sort()
         ->values()
         ->all();

      if (count($timestamps) < 2) {
         return null;
      }

      $gaps = [];
      for ($index = 1; $index < count($timestamps); $index++) {
         $gaps[] = ($timestamps[$index] - $timestamps[$index - 1]) / 3600;
      }

      $median = $this->median($gaps);
      $largest = max($gaps);
      $largestIndex = (int) array_search($largest, $gaps, true);

      return [
         'average_hours' => round(array_sum($gaps) / count($gaps), 1),
         'median_hours' => round($median, 1),
         'largest_gap_hours' => round($largest, 1),
         'largest_gap_from' => $timestamps[$largestIndex],
         'largest_gap_to' => $timestamps[$largestIndex + 1],
         'gap_is_suspicious' => $median > 0 && $largest >= $median * self::GAP_FACTOR,
      ];
   }

   public function sizeAnomaly(Collection $backups): ?array
   {
      $sizes = $backups
         ->sortByDesc('last_modified')
         ->pluck('size')
         ->map(fn($size) => (int) $size)
         ->values()
         ->all();

      $baseline = array_slice($sizes, 1, self::BASELINE_SAMPLE);

      if (count($baseline) < 2) {
         return null;
      }

      $median = $this->median($baseline);

      if ($median <= 0.0) {
         return null;
      }

      $latest = $sizes[0];
      $ratio = $latest / $median;

      return [
         'latest' => $latest,
         'median' => (int) round($median),
         'sample_size' => count($baseline),
         'deviation_percent' => round(($ratio - 1) * 100, 1),
         'shrunk' => $ratio < self::SHRINK_RATIO,
         'spiked' => $ratio > self::SPIKE_RATIO,
      ];
   }

   public function growth(Collection $backups): ?array
   {
      $cadence = $this->cadence($backups);

      if ($cadence === null || $cadence['median_hours'] <= 0) {
         return null;
      }

      $medianSize = $this->median(
         $backups->pluck('size')->map(fn($size) => (int) $size)->all()
      );

      $perMonth = self::HOURS_PER_MONTH / $cadence['median_hours'];

      return [
         'backups_per_month' => round($perMonth, 1),
         'bytes_per_month' => (int) round($perMonth * $medianSize),
      ];
   }

   /**
    * Mirrors CleanupBackupsAction: oldest-first, age-based expiry runs before the
    * count-based cut, and the count applies to whatever survived the age pass.
    */
   public function simulateCleanup(Collection $backups, int $maxBackups, int $maxDays): array
   {
      if ($maxBackups <= 0 && $maxDays <= 0) {
         return ['count' => 0, 'size' => 0];
      }

      $remaining = $backups->sortBy('last_modified')->values();

      if ($remaining->isEmpty()) {
         return ['count' => 0, 'size' => 0];
      }

      $doomed = collect();

      if ($maxDays > 0) {
         $threshold = Carbon::now()->subDays($maxDays)->getTimestamp();
         [$expired, $kept] = $remaining->partition(
            fn(array $entry) => (int) $entry['last_modified'] < $threshold
         );
         $doomed = $doomed->merge($expired);
         $remaining = $kept->values();
      }

      if ($maxBackups > 0 && $remaining->count() > $maxBackups) {
         $doomed = $doomed->merge($remaining->slice(0, $remaining->count() - $maxBackups));
      }

      return [
         'count' => $doomed->count(),
         'size' => (int) $doomed->sum('size'),
      ];
   }

   private function median(array $values): float
   {
      $values = array_values(array_filter($values, fn($value) => $value !== null));

      if ($values === []) {
         return 0.0;
      }

      sort($values);
      $count = count($values);
      $middle = intdiv($count, 2);

      return $count % 2 === 1
         ? (float) $values[$middle]
         : ($values[$middle - 1] + $values[$middle]) / 2;
   }
}
