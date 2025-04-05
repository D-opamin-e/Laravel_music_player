namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function index()
    {
        $userIP = request()->ip();
        $userPlaylistFile = storage_path('app/playlist/' . str_replace(':', '_', $userIP) . '.json');

        $songs = DB::table('songs')->get();
        $total_songs = $songs->count();
        $playlist = collect();

        if ($total_songs > 0) {
            $first_song = $songs->random();
            $current_bpm = $first_song->BPM;
            $playlist->push($first_song);
            $exclude_ids = [$first_song->index_number];
            $max_bpm_diff = 10;

            while ($playlist->count() < $total_songs) {
                $next_song = DB::table('songs')
                    ->whereNotIn('index_number', $exclude_ids)
                    ->whereBetween('BPM', [$current_bpm - $max_bpm_diff, $current_bpm + $max_bpm_diff])
                    ->inRandomOrder()
                    ->first();

                if ($next_song) {
                    $playlist->push($next_song);
                    $exclude_ids[] = $next_song->index_number;
                    $current_bpm = $next_song->BPM;
                } else {
                    $max_bpm_diff += 5;
                }
            }

            Storage::put('playlist/' . str_replace(':', '_', $userIP) . '.json', json_encode($playlist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return view('welcome', compact('playlist'));
    }
}
