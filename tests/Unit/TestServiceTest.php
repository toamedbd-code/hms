<?php

namespace Tests\Unit;

use App\Models\Test;
use App\Models\TestCategory;
use App\Services\TestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TestServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->app['db']->purge('sqlite');

        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('test_name');
            $table->string('category_type')->nullable();
            $table->unsignedBigInteger('test_category_id')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('testcategories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_tests_are_ordered_by_category_then_item_name(): void
    {
        $service = app(TestService::class);

        $categoryB = TestCategory::create(['name' => 'Beta']);
        $categoryA = TestCategory::create(['name' => 'Alpha']);

        Test::create(['test_name' => 'Zulu', 'test_category_id' => $categoryB->id, 'status' => 'Active']);
        Test::create(['test_name' => 'Alpha', 'test_category_id' => $categoryA->id, 'status' => 'Active']);
        Test::create(['test_name' => 'Beta', 'test_category_id' => $categoryB->id, 'status' => 'Active']);

        $ordered = $service->list()->get()->map(function ($item) {
            return [$item->category_name, $item->test_name];
        })->all();

        $this->assertSame([
            ['Alpha', 'Alpha'],
            ['Beta', 'Beta'],
            ['Beta', 'Zulu'],
        ], $ordered);
    }

    public function test_joined_queries_use_qualified_soft_delete_column(): void
    {
        $service = app(TestService::class);

        $query = $service->list()->where('tests.test_name', 'like', '%v%');
        $sql = $query->toSql();

        $this->assertStringContainsString('"tests"."deleted_at"', $sql);
    }

    public function test_uncategorized_items_are_sorted_by_item_name_after_categories(): void
    {
        $service = app(TestService::class);

        $category = TestCategory::create(['name' => 'Beta']);

        Test::create(['test_name' => 'Zulu', 'test_category_id' => $category->id, 'status' => 'Active']);
        Test::create(['test_name' => 'Alpha', 'status' => 'Active']);
        Test::create(['test_name' => 'Beta', 'status' => 'Active']);

        $ordered = $service->list()->get()->map(function ($item) {
            return [$item->category_name, $item->test_name];
        })->all();

        $this->assertSame([
            [null, 'Alpha'],
            [null, 'Beta'],
            ['Beta', 'Zulu'],
        ], $ordered);
    }
}
