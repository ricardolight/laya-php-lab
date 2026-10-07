<?php

declare(strict_types=1);

// Selective classification: answers below the threshold go to a person, the rest are automated.

it('computes coverage and accuracy of the automated answers at each threshold')->todo();

it('never increases coverage as the threshold rises')->todo();

it('recommends the lowest threshold that reaches the target accuracy on the automated answers')->todo();

it('says so when no threshold reaches the target')->todo();

it('picks the threshold on one split and reports it on another to avoid overfitting')->todo();
