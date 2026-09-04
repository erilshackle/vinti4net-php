<?php

namespace Eril\Sisp\Receipt;

interface ReceiptRendererInterface
{
    public function html(?string $company = null): string;
    public function text(?string $company = null): string;
}