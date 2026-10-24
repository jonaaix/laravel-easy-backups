<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

final class ByteFormatter
{
   private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

   public static function format(int|float $bytes): string
   {
      $size = (float) $bytes;
      $unit = 0;

      while ($size >= 1024 && $unit < count(self::UNITS) - 1) {
         $size /= 1024;
         $unit++;
      }

      return round($size, 2) . ' ' . self::UNITS[$unit];
   }
}
