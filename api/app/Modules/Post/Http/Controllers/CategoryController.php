<?php

namespace App\Modules\Post\Http\Controllers;

use App\Modules\Post\Http\Requests\StoreCategoryRequest;
use App\Modules\Post\Http\Requests\UpdateCategoryRequest;
use App\Modules\Post\Http\Resources\CategoryResource;
use App\Modules\Post\Models\Category;
use App\Modules\Post\Services\CategoryService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(): JsonResponse
    {
        return $this->success(CategoryResource::collection($this->service->list()));
    }

    public function show(Category $category): JsonResponse
    {
        return $this->success(CategoryResource::make($category->load('children')));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return $this->created(CategoryResource::make($this->service->create($request->validated())), 'Categoria criada com sucesso.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        return $this->success(CategoryResource::make($this->service->update($category, $request->validated())), 'Categoria atualizada com sucesso.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return $this->success(null, 'Categoria removida com sucesso.');
    }
}
