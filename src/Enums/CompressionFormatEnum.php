<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Enums;

enum CompressionFormatEnum: string
{
   case ZSTD = 'tar.zst';
   case GZIP = 'tar.gz';
   case ZIP = 'zip';
   case TAR = 'tar';

   public function getExtension(): string
   {
      return match ($this) {
         self::ZSTD => 'tar.zst',
         self::GZIP => 'tar.gz',
         self::ZIP => 'zip',
         self::TAR => 'tar',
      };
   }
}
