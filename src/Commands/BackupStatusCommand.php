<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Services\BackupInventoryService;
use Aaix\LaravelEasyBackups\Services\BackupStatisticsService;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
use Aaix\LaravelEasyBackups\Services\ScheduleInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class BackupStatusCommand extends Command
{
   protected $signature = 'easy-backups:status
                           {--max-remote-backups= : Retention count used for the remote cleanup preview}
                           {--max-remote-days= : Retention age in days used for the remote cleanup preview}
                           {--max-local-backups= : Retention count used for the local cleanup preview}
                           {--max-local-days= : Retention age in days used for the local cleanup preview}';

   protected $description = 'Show backup health: recent backups, cadence, size anomalies, growth, retention preview and scheduler state.';

   private const RECENT_LIMIT = 5;

   private const LEVEL_OK = 'ok';
   private const LEVEL_WARN = 'warn';
   private const LEVEL_MUTED = 'muted';

   private const TABLE_STYLE = 'box';

   private int $warnings = 0;

   public function handle(
      BackupInventoryService $inventory,
      BackupStatisticsService $statistics,
      ScheduleInspector $inspector,
      PathGenerator $paths
   ): int {
      $scheduledEvents = $inspector->backupEvents();
      $retention = $this->resolveRetention($inspector->retentionFrom($scheduledEvents));

      $this->renderHeader();

      foreach ($this->targets($paths) as $target) {
         $this->renderScope($this->inspect($inventory, $target), $statistics, $retention);
      }

      $this->renderScheduler($scheduledEvents);
      $this->renderVerdict();

      return self::SUCCESS;
   }

   // ----- Step 1: Resolve what to inspect -----

   private function targets(PathGenerator $paths): array
   {
      return [
         'local' => [
            'label' => 'Local',
            'icon' => '💾',
            'disk' => $paths->getDatabaseLocalDisk(),
            'path' => $paths->getDatabaseLocalPath(),
            'recursive' => false,
            'retention_keys' => ['max-local-backups', 'max-local-days'],
         ],
         'remote' => [
            'label' => 'Remote',
            'icon' => '🌐',
            'disk' => (string) config('easy-backups.defaults.database.remote_disk', 'backup'),
            'path' => $this->defaultRemotePath(),
            'recursive' => true,
            'retention_keys' => ['max-remote-backups', 'max-remote-days'],
         ],
      ];
   }

   private function inspect(BackupInventoryService $inventory, array $target): array
   {
      $scope = $target + ['error' => null, 'backups' => collect()];

      if ($scope['disk'] === '' || config("filesystems.disks.{$scope['disk']}") === null) {
         $scope['error'] = "Disk '{$scope['disk']}' is not defined in filesystems.php.";
         return $scope;
      }

      try {
         $scope['backups'] = $inventory->list($scope['disk'], $scope['path'], $scope['recursive']);
      } catch (Throwable $exception) {
         $scope['error'] = $exception->getMessage();
      }

      return $scope;
   }

   private function resolveRetention(array $scheduled): array
   {
      $retention = $scheduled;

      foreach (ScheduleInspector::RETENTION_FLAGS as $flag) {
         $value = $this->option($flag);
         if ($value !== null && $value !== '') {
            $retention[$flag] = (int) $value;
         }
      }

      return $retention;
   }

   // ----- Step 2: Rendering -----

   private function renderHeader(): void
   {
      $this->newLine();
      $this->line(sprintf(
         ' 📊 <options=bold>Easy Backups Status</>  <fg=gray>·  %s  ·  %s</>',
         config('app.env'),
         Carbon::now()->format('Y-m-d H:i')
      ));
   }

   private function renderScope(array $scope, BackupStatisticsService $statistics, array $retention): void
   {
      $this->newLine();

      if ($scope['error'] !== null) {
         $this->scopeHeadline($scope, 'unavailable');
         $this->signal(self::LEVEL_WARN, 'Disk', $scope['error']);
         return;
      }

      $backups = $scope['backups'];

      if ($backups->isEmpty()) {
         $this->scopeHeadline($scope, 'no backups');
         $this->signal(self::LEVEL_WARN, 'Backups', 'No backups found on this disk.');
         return;
      }

      $this->scopeHeadline($scope, sprintf(
         '%d backup%s · %s',
         $backups->count(),
         $backups->count() === 1 ? '' : 's',
         $this->formatSize((int) $backups->sum('size'))
      ));

      $this->renderRecent($backups);
      $this->renderCadence($statistics->cadence($backups));
      $this->renderSizeAnomaly($statistics->sizeAnomaly($backups));
      $this->renderGrowth($statistics->growth($backups));
      $this->renderRetention($scope, $statistics, $retention);
   }

   private function scopeHeadline(array $scope, string $facts): void
   {
      $this->line(sprintf(
         ' %s <options=bold>%s</>  <fg=gray>%s / %s  ·  %s</>',
         $scope['icon'],
         $scope['label'],
         $scope['disk'],
         $scope['path'],
         $facts
      ));
   }

   private function renderRecent(Collection $backups): void
   {
      $recent = $backups->take(self::RECENT_LIMIT)->values();
      $sizes = $recent->map(fn(array $entry) => $this->formatSize((int) $entry['size']))->all();
      $width = max(array_map('strlen', $sizes));

      $rows = $recent->map(fn(array $entry, int $index) => [
         'filename' => $entry['filename'],
         'size' => str_pad($sizes[$index], $width, ' ', STR_PAD_LEFT),
         'age' => $this->timestamp($entry['last_modified'])->diffForHumans(),
         'created' => $this->timestamp($entry['last_modified'])->format('Y-m-d H:i:s'),
      ])->all();

      $this->table(
         ['Filename', 'Size', 'Age', 'Created'],
         $rows,
         self::TABLE_STYLE
      );
   }

   private function renderCadence(?array $cadence): void
   {
      if ($cadence === null) {
         $this->signal(self::LEVEL_MUTED, 'Cadence', 'not enough backups to determine an interval');
         return;
      }

      $interval = sprintf('every ~%sh (median), average %sh', $cadence['median_hours'], $cadence['average_hours']);

      if (!$cadence['gap_is_suspicious']) {
         $this->signal(self::LEVEL_OK, 'Cadence', $interval);
         return;
      }

      $this->signal(self::LEVEL_WARN, 'Cadence', sprintf(
         '%s — largest gap %sh (%s → %s)',
         $interval,
         $cadence['largest_gap_hours'],
         $this->timestamp($cadence['largest_gap_from'])->format('Y-m-d H:i'),
         $this->timestamp($cadence['largest_gap_to'])->format('Y-m-d H:i')
      ));
   }

   private function renderSizeAnomaly(?array $anomaly): void
   {
      if ($anomaly === null) {
         $this->signal(self::LEVEL_MUTED, 'Size', 'not enough backups for a baseline comparison');
         return;
      }

      $deviation = $anomaly['deviation_percent'];
      $comparison = sprintf(
         '%s vs. %s median of previous %d (%s%s%%)',
         $this->formatSize($anomaly['latest']),
         $this->formatSize($anomaly['median']),
         $anomaly['sample_size'],
         $deviation > 0 ? '+' : '',
         $deviation
      );

      if ($anomaly['shrunk']) {
         $this->signal(self::LEVEL_WARN, 'Size', $comparison . ' — latest backup is unexpectedly small');
         return;
      }

      if ($anomaly['spiked']) {
         $this->signal(self::LEVEL_WARN, 'Size', $comparison . ' — latest backup is unexpectedly large');
         return;
      }

      $this->signal(self::LEVEL_OK, 'Size', $comparison);
   }

   private function renderGrowth(?array $growth): void
   {
      if ($growth === null) {
         $this->signal(self::LEVEL_MUTED, 'Growth', 'not enough backups to project growth');
         return;
      }

      $this->signal(self::LEVEL_OK, 'Growth', sprintf(
         '~%s backups/month · ~%s/month gross, before retention',
         $growth['backups_per_month'],
         $this->formatSize($growth['bytes_per_month'])
      ), '📈');
   }

   /**
    * Cleanup runs per upload directory and non-recursively, so the preview is grouped
    * by directory to match exactly what a cleanup pass would see.
    */
   private function renderRetention(array $scope, BackupStatisticsService $statistics, array $retention): void
   {
      [$countKey, $daysKey] = $scope['retention_keys'];
      $maxBackups = (int) ($retention[$countKey] ?? 0);
      $maxDays = (int) ($retention[$daysKey] ?? 0);

      if ($maxBackups <= 0 && $maxDays <= 0) {
         $this->signal(self::LEVEL_MUTED, 'Retention', sprintf(
            'no policy detected — pass --%s or --%s to preview',
            $countKey,
            $daysKey
         ));
         return;
      }

      $policy = implode(', ', array_filter([
         $maxBackups > 0 ? "keep {$maxBackups} newest" : null,
         $maxDays > 0 ? "max age {$maxDays} days" : null,
      ]));

      foreach ($scope['backups']->groupBy(fn(array $entry) => dirname($entry['path'])) as $directory => $entries) {
         $result = $statistics->simulateCleanup($entries, $maxBackups, $maxDays);

         $this->signal(self::LEVEL_OK, 'Retention', sprintf(
            '%s → next cleanup deletes %d backup%s, %s <fg=gray>(%s)</>',
            $policy,
            $result['count'],
            $result['count'] === 1 ? '' : 's',
            $this->formatSize($result['size']),
            $this->bucketLabel($scope, (string) $directory)
         ), '🧹');
      }
   }

   private function renderScheduler(array $events): void
   {
      $this->newLine();
      $this->line(' ⏰ <options=bold>Scheduler</>');

      if ($events === []) {
         $this->signal(self::LEVEL_WARN, 'Schedule', 'no easy-backups command is registered in the scheduler');
         return;
      }

      foreach ($events as $event) {
         $this->signal(self::LEVEL_OK, 'Schedule', sprintf(
            '%s  <fg=gray>·  %s  ·  next %s</>',
            $event['signature'],
            $event['expression'],
            $event['next_run_at']?->format('Y-m-d H:i') ?? 'unknown'
         ));
      }
   }

   private function renderVerdict(): void
   {
      $this->newLine();

      if ($this->warnings === 0) {
         $this->line(' ✅ <fg=green>No issues detected.</>');
      } else {
         $this->line(sprintf(
            ' ⚠️ <fg=yellow>%d issue%s above need attention.</>',
            $this->warnings,
            $this->warnings === 1 ? '' : 's'
         ));
      }

      $this->newLine();
   }

   private function signal(string $level, string $label, string $value, ?string $icon = null): void
   {
      if ($level === self::LEVEL_WARN) {
         $this->warnings++;
      }

      $icon ??= match ($level) {
         self::LEVEL_WARN => '⚠️',
         self::LEVEL_MUTED => '➖',
         default => '✅',
      };

      $color = match ($level) {
         self::LEVEL_WARN => 'yellow',
         self::LEVEL_MUTED => 'gray',
         default => 'default',
      };

      $this->line(sprintf(
         '    %s <fg=gray>%s</> <fg=%s>%s</>',
         $icon,
         str_pad($label, 10),
         $color,
         $value
      ));
   }

   // ----- Step 3: Shared helpers -----

   private function bucketLabel(array $scope, string $directory): string
   {
      if (!$scope['recursive']) {
         return $scope['path'];
      }

      $position = strpos($directory, $scope['path']);

      return $position === false ? $directory : substr($directory, $position);
   }

   private function timestamp(int|string $value): Carbon
   {
      return Carbon::createFromTimestamp((int) $value);
   }

   private function defaultRemotePath(): string
   {
      $parts = [];
      if (config('easy-backups.defaults.strategy.prefix_env', true)) {
         $parts[] = (string) config('app.env');
      }
      $parts[] = trim((string) config('easy-backups.defaults.database.remote_path', 'db-backups'), '/');
      return implode('/', array_filter($parts));
   }

   private function formatSize(int $bytes): string
   {
      if ($bytes < 1024) {
         return "{$bytes} B";
      }
      $units = ['KB', 'MB', 'GB', 'TB'];
      $value = $bytes / 1024;
      $unit = 'KB';
      foreach ($units as $u) {
         $unit = $u;
         if ($value < 1024) {
            break;
         }
         $value /= 1024;
      }
      return number_format($value, 2) . " {$unit}";
   }
}
