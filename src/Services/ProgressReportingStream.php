<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

final class ProgressReportingStream
{
   private const PROTOCOL = 'easy-backups-progress';

   /** @var resource|null */
   public $context;

   /** @var resource */
   private $inner;

   private TransferProgress $progress;

   /**
    * @return resource
    */
   public static function open(string $path, TransferProgress $progress)
   {
      if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
         stream_wrapper_register(self::PROTOCOL, self::class);
      }

      $context = stream_context_create([self::PROTOCOL => ['path' => $path, 'progress' => $progress]]);
      $stream = fopen(self::PROTOCOL . '://' . basename($path), 'rb', false, $context);

      if ($stream === false) {
         throw new \RuntimeException("Unable to open '{$path}' for reading.");
      }

      return $stream;
   }

   public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
   {
      $settings = stream_context_get_options($this->context)[self::PROTOCOL];
      $inner = fopen($settings['path'], 'rb');

      if ($inner === false) {
         return false;
      }

      $this->inner = $inner;
      $this->progress = $settings['progress'];

      return true;
   }

   public function stream_read(int $count): string|false
   {
      $data = fread($this->inner, $count);
      $this->progress->reach((int) ftell($this->inner));

      return $data;
   }

   public function stream_seek(int $offset, int $whence): bool
   {
      return fseek($this->inner, $offset, $whence) === 0;
   }

   public function stream_tell(): int
   {
      return (int) ftell($this->inner);
   }

   public function stream_eof(): bool
   {
      return feof($this->inner);
   }

   public function stream_stat(): array|false
   {
      return fstat($this->inner);
   }

   public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
   {
      return false;
   }

   public function stream_close(): void
   {
      fclose($this->inner);
   }
}
