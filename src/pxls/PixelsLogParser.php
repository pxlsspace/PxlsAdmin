<?php

namespace pxls;

class PixelsLogParser
{

    public function __construct()
    {
        $lines = [];
        $handle = @fopen("/var/www/admin/pixels.log", 'r');
        for($i=0;$i<10;$i++) {
            $buffer = $this->rfgets($handle);
            $lines[] = $buffer;
        }
        fclose($handle);
        return $lines;
    }

    protected function rfgets($handle)
    {
        $line = null;
        $n = 0;

        if ($handle) {
            $line = '';

            $started = false;
            $gotline = false;

            while (!$gotline) {
                if (ftell($handle) == 0) {
                    fseek($handle, -1, SEEK_END);
                } else {
                    fseek($handle, -2, SEEK_CUR);
                }

                $readres = ($char = fgetc($handle));

                if (false === $readres) {
                    $gotline = true;
                } elseif ($char == "\n" || $char == "\r") {
                    if ($started)
                        $gotline = true;
                    else
                        $started = true;
                } elseif ($started) {
                    $line .= $char;
                }
            }
        }

        fseek($handle, 1, SEEK_CUR);

        return strrev($line);
    }
}
