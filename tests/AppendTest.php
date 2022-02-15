<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\Exceptions\InvalidAppendQuery;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Tests\TestClasses\Models\AppendModel;

beforeEach(function () {
    $this->model = AppendModel::factory()->create();

    $this->modelTableName = $this->model->getTable();
});

it('does_not_require_appends', function () {
    $models = QueryBuilder::for(AppendModel::class, new Request())
        ->allowedAppends('fullname')
        ->get();

    expect($models)->toHaveCount(AppendModel::count());
});

it('can_append_attributes', function () {
    $model = createQueryFromAppendRequest('fullname')
        ->allowedAppends('fullname')
        ->first();

    assertAttributeLoaded($model, 'fullname');
});

it('cannot_append_case_insensitive', function () {
    createQueryFromAppendRequest('FullName')
        ->allowedAppends('fullname')
        ->first();
})->throws(InvalidAppendQuery::class);

it('can_append_collections', function () {
    $models = createQueryFromAppendRequest('FullName')
        ->allowedAppends('FullName')
        ->get();

    assertCollectionAttributeLoaded($models, 'FullName');
});

it('can_append_paginates', function () {
    $models = createQueryFromAppendRequest('FullName')
        ->allowedAppends('FullName')
        ->paginate();

    assertPaginateAttributeLoaded($models, 'FullName');
});

it('can_append_simple_paginates', function () {
    $models = createQueryFromAppendRequest('FullName')
        ->allowedAppends('FullName')
        ->simplePaginate();

    assertPaginateAttributeLoaded($models, 'FullName');
});

it('can_append_cursor_paginates', function () {
    $models = createQueryFromAppendRequest('FullName')
        ->allowedAppends('FullName')
        ->cursorPaginate();

    assertPaginateAttributeLoaded($models, 'FullName');
});

it('guards_against_invalid_appends', function () {
    createQueryFromAppendRequest('random-attribute-to-append')
        ->allowedAppends('attribute-to-append');
})->throws(InvalidAppendQuery::class);

it('can_allow_multiple_appends', function () {
    $model = createQueryFromAppendRequest('fullname')
        ->allowedAppends('fullname', 'randomAttribute')
        ->first();

    assertAttributeLoaded($model, 'fullname');
});

it('can_allow_multiple_appends_as_an_array', function () {
    $model = createQueryFromAppendRequest('fullname')
        ->allowedAppends(['fullname', 'randomAttribute'])
        ->first();

    assertAttributeLoaded($model, 'fullname');
});

it('can_append_multiple_attributes', function () {
    $model = createQueryFromAppendRequest('fullname,reversename')
        ->allowedAppends(['fullname', 'reversename'])
        ->first();

    assertAttributeLoaded($model, 'fullname');
    assertAttributeLoaded($model, 'reversename');
});

it('an_invalid_append_query_exception_contains_the_not_allowed_and_allowed_appends', function () {
    $exception = new InvalidAppendQuery(collect(['not allowed append']), collect(['allowed append']));

    $this->assertEquals(['not allowed append'], $exception->appendsNotAllowed->all());
    $this->assertEquals(['allowed append'], $exception->allowedAppends->all());
});

// Helpers
function createQueryFromAppendRequest(string $appends): QueryBuilder
{
    $request = new Request([
        'append' => $appends,
    ]);

    return QueryBuilder::for(AppendModel::class, $request);
}

function assertAttributeLoaded(AppendModel $model, string $attribute)
{
    expect(array_key_exists($attribute, $model->toArray()))->toBeTrue();
}

function assertCollectionAttributeLoaded(Collection $collection, string $attribute)
{
    $hasModelWithoutAttributeLoaded = $collection
        ->contains(function (Model $model) use ($attribute) {
            return ! array_key_exists($attribute, $model->toArray());
        });

    expect($hasModelWithoutAttributeLoaded)->toBeFalse();
}

function assertPaginateAttributeLoaded($collection, string $attribute)
{
    $hasModelWithoutAttributeLoaded = $collection
        ->contains(function (Model $model) use ($attribute) {
            return ! array_key_exists($attribute, $model->toArray());
        });

    expect($hasModelWithoutAttributeLoaded)->toBeFalse();
}
