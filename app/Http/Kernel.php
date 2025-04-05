protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\LogIpMiddleware::class,
    ],
];
