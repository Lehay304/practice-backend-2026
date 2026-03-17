<?php
namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class ResourceController extends Controller
{
    public function index()
    {
        $banyas = Resource::active()
            ->withAvg('reviews', 'rating')
            ->paginate(10);

        return response()->json([
            'message' => 'Список доступных мест',
            'data'    => $banyas
        ]);
    }

    public function show(Resource $resource)
    {
        return response()->json([
            'message' => 'Информация о бане',
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
            'message' => 'Место успешно создано',
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

        if (!$request->has('features')) {
            unset($validated['features']);
        }

        $resource->update($validated);

        return response()->json([
            'message' => 'Место обновлёно',
            'data'    => $resource
        ]);
    }

    public function destroy(Resource $resource)
    {
        $resource->delete();

        return response()->json([
            'message' => 'Место удалёно'
        ], 200);
    }

    // Расписание места на конкретную дату
    public function schedule(Request $request, Resource $resource)
    {
        $date = $request->get('date', now()->toDateString());
        
        // Проверка даты
        $request->validate([
            'date' => 'sometimes|date|after_or_equal:today'
        ]);

        $bookings = Booking::where('resource_id', $resource->id)
            ->whereDate('start_time', $date)
            ->where('status', 'active')
            ->with('user')
            ->orderBy('start_time')
            ->get()
            ->map(function ($booking) {
                return [
                    'id'         => $booking->id,
                    'start_time' => $booking->start_time->format('H:i'),
                    'end_time'   => $booking->end_time->format('H:i'),
                    'status'     => $booking->status,
                    'user_name'  => $booking->user->name,
                ];
            });

        return response()->json([
            'message' => "Расписание на {$date}",
            'resource' => $resource->name,
            'date'     => $date,
            'bookings' => $bookings
        ]);
    }

    public function scheduleWeek(Request $request, Resource $resource)
    {
        // Дата начала недели
        $startDate = $request->get('start_date', now()->toDateString());
        $startDate = \Carbon\Carbon::parse($startDate);
        $endDate = $startDate->copy()->addDays(6);

        $bookings = $resource->bronirovaniya()
            ->whereBetween('start_time', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->with('user')
            ->orderBy('start_time')
            ->get()
            ->groupBy(function ($booking) {
                return $booking->start_time->format('Y-m-d'); 
            })
            ->map(function ($dayBookings) {
                return $dayBookings->map(function ($booking) {
                    return [
                        'id'         => $booking->id,
                        'start_time' => $booking->start_time->format('H:i'),
                        'end_time'   => $booking->end_time->format('H:i'),
                        'status'     => $booking->status,
                        'user_name'  => $booking->user->name,
                    ];
                });
            });

        return response()->json([
            'message'     => "Расписание на неделю",
            'resource'    => $resource->name,
            'start_date'  => $startDate->format('Y-m-d'),
            'end_date'    => $endDate->format('Y-m-d'),
            'bookings'    => $bookings
        ]);
    }

    // Поиск свободных ресурсов на заданное время
    public function search(Request $request)
    {
        $validated = $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time'   => 'required|date|after:start_time',
            'capacity'   => 'sometimes|integer|min:1',
            'features'   => 'sometimes|string',
        ]);

        $startTime = $validated['start_time'];
        $endTime   = $validated['end_time'];

        $query = Resource::active();

        // Фильтр по вместимости
        if (isset($validated['capacity'])) {
            $query->where('capacity', '>=', $validated['capacity']);
        }

        // Фильтр по характеристикам
        if (isset($validated['features'])) {
            $features = explode(',', $validated['features']);
            foreach ($features as $feature) {
                $feature = trim($feature);
                $query->whereRaw("JSON_CONTAINS(features, ?)", [json_encode($feature)]);
            }
        }

        // Исключаем ресурсы, которые уже забронированы на это время
        $bookedResourceIds = Booking::where('status', 'active')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->pluck('resource_id');

        $query->whereNotIn('id', $bookedResourceIds);

        $resources = $query->with('reviews')->paginate(10);

        // Добавление рейтинга для каждого банного места
        $resources->getCollection()->transform(function ($resource) {
            $resource->average_rating = round($resource->reviews()->avg('rating') ?? 0, 2);
            return $resource;
        });

        return response()->json([
            'message' => 'Найдено свободных ресурсов',
            'search_params' => [
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'capacity'   => $validated['capacity'] ?? null,
                'features'   => $validated['features'] ?? null,
            ],
            'data' => $resources
        ]);
    }
}
