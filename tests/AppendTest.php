<?php

namespace Spatie\QueryBuilder\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\Exceptions\InvalidAppendQuery;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Tests\TestClasses\Models\AppendModel;

class AppendTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        factory(AppendModel::class, 10)->create();
    }

    /** @test */
    public function it_does_not_require_appends()
    {
        $models = QueryBuilder::for(AppendModel::class, new Request())
            ->allowedAppends('fullname')
            ->get();

        $this->assertCount(AppendModel::count(), $models);
    }

    /** @test */
    public function it_can_append_attributes()
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname')
            ->allowedAppends('fullname')
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_cannot_append_case_insensitive()
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createQueryFromAppendRequest('FullName')
            ->allowedAppends('fullname')
            ->first();
    }

    /** @test */
    public function it_can_append_collections()
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->allowedAppends('FullName')
            ->get();

        $this->assertCollectionAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_can_append_paginates()
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->allowedAppends('FullName')
            ->paginate();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
        $this->assertStringContainsString('append=FullName', $models->nextPageUrl());
    }

    /** @test */
    public function it_can_append_simple_paginates()
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->allowedAppends('FullName')
            ->simplePaginate();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
        $this->assertStringContainsString('append=FullName', $models->nextPageUrl());
    }

    /** @test */
    public function it_can_append_cursor_paginates()
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->allowedAppends('FullName')
            ->orderBy('firstname')
            ->cursorPaginate();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
        $this->assertStringContainsString('append=FullName', $models->nextPageUrl());
    }

    /** @test */
    public function it_guards_against_invalid_appends()
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createQueryFromAppendRequest('random-attribute-to-append')
            ->allowedAppends('attribute-to-append');
    }

    /** @test */
    public function it_can_allow_multiple_appends()
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname')
            ->allowedAppends('fullname', 'randomAttribute')
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_allow_multiple_appends_as_an_array()
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname')
            ->allowedAppends(['fullname', 'randomAttribute'])
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_append_multiple_attributes()
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname,reversename')
            ->allowedAppends(['fullname', 'reversename'])
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
        $this->assertAttributeLoaded($model, 'reversename');
    }

    /** @test */
    public function an_invalid_append_query_exception_contains_the_not_allowed_and_allowed_appends()
    {
        $exception = new InvalidAppendQuery(collect(['not allowed append']), collect(['allowed append']));

        $this->assertEquals(['not allowed append'], $exception->appendsNotAllowed->all());
        $this->assertEquals(['allowed append'], $exception->allowedAppends->all());
    }

    protected function createQueryFromAppendRequest(string $appends): QueryBuilder
    {
        $request = new Request([
            'append' => $appends,
        ]);

        return QueryBuilder::for(AppendModel::class, $request);
    }

    protected function assertAttributeLoaded(AppendModel $model, string $attribute)
    {
        $this->assertTrue(array_key_exists($attribute, $model->toArray()));
    }

    protected function assertCollectionAttributeLoaded(Collection $collection, string $attribute)
    {
        $hasModelWithoutAttributeLoaded = $collection
            ->contains(function (Model $model) use ($attribute) {
                return ! array_key_exists($attribute, $model->toArray());
            });

        $this->assertFalse($hasModelWithoutAttributeLoaded, "The `{$attribute}` attribute was expected but not loaded.");
    }

    /**
     * @param \Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Contracts\Pagination\Paginator $collection
     * @param string $attribute
     */
    protected function assertPaginateAttributeLoaded($collection, string $attribute)
    {
        $hasModelWithoutAttributeLoaded = $collection
            ->contains(function (Model $model) use ($attribute) {
                return ! array_key_exists($attribute, $model->toArray());
            });

        $this->assertFalse($hasModelWithoutAttributeLoaded, "The `{$attribute}` attribute was expected but not loaded.");
    }
}
