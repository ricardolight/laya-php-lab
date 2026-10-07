<?php

declare(strict_types=1);

// RetryingHttpClient decorates any PSR-18 client; Laya takes it through its $httpClient argument.

it('retries a 503 with backoff and returns the first successful response')->todo();

it('returns the last response once the attempts are used up')->todo();

it('does not retry 4xx responses')->todo();

it('retries transport errors and rethrows the last one')->todo();
