<?php

namespace Spatie\QueryBuilder\Tests;

use DB;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\Exceptions\AllowedFieldsMustBeCalledBeforeAllowedIncludes;
use Spatie\QueryBuilder\Exceptions\InvalidFieldQuery;
use Spatie\QueryBuilder\Exceptions\UnknownIncludedFieldsQuery;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Tests\TestClasses\Models\RelatedModel;
use Spatie\QueryBuilder\Tests\TestClasses\Models\TestModel;

class FieldsTest extends TestCase
{
    /** \Spatie\QueryBuilder\Tests\Models\TestModel */
    protected $model;

    /** @var string */
    protected $modelTableName;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = factory(TestModel::class)->create();
        $this->modelTableName = $this->model->getTable();
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested()
    {
        $query = QueryBuilder::for(TestModel::class)->toSql();

        $expected = TestModel::query()->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested_but_allowed_fields_were_specified()
    {
        $query = QueryBuilder::for(TestModel::class)->allowedFields('id', 'name')->toSql();

        $expected = TestModel::query()->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_replaces_selected_columns_on_the_query()
    {
        $query = $this
            ->createQueryFromFieldRequest(['test_models' => 'name,id'])
            ->select(['id', 'is_visible'])
            ->allowedFields(['name', 'id'])
            ->toSql();

        $expected = TestModel::query()
            ->select("{$this->modelTableName}.name", "{$this->modelTableName}.id")
            ->toSql();

        $this->assertEquals($expected, $query);
        $this->assertStringNotContainsString('is_visible', $expected);
    }

    /** @test */
    public function it_can_fetch_specific_columns()
    {
        $query = $this
            ->createQueryFromFieldRequest(['test_models' => 'name,id'])
            ->allowedFields(['name', 'id'])
            ->toSql();

        $expected = TestModel::query()
            ->select("{$this->modelTableName}.name", "{$this->modelTableName}.id")
            ->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_wont_fetch_a_specific_column_if_its_not_allowed()
    {
        $query = $this->createQueryFromFieldRequest(['test_models' => 'random-column'])->toSql();

        $expected = TestModel::query()->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_can_fetch_sketchy_columns_if_they_are_allowed_fields()
    {
        $query = $this
            ->createQueryFromFieldRequest(['test_models' => 'name->first,id'])
            ->allowedFields(['name->first', 'id'])
            ->toSql();

        $expected = TestModel::query()
            ->select("{$this->modelTableName}.name->first", "{$this->modelTableName}.id")
            ->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_guards_against_not_allowed_fields()
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createQueryFromFieldRequest(['test_models' => 'random-column'])
            ->allowedFields('name');
    }

    /** @test */
    public function it_guards_against_not_allowed_fields_from_an_included_resource()
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createQueryFromFieldRequest(['related_models' => 'random_column'])
            ->allowedFields('related_models.name');
    }

    /** @test */
    public function it_can_fetch_only_requested_columns_from_an_included_model()
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $request = new Request([
            'fields' => [
                'test_models' => 'id',
                'related_models' => 'name',
            ],
            'include' => ['relatedModels'],
        ]);

        $queryBuilder = QueryBuilder::for(TestModel::class, $request)
            ->allowedFields('related_models.name', 'id')
            ->allowedIncludes('relatedModels');

        DB::enableQueryLog();

        $queryBuilder->first()->relatedModels;

        $this->assertQueryLogContains('select `test_models`.`id` from `test_models`');
        $this->assertQueryLogContains('select `name` from `related_models`');
    }

    /** @test */
    public function it_can_fetch_requested_columns_from_included_models_up_to_two_levels_deep()
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $request = new Request([
            'fields' => [
                'test_models' => 'id,name',
                'related_models.test_models' => 'id',
            ],
            'include' => ['relatedModels.testModel'],
        ]);

        $result = QueryBuilder::for(TestModel::class, $request)
            ->allowedFields('related_models.test_models.id', 'id', 'name')
            ->allowedIncludes('relatedModels.testModel')
            ->first();

        $this->assertArrayHasKey('name', $result);

        $this->assertEquals(['id' => $this->model->id], $result->relatedModels->first()->testModel->toArray());
    }

    /** @test */
    public function it_throws_an_exception_when_calling_allowed_includes_before_allowed_fields()
    {
        $this->expectException(AllowedFieldsMustBeCalledBeforeAllowedIncludes::class);

        $this->createQueryFromFieldRequest()
            ->allowedIncludes('related-models')
            ->allowedFields('name');
    }

    /** @test */
    public function it_throws_an_exception_when_calling_allowed_includes_before_allowed_fields_but_with_requested_fields()
    {
        $request = new Request([
            'fields' => [
                'test_models' => 'id',
                'related_models' => 'name',
            ],
            'include' => ['relatedModels'],
        ]);

        $this->expectException(UnknownIncludedFieldsQuery::class);

        QueryBuilder::for(TestModel::class, $request)
            ->allowedIncludes('relatedModels')
            ->allowedFields('name');
    }

    /** @test */
    public function it_throws_an_exception_when_requesting_fields_for_an_allowed_included_without_any_allowed_fields()
    {
        $request = new Request([
            'fields' => [
                'test_models' => 'id',
                'related_models' => 'name',
            ],
            'include' => ['relatedModels'],
        ]);

        $this->expectException(UnknownIncludedFieldsQuery::class);

        QueryBuilder::for(TestModel::class, $request)
            ->allowedIncludes('relatedModels');
    }

    /** @test */
    public function it_can_allow_specific_fields_on_an_included_model()
    {
        $request = new Request([
            'fields' => ['related_models' => 'id,name'],
            'include' => ['relatedModels'],
        ]);

        $queryBuilder = QueryBuilder::for(TestModel::class, $request)
            ->allowedFields(['related_models.id', 'related_models.name'])
            ->allowedIncludes('relatedModels');

        DB::enableQueryLog();

        $queryBuilder->first()->relatedModels;

        $this->assertQueryLogContains('select * from `test_models`');
        $this->assertQueryLogContains('select `id`, `name` from `related_models`');
    }

    /** @test */
    public function it_wont_use_sketchy_field_requests()
    {
        $request = new Request([
            'fields' => ['test_models' => 'id->"\')from test_models--injection'],
        ]);

        DB::enableQueryLog();

        QueryBuilder::for(TestModel::class, $request)->get();

        $this->assertQueryLogDoesntContain('--injection');
    }

    /** @test */
    public function it_can_append_fields_to_paginate()
    {
        factory(TestModel::class, 9)->create();

        /** @var \Illuminate\Pagination\AbstractPaginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator $models */
        $models = $this->createQueryFromFieldRequest(['test_models' => 'name,id'])
            ->select(['id', 'is_visible'])
            ->allowedFields(['name', 'id'])
            ->paginate();

        $this->assertStringContainsString('fields%5Btest_models%5D=name%2Cid', $models->nextPageUrl());
    }

    /** @test */
    public function it_can_append_fields_to_simple_paginate()
    {
        factory(TestModel::class, 9)->create();

        /** @var \Illuminate\Pagination\AbstractPaginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator $models */
        $models = $this->createQueryFromFieldRequest(['test_models' => 'name,id'])
            ->select(['id', 'is_visible'])
            ->allowedFields(['name', 'id'])
            ->simplePaginate();

        $this->assertStringContainsString('fields%5Btest_models%5D=name%2Cid', $models->nextPageUrl());
    }

    /** @test */
    public function it_can_append_fields_to_cursor_paginate()
    {
        factory(TestModel::class, 9)->create();

        /** @var \Illuminate\Pagination\AbstractPaginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator $models */
        $models = $this->createQueryFromFieldRequest(['test_models' => 'name,id'])
            ->select(['id', 'is_visible'])
            ->allowedFields(['name', 'id'])
            ->cursorPaginate();

        $this->assertStringContainsString('fields%5Btest_models%5D=name%2Cid', $models->nextPageUrl());
    }

    protected function createQueryFromFieldRequest(array $fields = []): QueryBuilder
    {
        $request = new Request([
            'fields' => $fields,
        ]);

        return QueryBuilder::for(TestModel::class, $request);
    }
}
