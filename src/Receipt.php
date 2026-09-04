<?php

namespace Eril\Sisp;

use Eril\Sisp\Traits\ReceiptRenderer;

final class Receipt {

    use ReceiptRenderer;

    private Vinti4Response $vinti4Response;
    private string $companyName;

    public function __construct(Vinti4Response $vinti4Response, ?string $companyName = null)
    {
        $this->vinti4Response = $vinti4Response;
        $this->companyName = $companyName;
    } 

}