<?php

namespace AdAstra\Blueprint\Contracts;

use AdAstra\Blueprint\Blueprint;

/**
 * Implemented by anything that exposes a configurable fieldset (a "blueprint"):
 * field types, and — via the same contract — models in new layers that want
 * typed, validated, renderable settings without reimplementing the machinery.
 */
interface ProvidesBlueprint
{
    public function blueprint(): Blueprint;
}
