<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Carbon\CarbonInterval;
use Symfony\Component\Console\Helper\ProgressBar;

final class TransferProgress
{
   private const REDRAW_INTERVAL_SECONDS = 0.1;

   private int $transferredBytes = 0;
   private float $lastRedrawAt = 0.0;
   private readonly float $startedAt;
   private readonly ?ProgressBar $bar;

   private function __construct(
      private readonly string $completedVerb,
      private readonly int $totalBytes,
   ) {
      $this->startedAt = microtime(true);
      $this->bar = ConsoleFeedback::progressBar($totalBytes);
      $this->bar?->setFormat(' %message% [%bar%] %percent:3s%%');
      $this->redraw();
   }

   public static function start(string $completedVerb, int $totalBytes): self
   {
      return new self($completedVerb, $totalBytes);
   }

   public function advance(int $bytes): void
   {
      $this->reach($this->transferredBytes + $bytes);
   }

   public function reach(int $position): void
   {
      $position = min($position, $this->totalBytes);
      if ($position <= $this->transferredBytes) {
         return;
      }

      $this->transferredBytes = $position;

      if (microtime(true) - $this->lastRedrawAt >= self::REDRAW_INTERVAL_SECONDS) {
         $this->redraw();
      }
   }

   public function finish(): void
   {
      if ($this->bar) {
         $this->redraw();
         $this->bar->finish();
         ConsoleFeedback::newLine();
         return;
      }

      ConsoleFeedback::info(sprintf(
         '%s %s in %s (%s/s)',
         $this->completedVerb,
         ByteFormatter::format($this->transferredBytes),
         CarbonInterval::seconds((int) round($this->elapsedSeconds()))->cascade()->forHumans(['short' => true]),
         ByteFormatter::format($this->bytesPerSecond())
      ));
   }

   private function redraw(): void
   {
      if (!$this->bar) {
         return;
      }

      $this->lastRedrawAt = microtime(true);
      $this->bar->setMessage(sprintf(
         '%s / %s · %s/s',
         ByteFormatter::format($this->transferredBytes),
         ByteFormatter::format($this->totalBytes),
         ByteFormatter::format($this->bytesPerSecond())
      ));
      $this->bar->setProgress($this->transferredBytes);
   }

   private function elapsedSeconds(): float
   {
      return microtime(true) - $this->startedAt;
   }

   private function bytesPerSecond(): float
   {
      $elapsed = $this->elapsedSeconds();

      return $elapsed > 0 ? $this->transferredBytes / $elapsed : 0.0;
   }
}
