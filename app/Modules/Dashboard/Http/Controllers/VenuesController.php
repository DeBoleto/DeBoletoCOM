<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\EventCreator;
use App\Modules\Venues\Models\Ciudades;
use App\Modules\Venues\Models\Escenarios;
use App\Modules\Venues\Models\EscenariosFormatos;
use App\Support\StageMapParser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Recintos y sus formatos (mapas XML).
 */
class VenuesController extends Controller
{
    private const MAX_XML_KB = 2048;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('buscar', ''));

        return Inertia::render('Admin/Venues/Index', [
            'venues' => Escenarios::query()
                ->activos()
                ->with('ciudad.estado')
                ->when($search !== '', fn ($query) => $query->where('escenario', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->orderBy('escenario')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Escenarios $venue) => [
                    'id' => $venue->pk_escenario,
                    'name' => $venue->escenario,
                    'address' => $venue->direccion,
                    'city' => trim(($venue->ciudad?->ciudad ?? '').', '.($venue->ciudad?->estado?->estado ?? ''), ', '),
                ]),
            'filters' => ['buscar' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Venues/Form', ['venue' => null, 'cities' => $this->cities()]);
    }

    public function store(Request $request, EventCreator $images): RedirectResponse
    {
        $validated = $this->validateVenue($request, creating: true);

        $venue = new Escenarios;
        $venue->forceFill($this->venueAttributes($validated) + [
            'escenario_imagen' => $images->storeImage($request->file('imagen'), config('boxoffice.venue_images_dir')),
            'eliminado' => 0,
        ])->save();

        return redirect()->route('admin.venues.formats', $venue->pk_escenario)->with('success', "Recinto {$venue->escenario} creado. Agrega su primer formato.");
    }

    public function edit(int $escenario): Response
    {
        $venue = Escenarios::query()->activos()->findOrFail($escenario);

        return Inertia::render('Admin/Venues/Form', [
            'venue' => [
                'id' => $venue->pk_escenario,
                'escenario' => $venue->escenario,
                'direccion' => $venue->direccion,
                'pk_ciudad' => $venue->pk_ciudad,
                'latitud' => $venue->latitud,
                'longitud' => $venue->longitud,
                'xml_escenario' => $venue->xml_escenario,
                'image' => $venue->escenario_imagen ? Storage::disk(config('boxoffice.images_disk'))->url(config('boxoffice.venue_images_dir').'/'.$venue->escenario_imagen) : null,
            ],
            'cities' => $this->cities(),
        ]);
    }

    public function update(Request $request, EventCreator $images, int $escenario): RedirectResponse
    {
        $venue = Escenarios::query()->activos()->findOrFail($escenario);
        $validated = $this->validateVenue($request, creating: false);

        $venue->forceFill($this->venueAttributes($validated));

        if ($request->hasFile('imagen')) {
            $venue->escenario_imagen = $images->storeImage($request->file('imagen'), config('boxoffice.venue_images_dir'));
        }

        $venue->save();

        return redirect()->route('admin.venues.index')->with('success', "Recinto {$venue->escenario} actualizado.");
    }

    public function formats(int $escenario): Response
    {
        $venue = Escenarios::query()->activos()->findOrFail($escenario);

        return Inertia::render('Admin/Venues/Formats', [
            'venue' => ['id' => $venue->pk_escenario, 'name' => $venue->escenario],
            'formats' => EscenariosFormatos::query()->activos()->where('pk_escenario', $escenario)->orderByDesc('pk_escenario_formato')->get()
                ->map(fn (EscenariosFormatos $format) => [
                    'id' => $format->pk_escenario_formato,
                    'name' => $format->escenario_formato,
                    'xml' => $format->escenario_xml,
                    'localities' => count($format->localities()),
                ])->values(),
        ]);
    }

    public function storeFormat(Request $request, int $escenario): RedirectResponse
    {
        Escenarios::query()->activos()->findOrFail($escenario);
        $validated = $this->validateFormat($request);

        (new EscenariosFormatos)->forceFill([
            'pk_escenario' => $escenario,
            'escenario_formato' => $validated['nombre'],
            'escenario_xml' => $validated['xml'],
            'eliminado' => 0,
        ])->save();

        return back()->with('success', 'Formato agregado.');
    }

    public function updateFormat(Request $request, int $escenario, int $formato): RedirectResponse
    {
        $format = EscenariosFormatos::query()->activos()->where('pk_escenario', $escenario)->findOrFail($formato);
        $validated = $this->validateFormat($request);

        $format->forceFill(['escenario_formato' => $validated['nombre'], 'escenario_xml' => $validated['xml']])->save();

        return back()->with('success', "Formato {$format->escenario_formato} actualizado.");
    }

    /**
     * Interpreta un XML de mapa y devuelve las secciones para la vista previa.
     */
    public function preview(Request $request): JsonResponse
    {
        $xml = $request->validate(['xml' => ['required', 'string', 'max:'.(self::MAX_XML_KB * 1024)]])['xml'];

        try {
            return response()->json(StageMapParser::parseXml($xml));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function validateVenue(Request $request, bool $creating): array
    {
        return $request->validate([
            'escenario' => ['required', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:255'],
            'pk_ciudad' => ['required', 'integer', 'exists:ciudades,pk_ciudad'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'xml_escenario' => ['nullable', 'string', 'max:'.(self::MAX_XML_KB * 1024), $this->validStageXml()],
            'imagen' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
    }

    private function venueAttributes(array $validated): array
    {
        return [
            'escenario' => $validated['escenario'],
            'direccion' => $validated['direccion'],
            'pk_ciudad' => $validated['pk_ciudad'],
            'latitud' => $validated['latitud'] ?? null,
            'longitud' => $validated['longitud'] ?? null,
            'xml_escenario' => $validated['xml_escenario'] ?? '',
        ];
    }

    private function validateFormat(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'xml' => ['required', 'string', 'max:'.(self::MAX_XML_KB * 1024), $this->validStageXml()],
        ]);
    }

    private function validStageXml(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === null || $value === '') {
                return;
            }

            try {
                StageMapParser::parseXml((string) $value);
            } catch (\Throwable) {
                $fail('El XML del mapa no es válido.');
            }
        };
    }

    private function cities(): array
    {
        return Ciudades::query()->with('estado:pk_estado,estado')->where('eliminado', 0)->orderBy('ciudad')->get(['pk_ciudad', 'ciudad', 'pk_estado'])
            ->map(fn (Ciudades $city) => ['id' => $city->pk_ciudad, 'name' => "{$city->ciudad}, {$city->estado?->estado}"])
            ->values()->all();
    }
}
