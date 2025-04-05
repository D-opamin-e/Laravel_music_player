<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Song;
use App\Services\MappingService;

class SearchController extends Controller
{
    protected $mappingService;

    public function __construct(MappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    public function search(Request $request)
    {
        $query = $request->input('q');
        
        // 매핑된 결과 확인
        $mapped = $this->mappingService->map($query);
        $reverseMapped = $this->mappingService->reverseMap($query);

        $searchTerms = array_filter([$query, $mapped, $reverseMapped]);
        $searchTerms = array_unique($searchTerms);

        $songs = Song::where(function($q) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $q->orWhere('artist', 'like', "%$term%");
            }
        })->get();

        return response()->json($songs);
    }
}
