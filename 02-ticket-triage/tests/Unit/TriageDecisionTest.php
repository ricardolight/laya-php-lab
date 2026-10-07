<?php

declare(strict_types=1);

// Triage is a decision class: #[Ask] on constructor properties, Department as a backed enum with #[Describe].

it('turns the Triage class into one choice, one score and one yes/no question')->todo();

it('hydrates a Triage from a faked decision and asserts it was decided')->todo();

it('returns a null department when laya is below the minConfidence of that field')->todo();

it('uses the Describe attribute text as the option description sent to laya')->todo();

it('never uses yes/no/true/false as choice labels')->todo();
