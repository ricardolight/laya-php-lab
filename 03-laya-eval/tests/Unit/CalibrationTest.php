<?php

declare(strict_types=1);

it('gives a Brier score of 0 for perfect, certain predictions')->todo();

it('gives a Brier score of 0.25 for always predicting 0.5 on a yes/no question')->todo();

it('bins predictions by confidence for a reliability table')->todo();

it('computes ECE as the support-weighted gap between confidence and accuracy per bin')->todo();

it('gives an ECE of 0 when every bin is exactly as accurate as it is confident')->todo();

it('flags over-confidence when confidence is above accuracy')->todo();
