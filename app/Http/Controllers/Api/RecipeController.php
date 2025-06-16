<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class RecipeController extends Controller
{
    public function index()
    {
        return response()->json(Recipe::with(['galleries', 'todos', 'ingredients'])->latest()->get());
    }

    public function store(Request $request)
    {
        $recipes = $request->all();

        if (!isset($recipes[0])) {
            $recipes = [$recipes];
        }

        $createdRecipes = [];

        foreach ($recipes as $index => $recipeData) {
            // Validasi masing-masing recipe
            $validator = Validator::make($recipeData, [
                'title' => 'required|string|max:255',
                'prep' => 'required|integer',
                'cook' => 'required|integer',
                'level' => 'required|string|max:50',

                'galleries' => 'nullable|array',
                'galleries.*.path' => 'required|string',

                'todos' => 'nullable|array',
                'todos.*.todo' => 'required|string',

                'ingredients' => 'nullable|array',
                'ingredients.*.title' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => "Validation failed on item #$index",
                    'errors' => $validator->errors()
                ], 422);
            }

            // Tambahkan slug
            $recipeData['slug'] = Str::slug($recipeData['title']);

            // Simpan recipe utama
            $recipe = Recipe::create([
                'title' => $recipeData['title'],
                'slug' => $recipeData['slug'],
                'prep' => $recipeData['prep'],
                'cook' => $recipeData['cook'],
                'level' => $recipeData['level'],
            ]);

            // Simpan relasi jika ada
            if (!empty($recipeData['galleries'])) {
                $recipe->galleries()->createMany($recipeData['galleries']);
            }

            if (!empty($recipeData['todos'])) {
                $recipe->todos()->createMany($recipeData['todos']);
            }

            if (!empty($recipeData['ingredients'])) {
                $recipe->ingredients()->createMany($recipeData['ingredients']);
            }

            $createdRecipes[] = $recipe->load(['galleries', 'todos', 'ingredients']);
        }

        return response()->json([
            'message' => 'Recipe(s) created successfully.',
            'data' => count($createdRecipes) === 1 ? $createdRecipes[0] : $createdRecipes
        ], 201);
    }

    public function show(Recipe $recipe)
    {
        return response()->json($recipe->load(['galleries', 'todos', 'ingredients']));
    }

    public function update(Request $request, Recipe $recipe)
    {
        $recipeData = $request->all();

        // Validasi data utama
        $validator = Validator::make($recipeData, [
            'title' => 'sometimes|required|string|max:255',
            'prep' => 'sometimes|required|integer',
            'cook' => 'sometimes|required|integer',
            'level' => 'sometimes|required|string|max:50',

            'galleries' => 'sometimes|array',
            'galleries.*.path' => 'required_with:galleries|string',

            'todos' => 'sometimes|array',
            'todos.*.todo' => 'required_with:todos|string',

            'ingredients' => 'sometimes|array',
            'ingredients.*.title' => 'required_with:ingredients|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update slug jika title berubah
        if (isset($recipeData['title'])) {
            $recipeData['slug'] = Str::slug($recipeData['title']);
        }

        // Update data utama
        $recipe->update($recipeData);

        // Handle galleries
        if (array_key_exists('galleries', $recipeData)) {
            $recipe->galleries()->delete(); // Hapus yang lama
            if (!empty($recipeData['galleries'])) {
                $recipe->galleries()->createMany($recipeData['galleries']);
            }
        }

        // Handle todos
        if (array_key_exists('todos', $recipeData)) {
            $recipe->todos()->delete();
            if (!empty($recipeData['todos'])) {
                $recipe->todos()->createMany($recipeData['todos']);
            }
        }

        // Handle ingredients
        if (array_key_exists('ingredients', $recipeData)) {
            $recipe->ingredients()->delete();
            if (!empty($recipeData['ingredients'])) {
                $recipe->ingredients()->createMany($recipeData['ingredients']);
            }
        }

        return response()->json([
            'message' => 'Recipe updated successfully',
            'data' => $recipe->load(['galleries', 'todos', 'ingredients'])
        ]);
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->galleries()->delete();
        $recipe->todos()->delete();
        $recipe->ingredients()->delete();
        $recipe->delete();

        return response()->json(['message' => 'Recipe and related data deleted.']);
    }
}