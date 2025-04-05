public function boot()
{
    $this->routes(function () {
        Route::middleware('web')  // ✅ 반드시 `web` 미들웨어가 있어야 함
            ->group(base_path('routes/web.php'));
    });
}
