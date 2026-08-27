<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Discovers whether this package's commands are registered in the application
 * scheduler. Retention flags are read back from the scheduled invocation, since
 * the package holds no retention configuration of its own.
 */
final class ScheduleInspector
{
   public const COMMAND_NEEDLE = 'easy-backups:';

   public const RETENTION_FLAGS = [
      'max-remote-backups',
      'max-remote-days',
      'max-local-backups',
      'max-local-days',
   ];

   public function backupEvents(): array
   {
      try {
         $schedule = app(Schedule::class);
      } catch (Throwable) {
         return [];
      }

      $events = [];

      foreach ($schedule->events() as $event) {
         $signature = $this->signatureOf($event);

         if ($signature === null || !str_contains($signature, self::COMMAND_NEEDLE)) {
            continue;
         }

         $events[] = [
            'signature' => $signature,
            'expression' => $event->expression ?? '-',
            'next_run_at' => $this->nextRunAt($event),
            'retention' => $this->parseRetention($signature),
         ];
      }

      return $events;
   }

   public function retentionFrom(array $events): array
   {
      $retention = [];

      foreach ($events as $event) {
         foreach ($event['retention'] as $flag => $value) {
            $retention[$flag] = max($retention[$flag] ?? 0, $value);
         }
      }

      return $retention;
   }

   private function signatureOf(Event $event): ?string
   {
      $raw = $event->command ?? $event->description;

      if (!is_string($raw) || trim($raw) === '') {
         return null;
      }

      $position = strpos($raw, self::COMMAND_NEEDLE);
      $signature = $position === false ? $raw : substr($raw, $position);

      return trim((string) preg_replace('/\s+[12]?>.*$/', '', $signature));
   }

   private function nextRunAt(Event $event): ?Carbon
   {
      try {
         return Carbon::instance($event->nextRunDate());
      } catch (Throwable) {
         return null;
      }
   }

   private function parseRetention(string $signature): array
   {
      $retention = [];

      foreach (self::RETENTION_FLAGS as $flag) {
         $pattern = '/--' . preg_quote($flag, '/') . '[=\s]+"?\'?(\d+)/';

         if (preg_match($pattern, $signature, $matches) === 1) {
            $retention[$flag] = (int) $matches[1];
         }
      }

      return $retention;
   }
}
