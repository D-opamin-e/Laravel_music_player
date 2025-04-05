namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;

class LogIpMiddleware
{
    public function handle($request, Closure $next)
    {
        $ip = $request->ip();
        $data = ['ip' => $ip, 'visited_at' => now()];

        $filePath = storage_path("app/public/json/{$ip}.json");
        File::put($filePath, json_encode($data, JSON_PRETTY_PRINT));

        return $next($request);
    }
}
