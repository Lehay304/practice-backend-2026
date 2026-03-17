<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    /**
     * Показать список всех бань (доступно всем авторизованным)
     */
    public function index()
    {
        $banyas = Resource::active()->paginate(10);

        return response()->json([
            'message' => 'Список доступных бань и парных',
            'data'    => $banyas
        ]);
    }

    /**
     * Показать одну баню
     */
    public function show(Resource $resource)
    {
        return response()->json([
            'message' => 'Информация о бане',
            'data'    => $resource->load('reviews')
        ]);
    }

    /**
     * Создать новую баню (только admin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'required|string|max:255',
            'capacity'    => 'required|integer|min:1',
            'features'    => 'nullable|array',
            'features.*'  => 'string',
            'is_active'   => 'boolean',
        ]);

        $banya = Resource::create([
            'name'        => $request->name,
            'description' => $request->description,
            'location'    => $request->location,
            'capacity'    => $request->capacity,
            'features'    => $request->features,
            'is_active'   => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Новая баня успешно добавлена!',
            'data'    => $banya
        ], 201);
    }

    /**
     * Обновить баню (только admin)
     */
    public function update(Request $request, Resource $resource)
    {
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'sometimes|string|max:255',
            'capacity'    => 'sometimes|integer|min:1',
            'features'    => 'nullable|array',
            'features.*'  => 'string',
            'is_active'   => 'boolean',
        ]);

        $resource->update($request->only([
            'name', 'description', 'location', 'capacity', 'features', 'is_active'
        ]));

        return response()->json([
            'message' => 'Информация о бане обновлена',
            'data'    => $resource
        ]);
    }

    /**
     * Удалить баню (только admin)
     */
    public function destroy(Resource $resource)
    {
        $resource->delete();

        return response()->json([
            'message' => 'Баня успешно удалена'
        ], 200);
    }
}
