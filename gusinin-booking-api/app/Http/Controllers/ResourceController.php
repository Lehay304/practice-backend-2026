<?php
namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function index()
    {
        $resources = Resource::active()->paginate(10);
        return response()->json([
            'message' => 'Список доступных ресурсов',
            'data'    => $resources
        ]);
    }

    public function show(Resource $resource)
    {
        return response()->json([
            'message' => 'Информация о ресурсе',
            'data'    => $resource->load('reviews')
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'required|string|max:255',
            'capacity'    => 'required|integer|min:1',
            'features'    => 'nullable|array',
            'features.*'  => 'string|max:255',
            'is_active'   => 'sometimes|boolean',
        ]);

        $resource = Resource::create($validated);

        return response()->json([
            'message' => 'Ресурс успешно создан',
            'data'    => $resource
        ], 201);
    }

    public function update(Request $request, Resource $resource)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'sometimes|string|max:255',
            'capacity'    => 'sometimes|integer|min:1',
            'features'    => 'nullable|array',
            'features.*'  => 'string|max:255',
            'is_active'   => 'sometimes|boolean',
        ]);

        // Если features не передан — не перезаписываем
        if (!$request->has('features')) {
            unset($validated['features']);
        }

        $resource->update($validated);

        return response()->json([
            'message' => 'Ресурс обновлён',
            'data'    => $resource
        ]);
    }

    public function destroy(Resource $resource)
    {
        $resource->delete(); // или soft delete, если нужно

        return response()->json([
            'message' => 'Ресурс удалён'
        ], 200);
    }
}