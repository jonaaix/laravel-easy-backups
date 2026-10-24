<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Illuminate\Support\Facades\App;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Internal helper to provide feedback during long-running processes
 * when running in an interactive console environment.
 */
final class ConsoleFeedback
{
   private static ?SymfonyStyle $io = null;
   private static ?ConsoleOutput $output = null;

   public static function info(string $message): void
   {
      self::write(fn(SymfonyStyle $io) => $io->text($message));
   }

   public static function step(string $message): void
   {
      // Using 'section' or explicit formatting to make steps visible
      self::write(fn(SymfonyStyle $io) => $io->block($message, 'STEP', 'fg=black;bg=cyan', ' ', true));
   }

   public static function success(string $message): void
   {
      self::write(fn(SymfonyStyle $io) => $io->success($message));
   }

   public static function warning(string $message): void
   {
      self::write(fn(SymfonyStyle $io) => $io->warning($message));
   }

   public static function error(string $message): void
   {
      self::write(fn(SymfonyStyle $io) => $io->error($message));
   }

   public static function newLine(): void
   {
      self::write(fn(SymfonyStyle $io) => $io->newLine());
   }

   public static function progressBar(int $max): ?ProgressBar
   {
      if (!App::runningInConsole() || !stream_isatty(self::output()->getStream())) {
         return null;
      }

      return self::io()->createProgressBar($max);
   }

   /**
    * Executes the output operation only if we are running in the console.
    */
   private static function write(callable $operation): void
   {
      if (!App::runningInConsole()) {
         return;
      }

      $operation(self::io());
   }

   private static function io(): SymfonyStyle
   {
      return self::$io ??= new SymfonyStyle(new StringInput(''), self::output());
   }

   private static function output(): ConsoleOutput
   {
      return self::$output ??= new ConsoleOutput();
   }
}
