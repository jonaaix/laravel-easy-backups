<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

final class ByteFormatter
{
   private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

   public static function format(int|float $bytes): string
   {
      if ($bytes < 1024) {
         return (int) $bytes . ' B';
      }

      $size = $bytes / 1024;
      $unit = 1;

      while ($size >= 1024 && $unit < count(self::UNITS) - 1) {
         $size /= 1024;
         $unit++;
      }

      return number_format($size, 2) . ' ' . self::UNITS[$unit];
   }
}
