<?php

namespace AdAstra\Repositories\Contracts;

/**
 * Marker interface for application repositories.
 *
 * Each concrete repository is expected to expose at minimum:
 *
 *   applyData(<TModel> $model, array $data): <TModel>
 *       Apply a partial data payload to an existing model and persist it.
 *       Only keys present in $data are written; absent keys are left untouched.
 *
 *   delete(<TModel> $model): bool
 *       Persist the removal of a model. May be a soft- or hard-delete
 *       depending on whether the model uses SoftDeletes.
 *
 * PHP does not support generics, so these method signatures are enforced by
 * convention rather than the type system. Static-analysis tools (PHPStan /
 * Psalm) can use `@implements RepositoryInterface` on concrete classes to
 * document the intended model type.
 */
interface RepositoryInterface
{
}
