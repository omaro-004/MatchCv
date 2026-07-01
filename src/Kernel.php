<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            return sys_get_temp_dir() . '/matchcv/' . $this->environment . '/cache';
        }

        return parent::getCacheDir();
    }
}
